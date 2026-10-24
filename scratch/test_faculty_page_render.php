<?php
session_start();
$_SESSION['user_id'] = 2;
$_SESSION['username'] = 'faculty01';
$_SESSION['role'] = 'FACULTY';

ob_start();
require "faculty/timetable.php";
$html = ob_get_clean();

echo "Length: " . strlen($html) . " bytes\n";
echo "Has fd-sidebar: " . (strpos($html, 'fd-sidebar') !== false ? 'YES' : 'NO') . "\n";
echo "Has fd-topbar: " . (strpos($html, 'fd-topbar') !== false ? 'YES' : 'NO') . "\n";
echo "Has tt-hero: " . (strpos($html, 'tt-hero') !== false ? 'YES' : 'NO') . "\n";
echo "Has tt-summary: " . (strpos($html, 'tt-summary') !== false ? 'YES' : 'NO') . "\n";
echo "Has inst-paper-wrapper: " . (strpos($html, 'inst-paper-wrapper') !== false ? 'YES' : 'NO') . "\n";
echo "Has DEPARTMENT OF: " . (strpos($html, 'DEPARTMENT OF') !== false ? 'YES' : 'NO') . "\n";
echo "Has LUNCH BREAK: " . (strpos($html, 'LUNCH BREAK') !== false ? 'YES' : 'NO') . "\n";
