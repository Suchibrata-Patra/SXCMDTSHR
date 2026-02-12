<?php
declare(strict_types=1);
$page = basename($_SERVER['SCRIPT_NAME']);
$titles = [
    'index.php'      => 'SXC MDTS | Dashboard',
    'bulk_mail.php'  => 'SXC MDTS | Bulk Mail Manager',
    'login.php'      => 'SXC MDTS | Secure Login',
    'profile.php'    => 'SXC MDTS | User Profile',
];
$title = $titles[$page] ?? 'SXC MDTS';
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), camera=()");
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="/assets/favicon.png">
<title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">