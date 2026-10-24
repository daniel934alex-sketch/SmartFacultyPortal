<?php
// Complete test script for admin/my-timetable.php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'hod.cse';
$_SESSION['role'] = 'HOD';

ob_start();
// Include the script
require "admin/my-timetable.php";
$output = ob_get_clean();

echo "Rendered length: " . strlen($output) . " bytes\n";
if (strpos($output, "DEPARTMENT OF") !== false) {
    echo "Found: DEPARTMENT OF header\n";
} else {
    echo "MISSING: DEPARTMENT OF header\n";
}
if (strpos($output, "LUNCH BREAK") !== false || strpos($output, "LUNCH") !== false) {
    echo "Found: LUNCH\n";
} else {
    echo "MISSING: LUNCH\n";
}
