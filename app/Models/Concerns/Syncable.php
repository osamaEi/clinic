<?php

namespace App\Models\Concerns;

use App\Models\Clinic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * Tenant-scoped, offline-syncable row:
 *  - always filtered to the signed-in user's clinic
 *  - ids come from the client, so no auto-increment
 *  - every write takes a fresh per-clinic version (the pull cursor)
 */
trait Syncable
{
    use SoftDeletes;

    public static function bootSyncable(): void
    {
        static::addGlobalScope('clinic', function (Builder $query) {
            $user = Auth::user();
            if ($user && $user->clinic_id) {
                $query->where($query->getModel()->getTable().'.clinic_id', $user->clinic_id);
            }
        });

        static::saving(function ($model) {
            $model->version = Clinic::nextVersion($model->clinic_id);
        });
    }

    public function initializeSyncable(): void
    {
        $this->incrementing = false;
        $this->guarded = [];
    }

    protected static function dateOut($value): ?string
    {
        return $value ? substr((string) $value, 0, 10) : null;
    }

    protected static function numOut($value): int|float|string
    {
        if ($value === null) {
            return '';
        }

        return $value + 0;
    }

    /** Row in the shape the PWA uses. */
    abstract public function toClient(): array;
}
