<?php

namespace App\Support\Subscriptions;

use App\Models\Clinic;

class ChangeSubscriptionPlan implements SubscriptionAction
{
    public function name(): string
    {
        return 'plan';
    }

    public function apply(Clinic $clinic, array $data): string
    {
        $clinic->plan_id = $data['plan_id'];

        return 'الباقة اتغيّرت.';
    }
}
