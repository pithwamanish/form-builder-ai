<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface StorageServiceInterface
{
    /**
     * Upload a file to cloud storage or local disk fallback.
     */
    public function upload(UploadedFile $file, string $folder = 'form-uploads'): string;

    /**
     * Resolve and validate storage credentials.
     */
    public static function getCredentials(): array;
}
