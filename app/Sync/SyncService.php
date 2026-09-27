<?php

namespace App\Sync;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SyncService
{
    /**
     * Apply a batch of client changes. Each change is
     * {entity, op: upsert|delete, id, updatedAt (client ms), data}.
     *
     * Conflicts resolve last-write-wins on the client timestamp. Every result
     * carries the authoritative server row (or null) so the client can converge
     * even when its change was skipped or rejected.
     */
    public function push(Clinic $clinic, User $user, array $changes): array
    {
        $order = array_flip(SyncRegistry::names());
        $indexed = array_map(null, array_keys($changes), $changes);
        usort($indexed, fn ($a, $b) => [$order[$a[1]['entity']], $a[0]] <=> [$order[$b[1]['entity']], $b[0]]);

        return DB::transaction(function () use ($clinic, $user, $indexed) {
            // Lock the clinic first: serialises pushes per clinic (versions, file numbers, limits).
            $clinic = Clinic::query()->whereKey($clinic->id)->lockForUpdate()->first();

            return array_map(fn ($pair) => $this->applyOne($clinic, $user, $pair[1]), $indexed);
        });
    }

    private function applyOne(Clinic $clinic, User $user, array $change): array
    {
        $name = $change['entity'];
        $def = SyncRegistry::entities()[$name];
        /** @var class-string<Model> $class */
        $class = $def['model'];
        $id = (int) $change['id'];
        $at = (int) $change['updatedAt'];
        $result = ['entity' => $name, 'id' => $id];

        $model = $class::withTrashed()->where('clinic_id', $clinic->id)->find($id);
        $reject = fn (string $error) => $result + ['status' => 'rejected', 'error' => $error, 'row' => $this->rowOf($model)];

        if (! in_array($user->role, $def['roles'], true)) {
            return $reject('forbidden');
        }
        if (! $model && $class::withoutGlobalScopes()->withTrashed()->whereKey($id)->exists()) {
            return $reject('id_conflict');
        }
        if ($model && $model->client_updated_at > $at) {
            return $result + ['status' => 'stale', 'row' => $this->rowOf($model)];
        }

        if ($change['op'] === 'delete') {
            if ($model && ! $model->trashed()) {
                $model->deleted_at = now();
                $model->client_updated_at = $at;
                $model->save();
            }

            return $result + ['status' => 'ok', 'row' => null];
        }

        if (! $model && $name === 'files') {
            return $reject('file_not_uploaded');
        }

        $data = array_map(fn ($v) => $v === '' ? null : $v, (array) ($change['data'] ?? []));
        $data = array_intersect_key($data, $def['fields']);
        $validator = Validator::make($data, $def['rules']);
        if ($validator->fails()) {
            return $reject('invalid: '.$validator->errors()->first());
        }

        if (isset($data['pid']) && ! Patient::withTrashed()->where('clinic_id', $clinic->id)->whereKey($data['pid'])->exists()) {
            return $reject('unknown_patient');
        }

        if (! $model) {
            if ($name === 'patients' && $clinic->plan->max_patients !== null
                && Patient::where('clinic_id', $clinic->id)->count() >= $clinic->plan->max_patients) {
                return $reject('plan_limit_patients');
            }
            $model = new $class;
            $model->id = $id;
            $model->clinic_id = $clinic->id;
            if ($name === 'patients') {
                $model->file_no = (int) Patient::withTrashed()->where('clinic_id', $clinic->id)->max('file_no') + 1;
            }
        }

        foreach ($def['fields'] as $key => $column) {
            if (array_key_exists($key, $data)) {
                $value = $data[$key];
                if (is_string($value) && in_array($column, ['vitals_date', 'registered_on', 'date', 'start_date'], true)) {
                    $value = substr($value, 0, 10);
                }
                $model->{$column} = $value;
            }
        }
        $model->deleted_at = null; // a newer edit revives a row deleted elsewhere
        $model->client_updated_at = $at;
        $model->save();

        return $result + ['status' => 'ok', 'row' => $this->rowOf($model->refresh())];
    }

    private function rowOf(?Model $model): ?array
    {
        return $model && ! $model->trashed() ? $model->toClient() : null;
    }

    /** Everything changed after `$since` (a clinic sync_version). */
    public function pull(Clinic $clinic, int $since): array
    {
        // Read the cursor before the rows: anything committed later is picked up next time.
        $cursor = (int) Clinic::query()->whereKey($clinic->id)->value('sync_version');
        $reset = $since > $cursor;
        if ($reset) {
            $since = 0;
        }

        $changes = [];
        $deleted = [];
        foreach (SyncRegistry::entities() as $name => $def) {
            $changes[$name] = [];
            $deleted[$name] = [];
            $rows = $def['model']::withTrashed()
                ->where('clinic_id', $clinic->id)
                ->where('version', '>', $since)
                ->orderBy('version')
                ->cursor();
            foreach ($rows as $row) {
                if ($row->trashed()) {
                    $deleted[$name][] = $row->id;
                } else {
                    $changes[$name][] = $row->toClient();
                }
            }
        }

        return [
            'cursor' => $cursor,
            'reset' => $reset,
            'settings' => ($since === 0 || $clinic->settings_version > $since) ? $clinic->toClientSettings() : null,
            'changes' => $changes,
            'deleted' => $deleted,
        ];
    }
}
