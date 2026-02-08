<?php
/**
 * config.php - Environment Configuration Helper
 * This file provides the env() function to read from .env file
 */

if (!function_exists('env')) {
    /**
     * Get environment variable value
     * @param string $key The environment variable key
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    function env($key, $default = null) {
        // Try to get from actual environment variable first
        $value = getenv($key);
        
        // If not in environment, try $_ENV
        if ($value === false && isset($_ENV[$key])) {
            $value = $_ENV[$key];
        }
        
        // If still not found, return default
        if ($value === false) {
            return $default;
        }
        
        return $value;
    }
}
?>