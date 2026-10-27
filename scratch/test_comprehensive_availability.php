<?php
/**
 * Comprehensive test suite for Faculty Availability and Notification workflow.
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

// Ensure session directory is valid
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn->query("DELETE FROM notifications WHERE title LIKE '%Faculty UNAVAILABLE%' OR title LIKE '%Faculty LEAVE%' OR title LIKE '%Faculty Availability%' OR title LIKE '%Class Coverage%'");
$conn->query("DELETE FROM faculty_availability WHERE faculty_id = 2 AND availability_date = '2026-10-26'");

testHeader("1. Unauthorized Request (No Session)");
// Temporarily clear session
$_SESSION = [];
ob_start();
$requestMethod = 'POST';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['date' => '2026-10-26', 'period_id' => 1, 'status' => 'AVAILABLE'];
include __DIR__ . '/../api/availability.php';
$out = ob_get_clean();
$json = json_decode($out, true);
assertTest($json !== null, "Response is valid JSON");
assertTest($json['success'] === false, "Access denied without session");
assertTest(http_response_code() === 401, "HTTP status is 401");

testHeader("2. Unauthorized Request (Wrong Role - STUDENT)");
$_SESSION['user_id'] = 16;
$_SESSION['role'] = 'STUDENT';
ob_start();
$_POST = ['date' => '2026-10-26', 'period_id' => 1, 'status' => 'AVAILABLE'];
include __DIR__ . '/../api/availability.php';
$out = ob_get_clean();
$json = json_decode($out, true);
assertTest($json !== null, "Response is valid JSON");
assertTest($json['success'] === false, "Access denied for non-faculty role");
assertTest(http_response_code() === 403, "HTTP status is 403");

testHeader("3. Validation: Missing Parameters");
$_SESSION['user_id'] = 2;
$_SESSION['role'] = 'FACULTY';
ob_start();
$_POST = ['date' => '', 'period_id' => 1, 'status' => 'AVAILABLE'];
include __DIR__ . '/../api/availability.php';
$out = ob_get_clean();
$json = json_decode($out, true);
assertTest($json !== null && $json['success'] === false, "Rejects empty date");
assertTest(http_response_code() === 400, "HTTP status is 400");

testHeader("4. Validation: UNAVAILABLE without Reason");
ob_start();
$_POST = ['date' => '2026-10-26', 'period_id' => 4, 'status' => 'UNAVAILABLE', 'reason' => '   '];
include __DIR__ . '/../api/availability.php';
$out = ob_get_clean();
$json = json_decode($out, true);
assertTest($json !== null && $json['success'] === false, "Rejects UNAVAILABLE without reason");
assertTest(strpos($json['message'], 'provide a reason') !== false, "Helpful message provided");
assertTest(http_response_code() === 400, "HTTP status is 400");

testHeader("5. Validation: LEAVE without Reason");
ob_start();
$_POST = ['date' => '2026-10-26', 'period_id' => 1, 'status' => 'LEAVE', 'reason' => ''];
include __DIR__ . '/../api/availability.php';
$out = ob_get_clean();
$json = json_decode($out, true);
assertTest($json !== null && $json['success'] === false, "Rejects LEAVE without reason");
assertTest(http_response_code() === 400, "HTTP status is 400");

testHeader("6. Save CAN ATTEND (AVAILABLE) - Period 1 (No class scheduled)");
ob_start();
$_POST = ['date' => '2026-10-26', 'period_id' => 1, 'status' => 'AVAILABLE', 'reason' => ''];
include __DIR__ . '/../api/availability.php';
$out = ob_get_clean();
$json = json_decode($out, true);
assertTest($json !== null && $json['success'] === true, "AVAILABLE saved successfully");
assertTest(http_response_code() === 200, "HTTP status is 200");

// Check DB
$res = $conn->query("SELECT * FROM faculty_availability WHERE faculty_id = 2 AND availability_date = '2026-10-26' AND period_id = 1");
$row = $res->fetch_assoc();
assertTest($row !== null && $row['status'] === 'AVAILABLE', "DB record exists with status AVAILABLE");

testHeader("7. Save UNAVAILABLE with Class Affected - Period 4 (CSE-E DBMS Lab)");
ob_start();
$_POST = [
    'date' => '2026-10-26',
    'period_id' => 4,
    'status' => 'UNAVAILABLE',
    'reason' => 'Attending National AI Symposium'
];
include __DIR__ . '/../api/availability.php';
$out = ob_get_clean();
$json = json_decode($out, true);
assertTest($json !== null && $json['success'] === true, "UNAVAILABLE saved successfully");
assertTest($json['class_affected'] === true, "Recognized that a scheduled class is affected");
assertTest($json['notifications_sent'] > 0, "Notifications were sent: " . $json['notifications_sent']);

// Verify DB record
$res = $conn->query("SELECT * FROM faculty_availability WHERE faculty_id = 2 AND availability_date = '2026-10-26' AND period_id = 4");
$row = $res->fetch_assoc();
assertTest($row !== null && $row['status'] === 'UNAVAILABLE', "DB record has status UNAVAILABLE");
assertTest($row['reason'] === 'Attending National AI Symposium', "DB record has correct reason");

// Check notifications for HOD (user_id = 1)
$hod_notif = $conn->query("SELECT * FROM notifications WHERE user_id = 1 ORDER BY notification_id DESC LIMIT 1")->fetch_assoc();
assertTest($hod_notif !== null, "HOD received notification");
assertTest(strpos($hod_notif['message'], 'Prof. Priya Sharma') !== false, "Message includes faculty name");
assertTest(strpos($hod_notif['message'], 'DBMS Lab') !== false, "Message includes subject name");
assertTest(strpos($hod_notif['message'], 'CSE-E') !== false, "Message includes section name");
assertTest(strpos($hod_notif['message'], 'Attending National AI Symposium') !== false, "Message includes reason");

// Check notifications for eligible alternates (e.g. user_id = 12 Prof. Harini V who teaches DBMS Lab)
$alt_notif = $conn->query("SELECT * FROM notifications WHERE user_id = 12 ORDER BY notification_id DESC LIMIT 1")->fetch_assoc();
assertTest($alt_notif !== null, "Alternate faculty (Prof. Harini V) received coverage alert");
assertTest(strpos($alt_notif['title'], 'Class Coverage Needed') !== false, "Alternate alert title is correct");
assertTest(strpos($alt_notif['message'], 'CSE-E') !== false, "Alternate message includes section");

testHeader("8. Prevent Duplicate Notifications on Repeated Submission");
$notif_count_before = (int)$conn->query("SELECT COUNT(*) as c FROM notifications")->fetch_assoc()['c'];
ob_start();
$_POST = [
    'date' => '2026-10-26',
    'period_id' => 4,
    'status' => 'UNAVAILABLE',
    'reason' => 'Attending National AI Symposium'
];
include __DIR__ . '/../api/availability.php';
$out = ob_get_clean();
$json = json_decode($out, true);
$notif_count_after = (int)$conn->query("SELECT COUNT(*) as c FROM notifications")->fetch_assoc()['c'];
assertTest($json !== null && $json['success'] === true, "Repeated submission returns success");
assertTest($json['notifications_sent'] === 0, "No duplicate notifications triggered");
assertTest($notif_count_before === $notif_count_after, "Total notifications count remained unchanged");

testHeader("9. Save LEAVE - Period 2 (No class scheduled)");
ob_start();
$_POST = [
    'date' => '2026-10-26',
    'period_id' => 2,
    'status' => 'LEAVE',
    'reason' => 'Personal medical leave'
];
include __DIR__ . '/../api/availability.php';
$out = ob_get_clean();
$json = json_decode($out, true);
assertTest($json !== null && $json['success'] === true, "LEAVE saved successfully");
assertTest($json['class_affected'] === false, "Period 2 has no class scheduled");
// Verify only HOD notified, not alternate faculty
$leave_notifs = $conn->query("SELECT * FROM notifications WHERE message LIKE '%Personal medical leave%'");
$rows = $leave_notifs->fetch_all(MYSQLI_ASSOC);
assertTest(count($rows) === 1, "Only 1 notification sent (to HOD)");
assertTest((int)$rows[0]['user_id'] === 1, "Recipient is HOD (user_id = 1)");

testHeader("10. Fetch Availability Map via GET");
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['date' => '2026-10-26'];
ob_start();
include __DIR__ . '/../api/availability.php';
$out = ob_get_clean();
$json = json_decode($out, true);
assertTest($json !== null && $json['success'] === true, "GET returned success");
assertTest(isset($json['availability'][1]) && $json['availability'][1]['status'] === 'AVAILABLE', "Period 1 is AVAILABLE");
assertTest(isset($json['availability'][2]) && $json['availability'][2]['status'] === 'LEAVE', "Period 2 is LEAVE");
assertTest(isset($json['availability'][4]) && $json['availability'][4]['status'] === 'UNAVAILABLE', "Period 4 is UNAVAILABLE");

testHeader("11. Verify api/check_class.php returns joined class info");
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['date' => '2026-10-26', 'period_id' => 4];
ob_start();
include __DIR__ . '/../api/check_class.php';
$out = ob_get_clean();
$json = json_decode($out, true);
assertTest($json !== null && $json['success'] === true, "check_class returned success");
assertTest($json['class_found'] === true, "Class found for period 4");
assertTest($json['class']['section_name'] === 'CSE-E', "Joined section_name is CSE-E");
assertTest($json['class']['subject_name'] === 'DBMS Lab', "Joined subject_name is DBMS Lab");
assertTest($json['class']['room_number'] === 'LAB-1', "Joined room_number is LAB-1");

testHeader("12. Verify Notifications Inbox View (as in faculty/notifications.php)");
$stmt = $conn->prepare("
    SELECT notification_id, title, message, notification_type, is_read, created_at
    FROM notifications
    WHERE user_id = 12
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute();
$inbox = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
assertTest(!empty($inbox), "Recipient inbox has notifications");
echo "Sample inbox notification:\n";
echo " - Title: " . $inbox[0]['title'] . "\n";
echo " - Message: " . $inbox[0]['message'] . "\n";
echo " - Type: " . $inbox[0]['notification_type'] . "\n";

echo "\n" . str_repeat('=', 65) . "\n";
echo "ALL TESTS PASSED SUCCESSFULLY!\n";
echo str_repeat('=', 65) . "\n";
