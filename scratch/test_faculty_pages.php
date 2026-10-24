<?php
// Test script to login as faculty and fetch all 6 faculty pages
$cookieFile = __DIR__ . '/cookie_faculty.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

// 1. Login
$ch = curl_init('http://localhost/SmartFacultyPortal/auth/login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'username' => 'faculty01',
    'password' => 'faculty@01'
]));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$res = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "Login status: " . $info['http_code'] . "\n";
echo "Final URL: " . $info['url'] . "\n";

$pages = [
    'dashboard.php',
    'timetable.php',
    'notifications.php',
    'profile.php',
    'availability.php',
    'requests.php'
];

foreach ($pages as $p) {
    $url = "http://localhost/SmartFacultyPortal/faculty/$p";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $hasSigBlock = (strpos($html, 'inst-sig-block') !== false);
    $hasTimetableCoord = (strpos($html, 'Timetable Coordinator') !== false);
    echo "$p: HTTP $code, length " . strlen($html) . ", has inst-sig-block: " . ($hasSigBlock ? 'YES' : 'NO') . ", has TT Coord: " . ($hasTimetableCoord ? 'YES' : 'NO') . "\n";
}

// 2. Test HOD
$cookieHod = __DIR__ . '/cookie_hod.txt';
if (file_exists($cookieHod)) unlink($cookieHod);
$ch = curl_init('http://localhost/SmartFacultyPortal/auth/login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieHod);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieHod);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'username' => 'hod.cse',
    'password' => 'Admin@123'
]));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_exec($ch);
curl_close($ch);

$ch = curl_init('http://localhost/SmartFacultyPortal/admin/my-timetable.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieHod);
$htmlHod = curl_exec($ch);
$codeHod = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$hasSigBlockHod = (strpos($htmlHod, 'inst-sig-block') !== false);
$hasTimetableCoordHod = (strpos($htmlHod, 'Timetable Coordinator') !== false);
echo "admin/my-timetable.php: HTTP $codeHod, length " . strlen($htmlHod) . ", has inst-sig-block: " . ($hasSigBlockHod ? 'YES' : 'NO') . ", has TT Coord: " . ($hasTimetableCoordHod ? 'YES' : 'NO') . "\n";

