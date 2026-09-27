<?php

namespace App\Support\Subscriptions;

use App\Models\Clinic;

class ResumeSubscription implements SubscriptionAction
{
    public function name(): string
    {
        return 'resume';
    }

    public function apply(Clinic $clinic, array $data): string
    {
        $clinic->status = $clinic->subscription_ends_at?->isFuture() ? 'active' : 'trial';

        return 'العيادة رجعت تشتغل.';
    }
}
