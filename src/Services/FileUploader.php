<?php

/**
 * File Upload Service
 *
 * Following Gibbon patterns with Google Cloud Storage integration
 * Implements security best practices for file uploads:
 * - Extension blacklist (prevent executable uploads)
 * - Filename sanitization
 * - Randomized filenames
 * - Cloud Storage persistence (ephemeral filesystem workaround)
 *
 * Based on Gibbon's FileUploader class, adapted for Google Cloud
 *
 * @version v1.0.0
 * @since v1.0.0
 */

namespace Cor4Edu\Services;

use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Storage\Bucket;

class FileUploader
{
    const FILE_SUFFIX_NONE = 0;
    const FILE_SUFFIX_INCREMENTAL = 1;
    const FILE_SUFFIX_ALPHANUMERIC = 2;

    /**
     * Google Cloud Storage client
     */
    protected StorageClient $storage;

    /**
     * Cloud Storage bucket
     */
    protected Bucket $bucket;

    /**
     * Bucket name
     */
    protected string $bucketName;

    /**
     * Last error code
     */
    protected int $errorCode = 0;

    /**
     * File suffix type
     */
    protected int $fileSuffixType = self::FILE_SUFFIX_ALPHANUMERIC;

    /**
     * Hard-coded illegal file extensions (security critical)
     * These should NEVER be allowed regardless of system settings
     *
     * Based on Gibbon's FileUploader.php:73
     */
    protected static array $illegalFileExtensions = [
        'js', 'htm', 'html', 'css', 'php', 'php3', 'php4', 'php5',
        'php7', 'phtml', 'asp', 'jsp', 'py', 'svg', 'exe', 'bat',
        'sh', 'cmd', 'vbs', 'ps1'
    ];

    /**
     * Illegal characters regex (security critical)
     *
     * Based on Gibbon's FileUploader.php:79
     */
    protected static string $illegalCharactersRegex = '/[\\\~`!@%#\$%\^&\*\(\)\+=\{\}\[\]\|\:;"\'<>,\?\\/]/';

    /**
     * Constructor
     *
     * @param string $projectId Google Cloud project ID
     * @param string $bucketName Cloud Storage bucket name
     */
    public function __construct(string $projectId, string $bucketName = 'sms-edu-uploads')
    {
        $this->storage = new StorageClient([
            'projectId' => $projectId,
        ]);

        $this->bucketName = $bucketName;
        $this->bucket = $this->storage->bucket($bucketName);
    }

    /**
     * Upload a file to Cloud Storage
     *
     * Based on Gibbon's FileUploader::upload() method
     *
     * @param string $filename Desired filename
     * @param string $sourcePath Absolute path of temp file to upload
     * @param string $destinationFolder Folder within bucket (e.g., 'documents/2025/01')
     * @return string|false Resulting path of uploaded file, FALSE on failure
     */
    public function upload(string $filename, string $sourcePath, string $destinationFolder = ''): string|false
    {
        // Trim and remove excess path info
        $filename = basename($filename);
        $filename = preg_replace(static::$illegalCharactersRegex, '', $filename);

        $destinationFolder = trim($destinationFolder, '/');

        // Check the existence of the temp file to upload
        if (empty($sourcePath) || !file_exists($sourcePath)) {
            $this->errorCode = UPLOAD_ERR_NO_FILE;
            return false;
        }

        // Validate the file extension
        if (empty($filename) || !$this->isFileTypeValid($filename)) {
            $this->errorCode = UPLOAD_ERR_EXTENSION;
            return false;
        }

        // Generate a default folder based on date if one isn't provided
        if (empty($destinationFolder)) {
            $destinationFolder = $this->getUploadsFolderByDate();
        }

        // Add randomized suffix for security (prevent filename guessing)
        $filename = $this->getRandomizedFilename($filename);

        // Construct full object name (path within bucket)
        $objectName = $destinationFolder . '/' . $filename;

        try {
            // Upload to Cloud Storage
            $fileContents = file_get_contents($sourcePath);

            $this->bucket->upload($fileContents, [
                'name' => $objectName,
                'metadata' => [
                    'contentType' => mime_content_type($sourcePath),
                    'uploadedAt' => date('Y-m-d H:i:s'),
                ]
            ]);

            return $objectName;
        } catch (\Exception $e) {
            error_log("Cloud Storage upload failed: " . $e->getMessage());
            $this->errorCode = UPLOAD_ERR_CANT_WRITE;
            return false;
        }
    }

