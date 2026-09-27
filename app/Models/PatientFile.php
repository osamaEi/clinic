<?php

namespace App\Models;

use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class PatientFile extends Model
{
    use Syncable;

    public const DISK = 'local';

    public function signedUrl(): string
    {
        // Path-stable URL: the service worker caches it ignoring the query string,
        // so a file viewed once stays viewable offline even after the signature rotates.
        return URL::temporarySignedRoute('files.show', now()->addDays(7), ['file' => $this->id]);
    }

    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'pid' => $this->patient_id,
            'rid' => $this->record_id,
            'name' => $this->name,
            'mime' => $this->mime,
            'size' => (int) $this->size,
            'kind' => $this->kind ?? 'مستند آخر',
            'note' => $this->note ?? '',
            'date' => self::dateOut($this->date),
            'data' => $this->signedUrl(),
            'updatedAt' => $this->client_updated_at,
        ];
    }
}
