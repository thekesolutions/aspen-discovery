<?php

/**
 * Backend Initializer
 */
class BackendInitializer extends InitService {
    private array $templatePaths = [
        "/usr/local/aspen-discovery/sites/template.linux/conf/badBotsLocal.conf",
        "/usr/local/aspen-discovery/sites/template.linux/conf/config.cron.ini",
        "/usr/local/aspen-discovery/sites/template.linux/conf/config.ini",
        "/usr/local/aspen-discovery/sites/template.linux/conf/config.pwd.ini.template",
        "/usr/local/aspen-discovery/sites/template.linux/conf/crontab_settings.txt",
        "/usr/local/aspen-discovery/docker/files/php_fpm/php-fpm.conf"
    ];

    public function __construct() {
        parent::__construct('backend');
    }

    public function run(): void {
        $this->setCurrentStep('create_directories');
        $this->createDirectories();

        $this->setCurrentStep('process_templates');
        $this->processTemplates();

        $this->setCurrentStep('set_permissions');
        $this->setPermissions();
    }

    private function createDirectories(): void {
        $this->log("Creating backend directories...");
        
        $directoriesFile = __DIR__ . "/../directories.json";
        if (!file_exists($directoriesFile)) {
            throw new InitException($this->mode, $this->currentStep, "Directories configuration not found: {$directoriesFile}");
        }

        $directories = json_decode(file_get_contents($directoriesFile), true);
        if (!$directories) {
            throw new InitException($this->mode, $this->currentStep, "Failed to parse directories configuration");
        }

        foreach ($directories as $dirName => $dir) {
            $pathDir = $this->getMainDirectory($dirName);
            $this->createDirectoryStructure($dir, $pathDir);
        }
    }

    private function getMainDirectory(string $dirName): string {
        switch ($dirName) {
            case 'baseDir':
                return $this->baseDir;
            case 'configDir':
                return $this->configDir;
            case 'dataDir':
                return $this->dataDir;
            case 'logsDir':
                return $this->logsDir;
            default:
                throw new InitException($this->mode, $this->currentStep, "Unknown directory type: {$dirName}");
        }
    }

    private function createDirectoryStructure(array $dir, string $pathDir): void {
        $this->ensureDirectoryExists($pathDir);

        foreach ($dir as $property => $value) {
            if ($property !== 'owner' && $property !== 'permissions') {
                $fullPath = $pathDir . DIRECTORY_SEPARATOR . $property;
                $this->ensureDirectoryExists($fullPath);

                if (is_array($value)) {
                    $this->createDirectoryStructure($value, $fullPath);
                }
            }
        }
    }

    private function processTemplates(): void {
        $this->log("Processing configuration templates...");

        foreach ($this->templatePaths as $templatePath) {
            if (file_exists($templatePath)) {
                $outputPath = $this->configDir . "/conf/" . basename($templatePath);
                $this->processTemplate($templatePath, $outputPath);
            } else {
                $this->log("Template not found, skipping: {$templatePath}");
            }
        }
    }

    private function setPermissions(): void {
        $this->log("Setting directory permissions and ownership...");

        $owner = "www-data";
        $localUserId = $this->getConfig('localUserId');

        // Set user ID for www-data
        $this->executeCommand("usermod -o -u {$localUserId} {$owner}", true, true);

        // Set ownership for main directories
        $mainDirectories = [
            $this->baseDir,
            $this->configDir,
            $this->dataDir,
            $this->logsDir
        ];

        foreach ($mainDirectories as $directory) {
            if (is_dir($directory)) {
                $this->executeCommand("chown -R {$owner} {$directory}", true, true);
                $this->executeCommand("chmod -R 755 {$directory}", true, true);
                $this->log("Set permissions for: {$directory}");
            }
        }
    }
}
