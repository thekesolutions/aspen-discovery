<?php

require_once ROOT_DIR . '/sys/Storage/StorageBackendInterface.php';

/**
 * LocalStorageBackend - Local filesystem storage implementation
 * 
 * This backend stores files on the local filesystem, which can be:
 * - Local directories for development
 * - Mounted volumes for Docker deployments
 * - Network-mounted storage for production
 */
class LocalStorageBackend implements StorageBackendInterface {
    
    private $config;
    
    public function __construct(array $config) {
        $this->config = $config;
    }
    
    /**
     * Store a file in the local filesystem
     */
    public function storeFile(string $sourcePath, string $destinationPath, array $options = []): bool {
        try {
            // Ensure the destination directory exists
            $destinationDir = dirname($destinationPath);
            if (!$this->ensureDirectoryExists($destinationDir)) {
                return false;
            }
            
            // Copy the file
            $result = copy($sourcePath, $destinationPath);
            
            if ($result) {
                // Set appropriate permissions
                chmod($destinationPath, $this->config['permissions']['files'] ?? 0644);
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("LocalStorageBackend::storeFile failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Retrieve a file from local storage (essentially a copy operation)
     */
    public function retrieveFile(string $filePath, string $destinationPath): bool {
        try {
            if (!$this->fileExists($filePath)) {
                return false;
            }
            
            // Ensure destination directory exists
            $destinationDir = dirname($destinationPath);
            if (!is_dir($destinationDir)) {
                mkdir($destinationDir, $this->config['permissions']['directories'] ?? 0755, true);
            }
            
            return copy($filePath, $destinationPath);
            
        } catch (Exception $e) {
            error_log("LocalStorageBackend::retrieveFile failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a file from local storage
     */
    public function deleteFile(string $filePath): bool {
        try {
            if ($this->fileExists($filePath)) {
                return unlink($filePath);
            }
            return true; // File doesn't exist, consider it "deleted"
            
        } catch (Exception $e) {
            error_log("LocalStorageBackend::deleteFile failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if a file exists in local storage
     */
    public function fileExists(string $filePath): bool {
        return file_exists($filePath) && is_file($filePath);
    }
    
    /**
     * Get a public URL for a local file
     * This converts the filesystem path to a web-accessible URL
     */
    public function getPublicUrl(string $filePath): string {
        global $configArray;
        
        // Convert absolute filesystem path to web-accessible URL
        $webRoot = $configArray['Site']['local'] ?? '/usr/local/aspen-discovery/code/web';
        $baseUrl = $configArray['Site']['url'] ?? '';
        
        // For user uploads, we need to map the storage path to a web path
        foreach ($this->config['base_paths'] as $type => $basePath) {
            if (strpos($filePath, $basePath) === 0) {
                $relativePath = substr($filePath, strlen($basePath));
                return $baseUrl . '/storage/' . $type . $relativePath;
            }
        }
        
        // Fallback for files in web directory
        if (strpos($filePath, $webRoot) === 0) {
            $relativePath = substr($filePath, strlen($webRoot));
            return $baseUrl . $relativePath;
        }
        
        // If we can't map it, return the file path (may not be web-accessible)
        return $filePath;
    }
    
    /**
     * Ensure a directory exists in local storage
     */
    public function ensureDirectoryExists(string $directoryPath): bool {
        try {
            if (!is_dir($directoryPath)) {
                $result = mkdir($directoryPath, $this->config['permissions']['directories'] ?? 0755, true);
                if ($result) {
                    // Set group ownership if specified (useful for web server access)
                    if (isset($this->config['group'])) {
                        chgrp($directoryPath, $this->config['group']);
                    }
                }
                return $result;
            }
            return true;
            
        } catch (Exception $e) {
            error_log("LocalStorageBackend::ensureDirectoryExists failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get configuration for this backend
     */
    public function getConfiguration(): array {
        return [
            'backend_type' => 'local',
            'base_paths' => $this->config['base_paths'],
            'permissions' => $this->config['permissions']
        ];
    }
    
    /**
     * Get disk usage information for monitoring
     */
    public function getDiskUsage(): array {
        $usage = [];
        
        foreach ($this->config['base_paths'] as $type => $path) {
            if (is_dir($path)) {
                $usage[$type] = [
                    'path' => $path,
                    'size' => $this->getDirectorySize($path),
                    'free_space' => disk_free_space($path),
                    'total_space' => disk_total_space($path)
                ];
            }
        }
        
        return $usage;
    }
    
    /**
     * Calculate directory size recursively
     */
    private function getDirectorySize(string $directory): int {
        $size = 0;
        
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (Exception $e) {
            error_log("Error calculating directory size for $directory: " . $e->getMessage());
        }
        
        return $size;
    }
}
