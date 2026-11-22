<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImageUploadService
{
    /**
     * Allowed MIME types mapped to standard file extensions.
     */
    protected const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
    ];

    /**
     * Maximum allowed file size in bytes (5 MB).
     */
    protected const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

    /**
     * Upload an image file to local file-based storage.
     *
     * @param  UploadedFile  $file
     * @param  string  $subDirectory  Relative folder inside public/uploads (e.g. 'products')
     * @return string Relative stored path for database storage (e.g. 'uploads/products/prod_20261122_150000_abc123.webp')
     *
     * @throws ValidationException
     */
    public function upload(UploadedFile $file, string $subDirectory = 'products'): string
    {
        $this->validateFile($file);

        $sanitizedSubDir = trim(str_replace(['..', '\\'], ['', '/'], $subDirectory), '/');
        $targetDirectory = public_path('uploads/' . $sanitizedSubDir);

        if (! File::isDirectory($targetDirectory)) {
            File::makeDirectory($targetDirectory, 0755, true, true);
        }

        $extension = $this->determineExtension($file);
        $filename = $this->generateUniqueFilename($extension);

        $file->move($targetDirectory, $filename);

        return "uploads/{$sanitizedSubDir}/{$filename}";
    }

    /**
     * Delete an image file from storage safely.
     *
     * @param  string  $path  Relative path stored in DB (e.g. 'uploads/products/xyz.jpg')
     * @return bool
     */
    public function delete(string $path): bool
    {
        if (empty($path) || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return false;
        }

        // Prevent directory traversal attacks
        if (str_contains($path, '..')) {
            return false;
        }

        $cleanPath = ltrim($path, '/\\');

        // Check in public/ (e.g. public/uploads/products/...)
        $fullPath = public_path($cleanPath);
        if (File::exists($fullPath) && File::isFile($fullPath)) {
            return File::delete($fullPath);
        }

        // Fallback for legacy storage/app/public/ paths
        $storagePath = storage_path('app/public/' . preg_replace('#^(storage/|public/)#', '', $cleanPath));
        if (File::exists($storagePath) && File::isFile($storagePath)) {
            return File::delete($storagePath);
        }

        return false;
    }

    /**
     * Validate the uploaded file for integrity, size, and MIME type.
     *
     * @param  UploadedFile  $file
     * @throws ValidationException
     */
    protected function validateFile(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'image' => ['The uploaded image file is invalid or corrupted.'],
            ]);
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw ValidationException::withMessages([
                'image' => ['The image must not exceed 5MB in size.'],
            ]);
        }

        $mimeType = $file->getMimeType();
        if (! array_key_exists($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw ValidationException::withMessages([
                'image' => ['Invalid image type. Supported formats: JPEG, PNG, WEBP, GIF, SVG.'],
            ]);
        }

        // Security check for SVG to prevent stored XSS attacks
        if ($mimeType === 'image/svg+xml') {
            $contents = file_get_contents($file->getRealPath());
            if ($contents && preg_match('/<script|javascript:|onload|onerror|onclick/i', $contents)) {
                throw ValidationException::withMessages([
                    'image' => ['The SVG file contains potentially unsafe scripts.'],
                ]);
            }
        }
    }

    /**
     * Determine a safe and trusted extension based on MIME type and client extension.
     */
    protected function determineExtension(UploadedFile $file): string
    {
        $mime = $file->getMimeType();
        $trustedExtension = self::ALLOWED_MIME_TYPES[$mime] ?? 'jpg';

        $clientExt = strtolower($file->getClientOriginalExtension());
        if ($clientExt === 'jpeg' || $clientExt === 'jpg') {
            return 'jpg';
        }

        return $clientExt === $trustedExtension ? $clientExt : $trustedExtension;
    }

    /**
     * Generate a collision-resistant, cryptographically random, clean filename.
     */
    protected function generateUniqueFilename(string $extension): string
    {
        return sprintf(
            'prod_%s_%s.%s',
            date('Ymd_His'),
            Str::random(16),
            $extension
        );
    }
}
