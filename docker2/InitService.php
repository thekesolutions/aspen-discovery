<?php

/**
 * Init Exception
 */
class InitException extends Exception {
    private string $mode;
    private string $step;
    
    public function __construct(string $mode, string $step, string $message, int $code = 0, ?Throwable $previous = null) {
        $this->mode = $mode;
        $this->step = $step;
        parent::__construct("Initialization '{$mode}' failed at step '{$step}': {$message}", $code, $previous);
    }
    
    public function getMode(): string {
        return $this->mode;
    }
    
    public function getStep(): string {
        return $this->step;
    }
}

/**
 * Abstract Init Service Class
 */
abstract class InitService {
    protected string $mode;
    protected array $config;
    protected string $currentStep;
    protected string $baseDir;
    protected string $configDir;
    protected string $dataDir;
    protected string $logsDir;

    public function __construct(string $mode) {
        $this->mode = $mode;
        $this->config = [];
        $this->currentStep = "Initialization: {$mode}";
        $this->loadConfig();
        $this->loadMainDirs();
    }

    /**
     * Main run method - implemented by each initializer
     */
    abstract public function run(): void;

    /**
     * Load configuration from INI file and environment variables
     */
    protected function loadConfig(): void {
        $iniFilePath = "/usr/local/aspen-discovery/docker2/config.default.ini";
        $environVars = getenv();
        $environFallbacks = parse_ini_file($iniFilePath, true);

        if (!$environVars || !$environFallbacks) {
            throw new InitException($this->mode, $this->currentStep, "Failed to load configuration variables");
        }

        // Load common and service-specific variables
        foreach ($environFallbacks as $serviceName => $serviceVariables) {
            if ($serviceName == 'common' || $this->shouldLoadServiceConfig($serviceName)) {
                $this->log("Loading '{$serviceName}' variables...");
                foreach ($serviceVariables as $key => $value) {
                    $camelCaseKey = $this->toCamelCase($key);

                    // FIXME: The config variable 'sitename' should be called siteName
                    if ($camelCaseKey == 'siteName') {
                        $camelCaseKey = 'sitename';
                    }

                    if (!array_key_exists($key, $environVars) || empty($environVars[$key])) {
                        $this->config[$camelCaseKey] = $value;
                    } else {
                        $this->config[$camelCaseKey] = $environVars[$key];
                    }
                    $this->log("'{$camelCaseKey}' => " . $this->config[$camelCaseKey]);
                }
            }
        }

        // Set server name from URL
        $serverName = preg_replace('~https?://~', '', $this->config['url']);
        $this->config['serverName'] = $serverName;
        $this->log("'serverName' => " . $serverName);
    }

    /**
     * Determine if service config should be loaded for this initializer
     */
    protected function shouldLoadServiceConfig(string $serviceName): bool {
        // Override in specific initializers if needed
        return true;
    }

    /**
     * Load main directories
     */
    protected function loadMainDirs(): void {
        $this->log("Loading main directories...");
        
        $siteName = $this->config['sitename'];
        
        $this->baseDir = getenv("BASE_DIR") ?: "/usr/local/aspen-discovery";
        $this->configDir = getenv("CONFIG_DIR") ?: "/usr/local/aspen-discovery/sites/{$siteName}";
        $this->dataDir = getenv("DATA_DIR") ?: "/data/aspen-discovery/{$siteName}";
        $this->logsDir = getenv("LOGS_DIR") ?: "/var/log/aspen-discovery/{$siteName}";
        
        $this->log("Base directory => " . $this->baseDir);
        $this->log("Configuration directory => " . $this->configDir);
        $this->log("Data directory => " . $this->dataDir);
        $this->log("Logs directory => " . $this->logsDir);
    }

    /**
     * Get configuration value
     */
    protected function getConfig(?string $key = null): array|string {
        if ($key === null) {
            return $this->config;
        }

        if (array_key_exists($key, $this->config)) {
            return (string)$this->config[$key];
        }

        throw new InitException($this->mode, $this->currentStep, "Configuration key not found: {$key}");
    }

    /**
     * Set current step
     */
    protected function setCurrentStep(string $step): void {
        $this->currentStep = $step;
        $this->log("Starting step → '{$step}'");
    }

    /**
     * Execute a shell command and handle errors
     */
    protected function executeCommand(string $command, bool $throwOnError = true, bool $quiet = false): array {
        if (!$quiet) {
            $this->log("Executing command: {$command}");
        }

        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0 && $throwOnError) {
            $errorMessage = "Command failed with exit code {$exitCode}: " . implode("\n", $output);
            throw new InitException($this->mode, $this->currentStep, $errorMessage);
        }

        return [
            'output' => $output,
            'exit_code' => $exitCode
        ];
    }

    /**
     * Ensure directory exists
     */
    protected function ensureDirectoryExists(string $dir): void {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new InitException($this->mode, $this->currentStep, "Failed to create directory: {$dir}");
            }
            $this->log("Created directory: {$dir}");
        }
    }

    /**
     * Process template file with variable replacement
     */
    protected function processTemplate(string $templatePath, string $outputPath, array $additionalVariables = []): void {
        if (!file_exists($templatePath)) {
            throw new InitException($this->mode, $this->currentStep, "Template file not found: {$templatePath}");
        }

        // Get variables from the configuration array
        $variables = $this->getConfig();

        // Add any additional variables
        $variables = array_merge($variables, $additionalVariables);

        // Replace variables in file
        $this->replaceVariablesInFile($templatePath, $outputPath, $variables);

        $this->log("Processed template {$templatePath} to {$outputPath}");
    }

    /**
     * Replace variables in file using {variable} format
     */
    protected function replaceVariablesInFile(string $templatePath, string $outputPath, array $variables): void {
        $contents = file($templatePath);
        if ($contents === false) {
            throw new InitException($this->mode, $this->currentStep, "Failed to read template: {$templatePath}");
        }

        $outputDir = dirname($outputPath);
        $this->ensureDirectoryExists($outputDir);

        $fHnd = fopen($outputPath, 'w');
        if ($fHnd === false) {
            throw new InitException($this->mode, $this->currentStep, "Failed to open output file: {$outputPath}");
        }

        foreach ($contents as $line) {
            foreach ($variables as $name => $value) {
                $line = str_replace('{' . $name . '}', $value, $line);
            }
            fwrite($fHnd, $line);
        }
        fclose($fHnd);
    }

    /**
     * Convert string to camelCase
     */
    private function toCamelCase(string $string, bool $capitalizeFirstCharacter = false): string {
        $str = str_replace(' ', '', ucwords(strtolower(str_replace(['-', '_'], ' ', $string))));

        if (!$capitalizeFirstCharacter) {
            $str[0] = strtolower($str[0]);
        }

        return $str;
    }

    /**
     * Log message with timestamp
     */
    protected function log(string $message, string $level = 'INFO'): void {
        $timestamp = date('Y-m-d H:i:s');

        $level = $level == 'INFO' ? "\033[38;5;117mINFO\033[0m" : $level;
        $level = $level == 'ERROR' ? "\033[31mERROR\033[0m" : $level;

        $logMessage = "[{$timestamp}] [{$level}] [{$this->mode}] {$message}" . PHP_EOL;

        // Log to stdout for Docker
        echo $logMessage;
    }
}
