<?php

namespace App\Models;

use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Model;

class LabTest extends Model
{
    use Syncable;

    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'kind' => $this->kind ?? 'تحليل',
            'note' => $this->note ?? '',
            'updatedAt' => $this->client_updated_at,
        ];
    }
}
