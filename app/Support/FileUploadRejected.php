<?php

namespace App\Support;

use RuntimeException;

class FileUploadRejected extends RuntimeException
{
    public function __construct(string $message, public readonly int $status, public readonly ?string $error = null)
    {
        parent::__construct($message);
    }
}
