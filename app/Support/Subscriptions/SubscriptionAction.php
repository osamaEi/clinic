<?php

namespace App\Support\Subscriptions;

use App\Models\Clinic;

interface SubscriptionAction
{
    public function name(): string;

    /**
     * Mutate the clinic and return its confirmation message; the registry persists it.
     *
     * @param  array{action: string, months?: int|string|null, plan_id?: int|string|null}  $data
     */
    public function apply(Clinic $clinic, array $data): string;
}
