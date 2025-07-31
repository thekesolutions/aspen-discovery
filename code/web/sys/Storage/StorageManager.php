<?php

require_once ROOT_DIR . '/sys/Storage/StorageBackendInterface.php';
require_once ROOT_DIR . '/sys/Storage/LocalStorageBackend.php';

/**
 * StorageManager - Centralized file storage management for Aspen Discovery
 * 
 * This class abstracts all file storage operations, making it easy to:
 * - Separate user uploads from application code
 * - Configure different storage backends (local, S3, etc.)
 * - Support Docker deployments with volume mounts
 * - Maintain clean separation of concerns
 */
class StorageManager {
    
    private static $instance = null;
    private $config;
    private $storageBackend;
    
    // Storage type constants
    const TYPE_USER_DATA = 'user_data';  // All user-uploaded content
    const TYPE_STATIC_ASSETS = 'static_assets';
    const TYPE_TEMPORARY = 'temporary';
    
    // Upload categories (all under user_data)
    const CATEGORY_IMAGES = 'images';
    const CATEGORY_FILES = 'files';
    const CATEGORY_FONTS = 'fonts';
    const CATEGORY_COVERS = 'covers';  // User-uploaded covers
    const CATEGORY_WEB_BUILDER = 'web_builder';
    const CATEGORY_REWARDS = 'rewards';
    const CATEGORY_LEGACY = 'legacy';
    
    // Image subcategories
    const CATEGORY_THEMES = 'themes';      // Theme images (logos, favicons, etc.)
    const CATEGORY_FACETS = 'facets';      // Facet category images
    const CATEGORY_LOCATIONS = 'locations'; // Location images
    const CATEGORY_DEFAULT_COVERS = 'default_covers'; // Default cover images

    private function __construct() {
        $this->loadConfiguration();
        $this->initializeStorageBackend();
    }
    
    public static function getInstance(): StorageManager {
        if (self::$instance === null) {
            self::$instance = new StorageManager();
        }
        return self::$instance;
    }
    
    /**
     * Get server name using the same logic as ConfigArray.php
     * This handles cases where StorageManager is initialized before ConfigArray sets global $serverName
     */
    private function getServerName() {
        // Use the same detection logic as ConfigArray.php
        if (!empty($_SERVER['aspen_server'])) {
            return $_SERVER['aspen_server'];
        } elseif (!empty($_SERVER['SERVER_NAME'])) {
            return $_SERVER['SERVER_NAME'];
        } elseif (count($_SERVER['argv']) > 1) {
            return $_SERVER['argv'][1];
        } elseif (!empty(getenv('SITE_NAME'))) {
            return getenv('SITE_NAME');
        } else {
            error_log("StorageManager: Unable to determine server name");
            return 'localhost'; // Safe fallback
        }
    }

    /**
     * Load storage configuration from config files and environment
     */
    private function loadConfiguration() {
        global $configArray, $serverName;
        
        // Get server name directly if global variable isn't set yet (bootstrap timing issue)
        if (empty($serverName)) {
            $serverName = $this->getServerName();
        }
        
        // Default configuration - all user data directly in the data directory
        $this->config = [
            'backend_type' => 'local', // Only local storage supported
            'base_paths' => [
                // ALL user-uploaded data goes directly in the main data directory
                self::TYPE_USER_DATA => getenv('ASPEN_USER_DATA_PATH') ?: "/data/aspen-discovery/{$serverName}",
                
                // Application static assets (read-only)
                self::TYPE_STATIC_ASSETS => getenv('ASPEN_ASSETS_PATH') ?: "/usr/local/aspen-discovery/code/web/assets",
                
                // Temporary storage (always local)
                self::TYPE_TEMPORARY => getenv('ASPEN_TEMP_PATH') ?: "/tmp/aspen-uploads",
            ],
            'permissions' => [
                'directories' => 0755,
                'files' => 0644
            ]
        ];
        
        // Override with config file settings if they exist
        if (isset($configArray['Storage'])) {
            $this->config = array_merge($this->config, $configArray['Storage']);
        }
    }
    
    /**
     * Initialize the local storage backend
     */
    private function initializeStorageBackend() {
        $this->storageBackend = new LocalStorageBackend($this->config);
    }
    
