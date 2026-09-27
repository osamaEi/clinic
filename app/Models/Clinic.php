<?php

namespace App\Models;

use Database\Factories\ClinicFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class Clinic extends Model
{
    /** @use HasFactory<ClinicFactory> */
    use HasFactory;

    public const TRIAL_DAYS = 14;

    public const DEFAULT_FEES = ['كشف' => 300, 'استشارة' => 200, 'متابعة' => 150];

    /** Prescription print layout; margins are millimetres from the paper edge. */
    public const DEFAULT_RX = [
        'paper' => 'A5',
        'top' => 45,
        'right' => 15,
        'bottom' => 25,
        'left' => 15,
        'fontScale' => 100,
        'showHeader' => true,
        'showDiagnosis' => true,
        'printImage' => true,
    ];

    public const RX_TEMPLATE_DISK = 'local';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fees' => 'array',
            'rx_settings' => 'array',
            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    /** trial | active | expired | suspended */
    public function subscriptionState(): string
    {
        if ($this->status === 'suspended') {
            return 'suspended';
        }
        if (config('app.standalone')) {
            return 'active';
        }
        if ($this->status === 'active' && $this->subscription_ends_at?->isFuture()) {
            return 'active';
        }
        if ($this->status === 'trial' && $this->trial_ends_at?->isFuture()) {
            return 'trial';
        }

        return 'expired';
    }

    public function canWrite(): bool
    {
        return in_array($this->subscriptionState(), ['trial', 'active'], true);
    }

    public function endsAt(): ?Carbon
    {
        return $this->status === 'trial' ? $this->trial_ends_at : $this->subscription_ends_at;
    }

    /**
     * Reserve the next sync version for this clinic. The row lock serialises
     * writers per clinic, so versions become visible to pulls in commit order.
     */
    public static function nextVersion(int $clinicId): int
    {
        return DB::transaction(function () use ($clinicId) {
            $current = static::query()->whereKey($clinicId)->lockForUpdate()->value('sync_version');
            $next = (int) $current + 1;
            static::query()->whereKey($clinicId)->toBase()->update(['sync_version' => $next]);

            return $next;
        });
    }

    public function storageUsedBytes(): int
    {
        return (int) PatientFile::withoutGlobalScopes()->where('clinic_id', $this->id)->whereNull('deleted_at')->sum('size');
    }

    /** Make a settings change visible to every device's next pull. Call inside a transaction. */
    public function touchSettings(): void
    {
        $this->settings_version = self::nextVersion($this->id);
        $this->sync_version = $this->settings_version;
    }

    /** Prescription layout for the PWA, with a signed, cacheable URL for the template image. */
    public function rxClientSettings(): array
    {
        $image = $this->rx_template_path
            ? URL::signedRoute('rx-template.show', ['clinic' => $this->id, 'name' => basename($this->rx_template_path)])
            : null;

        return array_merge(self::DEFAULT_RX, $this->rx_settings ?? [], ['image' => $image]);
    }

    /** Settings payload in the shape the PWA expects (DB.clinic + DB.fees + DB.rx). */
    public function toClientSettings(): array
    {
        return [
            'clinic' => [
                'name' => $this->name,
                'spec' => $this->specialty ?? '',
                'doctor' => $this->doctor_name ?? '',
                'phone' => $this->phone ?? '',
                'address' => $this->address ?? '',
                'open' => $this->open_time,
                'close' => $this->close_time,
            ],
            'fees' => $this->fees ?: self::DEFAULT_FEES,
            'rx' => $this->rxClientSettings(),
        ];
    }
}