    /**
     * Delete a file from Cloud Storage
     *
     * @param string $objectName Path within bucket
     * @return bool Success status
     */
    public function delete(string $objectName): bool
    {
        try {
            $object = $this->bucket->object($objectName);
            $object->delete();
            return true;
        } catch (\Exception $e) {
            error_log("Cloud Storage delete failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get signed URL for secure file download
     *
     * Generates time-limited URL (expires after specified duration)
     * This prevents unauthorized access to files
     *
     * @param string $objectName Path within bucket
     * @param int $expirationSeconds URL expiration time (default: 1 hour)
     * @return string Signed URL
     */
    public function getSignedUrl(string $objectName, int $expirationSeconds = 3600): string
    {
        $object = $this->bucket->object($objectName);

        $url = $object->signedUrl(
            new \DateTime('+' . $expirationSeconds . ' seconds')
        );

        return $url;
    }

    /**
     * Get public URL for file (if bucket is public)
     *
     * @param string $objectName Path within bucket
     * @return string Public URL
     */
    public function getPublicUrl(string $objectName): string
    {
        return sprintf(
            'https://storage.googleapis.com/%s/%s',
            $this->bucketName,
            $objectName
        );
    }

    /**
     * Check if file extension is valid (not blacklisted)
     *
     * Based on Gibbon's FileUploader::isFileTypeValid()
     *
     * @param string $filename Filename to check
     * @return bool Valid status
     */
    protected function isFileTypeValid(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Check against hard-coded illegal extensions
        if (in_array($extension, static::$illegalFileExtensions)) {
            return false;
        }

        // Additional check: must have an extension
        if (empty($extension)) {
            return false;
        }

        return true;
    }

    /**
     * Generate uploads folder path based on current date
     * Format: uploads/YYYY/MM
     *
     * Based on Gibbon's FileUploader::getUploadsFolderByDate()
     *
     * @return string Folder path
     */
    protected function getUploadsFolderByDate(): string
    {
        return 'uploads/' . date('Y') . '/' . date('m');
    }

    /**
     * Get randomized filename for security
     *
     * Based on Gibbon's FileUploader filename generation
     *
     * @param string $filename Original filename
     * @return string Randomized filename
     */
    protected function getRandomizedFilename(string $filename): string
    {
        if ($this->fileSuffixType === self::FILE_SUFFIX_NONE) {
            return $filename;
        }

        $pathInfo = pathinfo($filename);
        $extension = $pathInfo['extension'] ?? '';
        $basename = $pathInfo['filename'] ?? '';

        if ($this->fileSuffixType === self::FILE_SUFFIX_ALPHANUMERIC) {
            // Generate random alphanumeric suffix
            $suffix = bin2hex(random_bytes(8)); // 16 character hex string
            return $basename . '_' . $suffix . '.' . $extension;
        }

        // Incremental suffix (not implemented for Cloud Storage)
        return $filename;
    }

    /**
     * Get last error code
     *
     * @return int Error code (PHP upload error constants)
     */
    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    /**
     * Get list of illegal file extensions
     *
     * @return array Illegal extensions
     */
    public static function getIllegalFileExtensions(): array
    {
        return self::$illegalFileExtensions;
    }

    /**
     * Set file suffix type
     *
     * @param int $type FILE_SUFFIX_* constant
     * @return self
     */
    public function setFileSuffixType(int $type): self
    {
        $this->fileSuffixType = $type;
        return $this;
    }
}
