<?php

namespace App\Support\Subscriptions;

use App\Models\Clinic;
use InvalidArgumentException;

class SubscriptionActions
{
    /** @var array<string, SubscriptionAction> */
    private array $actions = [];

    /** @param iterable<SubscriptionAction> $actions */
    public function __construct(iterable $actions)
    {
        foreach ($actions as $action) {
            $this->actions[$action->name()] = $action;
        }
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->actions);
    }

    /** @param array{action: string, months?: int|string|null, plan_id?: int|string|null} $data */
    public function apply(Clinic $clinic, array $data): string
    {
        $action = $this->actions[$data['action']] ?? throw new InvalidArgumentException('Unknown subscription action.');
        $message = $action->apply($clinic, $data);
        $clinic->save();

        return $message;
    }
}
