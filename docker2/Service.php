<?php

/**
 * Service Status
 */
class ServiceStatus {
    const STOPPED = 'stopped';
    const STARTING = 'starting';
    const RUNNING = 'running';
}

/**
 * Service Exception
 */
class ServiceException extends Exception {
    private string $serviceName;
    private string $step;
    
    public function __construct(string $serviceName, string $step, string $message, int $code = 0, ?Throwable $previous = null) {
        $this->serviceName = $serviceName;
        $this->step = $step;
        parent::__construct("Service '{$serviceName}' failed at step '{$step}': {$message}", $code, $previous);
    }
    
    public function getServiceName(): string {
        return $this->serviceName;
    }
    
    public function getStep(): string {
        return $this->step;
    }
}

/**
 * Abstract Service Class (Runtime Operations Only)
 * 
 * Handles runtime operations for production services
 * Initialization is handled separately by InitService
 */
abstract class Service {
    protected string $name;
    protected string $status;
    protected array $config;
    protected string $currentStep;

    public function __construct(string $name) {
        $this->name = $name;
        $this->status = ServiceStatus::STOPPED;
        $this->currentStep = "Service startup: {$name}";
        $this->loadConfig();
    }

    /**
     * Main runtime execution - checks dependencies, sets up runtime, starts service
     */
    public function run(): void {
        try {
            $this->log("Starting service: {$this->name}");
            $this->setStatus(ServiceStatus::STARTING);
            
            $this->setCurrentStep('check_dependencies');
            $this->checkDependencies();
            
            $this->setCurrentStep('setup_runtime');
            $this->setupRuntime();
            
            $this->setCurrentStep('start_service');
            $this->setStatus(ServiceStatus::RUNNING);
            $this->startService();
            
        } catch (ServiceException $e) {
            $this->log("Service failed: " . $e->getMessage(), 'ERROR');
            throw $e;
        } catch (Exception $e) {
            $serviceException = new ServiceException($this->name, $this->currentStep, $e->getMessage(), $e->getCode(), $e);
            $this->log("Service failed: " . $serviceException->getMessage(), 'ERROR');
            throw $serviceException;
        }
    }

    /**
     * Check service-specific dependencies (implemented by each service)
     */
    abstract protected function checkDependencies(): void;

    /**
     * Setup runtime environment (implemented by each service)
     */
    abstract protected function setupRuntime(): void;

    /**
     * Start the actual service process (implemented by each service)
     */
    abstract protected function startService(): void;

    /**
     * Load configuration from INI file and environment variables
     */
    protected function loadConfig(): void {
        $iniFilePath = "/usr/local/aspen-discovery/docker2/config.default.ini";
        
        if (!file_exists($iniFilePath)) {
            throw new ServiceException($this->name, $this->currentStep, "Configuration file not found: {$iniFilePath}");
        }
        
        $environVars = getenv();
        $environFallbacks = parse_ini_file($iniFilePath, true);
        
        if (!$environFallbacks) {
            throw new ServiceException($this->name, $this->currentStep, "Failed to parse configuration file: {$iniFilePath}");
        }
        
        $this->config = [];
        
        // Load common and service-specific configurations
        foreach ($environFallbacks as $serviceName => $serviceVariables) {
            if ($serviceName === 'common' || $serviceName === $this->name) {
                foreach ($serviceVariables as $key => $value) {
                    $camelCaseKey = $this->toCamelCase($key);
                    
                    // Handle sitename special case
                    if ($camelCaseKey === 'siteName') {
                        $camelCaseKey = 'sitename';
                    }
                    
                    // Priority: Environment variable > INI file default
                    if (array_key_exists($key, $environVars) && !empty($environVars[$key])) {
                        $this->config[$camelCaseKey] = $environVars[$key];
                    } else {
                        $this->config[$camelCaseKey] = $value;
                    }
                }
            }
        }
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

        throw new ServiceException($this->name, $this->currentStep, "Configuration key not found: {$key}");
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
            throw new ServiceException($this->name, $this->currentStep, $errorMessage);
        }

        return [
            'output' => $output,
            'exit_code' => $exitCode
        ];
    }

    /**
     * Set service status
     */
    protected function setStatus(string $status): void {
        $this->status = $status;
        $this->log("Status changed to: {$status}");
    }

    /**
     * Get service status
     */
    public function getStatus(): string {
        return $this->status;
    }

    /**
     * Set current step
     */
    protected function setCurrentStep(string $step): void {
        $this->currentStep = $step;
        $this->log("Starting step → '{$step}'");
    }

    /**
     * Get current step
     */
    public function getCurrentStep(): string {
        return $this->currentStep;
    }

    /**
     * Convert string to camelCase
     */
    private function toCamelCase(string $string): string {
        $str = str_replace(' ', '', ucwords(strtolower(str_replace(['-', '_'], ' ', $string))));
        $str[0] = strtolower($str[0]);
        return $str;
    }

    /**
     * Log message with timestamp and service name
     */
    protected function log(string $message, string $level = 'INFO'): void {
        $timestamp = date('Y-m-d H:i:s');

        $level = $level == 'INFO' ? "\033[38;5;117mINFO\033[0m" : $level;
        $level = $level == 'ERROR' ? "\033[31mERROR\033[0m" : $level;

        $logMessage = "[{$timestamp}] [{$level}] [{$this->name}] {$message}" . PHP_EOL;

        // Log to stdout for Docker
        echo $logMessage;
    }

    /**
     * Get service information as array
     */
    public function toArray(): array {
        return [
            'name' => $this->name,
            'status' => $this->status,
            'current_step' => $this->currentStep,
            'config' => $this->config
        ];
    }
}
