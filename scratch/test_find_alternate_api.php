<?php
// Simulate session for user_id = 2 (Prof. Priya Sharma)
session_start();
$_SESSION['user_id'] = 2;
$_SESSION['role'] = 'FACULTY';

$_POST['date'] = '2026-10-26';
$_POST['period_id'] = 4;

ob_start();
require_once __DIR__ . '/../api/find_alternate_faculty.php';
$output = ob_get_clean();

echo "find_alternate_faculty response:\n" . $output . PHP_EOL;
