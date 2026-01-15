<?php

namespace App\Services\V1\Document\Upload;

use App\Exceptions\FileUploadException;
use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Transformation\Transformation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FileStorageService
{
    private ?Cloudinary $cloudinary = null;

    /**
     * Configure Cloudinary with credentials from environment.
     *
     * @throws FileUploadException If required configuration is missing
     */
    private function configureCloudinary()
    {
        $cloudName = config('cloudinary.cloud_name');
        $apiKey = config('cloudinary.api_key');
        $apiSecret = config('cloudinary.api_secret');

        // Validate configuration before initializing
        if (empty($cloudName) || empty($apiKey) || empty($apiSecret)) {
            throw new FileUploadException(
                'Cloudinary configuration is missing. Please set CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, and CLOUDINARY_API_SECRET in your .env file.'
            );
        }

        // Initialize Cloudinary configuration
        return Configuration::instance([
            'cloud' => [
                'cloud_name' => $cloudName,
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
            ],
            'url' => [
                'secure' => config('cloudinary.secure', true),
            ],
        ]);
    }

    /**
     * Get or initialize Cloudinary instance.
     * Initializes only when needed (lazy loading).
     */
    private function getCloudinary(): Cloudinary
    {
        if ($this->cloudinary === null) {
            $config = $this->configureCloudinary();
            $this->cloudinary = new Cloudinary($config);
        }

        return $this->cloudinary;
    }

    /**
     * Store uploaded file to Cloudinary and return public ID and URL.
     * Returns array with 'public_id' and 'url' keys.
     * Public ID is stored in public_id field in the database.
     */
    public function storeFile(int $companyId, UploadedFile $file)
    {
        try {
            $uuid = Str::uuid();
            $folder = config('cloudinary.upload.folder', 'docwise/documents') . '/' . $companyId;

            // Get file extension from original filename to preserve file type
            $publicId = "{$folder}/{$uuid}";

            // Get original filename for Cloudinary to detect file type correctly
            $originalFilename = $file->getClientOriginalName();
            return $this->getCloudinary()->uploadApi()->upload(
                $file->getRealPath(),
                [
                    'public_id' => $publicId,
                    'filename' => $originalFilename, // Helps Cloudinary detect file type
                    'resource_type' => config('cloudinary.upload.resource_type', 'auto'),
                    'overwrite' => config('cloudinary.upload.overwrite', false),
                    'invalidate' => config('cloudinary.upload.invalidate', true),
                    'use_filename' => false, // Use our custom public_id
                    'unique_filename' => false, // Use our custom public_id
                ]
            );
        } catch (\Exception $e) {
            throw new FileUploadException('Failed to upload file to Cloudinary: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Calculate file checksum from uploaded file (before storage).
     */
    public function calculateChecksumFromFile(UploadedFile $file): string
    {
        return hash('sha256', $file->getContent());
    }

    /**
     * Delete file from Cloudinary using public_id.
     */
    public function deleteFile(string $publicId): bool
    {
        try {
            // Extract resource type from public_id if needed
            // For documents, we'll use 'raw' as default resource type
            $result = $this->getCloudinary()->uploadApi()->destroy($publicId, [
                'resource_type' => 'raw', // Documents are typically raw files
            ]);

            return $result['result'] === 'ok';
        } catch (\Exception $e) {
            // Log error but don't throw - file might already be deleted
            Log::warning('Failed to delete file from Cloudinary', [
                'public_id' => $publicId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get file metadata.
     */
    public function getFileMetadata(UploadedFile $file): array
    {
        return [
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'extension' => strtolower($file->getClientOriginalExtension()),
        ];
    }

    /**
     * Download file content from Cloudinary URL.
     * Uses Admin API to get the correct URL, then downloads with Laravel HTTP client.
     *
     * @param string $_fileUrl The Cloudinary URL (kept for backward compatibility, but not used)
     * @param string $publicId The public ID for error messages
     * @return string The file content
     * @throws FileUploadException If download fails
     */
    public function downloadFile(string $fileUrl, string $publicId): string
    {
        try {
            $response = Http::timeout(300) // 5 minutes timeout for large files
                ->connectTimeout(30)
                ->withUserAgent('DocWise/1.0')
                ->get($fileUrl);

            if (!$response->successful()) {
                throw new FileUploadException(
                    "Failed to download file from Cloudinary: {$publicId}. HTTP Status: {$response->status()}. URL: {$publicId}",
                    $response->status()
                );
            }

            return $response->body();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            throw new FileUploadException(
                "Failed to download file from Cloudinary: {$publicId}. Error: {$e->getMessage()}. URL: {$publicId}",
                500,
                $e
            );
        } catch (\Exception $e) {
            throw new FileUploadException(
                "Failed to download file from Cloudinary: {$publicId}. Error: {$e->getMessage()}",
                500,
                $e
            );
        }
    }
}
