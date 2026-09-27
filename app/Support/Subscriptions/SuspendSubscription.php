<?php

namespace App\Support\Subscriptions;

use App\Models\Clinic;

class SuspendSubscription implements SubscriptionAction
{
    public function name(): string
    {
        return 'suspend';
    }

    public function apply(Clinic $clinic, array $data): string
    {
        $clinic->status = 'suspended';

        return 'العيادة اتوقفت (قراءة فقط).';
    }
}
