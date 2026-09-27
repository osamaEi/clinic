<?php

namespace App\Models;

use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use Syncable;

    protected function casts(): array
    {
        return ['paid' => 'boolean'];
    }

    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'pid' => $this->patient_id,
            'date' => self::dateOut($this->date),
            'time' => $this->time,
            'type' => $this->type,
            'status' => $this->status,
            'fee' => $this->fee + 0,
            'paid' => $this->paid,
            'method' => $this->method ?? '',
            'arrived' => $this->arrived,
            'updatedAt' => $this->client_updated_at,
        ];
    }
}
