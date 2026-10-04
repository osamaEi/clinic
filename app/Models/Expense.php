<?php

namespace App\Models;

use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use Syncable;

    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'cat' => $this->category,
            'amount' => $this->amount + 0,
            'date' => self::dateOut($this->date),
            'method' => $this->method ?? '',
            'note' => $this->note ?? '',
            'updatedAt' => $this->client_updated_at,
        ];
    }
}
