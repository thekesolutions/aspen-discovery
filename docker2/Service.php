<?php

/**
 * Service Status
 * 
 * 
 */

class ServiceStatus {
    const STOPPED = 'stopped';
    const CONFIGURING = 'configuring';
    const READY = 'ready';
}

/**
 * Service Exception
 * 
 * 
 */
class ServiceException extends Exception {
    private string $serviceName;
    private string $step;
    
    
    public function __construct(string $serviceName, string $step, string $message, int $code = 0, ?Throwable $previous = null) {
        $this->serviceName = $serviceName;
        $this->step = $step;
        parent::__construct("Service '{$serviceName}' failed at step '{$step}' on line {$this->line}: {$message}", $code, $previous);
    }
    
    public function getServiceName(): string {
        return $this->serviceName;
    }
    
    public function getStep(): string {
        return $this->step;
    }
}

/**
 * Abstract Service Class
 * 
 * 
 */

abstract class Service {
    protected string $name;
    protected string $owner;
    protected string $status;
    protected array $config;
    protected string $currentStep;
    protected string $baseDir;
    protected string $configDir;
    protected string $dataDir;
    protected string $logsDir;
    protected array $steps;
    protected array $templatesPaths;

    
    public function __construct(string $name, ?string $owner = "www-data") {
        $this->name = $name;
        $this->owner = $owner;
        $this->status = ServiceStatus::STOPPED;
        $this->config = [];
        $this->baseDir = "";
        $this->configDir = "";
        $this->dataDir = "";
        $this->logsDir = "";
        $this->templatesPaths = [];
        $this->currentStep = "Instance creation: $name";
        $this->loadConfig();
        $this->assignOwner();
        $this->loadMainDirs();
        $this->setTemplatePaths();
        if(!$this->configExists()){
            $this->setSteps();
        }
    }

    protected const TEMPLATE_PATHS = [];

    protected function getMainDefaultDirectories($key = null): array|string {
        $siteName = $this->config['sitename'];
        $mainDirs = [
            'baseDir' => "/usr/local/aspen-discovery",
            'configDir' => "/usr/local/aspen-discovery/sites/$siteName",
            'dataDir' => "/data/aspen-discovery/$siteName",
            'logsDir' => "/var/log/aspen-discovery/$siteName"
        ];

        if($key === null){
            return $mainDirs;
        }

        if (array_key_exists($key,$mainDirs)){
            return $mainDirs[$key];
        }

        $message = "The provided key is not a valid entry to get a main directory";
        throw new ServiceException($this->name,$this->getCurrentStep(),$message);
    }
    
    public function getName(): string {
        return $this->name;
    }

    public function getConfig(?string $key = null): array|string {
    if ($key === null) {
        return $this->config;
    }

    if (array_key_exists($key, $this->config)) {
        return (string)$this->config[$key];
    }
    
    $message = "Something is wrong trying to get the required configuration";
    throw new ServiceException($this->name,$this->getCurrentStep(),$message,);
}
    
    public function getStatus(): string {
        return $this->status;
    }
    
    protected function setStatus(string $status): void {
        $this->status = $status;
        $this->log("Status changed to: {$status}");
    }
    
    public function getCurrentStep(): string {
        return $this->currentStep;
    }
    
    protected function setCurrentStep(string $step): void {
        $this->currentStep = $step;
        $this->log("Starting step → '{$step}'");
    }

    protected function setSteps(): void {
        $className = get_class($this);
        $methods = get_class_methods($className);

        foreach($methods as $method){
            if (str_starts_with($method,"step_")){
                $step = substr($method,5);
                $this->steps[] = $step;
                $this->log("Added step → $step");
            }
        }
    }

    protected function getTemplatesPaths(): array {
        return $this->templatesPaths;
    }

