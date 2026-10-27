<?php
/**
 * Live HTTP Test Suite for Faculty Availability Workflow
 */

require_once __DIR__ . '/../config/database.php';

function testHeader($name) {
    echo "\n" . str_repeat('=', 65) . "\n";
    echo "TEST: " . $name . "\n";
    echo str_repeat('=', 65) . "\n";
}

function assertTest($condition, $message) {
    if ($condition) {
        echo "[PASS] " . $message . "\n";
    } else {
        echo "[FAIL] " . $message . "\n";
        throw new Exception("Assertion failed: " . $message);
    }
}

$cookieFaculty = tempnam(sys_get_temp_dir(), 'sf_fac_');
$cookieStudent = tempnam(sys_get_temp_dir(), 'sf_stu_');
$cookieAnon = tempnam(sys_get_temp_dir(), 'sf_ano_');

function httpReq($url, $method = 'GET', $data = [], $cookieFile = null) {
    $ch = curl_init();
    $fullUrl = 'http://127.0.0.1/SmartFacultyPortal/' . ltrim($url, '/');

    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);

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
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'headers' => $headers,
        'body' => $body,
        'json' => json_decode($body, true)
    ];
}

// 0. Clean test data
$conn->query("DELETE FROM notifications WHERE title LIKE '%Faculty UNAVAILABLE%' OR title LIKE '%Faculty LEAVE%' OR title LIKE '%Faculty Availability%' OR title LIKE '%Class Coverage%'");
$conn->query("DELETE FROM faculty_availability WHERE faculty_id = 2 AND availability_date = '2026-10-26'");

// Initialize faculty session via web server
$facInit = httpReq('scratch/test_login_helper.php?role=FACULTY&user_id=2', 'GET', [], $cookieFaculty);
$facultyCsrf = $facInit['json']['csrf_token'] ?? '';

// Initialize student session via web server
$stuInit = httpReq('scratch/test_login_helper.php?role=STUDENT&user_id=16', 'GET', [], $cookieStudent);

testHeader("1. Anonymous Request (No Session)");
$res = httpReq('api/availability.php', 'POST', ['date' => '2026-10-26', 'period_id' => 1, 'status' => 'AVAILABLE']);
assertTest($res['code'] === 401, "HTTP status is 401 Unauthorized (got {$res['code']})");
assertTest($res['json'] !== null && $res['json']['success'] === false, "Returns valid error JSON");

testHeader("2. Unauthorized Request (STUDENT Role)");
$res = httpReq('api/availability.php', 'POST', ['date' => '2026-10-26', 'period_id' => 1, 'status' => 'AVAILABLE'], $cookieStudent);
assertTest($res['code'] === 403, "HTTP status is 403 Forbidden (got {$res['code']})");
assertTest($res['json'] !== null && $res['json']['success'] === false, "Returns valid error JSON");

testHeader("3. Validation: Missing Parameters");
$res = httpReq('api/availability.php', 'POST', ['date' => '', 'period_id' => 1, 'status' => 'AVAILABLE'], $cookieFaculty);
assertTest($res['code'] === 400, "HTTP status is 400 Bad Request for empty date (got {$res['code']})");
assertTest($res['json'] !== null && $res['json']['success'] === false, "Returns valid error JSON");

testHeader("4. Validation: UNAVAILABLE without Reason");
$res = httpReq('api/availability.php', 'POST', ['date' => '2026-10-26', 'period_id' => 4, 'status' => 'UNAVAILABLE', 'reason' => '   '], $cookieFaculty);
assertTest($res['code'] === 400, "HTTP status is 400 for empty reason on UNAVAILABLE (got {$res['code']})");
assertTest(strpos($res['json']['message'], 'provide a reason') !== false, "Helpful error message: " . $res['json']['message']);

