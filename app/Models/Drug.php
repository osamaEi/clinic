<?php

namespace App\Models;

use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Model;

class Drug extends Model
{
    use Syncable;

    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'freq' => $this->freq ?? '',
            'when' => $this->timing ?? '',
            'dur' => $this->duration ?? '',
            'extra' => $this->note ?? '',
            'updatedAt' => $this->client_updated_at,
        ];
    }
}
