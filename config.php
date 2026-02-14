<?php
/**
 * config.php - Environment Configuration Helper
 * This file provides the env() function and loads .env file
 */

// Load .env file if it exists
if (file_exists(__DIR__ . '/.env')) {
    $envFile = __DIR__ . '/.env';
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Skip comments and empty lines
        if (strpos(trim($line), '#') === 0 || empty(trim($line))) {
            continue;
        }
        
        // Parse the line
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes from value if present
            if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            
            // Set in environment
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

if (!function_exists('env')) {
    /**
     * Get environment variable value
     * @param string $key The environment variable key
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    function env($key, $default = null) {
        // Try to get from $_ENV first (loaded from .env file)
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        // Try to get from actual environment variable
        $value = getenv($key);
        
        // If still not found, return default
        if ($value === false) {
            return $default;
        }
        
        return $value;
    }
}
?>