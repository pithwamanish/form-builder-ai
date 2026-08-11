<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\StorageServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CloudinaryStorageService implements StorageServiceInterface
{
    /**
     * Resolve and validate Cloudinary credentials from separate env vars or CLOUDINARY_URL string.
     */
    public static function getCredentials(): array
    {
        $cloudUrl = trim((string) (config('filesystems.disks.cloudinary.url') ?: env('CLOUDINARY_URL', '')));
        $cloudName = trim((string) (config('filesystems.disks.cloudinary.cloud_name') ?: env('CLOUDINARY_CLOUD_NAME', '')));
        $apiKey = trim((string) (config('filesystems.disks.cloudinary.api_key') ?: env('CLOUDINARY_API_KEY', '')));
        $apiSecret = trim((string) (config('filesystems.disks.cloudinary.api_secret') ?: env('CLOUDINARY_API_SECRET', '')));

        if ((empty($cloudName) || $cloudName === 'your_cloud_name') && !empty($cloudUrl)) {
            $cleanUrl = preg_replace('/^["\']|["\']$/', '', $cloudUrl);
            if (!str_contains($cleanUrl, '://') && str_contains($cleanUrl, '@')) {
                $cleanUrl = 'cloudinary://' . $cleanUrl;
            }
            $parsed = parse_url($cleanUrl);
            if (is_array($parsed)) {
                if (!empty($parsed['host'])) {
                    $cloudName = $parsed['host'];
                }
                if (!empty($parsed['user'])) {
                    $apiKey = $parsed['user'];
                }
                if (!empty($parsed['pass'])) {
                    $apiSecret = $parsed['pass'];
                }
            }
        }

        $isConfigured = !empty($cloudName) && !empty($apiKey) && !empty($apiSecret) && $cloudName !== 'your_cloud_name';

        return [
            'cloud_name' => $cloudName,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'configured' => $isConfigured,
        ];
    }

    /**
     * Upload a file directly to Cloudinary Cloud Storage.
     * Guarantees filename extension sanitization and multi-endpoint fallback.
     */
    public function upload(UploadedFile $file, string $folder = 'form-uploads'): string
    {
        $creds = self::getCredentials();
        $cloudName = $creds['cloud_name'];
        $apiKey = $creds['api_key'];
        $apiSecret = $creds['api_secret'];

        // 1. Resolve actual existing local file path (fixes Livewire 3 path resolution)
        $actualPath = $this->getActualFilePath($file);

        if (!$actualPath || !file_exists($actualPath)) {
            Log::error("CloudinaryUploadError: File path could not be resolved for file " . $file->getClientOriginalName());
            return $this->saveLocalFallback(null, null, $folder, $file->getClientOriginalName());
        }

        $fileContents = file_get_contents($actualPath);

        // Derive clean original extension and sanitized upload filename
        $originalClientName = $file->getClientOriginalName();
        $ext = strtolower(pathinfo($originalClientName, PATHINFO_EXTENSION));
        if (empty($ext) || $ext === 'tmp') {
            $ext = strtolower($file->getClientOriginalExtension() ?: 'pdf');
        }

        $uploadFilename = pathinfo($originalClientName, PATHINFO_FILENAME);
        if (empty($uploadFilename) || str_contains($uploadFilename, 'tmp')) {
            $uploadFilename = Str::random(20);
        }
        $uploadFilenameWithExt = $uploadFilename . '.' . $ext;

        $cloudinaryUrl = null;

        // 2. Official Cloudinary PHP SDK Upload Execution
        if ($creds['configured']) {
            try {
                $cloudinary = new \Cloudinary\Cloudinary([
                    'cloud' => [
                        'cloud_name' => $cloudName,
                        'api_key'    => $apiKey,
                        'api_secret' => $apiSecret,
                    ],
                    'url' => [
                        'secure' => true,
                    ],
                ]);

                $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
                $isImage = in_array($ext, $imageExtensions);

                $options = [
                    'folder' => $folder,
                ];

                if (!$isImage) {
                    $options['resource_type'] = 'raw';
                    $options['public_id'] = $uploadFilename . '.' . $ext;
                }

                $response = $cloudinary->uploadApi()->upload($actualPath, $options);
                $cloudinaryUrl = $response['secure_url'] ?? $response['url'] ?? null;
            } catch (\Exception $e) {
                Log::error("Cloudinary SDK Upload Exception: " . $e->getMessage());
            }
        }

        if (!$cloudinaryUrl) {
            $cloudinaryUrl = $this->saveLocalFallback($actualPath, $fileContents, $folder, $uploadFilenameWithExt);
        } else {
            // Clean up temporary local file if uploaded to Cloudinary
            if ($actualPath && file_exists($actualPath)) {
                @unlink($actualPath);
            }
        }

        return $cloudinaryUrl;
    }

    /**
     * Resolve the actual existing file path on disk (fixes Livewire 3 path bugs).
     */
    protected function getActualFilePath(UploadedFile $file): ?string
    {
        $realPath = $file->getRealPath();
        if ($realPath && file_exists($realPath) && is_file($realPath)) {
            return $realPath;
        }

        $filename = basename($realPath ?: $file->getPathname());

        $candidates = [
            storage_path('app/livewire-tmp/' . $filename),
            storage_path('app/public/livewire-tmp/' . $filename),
            storage_path('app/' . $filename),
            storage_path('app/public/' . $filename),
            $file->getPathname(),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate && file_exists($candidate) && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Save uploaded file to local public disk storage fallback when Cloudinary is unavailable.
     */
    protected function saveLocalFallback(?string $actualPath, ?string $fileContents, string $folder, string $filename): string
    {
        try {
            $path = "{$folder}/{$filename}";
            if ($actualPath && file_exists($actualPath)) {
                \Illuminate\Support\Facades\Storage::disk('public')->putFileAs($folder, new \Illuminate\Http\File($actualPath), $filename);
            } elseif ($fileContents !== null) {
                \Illuminate\Support\Facades\Storage::disk('public')->put($path, $fileContents);
            }
            return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
        } catch (\Throwable $e) {
            Log::error("LocalStorageFallbackError: " . $e->getMessage());
            return asset("storage/{$folder}/{$filename}");
        }
    }
}