testHeader("5. Validation: LEAVE without Reason");
$res = httpReq('api/availability.php', 'POST', ['date' => '2026-10-26', 'period_id' => 2, 'status' => 'LEAVE', 'reason' => ''], $cookieFaculty);
assertTest($res['code'] === 400, "HTTP status is 400 for empty reason on LEAVE (got {$res['code']})");
assertTest(strpos($res['json']['message'], 'provide a reason') !== false, "Helpful error message: " . $res['json']['message']);

testHeader("6. Save CAN ATTEND (AVAILABLE) - Period 1");
$res = httpReq('api/availability.php', 'POST', [
    'date' => '2026-10-26',
    'period_id' => 1,
    'status' => 'AVAILABLE',
    'csrf_token' => $facultyCsrf
], $cookieFaculty);
assertTest($res['code'] === 200, "HTTP status is 200 OK (got {$res['code']})");
assertTest($res['json']['success'] === true, "AVAILABLE saved successfully");
$dbRow = $conn->query("SELECT * FROM faculty_availability WHERE faculty_id = 2 AND availability_date = '2026-10-26' AND period_id = 1")->fetch_assoc();
assertTest($dbRow !== null && $dbRow['status'] === 'AVAILABLE', "DB record has status AVAILABLE");

testHeader("7. Save UNAVAILABLE with Scheduled Class (Period 4 - CSE-E DBMS Lab)");
$res = httpReq('api/availability.php', 'POST', [
    'date' => '2026-10-26',
    'period_id' => 4,
    'status' => 'UNAVAILABLE',
    'reason' => 'Attending National AI Symposium',
    'csrf_token' => $facultyCsrf
], $cookieFaculty);
assertTest($res['code'] === 200, "HTTP status is 200 OK (got {$res['code']})");
assertTest($res['json']['success'] === true, "UNAVAILABLE saved successfully");
assertTest($res['json']['class_affected'] === true, "Detected scheduled class affected");
assertTest($res['json']['notifications_sent'] > 0, "Sent {$res['json']['notifications_sent']} notifications");

// Verify DB availability record
$dbRow = $conn->query("SELECT * FROM faculty_availability WHERE faculty_id = 2 AND availability_date = '2026-10-26' AND period_id = 4")->fetch_assoc();
assertTest($dbRow !== null && $dbRow['status'] === 'UNAVAILABLE', "DB record has status UNAVAILABLE");
assertTest($dbRow['reason'] === 'Attending National AI Symposium', "DB record has correct reason");

// Verify HOD notification (user_id = 1)
$hodNotif = $conn->query("SELECT * FROM notifications WHERE user_id = 1 ORDER BY notification_id DESC LIMIT 1")->fetch_assoc();
assertTest($hodNotif !== null, "HOD notification created in DB");
assertTest(strpos($hodNotif['message'], 'Prof. Priya Sharma') !== false, "Message includes faculty name");
assertTest(strpos($hodNotif['message'], 'DBMS Lab') !== false, "Message includes subject name");
assertTest(strpos($hodNotif['message'], 'CSE-E') !== false, "Message includes section name");
assertTest(strpos($hodNotif['message'], 'Attending National AI Symposium') !== false, "Message includes reason");

// Verify alternate faculty notification (user_id = 12 Prof. Harini V who teaches DBMS Lab)
$altNotif = $conn->query("SELECT * FROM notifications WHERE user_id = 12 ORDER BY notification_id DESC LIMIT 1")->fetch_assoc();
assertTest($altNotif !== null, "Alternate faculty notification created in DB");
assertTest(strpos($altNotif['title'], 'Class Coverage Needed') !== false, "Title mentions Class Coverage Needed");
assertTest(strpos($altNotif['message'], 'CSE-E') !== false, "Message mentions section CSE-E");

