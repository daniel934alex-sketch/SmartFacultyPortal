<?php
// Note: no requires before session_start!
ini_set('session.save_path', 'C:/laragon/tmp');
$sessId = substr(md5('fac_test_' . microtime()), 0, 26);
session_id($sessId);
session_start();
$_SESSION['user_id'] = 2; // Prof. Priya Sharma (faculty_id = 2)
$_SESSION['username'] = 'faculty01';
$_SESSION['role'] = 'FACULTY';
$_SESSION['csrf_token'] = bin2hex(random_bytes(16));
session_write_close();

$postData = http_build_query([
    'date' => date('Y-m-d'),
    'period_id' => 1,
    'status' => 'UNAVAILABLE',
    'reason' => 'Doctor appointment'
]);

$opts = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                    "Cookie: PHPSESSID=$sessId\r\n",
        'content' => $postData,
        'ignore_errors' => true
    ]
];

$ctx = stream_context_create($opts);
$url = "http://localhost/SmartFacultyPortal/api/availability.php";
$response = file_get_contents($url, false, $ctx);
$statusLine = $http_response_header[0] ?? '';

echo "Status: $statusLine\n";
echo "Response Length: " . strlen($response) . " bytes\n";
echo "Raw Response:\n$response\n";
