<?php

namespace App\Models;

use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use Syncable;

    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'fileNo' => $this->file_no,
            'name' => $this->name,
            'phone' => $this->phone ?? '',
            'age' => $this->age ?? '',
            'gender' => $this->gender ?? '',
            'blood' => $this->blood ?? '',
            'chronic' => $this->chronic ?? '',
            'allergy' => $this->allergy ?? '',
            'bp' => $this->bp ?? '',
            'sugar' => $this->sugar ?? '',
            'weight' => self::numOut($this->weight),
            'height' => self::numOut($this->height),
            'vitalsDate' => self::dateOut($this->vitals_date),
            'note' => $this->note ?? '',
            'visitFee' => $this->visit_fee === null ? null : $this->visit_fee + 0,
            'created' => self::dateOut($this->registered_on ?? $this->created_at),
            'updatedAt' => $this->client_updated_at,
        ];
    }
}
