<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Maintenance / Under Development Page
|--------------------------------------------------------------------------
| Secure, no info leakage, no caching
|--------------------------------------------------------------------------
*/

// Send proper HTTP status
http_response_code(503);

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Optional: restrict access by IP (uncomment and change IP)
// $allowedIPs = ['123.123.123.123'];
// if (!in_array($_SERVER['REMOTE_ADDR'], $allowedIPs, true)) {
//     http_response_code(403);
//     exit('Access denied.');
// }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Under Development</title>
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    <style>
        body {
            background: #111;
            color: #fff;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .box {
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="box">
        <h1>Under Development</h1>
        <p>We’ll be back soon.</p>
    </div>
</body>
</html>
