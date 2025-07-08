<?php

/**
 * Aspen Discovery Service Entrypoint
 * 
 * Main entrypoint that initializes and runs specific services based on command parameter
 */



// Include the Service base class
require_once __DIR__ . '/Service.php';

/**
 * Main execution
 */
try {
    logMessage("Aspen Discovery Service Entrypoint started");
    
    // Get command from arguments (this matches docker-compose command)
    $command = $argv[1] ?? null;
    
    if (!$command) {
        logMessage("No service command provided", 'ERROR');
        echo "Usage: php entrypoint.php <service_name> [<owner>]\n";
        echo "Available services: apache, backend, cron\n";
        exit(1);
    }
    
    logMessage("Initializing service: {$command}");
    
    // Load service class based on command name
    if (!loadServiceClass($command)) {
        exit(1);
    }
    
    // Create service class name (e.g., 'apache' -> 'Apache')
    $serviceClassName = ucfirst($command);
    
    logMessage("Loading service class: {$serviceClassName}");
    
    // Create service instance
    $service = new $serviceClassName($command);
    
    logMessage("Service instance created: {$command}");
    logMessage("Starting service execution...");
    
    // Run the service (this will handle configure -> ready -> start)
    // The service itself will check if files exist to avoid overwriting
    $service->run();
    
    logMessage("Service completed successfully: {$command}");
    
} catch (ServiceException $e) {
    logMessage("Service exception: " . $e->getMessage(), 'ERROR');
    logMessage("Service: " . $e->getServiceName(), 'ERROR');
    logMessage("Step: " . $e->getStep(), 'ERROR');
    exit(1);
    
} catch (Exception $e) {
    logMessage("Unexpected error: " . $e->getMessage(), 'ERROR');
    logMessage("File: " . $e->getFile() . " Line: " . $e->getLine(), 'ERROR');
    exit(1);
    
} catch (Error $e) {
    logMessage("Fatal error: " . $e->getMessage(), 'ERROR');
    logMessage("File: " . $e->getFile() . " Line: " . $e->getLine(), 'ERROR');
    exit(1);
}


/**
 * Log function for entrypoint - only stdout for Docker
 */
function logMessage(string $message, string $level = 'INFO'): void {
    $timestamp = date('Y-m-d H:i:s');

    $level = $level == 'INFO' ? "\033[38;5;117mINFO\033[0m" : $level;
    $level = $level == 'ERROR' ? "\033[31mERROR\033[0m" : $level;

    $logMessage = "[{$timestamp}] [{$level}] [ENTRYPOINT] {$message}" . PHP_EOL;
    
    // Only log to stdout for Docker
    echo $logMessage;
}

/**
 * Load service class file based on service name
 */
function loadServiceClass(string $serviceName): bool {
    $serviceFile = __DIR__ . "/services/" . ucfirst($serviceName) . ".php";
    
    if (!file_exists($serviceFile)) {
        logMessage("Service file not found: {$serviceFile}", 'ERROR');
        return false;
    }
    
    require_once $serviceFile;
    
    // Check if class exists (e.g., 'apache' -> 'Apache')
    $className = ucfirst($serviceName);
    if (!class_exists($className)) {
        logMessage("Service class not found: {$className}", 'ERROR');
        return false;
    }
    
    return true;
}