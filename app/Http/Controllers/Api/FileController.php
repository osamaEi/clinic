<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\PatientFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    /** Upload a file queued offline. Idempotent on the client-generated id. */
    public function store(Request $request): JsonResponse|array
    {
        $clinic = $request->user()->clinic()->with('plan')->first();
        if (! $clinic->canWrite()) {
            return response()->json(['message' => 'الاشتراك منتهي — رفع الملفات متوقف لحد التجديد.'], 402);
        }

        $data = $request->validate([
            'id' => 'required|integer|min:1',
            'pid' => 'required|integer',
            'rid' => 'nullable|integer',
            'kind' => 'nullable|string|max:30',
            'note' => 'nullable|string|max:2000',
            'date' => 'nullable|date',
            'updatedAt' => 'required|integer|min:0',
            'file' => 'required|file|max:20480|mimes:jpg,jpeg,png,gif,webp,heic,pdf',
        ]);

        if ($existing = PatientFile::withTrashed()->find($data['id'])) {
            return ['row' => $existing->trashed() ? null : $existing->toClient()];
        }
        if (PatientFile::withoutGlobalScopes()->withTrashed()->whereKey($data['id'])->exists()) {
            return response()->json(['message' => 'id_conflict'], 409);
        }
        abort_unless(Patient::withTrashed()->whereKey($data['pid'])->exists(), 422, 'unknown_patient');
        if (! empty($data['rid'])) {
            abort_unless(MedicalRecord::withTrashed()->whereKey($data['rid'])->exists(), 422, 'unknown_record');
        }

        $upload = $request->file('file');
        $limitMb = $clinic->plan->max_storage_mb;
        if ($limitMb !== null && $clinic->storageUsedBytes() + $upload->getSize() > $limitMb * 1048576) {
            return response()->json(['message' => 'مساحة التخزين في باقتك خلصت.', 'error' => 'plan_limit_storage'], 422);
        }

        $path = $upload->store("clinics/{$clinic->id}/files", PatientFile::DISK);

        $file = DB::transaction(function () use ($clinic, $data, $upload, $path) {
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

        return response()->json(['row' => $file->refresh()->toClient()], 201);
    }

    /** Signed, token-free download so <img src> works; see PatientFile::signedUrl(). */
    public function show(int $file): StreamedResponse
    {
        $row = PatientFile::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($file);
        abort_unless($row->path && Storage::disk(PatientFile::DISK)->exists($row->path), 404);

        return Storage::disk(PatientFile::DISK)->response($row->path, $row->name, [
            'Content-Type' => $row->mime,
            'Cache-Control' => 'private, max-age=604800',
        ], 'inline');
    }
}
