<?php

namespace App\Support;

use App\Models\PatientFile;

final readonly class FileUploadResult
{
    public function __construct(public ?PatientFile $file, public bool $created) {}
}
