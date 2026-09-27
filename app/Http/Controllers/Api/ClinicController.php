<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Support\ClinicSettings;
use App\Support\PrescriptionTemplates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClinicController extends Controller
{
    public function update(Request $request, ClinicSettings $settings): JsonResponse|array
    {
        $clinic = $this->writableClinic($request);
        if ($clinic instanceof JsonResponse) {
            return $clinic;
        }

        $data = $request->validate([
            'clinic.name' => 'required|string|max:255',
            'clinic.spec' => 'nullable|string|max:255',
            'clinic.doctor' => 'nullable|string|max:255',
            'clinic.phone' => 'nullable|string|max:40',
            'clinic.address' => 'nullable|string|max:255',
            'clinic.open' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'clinic.close' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'fees' => 'required|array|min:1|max:20',
            'fees.*' => 'numeric|min:0',
        ]);

        return $settings->update($clinic, $data)->toClientSettings();
    }

    /** Prescription print layout (paper size, writing area on the template, what to show). */
    public function updateRx(Request $request, ClinicSettings $settings): JsonResponse|array
    {
        $clinic = $this->writableClinic($request);
        if ($clinic instanceof JsonResponse) {
            return $clinic;
        }

        $data = $request->validate([
            'paper' => 'required|in:A4,A5',
            'top' => 'required|numeric|min:0|max:150',
            'right' => 'required|numeric|min:0|max:100',
            'bottom' => 'required|numeric|min:0|max:150',
            'left' => 'required|numeric|min:0|max:100',
            'fontScale' => 'required|integer|min:70|max:140',
            'showHeader' => 'required|boolean',
            'showDiagnosis' => 'required|boolean',
            'printImage' => 'required|boolean',
        ]);

        return $settings->updateRx($clinic, $data)->rxClientSettings();
    }

    /** Scan/photo of the clinic's printed prescription paper; the prescription is printed on top of it. */
    public function uploadRxTemplate(Request $request, PrescriptionTemplates $templates): JsonResponse|array
    {
        $clinic = $this->writableClinic($request);
        if ($clinic instanceof JsonResponse) {
            return $clinic;
        }

        $request->validate(['image' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120']);

        return $templates->upload($clinic, $request->file('image'))->rxClientSettings();
    }

    public function deleteRxTemplate(Request $request, PrescriptionTemplates $templates): JsonResponse|array
    {
        $clinic = $this->writableClinic($request);
        if ($clinic instanceof JsonResponse) {
            return $clinic;
        }

        return $templates->delete($clinic)->rxClientSettings();
    }

    /** Signed, token-free so it works as <img src>; the file name changes on every upload, so caches never go stale. */
    public function showRxTemplate(Clinic $clinic, string $name): StreamedResponse
    {
        abort_unless($clinic->rx_template_path && basename($clinic->rx_template_path) === $name, 404);

        return Storage::disk(Clinic::RX_TEMPLATE_DISK)->response($clinic->rx_template_path, $name, [
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ], 'inline');
    }

    private function writableClinic(Request $request): Clinic|JsonResponse
    {
        $user = $request->user();
        abort_unless($user->is_owner, 403, 'صاحب العيادة بس هو اللي يعدّل الإعدادات.');
        $clinic = $user->clinic;
        if (! $clinic->canWrite()) {
            return response()->json(['message' => 'الاشتراك منتهي — الإعدادات للعرض فقط.'], 402);
        }

        return $clinic;
    }
}