    protected function setTemplatePaths(): void {

        $defaultPaths = [];

        $this->log("Getting template paths...");

        if (!defined('static::TEMPLATE_PATHS')) {
            throw new ServiceException($this->getName(),$this->getCurrentStep(),
            "The class " . $this::class . "needs to define the static array TEMPLATE_PATHS");
        }
        
        if (!is_array(static::TEMPLATE_PATHS)) {
            throw new ServiceException($this->getName(),$this->getCurrentStep(),
            "The TEMPLATE_PATHS needs to be an array");
        }
        
        if (empty(static::TEMPLATE_PATHS)) {
            throw new ServiceException($this->getName(),$this->getCurrentStep(),
            "The TEMPLATE_PATHS is empty");
        }
        
        foreach (static::TEMPLATE_PATHS as $templatePath) {
            if (empty(trim($templatePath))) {
                throw new ServiceException($this->getName(),$this->getCurrentStep(),
                "The '{$templatePath}' path cannot be empty");
            }

            if (is_dir($templatePath)) {
                throw new ServiceException($this->getName(),$this->getCurrentStep(),
                "The path $templatePath is a directory and needs to be a regular file");
            }
        }
        
        $this->log("TEMPLATES : ");
        foreach (static::TEMPLATE_PATHS as $path) {
            $this->log("- " . $path);
            $defaultPaths[] = $path;

        }
        
        $this->templatesPaths = $defaultPaths;
    }

    /**
     * Assign an owner to the source code
     */
    protected function assignOwner(): void {

        try{
            $localUserId = $this->getConfig('localUserId');
            $this->log("Setting " . $this->owner . " to UID = " . $localUserId);
            $result = $this->executeCommand("usermod -o -u " . $localUserId . " $this->owner");

            $this->log("Assigning owner to the source code");
            $sourceCodePath = "/usr/local/aspen-discovery";
            $result = $this->executeCommand("chown -R $this->owner " . $sourceCodePath);
        } catch (ServiceException $e){
            $this->log("The assignment failed");
            throw $e;
        }
    }
    
    /**
     * Load service configuration from INI file and environment variables
     * Use values from the INI file as default values.
     */
    protected function loadConfig(): void {
        $iniFilePath = "/usr/local/aspen-discovery/docker2/config.default.ini";
        $environVars = getenv();
        $environFallbacks = parse_ini_file($iniFilePath,true);

        if(!$environVars || !$environFallbacks){
            $message = "Something is wrong by loading the variables";
            throw new ServiceException($this->name,$this->getCurrentStep(),$message);
        }

        // Load variables either from INI or .env files
        
        foreach($environFallbacks as $serviceName => $serviceVariables) { 
            if($serviceName == 'common' || $serviceName == $this->name){
                $this->log("Loading '$serviceName' variables...");
                foreach($serviceVariables as $key => $value) {
                    $camelCaseKey = $this->toCamelCase($key);

                    # FIXME : The config variable 'sitename' should be called siteName
                    # and follow the lower camel case convention
                    if($camelCaseKey == 'siteName'){
                        $camelCaseKey = 'sitename';
                    }

                    if(!in_array($key,array_keys($environVars)) || (in_array($key,array_keys($environVars)) && empty($environVars[$key]))){
                        $this->config[$camelCaseKey] = $value;
                    } else{
                        $this->config[$camelCaseKey] = $environVars[$key];
                    }
                    $this->log("'$camelCaseKey' => " . $this->config[$camelCaseKey]);
                }
            }
        }

        // Set server name from url
        $serverName = preg_replace('~https?://~', '', $this->config['url']);
        $this->config['serverName'] = $serverName;
        $this->log("'serverName' => " . $serverName);
    }

    protected function loadMainDirs(){
        $this->log("Loading main directories...");

        // Base directory
        $this->baseDir = empty(getenv("BASE_DIR")) ? $this->getMainDefaultDirectories(('baseDir')) : getenv("BASE_DIR");
        $this->log('Base directory => ' . $this->baseDir);

        // Configuration directory
        $this->configDir = empty(getenv("CONFIG_DIR")) ? $this->getMainDefaultDirectories(('configDir')) : getenv("CONFIG_DIR");
        $this->log('Configuration directory => ' . $this->configDir);
        
        // Data directory
        $this->dataDir = empty(getenv("DATA_DIR")) ? $this->getMainDefaultDirectories(('dataDir')) : getenv("DATA_DIR");
        $this->log('Data directory => ' . $this->dataDir);

        // Logs directory
        $this->logsDir = empty(getenv("LOGS_DIR")) ? $this->getMainDefaultDirectories(('logsDir')) : getenv("LOGS_DIR");
        $this->log('Logs directory => ' . $this->logsDir);
    }

    /**
     * Check if configuration directory exists and it is not empty
     */

    protected function configExists(): bool {
        $configExists = false;

        if(count(scandir($this->configDir)) > 2){
            $this->status = ServiceStatus::READY;
            $this->log("The service $this->name has already been initialized");
            $this->log("Configuration directory : $this->configDir");
            $configExists = true;
        }

        return $configExists;
    }

