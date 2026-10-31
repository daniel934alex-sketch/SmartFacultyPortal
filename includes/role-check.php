<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireRole($requiredRole)
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit;
    }

    if ($_SESSION['role'] !== $requiredRole) {
        http_response_code(403);
        die("Access denied.");
    }
}