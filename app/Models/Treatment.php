<?php

namespace App\Models;

use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Model;

class Treatment extends Model
{
    use Syncable;

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'pid' => $this->patient_id,
            'drug' => $this->drug,
            'dose' => $this->dose ?? '',
            'duration' => $this->duration ?? '',
            'start' => self::dateOut($this->start_date),
            'note' => $this->note ?? '',
            'active' => $this->active,
            'updatedAt' => $this->client_updated_at,
        ];
    }
}