    /**
     * Run the current step
     */
    protected function runCurrentStep(string $step): void {

        $this->setCurrentStep($step);
        $this->setStatus(ServiceStatus::CONFIGURING);
        $stepMethodName = "step_" . $step;
        $this->$stepMethodName();

        $this->setStatus(ServiceStatus::READY);
        $this->log("Step completed → '$step'");

    }

    /**
     * Run all the steps
     */
    protected function runSteps(){
        foreach($this->steps as $step){
            $this->runCurrentStep($step);
        }
    }

    /**
     * Execute procedures to be executed whenever the service is started.
     * This method will start procedures regardless of whether a new instance is being started or not. 
     */

    protected function afterSteps(){}

    /**
     * Start the service (run in foreground)
     */
    abstract protected function start(): void;
    
    /**
     * Main execution method - runs through all steps
     */
    public function run(): void {
        try {
            $this->log("Starting service: {$this->name}");
            
            if (!$this->configExists()){
                $this->runSteps();
            }

            $this->afterSteps();
            
            // Step 3: Start (runs in foreground)
            $this->setCurrentStep('start');
            $this->start();
            
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
     * Get configuration value with fallback
     */
    protected function getConfigValue(string $key, $default = null) {
        return $this->config[$key] ?? $default;
    }
    
    /**
     * Set configuration value
     */
    protected function setConfigValue(string $key, $value): void {
        $this->config[$key] = $value;
    }
    
    /**
     * Execute a shell command and handle errors
     */
    protected function executeCommand(string $command, bool $throwOnError = true, bool $quite = false): array {
        if (!$quite) {
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
     * Copy file with error handling
     */
    protected function copyFile(string $source, string $destination): void {
        if (!file_exists($source)) {
            throw new ServiceException($this->name, $this->currentStep, "Source file not found: {$source}");
        }
        
        $destinationDir = dirname($destination);
        $this->ensureDirectoryExists($destinationDir);
        
        if (!copy($source, $destination)) {
            throw new ServiceException($this->name, $this->currentStep, "Failed to copy {$source} to {$destination}");
        }
        
        $this->log("Copied {$source} to {$destination}");
    }
    
    /**
     * Process template file with variable replacement (format: {sitename})
     */
    protected function processTemplate(string $templatePath, string $outputPath, array $additionalVariables = [], bool $overwrite = false): void {
        if (!file_exists($templatePath)) {
            throw new ServiceException($this->name, $this->currentStep, "Template file not found: {$templatePath}");
        }
        
        // Check if output file already exists and skip if not overwriting
        if (!$overwrite && file_exists($outputPath)) {
            $this->log("Skipping template processing, file already exists: {$outputPath}");
            return;
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
            throw new ServiceException($this->name, $this->currentStep, "Failed to read template: {$templatePath}");
        }
        
        $outputDir = dirname($outputPath);
        $this->ensureDirectoryExists($outputDir);
        
        $fHnd = fopen($outputPath, 'w');
        if ($fHnd === false) {
            throw new ServiceException($this->name, $this->currentStep, "Failed to open output file: {$outputPath}");
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
     * Ensure directory exists
     */
    protected function ensureDirectoryExists(string $dir): void {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) ) {
                throw new ServiceException($this->name, $this->currentStep, "Failed to create directory: {$dir}");
            }
        }
    }
    
    /**
     * Log message with timestamp and service name
     */
    protected function log(string $message, string $level = 'INFO' ): void {
        $timestamp = date('Y-m-d H:i:s');

        $level = $level == 'INFO' ? "\033[38;5;117mINFO\033[0m" : $level;
        $level = $level == 'ERROR' ? "\033[31mERROR\033[0m" : $level;

        $logMessage = "[{$timestamp}] [{$level}] [{$this->name}] {$message}" . PHP_EOL;

        // Log to stdout for Docker
        echo $logMessage;
    }

    /**
     * Addapt a **string** to camelCase
     */
    private function toCamelCase(string $string, bool $capitalizeFirstCharacter = false): string {
      $str = str_replace(' ', '', ucwords(strtolower(str_replace(['-', '_'], ' ', $string))));
        
      if (!$capitalizeFirstCharacter) {
        $str[0] = strtolower($str[0]);
      }
    
      return $str;
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
