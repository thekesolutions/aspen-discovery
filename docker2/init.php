<?php

/**
 * Aspen Discovery Initialization Script
 * 
 * Handles one-time initialization tasks for production deployment
 * This runs in init containers before the main services start
 */

require_once __DIR__ . '/InitService.php';

/**
 * Main execution
 */
try {
    logMessage("Aspen Discovery Initialization started");
    
    // Get initialization mode from arguments or environment
    $mode = $argv[1] ?? getenv('INIT_MODE');
    
    if (!$mode) {
        logMessage("No initialization mode provided", 'ERROR');
        echo "Usage: php init.php <mode>\n";
        echo "Available modes: database, backend\n";
        echo "Or set INIT_MODE environment variable\n";
        exit(1);
    }
    
    logMessage("Initialization mode: {$mode}");
    
    // Load and run the appropriate initializer
    $initializer = createInitializer($mode);
    $initializer->run();
    
    logMessage("Initialization completed successfully: {$mode}");
    
} catch (InitException $e) {
    logMessage("Initialization exception: " . $e->getMessage(), 'ERROR');
    logMessage("Mode: " . $e->getMode(), 'ERROR');
    logMessage("Step: " . $e->getStep(), 'ERROR');
    exit(1);
    
} catch (Exception $e) {
    logMessage("Unexpected error: " . $e->getMessage(), 'ERROR');
    logMessage("File: " . $e->getFile() . " Line: " . $e->getLine(), 'ERROR');
    exit(1);
}

/**
 * Create initializer dynamically without switch statements
 */
function createInitializer(string $mode): InitService {
    // Convert mode to class name (e.g., 'database' -> 'DatabaseInitializer')
    $className = ucfirst($mode) . 'Initializer';
    $initializerFile = __DIR__ . "/initializers/{$className}.php";
    
    // Load the initializer file
    if (!file_exists($initializerFile)) {
        throw new Exception("Initializer file not found: {$initializerFile}");
    }
    
    require_once $initializerFile;
    
    // Check if the initializer class exists
    if (!class_exists($className)) {
        throw new Exception("Initializer class not found: {$className}");
    }
    
    logMessage("Loading initializer: {$className}");
    
    // Create and return the initializer instance
    return new $className();
}

/**
 * Log function for initialization - only stdout for Docker
 */
function logMessage(string $message, string $level = 'INFO'): void {
    $timestamp = date('Y-m-d H:i:s');

    $level = $level == 'INFO' ? "\033[38;5;117mINFO\033[0m" : $level;
    $level = $level == 'ERROR' ? "\033[31mERROR\033[0m" : $level;

    $logMessage = "[{$timestamp}] [{$level}] [INIT] {$message}" . PHP_EOL;
    
    // Only log to stdout for Docker
    echo $logMessage;
}
