<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PatientFile;
use App\Support\FileUploadRejected;
use App\Support\PatientFileUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    /** Upload a file queued offline. Idempotent on the client-generated id. */
    public function store(Request $request, PatientFileUpload $uploads): JsonResponse
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

        try {
            $result = $uploads->store($clinic, $data, $request->file('file'));
        } catch (FileUploadRejected $exception) {
            $payload = ['message' => $exception->getMessage()];
            if ($exception->error !== null) {
                $payload['error'] = $exception->error;
            }

            return response()->json($payload, $exception->status);
        }

        return response()->json(['row' => $result->file?->toClient()], $result->created ? 201 : 200);
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
