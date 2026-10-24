
<?php
require_once "../config/config.php";

/*
 * Load HOD styling for every page inside /admin/.
 * Do not apply it to the Faculty or Student portals.
 */
$currentScript = str_replace(
    '\\',
    '/',
    $_SERVER['SCRIPT_NAME'] ?? ''
);

$isAdminPage = strpos($currentScript, '/admin/') !== false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        <?= htmlspecialchars(
            (string)($pageTitle ?? APP_NAME),
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </title>

    <!-- Existing shared stylesheet -->
    <link rel="stylesheet"
          href="<?= BASE_URL ?>assets/css/style.css">

    <!-- HOD theme only for Admin pages -->
    <?php if ($isAdminPage): ?>
        <link rel="stylesheet"
              href="<?= BASE_URL ?>assets/css/hod-dashboard.css?v=2">
    <?php endif; ?>
</head>

<body>
