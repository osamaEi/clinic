<?php

namespace App\Models;

use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Model;

class MedicalRecord extends Model
{
    use Syncable;

    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'pid' => $this->patient_id,
            'date' => self::dateOut($this->date),
            'complaint' => $this->complaint ?? '',
            'diagnosis' => $this->diagnosis,
            'rx' => $this->rx ?? '',
            'tests' => $this->tests ?? '',
            'next' => $this->next_visit ?? '',
            'updatedAt' => $this->client_updated_at,
        ];
    }
}
