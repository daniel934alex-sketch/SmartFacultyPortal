<?php
// Test compilation and rendering of dark theme styles for admin/my-timetable.php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'hod.cse';
$_SESSION['role'] = 'HOD';

ob_start();
require_once "admin/my-timetable.php";
$output = ob_get_clean();

echo "Page length: " . strlen($output) . " bytes\n";
echo "Syntax and inclusion OK.\n";
