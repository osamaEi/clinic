<?php

namespace App\Support;

use App\Models\Clinic;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\PatientFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class PatientFileUpload
{
    /**
     * @param  array{id: int|string, pid: int|string, rid?: int|string|null, kind?: ?string, note?: ?string, date?: ?string, updatedAt: int|string, file: UploadedFile}  $data
     */
    public function store(Clinic $clinic, array $data, UploadedFile $upload): FileUploadResult
    {
        if ($existing = PatientFile::withTrashed()->find($data['id'])) {
            return new FileUploadResult($existing->trashed() ? null : $existing, false);
        }
        if (PatientFile::withoutGlobalScopes()->withTrashed()->whereKey($data['id'])->exists()) {
            throw new FileUploadRejected('id_conflict', 409);
        }
        abort_unless(Patient::withTrashed()->whereKey($data['pid'])->exists(), 422, 'unknown_patient');
        if (! empty($data['rid'])) {
            abort_unless(MedicalRecord::withTrashed()->whereKey($data['rid'])->exists(), 422, 'unknown_record');
        }

        $limitMb = $clinic->plan->max_storage_mb;
        if ($limitMb !== null && $clinic->storageUsedBytes() + $upload->getSize() > $limitMb * 1048576) {
            throw new FileUploadRejected('مساحة التخزين في باقتك خلصت.', 422, 'plan_limit_storage');
        }

        $path = $upload->store("clinics/{$clinic->id}/files", PatientFile::DISK);

        $file = DB::transaction(function () use ($clinic, $data, $upload, $path): PatientFile {
            Clinic::query()->whereKey($clinic->id)->lockForUpdate()->first();
            $file = new PatientFile;
            $file->id = $data['id'];
            $file->clinic_id = $clinic->id;
            $file->patient_id = $data['pid'];
            $file->record_id = $data['rid'] ?? null;
            $file->name = mb_substr($upload->getClientOriginalName(), 0, 255);
            $file->mime = $upload->getMimeType() ?: 'application/octet-stream';
            $file->size = $upload->getSize();
            $file->kind = $data['kind'] ?? null;
            $file->note = $data['note'] ?? null;
            $file->date = isset($data['date']) ? substr($data['date'], 0, 10) : now()->toDateString();
            $file->path = $path;
            $file->client_updated_at = $data['updatedAt'];
            $file->save();

            return $file;
        });

        return new FileUploadResult($file->refresh(), true);
    }
}
