<?php

/**
 * Aspen Discovery Service Entrypoint (Production)
 * 
 * Loads and runs services using the existing Service class pattern
 * Initialization is handled separately by init containers
 */

// Include the Service base class
require_once __DIR__ . '/Service.php';

/**
 * Main execution
 */
try {
    logMessage("Aspen Discovery Service Entrypoint started");
    
    // Get service name from arguments
    $service = $argv[1] ?? null;
    
    if (!$service) {
        logMessage("No service specified", 'ERROR');
        echo "Usage: php entrypoint.php <service>\n";
        echo "Available services: apache, backend, cron\n";
        exit(1);
    }
    
    logMessage("Starting service: {$service}");
    
    // Load service class based on service name
    if (!loadServiceClass($service)) {
        exit(1);
    }
    
    // Create service class name (e.g., 'backend' -> 'Backend')
    $serviceClassName = ucfirst($service);
    
    logMessage("Loading service class: {$serviceClassName}");
    
    // Create service instance
    $serviceInstance = new $serviceClassName($service);
    
    logMessage("Service instance created: {$service}");
    logMessage("Starting service execution...");
    
    // Run the service (this will handle dependencies -> runtime -> start)
    $serviceInstance->run();
    
    logMessage("Service completed successfully: {$service}");
    
} catch (ServiceException $e) {
    logMessage("Service exception: " . $e->getMessage(), 'ERROR');
    logMessage("Service: " . $e->getServiceName(), 'ERROR');
    logMessage("Step: " . $e->getStep(), 'ERROR');
    exit(1);
    
} catch (Exception $e) {
    logMessage("Unexpected error: " . $e->getMessage(), 'ERROR');
    logMessage("File: " . $e->getFile() . " Line: " . $e->getLine(), 'ERROR');
    exit(1);
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
    
    // Check if class exists (e.g., 'backend' -> 'Backend')
    $className = ucfirst($serviceName);
    if (!class_exists($className)) {
        logMessage("Service class not found: {$className}", 'ERROR');
        return false;
    }
    
    return true;
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
