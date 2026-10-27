<?php
require_once __DIR__ . '/../config/database.php';

// Test restoring Period 4 to AVAILABLE
$cookieFaculty = tempnam(sys_get_temp_dir(), 'sf_fac_');
function httpReq($url, $method = 'GET', $data = [], $cookieFile = null) {
    $ch = curl_init();
    $fullUrl = 'http://127.0.0.1/SmartFacultyPortal/' . ltrim($url, '/');
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'json' => json_decode($response, true)];
}

$init = httpReq('scratch/test_login_helper.php?role=FACULTY&user_id=2', 'GET', [], $cookieFaculty);
$csrf = $init['json']['csrf_token'];

$res = httpReq('api/availability.php', 'POST', [
    'date' => '2026-10-26',
    'period_id' => 4,
    'status' => 'AVAILABLE',
    'csrf_token' => $csrf
], $cookieFaculty);

echo "Restore to AVAILABLE response code: " . $res['code'] . PHP_EOL;
echo json_encode($res['json'], JSON_PRETTY_PRINT) . PHP_EOL;

// Check notification created for HOD
$r = $conn->query("SELECT * FROM notifications WHERE user_id = 1 AND title LIKE '%Restored%' ORDER BY notification_id DESC LIMIT 1");
$notif = $r->fetch_assoc();
echo "Restoration notification:\n" . json_encode($notif, JSON_PRETTY_PRINT) . PHP_EOL;
@unlink($cookieFaculty);