    /**
     * Get the full path for user-uploaded content
     * 
     * @param string $category Category (images, files, covers, fonts)
     * @param string $subCategory Optional subcategory (web_builder, rewards, etc.)
     * @param string $size Optional size variant (full, thumbnail, medium, etc.)
     * @return string Full storage path
     */
    public function getUserDataPath(string $category, string $subCategory = '', string $size = ''): string {
        $basePath = $this->config['base_paths'][self::TYPE_USER_DATA];
        
        $fullPath = $basePath . '/' . $category;
        
        if (!empty($subCategory)) {
            $fullPath .= '/' . $subCategory;
        }
        
        if (!empty($size)) {
            $fullPath .= '/' . $size;
        }
        
        return $fullPath;
    }
    
    /**
     * Get upload path for user-generated content (alias for getUserDataPath)
     */
    public function getUploadPath(string $category, string $subCategory = '', string $size = ''): string {
        return $this->getUserDataPath($category, $subCategory, $size);
    }
    
    /**
     * Get static asset path for application resources
     */
    public function getStaticAssetPath(string $assetPath = ''): string {
        $basePath = $this->config['base_paths'][self::TYPE_STATIC_ASSETS];
        return empty($assetPath) ? $basePath : $basePath . '/' . ltrim($assetPath, '/');
    }
    
    /**
     * Get temporary storage path
     */
    public function getTemporaryPath(string $subPath = ''): string {
        $basePath = $this->config['base_paths'][self::TYPE_TEMPORARY];
        return empty($subPath) ? $basePath : $basePath . '/' . ltrim($subPath, '/');
    }
    
    /**
     * Store a file using the configured storage backend
     */
    public function storeFile(string $sourcePath, string $destinationPath, array $options = []): bool {
        return $this->storageBackend->storeFile($sourcePath, $destinationPath, $options);
    }
    
    /**
     * Retrieve a file from storage
     */
    public function retrieveFile(string $filePath, string $destinationPath): bool {
        return $this->storageBackend->retrieveFile($filePath, $destinationPath);
    }
    
    /**
     * Delete a file from storage
     */
    public function deleteFile(string $filePath): bool {
        return $this->storageBackend->deleteFile($filePath);
    }
    
    /**
     * Check if a file exists in storage
     */
    public function fileExists(string $filePath): bool {
        return $this->storageBackend->fileExists($filePath);
    }
    
    /**
     * Get a public URL for a stored file
     */
    public function getPublicUrl(string $filePath): string {
        return $this->storageBackend->getPublicUrl($filePath);
    }
    
    /**
     * Ensure a directory exists in storage
     */
    public function ensureDirectoryExists(string $directoryPath): bool {
        return $this->storageBackend->ensureDirectoryExists($directoryPath);
    }
    
    /**
     * Initialize all required storage directories
     */
    public function initializeStorage(): bool {
        $requiredPaths = [
            // All user data categories
            $this->getUserDataPath(self::CATEGORY_IMAGES),
            $this->getUserDataPath(self::CATEGORY_IMAGES, self::CATEGORY_WEB_BUILDER, 'full'),
            $this->getUserDataPath(self::CATEGORY_IMAGES, self::CATEGORY_WEB_BUILDER, 'x-large'),
            $this->getUserDataPath(self::CATEGORY_IMAGES, self::CATEGORY_WEB_BUILDER, 'large'),
            $this->getUserDataPath(self::CATEGORY_IMAGES, self::CATEGORY_WEB_BUILDER, 'medium'),
            $this->getUserDataPath(self::CATEGORY_IMAGES, self::CATEGORY_WEB_BUILDER, 'small'),
            $this->getUserDataPath(self::CATEGORY_IMAGES, self::CATEGORY_REWARDS, 'full'),
            $this->getUserDataPath(self::CATEGORY_FILES),
            $this->getUserDataPath(self::CATEGORY_FILES, 'web_builder_pdf'),
            $this->getUserDataPath(self::CATEGORY_FILES, 'record_pdfs'),
            $this->getUserDataPath(self::CATEGORY_FONTS),
            $this->getUserDataPath(self::CATEGORY_COVERS),
            $this->getUserDataPath(self::CATEGORY_COVERS, 'original'),
            $this->getUserDataPath(self::CATEGORY_COVERS, 'thumbnail'),
            $this->getUserDataPath(self::CATEGORY_COVERS, 'medium'),
            
            // Temporary storage
            $this->getTemporaryPath(),
        ];
        
        $success = true;
        foreach ($requiredPaths as $path) {
            if (!$this->ensureDirectoryExists($path)) {
                error_log("Failed to create storage directory: $path");
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Get configuration for debugging
     */
    public function getConfiguration(): array {
        return $this->config;
    }
    
    /**
     * Get storage backend type
     */
    public function getBackendType(): string {
        return $this->config['backend_type'];
    }
}
