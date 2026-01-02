<?php

namespace App\Service;

use Cloudinary\Cloudinary;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CloudinaryService
{
    private Cloudinary $cloudinary;

    public function __construct(string $cloudinaryUrl)
    {
        $this->cloudinary = new Cloudinary($cloudinaryUrl);
    }

    /**
     * Upload a file to Cloudinary and return the secure URL
     *
     * @param UploadedFile $file The file to upload
     * @param string $folder The folder in Cloudinary (e.g., 'beatmarket/profiles')
     * @param string $publicId Optional public ID for the resource
     * @return string|null The secure URL of the uploaded file, or null on failure
     */
    public function upload(UploadedFile $file, string $folder = 'beatmarket/profiles', ?string $publicId = null): ?string
    {
        try {
            $options = [
                'folder' => $folder,
                'resource_type' => 'auto',
            ];

            if ($publicId) {
                $options['public_id'] = $publicId;
            }

            $response = $this->cloudinary->uploadApi()->upload($file->getPathname(), $options);

            return $response['secure_url'] ?? null;
        } catch (\Exception $e) {
            error_log("Cloudinary upload error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete a file from Cloudinary by URL or public ID
     *
     * @param string $publicIdOrUrl The public ID or full URL of the resource
     * @return bool True if deletion was successful
     */
    public function delete(string $publicIdOrUrl): bool
    {
        try {
            // If it's a full URL, extract the public ID
            if (strpos($publicIdOrUrl, 'http') === 0) {
                // Extract public ID from URL: https://res.cloudinary.com/.../image/upload/v1234/folder/publicid.jpg
                preg_match('/upload\/(?:v\d+\/)?(.+)\.\w+$/', $publicIdOrUrl, $matches);
                $publicId = $matches[1] ?? null;
                if (!$publicId) {
                    return false;
                }
            } else {
                $publicId = $publicIdOrUrl;
            }

            $this->cloudinary->uploadApi()->destroy($publicId);
            return true;
        } catch (\Exception $e) {
            error_log("Cloudinary delete error: " . $e->getMessage());
            return false;
        }
    }
}