testHeader("8. Prevent Duplicate Notifications on Repeated Submission");
$countBefore = (int)$conn->query("SELECT COUNT(*) as c FROM notifications")->fetch_assoc()['c'];
$res = httpReq('api/availability.php', 'POST', [
    'date' => '2026-10-26',
    'period_id' => 4,
    'status' => 'UNAVAILABLE',
    'reason' => 'Attending National AI Symposium',
    'csrf_token' => $facultyCsrf
], $cookieFaculty);
assertTest($res['code'] === 200, "HTTP status is 200 OK");
assertTest($res['json']['success'] === true, "Returned success response");
assertTest($res['json']['notifications_sent'] === 0, "0 duplicate notifications sent");
$countAfter = (int)$conn->query("SELECT COUNT(*) as c FROM notifications")->fetch_assoc()['c'];
assertTest($countBefore === $countAfter, "Total notification count unchanged ({$countBefore})");

testHeader("9. Save LEAVE for Free Period (Period 2)");
$res = httpReq('api/availability.php', 'POST', [
    'date' => '2026-10-26',
    'period_id' => 2,
    'status' => 'LEAVE',
    'reason' => 'Personal medical leave',
    'csrf_token' => $facultyCsrf
], $cookieFaculty);
assertTest($res['code'] === 200, "HTTP status is 200 OK");
assertTest($res['json']['success'] === true, "LEAVE saved successfully");
assertTest($res['json']['class_affected'] === false, "Period 2 correctly recognized as having no class");
// Verify only HOD notified, not other faculty
$leaveNotifs = $conn->query("SELECT * FROM notifications WHERE message LIKE '%Personal medical leave%'")->fetch_all(MYSQLI_ASSOC);
assertTest(count($leaveNotifs) === 1, "Exactly 1 notification created for LEAVE without class");
assertTest((int)$leaveNotifs[0]['user_id'] === 1, "Recipient was authorized HOD (user_id = 1)");

testHeader("10. Fetch Availability Map via GET");
$res = httpReq('api/availability.php?date=2026-10-26', 'GET', [], $cookieFaculty);
assertTest($res['code'] === 200, "GET HTTP status is 200 OK");
assertTest($res['json']['success'] === true, "GET returned success: true");
assertTest(isset($res['json']['availability'][1]) && $res['json']['availability'][1]['status'] === 'AVAILABLE', "Period 1 status is AVAILABLE");
assertTest(isset($res['json']['availability'][2]) && $res['json']['availability'][2]['status'] === 'LEAVE', "Period 2 status is LEAVE");
assertTest(isset($res['json']['availability'][4]) && $res['json']['availability'][4]['status'] === 'UNAVAILABLE', "Period 4 status is UNAVAILABLE");

testHeader("11. Check Class API (api/check_class.php) with Joined Details");
$res = httpReq('api/check_class.php', 'POST', ['date' => '2026-10-26', 'period_id' => 4], $cookieFaculty);
assertTest($res['code'] === 200, "check_class HTTP status is 200 OK");
assertTest($res['json']['class_found'] === true, "Class found for period 4");
assertTest($res['json']['class']['section_name'] === 'CSE-E', "section_name is CSE-E");
assertTest($res['json']['class']['subject_name'] === 'DBMS Lab', "subject_name is DBMS Lab");
assertTest($res['json']['class']['room_number'] === 'LAB-1', "room_number is LAB-1");

testHeader("12. Verify Faculty Notifications Inbox View");
$inbox = $conn->query("SELECT * FROM notifications WHERE user_id = 12 ORDER BY notification_id DESC LIMIT 1")->fetch_assoc();
assertTest($inbox !== null, "Notification exists in inbox for recipient");
assertTest(strpos($inbox['message'], 'CSE-E') !== false, "Inbox notification contains section");
assertTest(strpos($inbox['message'], 'DBMS Lab') !== false, "Inbox notification contains subject");
assertTest(strpos($inbox['message'], 'Prof. Priya Sharma') !== false, "Inbox notification contains faculty name");

@unlink($cookieFaculty);
@unlink($cookieStudent);
@unlink($cookieAnon);

echo "\n" . str_repeat('=', 65) . "\n";
echo ">>> ALL 12 END-TO-END TESTS COMPLETED SUCCESSFULLY! <<<\n";
echo str_repeat('=', 65) . "\n";
