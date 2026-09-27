<?php

namespace App\Support;

use App\Models\User;

/** What the PWA caches about the signed-in user and their clinic's subscription. */
final class Account
{
    public static function payload(User $user): array
    {
        $clinic = $user->clinic()->with('plan')->first();
        $endsAt = $clinic->endsAt();

        return [
            'user' => $user->toClient(),
            'clinic' => [
                'id' => $clinic->id,
                'name' => $clinic->name,
                'state' => $clinic->subscriptionState(),
                'canWrite' => $clinic->canWrite(),
                'endsAt' => $endsAt?->toIso8601String(),
                'daysLeft' => $endsAt ? max(0, (int) ceil(now()->diffInHours($endsAt, false) / 24)) : 0,
                'plan' => [
                    'slug' => $clinic->plan->slug,
                    'name' => $clinic->plan->name,
                    'price' => $clinic->plan->price_monthly + 0,
                    'limits' => $clinic->plan->limits(),
                ],
                'usage' => [
                    'users' => $clinic->users()->where('is_active', true)->count(),
                    'patients' => $clinic->patients()->count(),
                    'storageMb' => round($clinic->storageUsedBytes() / 1048576, 1),
                ],
            ],
        ];
    }
}
