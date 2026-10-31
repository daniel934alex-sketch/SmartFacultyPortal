<?php

// Application name
define("APP_NAME", "Smart Faculty Assistant");

// Base URL configuration (supports Render production & local Laragon)
$envBaseUrl = getenv("BASE_URL") ?: getenv("APP_URL");

if (!empty($envBaseUrl)) {
    $baseUrl = rtrim($envBaseUrl, '/') . '/';
} elseif (isset($_SERVER['HTTP_HOST'])) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $scheme = $isHttps ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];

    // In local Laragon / XAMPP, the project is under /SmartFacultyPortal/
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/SmartFacultyPortal/') !== false || $host === 'localhost' || $host === '127.0.0.1') {
        $baseUrl = $scheme . $host . '/SmartFacultyPortal/';
    } else {
        $baseUrl = $scheme . $host . '/';
    }
} else {
    // Default fallback for CLI or local development
    $baseUrl = "http://localhost/SmartFacultyPortal/";
}

define("BASE_URL", $baseUrl);

// Timezone
date_default_timezone_set("Asia/Kolkata");

?>