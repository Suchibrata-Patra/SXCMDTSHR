<?php
// htdocs/config.php

function env($key, $default = null) {
    // Check various sources where Dotenv might have placed the value
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    
    if ($value === false || $value === null) {
        return $default;
    }
    
    return $value;
}
?>