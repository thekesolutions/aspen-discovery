<?php

class Backend extends Service {

    public function __construct(string $name) {
        parent::__construct($name);
    }

    /**
     * Check service dependencies
     */
    protected function checkDependencies(): void {
        $this->log("Checking backend dependencies...");
        
        // Check database connection
        $this->checkDatabaseConnection();
        
        // Check configuration exists
        $this->checkConfigurationExists();
        
        $this->log("Backend dependencies verified");
    }

    /**
     * Setup runtime environment
     */
    protected function setupRuntime(): void {
        $this->log("Setting up backend runtime environment...");
        
        // Set timezone
        if (!empty($this->getConfig('timezone'))) {
            date_default_timezone_set($this->getConfig('timezone'));
            $this->log("Timezone set to: " . $this->getConfig('timezone'));
        }
        
        // Set user permissions
        $localUserId = $this->getConfig('localUserId');
        $owner = 'www-data';
        
        // Ensure www-data has correct UID (idempotent operation)
        $result = $this->executeCommand("id -u {$owner}", false, true);
        if ($result['exit_code'] === 0 && trim($result['output'][0]) !== $localUserId) {
            $this->executeCommand("usermod -o -u {$localUserId} {$owner}", true, true);
            $this->log("Updated {$owner} UID to: {$localUserId}");
        }
        
        $this->log("Backend runtime setup completed");
    }

    /**
     * Start the backend service
     */
    protected function startService(): void {
        $this->log("Starting backend service...");
        
        // Test PHP-FPM configuration first
        $result = $this->executeCommand('php-fpm8.4 --test', false);
        if ($result['exit_code'] !== 0) {
            throw new ServiceException($this->name, $this->currentStep, 
                "PHP-FPM configuration test failed: " . implode("\n", $result['output']));
        }
        $this->log("PHP-FPM configuration test passed");
        
        // Start PHP-FPM in foreground
        $this->log("Starting PHP-FPM in foreground mode");
        $this->executeCommand('php-fpm8.4 -F');
    }

    /**
     * Check database connection
     */
    private function checkDatabaseConnection(): void {
        $dsn = "mysql:host=" . $this->getConfig('databaseHost') . 
               ";port=" . $this->getConfig('databasePort') . 
               ";dbname=" . $this->getConfig('databaseName');
        
        $maxAttempts = 10;
        $attempt = 1;
        
        while ($attempt <= $maxAttempts) {
            try {
                $pdo = new PDO($dsn, $this->getConfig('databaseUser'), $this->getConfig('databasePassword'), [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5
                ]);
                
                // Test with a simple query
                $pdo->query('SELECT 1');
                $this->log("Database connection verified");
                return;
                
            } catch (PDOException $e) {
                if ($attempt === $maxAttempts) {
                    throw new ServiceException($this->name, $this->currentStep, 
                        "Database connection failed after {$maxAttempts} attempts: " . $e->getMessage());
                }
                
                $this->log("Database connection attempt {$attempt}/{$maxAttempts} failed, retrying...");
                sleep(2);
                $attempt++;
            }
        }
    }

    /**
     * Check if configuration exists
     */
    private function checkConfigurationExists(): void {
        $configDir = "/usr/local/aspen-discovery/sites/" . $this->getConfig('sitename');
        
        if (!is_dir($configDir) || count(scandir($configDir)) <= 2) {
            throw new ServiceException($this->name, $this->currentStep, 
                "Configuration directory not found or empty: {$configDir}. Run initialization first.");
        }
        
        // Check for essential config files
        $essentialFiles = [
            $configDir . '/conf/config.ini',
        ];
        
        foreach ($essentialFiles as $file) {
            if (!file_exists($file)) {
                throw new ServiceException($this->name, $this->currentStep, 
                    "Essential configuration file missing: {$file}");
            }
        }
        
        $this->log("Configuration files verified");
    }
}
