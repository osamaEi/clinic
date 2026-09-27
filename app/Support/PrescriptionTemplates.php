<?php

namespace App\Support;

use App\Models\Clinic;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class PrescriptionTemplates
{
    public function __construct(private Factory $storage) {}

    public function upload(Clinic $clinic, UploadedFile $image): Clinic
    {
        $previousPath = $clinic->rx_template_path;
        $path = $image->store("clinics/{$clinic->id}/rx-template", Clinic::RX_TEMPLATE_DISK);

        DB::transaction(function () use ($clinic, $path): void {
            $clinic->rx_template_path = $path;
            $clinic->touchSettings();
            $clinic->save();
        });
        if ($previousPath) {
            $this->storage->disk(Clinic::RX_TEMPLATE_DISK)->delete($previousPath);
        }

        return $clinic->fresh();
    }

    public function delete(Clinic $clinic): Clinic
    {
        $previousPath = $clinic->rx_template_path;
        DB::transaction(function () use ($clinic): void {
            $clinic->rx_template_path = null;
            $clinic->touchSettings();
            $clinic->save();
        });
        if ($previousPath) {
            $this->storage->disk(Clinic::RX_TEMPLATE_DISK)->delete($previousPath);
        }

        return $clinic->fresh();
    }
}
