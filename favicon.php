<?php
declare(strict_types=1);
$page = basename($_SERVER['SCRIPT_NAME']);
$titles = [
    'index.php'      => 'Index Page',
    'bulk_mail.php'  => 'SXC MDTS | Bulk Mail Manager',
    'login.php'      => 'SXC MDTS | Secure Login',
    'profile.php'    => 'SXC MDTS | User Profile',
];
$title = $titles[$page] ?? 'SXC MDTS';
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: no-referrer-when-downgrade");
header("X-XSS-Protection: 1; mode=block");
?>
<link rel="icon" type="/Assets/image/sxc_logo.png" href="/assets/favicon.png">
<title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>