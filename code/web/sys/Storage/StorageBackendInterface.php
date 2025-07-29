<?php

/**
 * StorageBackendInterface - Contract for all storage backends
 * 
 * This interface ensures that all storage backends (local, S3, GCS, etc.)
 * implement the same methods, making them interchangeable.
 */
interface StorageBackendInterface {
    
    /**
     * Store a file in the storage backend
     * 
     * @param string $sourcePath Local source file path
     * @param string $destinationPath Destination path in storage
     * @param array $options Backend-specific options
     * @return bool Success status
     */
    public function storeFile(string $sourcePath, string $destinationPath, array $options = []): bool;
    
    /**
     * Retrieve a file from storage to local filesystem
     * 
     * @param string $filePath File path in storage
     * @param string $destinationPath Local destination path
     * @return bool Success status
     */
    public function retrieveFile(string $filePath, string $destinationPath): bool;
    
    /**
     * Delete a file from storage
     * 
     * @param string $filePath File path in storage
     * @return bool Success status
     */
    public function deleteFile(string $filePath): bool;
    
    /**
     * Check if a file exists in storage
     * 
     * @param string $filePath File path in storage
     * @return bool File exists
     */
    public function fileExists(string $filePath): bool;
    
    /**
     * Get a public URL for accessing a stored file
     * 
     * @param string $filePath File path in storage
     * @return string Public URL
     */
    public function getPublicUrl(string $filePath): string;
    
    /**
     * Ensure a directory exists in storage
     * 
     * @param string $directoryPath Directory path
     * @return bool Success status
     */
    public function ensureDirectoryExists(string $directoryPath): bool;
    
    /**
     * Get backend-specific configuration
     * 
     * @return array Configuration array
     */
    public function getConfiguration(): array;
}
