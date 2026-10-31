<?php

// Database Configuration supporting environment variables (Render / Aiven) and local Laragon defaults
$host = getenv('DB_HOST') ?: "127.0.0.1";
$port = (int)(getenv('DB_PORT') ?: 3306);
$database = getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: "smart_faculty");
$username = getenv('DB_USER') ?: (getenv('DB_USERNAME') ?: "root");
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : "");

// SSL Configuration (Required for Aiven MySQL and remote cloud databases)
$sslEnabled = false;
$dbSsl = getenv('DB_SSL');
$dbSslMode = strtoupper((string)getenv('DB_SSL_MODE'));
$dbSslCa = getenv('DB_SSL_CA');
$dbSslCaCert = getenv('DB_SSL_CA_CERT') ?: getenv('AIVEN_CA_CERT');

if ($dbSsl !== false && in_array(strtolower($dbSsl), ['true', '1', 'required', 'on'], true)) {
    $sslEnabled = true;
} elseif (in_array($dbSslMode, ['REQUIRED', 'VERIFY_CA', 'VERIFY_IDENTITY'], true)) {
    $sslEnabled = true;
} elseif (!empty($dbSslCa) || !empty($dbSslCaCert)) {
    $sslEnabled = true;
} elseif ($host !== '127.0.0.1' && $host !== 'localhost' && ($dbSsl === false || !in_array(strtolower($dbSsl), ['false', '0', 'disable', 'off'], true))) {
    // Default to SSL for remote hosts (e.g. Aiven Cloud) unless explicitly disabled
    $sslEnabled = true;
}

$conn = mysqli_init();

if (!$conn) {
    die("Database initialization failed (mysqli_init)");
}

// Connection timeout (seconds)
$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);

$clientFlags = 0;

if ($sslEnabled) {
    $clientFlags |= MYSQLI_CLIENT_SSL;

    $caFile = null;
    if (!empty($dbSslCa) && file_exists($dbSslCa)) {
        $caFile = $dbSslCa;
    } elseif (!empty($dbSslCaCert)) {
        // Write raw PEM from environment variable to secure temp file if provided
        $tempCaPath = sys_get_temp_dir() . '/aiven-ca-' . md5($dbSslCaCert) . '.pem';
        if (!file_exists($tempCaPath)) {
            @file_put_contents($tempCaPath, $dbSslCaCert);
        }
        $caFile = $tempCaPath;
    }

    if (!empty($caFile)) {
        $conn->ssl_set(null, null, $caFile, null, null);
    }

    $verifyCert = filter_var(getenv('DB_SSL_VERIFY_SERVER_CERT'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($verifyCert === false || ($verifyCert === null && empty($caFile))) {
        // If no specific CA file is provided, allow TLS connection without strict CA chain verification
        $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    } else {
        $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
    }
}

$connected = @$conn->real_connect(
    $host,
    $username,
    $password,
    $database,
    $port,
    null,
    $clientFlags
);

if (!$connected || $conn->connect_error) {
    die("Database connection failed: " . ($conn->connect_error ?: mysqli_connect_error()));
}

$conn->set_charset("utf8mb4");
