<?php

namespace App\Support\Subscriptions;

use App\Models\Clinic;

class ExtendSubscription implements SubscriptionAction
{
    public function name(): string
    {
        return 'extend';
    }

    public function apply(Clinic $clinic, array $data): string
    {
        $renewalStartsAt = $clinic->subscription_ends_at?->isFuture() ? $clinic->subscription_ends_at : now();
        $clinic->subscription_ends_at = $renewalStartsAt->copy()->addMonths($data['months'] ?? 1);
        $clinic->status = 'active';

        return 'الاشتراك اتجدد لحد '.$clinic->subscription_ends_at->format('Y-m-d');
    }
}
