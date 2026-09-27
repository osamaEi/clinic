<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClinicController extends Controller
{
    public function update(Request $request): JsonResponse|array
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

        DB::transaction(function () use ($clinic, $data) {
            $clinic->fill([
                'name' => $data['clinic']['name'],
                'specialty' => $data['clinic']['spec'] ?? null,
                'doctor_name' => $data['clinic']['doctor'] ?? null,
                'phone' => $data['clinic']['phone'] ?? null,
                'address' => $data['clinic']['address'] ?? null,
                'open_time' => $data['clinic']['open'],
                'close_time' => $data['clinic']['close'],
                'fees' => array_map(fn ($v) => $v + 0, $data['fees']),
            ]);
            $clinic->touchSettings();
            $clinic->save();
        });

        return $clinic->fresh()->toClientSettings();
    }

    /** Prescription print layout (paper size, writing area on the template, what to show). */
    public function updateRx(Request $request): JsonResponse|array
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

        DB::transaction(function () use ($clinic, $data) {
            $clinic->rx_settings = array_map(fn ($v) => is_numeric($v) && ! is_bool($v) ? $v + 0 : $v, $data);
            $clinic->touchSettings();
            $clinic->save();
        });

        return $clinic->fresh()->rxClientSettings();
    }

    /** Scan/photo of the clinic's printed prescription paper; the prescription is printed on top of it. */
    public function uploadRxTemplate(Request $request): JsonResponse|array
    {
        $clinic = $this->writableClinic($request);
        if ($clinic instanceof JsonResponse) {
            return $clinic;
        }

        $request->validate(['image' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120']);

        $old = $clinic->rx_template_path;
        $path = $request->file('image')->store("clinics/{$clinic->id}/rx-template", Clinic::RX_TEMPLATE_DISK);

        DB::transaction(function () use ($clinic, $path) {
            $clinic->rx_template_path = $path;
            $clinic->touchSettings();
            $clinic->save();
        });
        if ($old) {
            Storage::disk(Clinic::RX_TEMPLATE_DISK)->delete($old);
        }

        return $clinic->fresh()->rxClientSettings();
    }

    public function deleteRxTemplate(Request $request): JsonResponse|array
    {
        $clinic = $this->writableClinic($request);
        if ($clinic instanceof JsonResponse) {
            return $clinic;
        }

        $old = $clinic->rx_template_path;
        DB::transaction(function () use ($clinic) {
            $clinic->rx_template_path = null;
            $clinic->touchSettings();
            $clinic->save();
        });
        if ($old) {
            Storage::disk(Clinic::RX_TEMPLATE_DISK)->delete($old);
        }

        return $clinic->fresh()->rxClientSettings();
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
