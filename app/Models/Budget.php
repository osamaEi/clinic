<?php

namespace App\Models;

use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Model;

/** Monthly spending limit for one expense category. */
class Budget extends Model
{
    use Syncable;

    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'cat' => $this->category,
            'amount' => $this->amount + 0,
            'updatedAt' => $this->client_updated_at,
        ];
    }
}
