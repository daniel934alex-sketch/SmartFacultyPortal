<?php
/**
 * Comprehensive Test for Student Timetable Authorization & View Link
 */

$baseUrl = "http://localhost/SmartFacultyPortal";

function makeRequest($url, $cookieFile = null, $postData = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $header = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    return ['status' => $httpCode, 'header' => $header, 'body' => $body];
}

echo "=========================================================\n";
echo "TEST SUITE: STUDENT VIEW AUTHORIZATION & TIMETABLE DISPLAY\n";
echo "=========================================================\n\n";

// -------------------------------------------------------------
// TEST 1: Unauthenticated user accessing student/timetable.php
// -------------------------------------------------------------
echo "[TEST 1] Unauthenticated user requesting student/timetable.php...\n";
$unauth = makeRequest("{$baseUrl}/student/timetable.php");
echo "  -> HTTP Status: {$unauth['status']}\n";
assert($unauth['status'] === 302, "Unauthenticated user must be redirected (HTTP 302)");
assert(strpos($unauth['header'], 'Location: ../auth/login.php') !== false, "Redirect header must point to login.php");
echo "  -> [TEST 1 PASSED]: Redirects to login.php correctly.\n\n";

// -------------------------------------------------------------
// TEST 2: HOD user accessing student/timetable.php
// -------------------------------------------------------------
echo "[TEST 2] HOD user (hod.cse) clicking / opening student/timetable.php...\n";
$hodCookie = tempnam(sys_get_temp_dir(), 'ck_hod_');
// Login as HOD
makeRequest("{$baseUrl}/auth/login.php", $hodCookie, [
    'username' => 'hod.cse',
    'password' => 'Admin@123'
]);

$hodAccess = makeRequest("{$baseUrl}/student/timetable.php", $hodCookie);
echo "  -> HTTP Status: {$hodAccess['status']}\n";
assert($hodAccess['status'] === 403, "HOD accessing student timetable must return HTTP 403 Forbidden");

// Verify clear message is shown
assert(strpos($hodAccess['body'], 'Student Account Required') !== false, "Must display 'Student Account Required' title");
assert(strpos($hodAccess['body'], 'hod.cse') !== false, "Must display current authenticated username");
assert(strpos($hodAccess['body'], 'HOD') !== false, "Must display current authenticated role");
assert(strpos($hodAccess['body'], 'Switch to Student Login') !== false, "Must provide Switch to Student Login button");
assert(strpos($hodAccess['body'], 'Open HOD Timetable Management') !== false, "Must provide link to HOD timetable management");
assert(strpos($hodAccess['body'], 'Access denied.') === false, "Must NOT show unstyled raw 'Access denied.' message");

echo "  -> Clear message verified: shows 'Student Account Required' with HOD session details and navigation.\n";
echo "  -> [TEST 2 PASSED]: Role-based security intact; HOD cannot bypass student authorization.\n\n";

// -------------------------------------------------------------
// TEST 3: Authenticated Student (student01) accessing student/timetable.php
// -------------------------------------------------------------
echo "[TEST 3] Authenticated Student (student01 - CSE-A) accessing student/timetable.php...\n";
$student1Cookie = tempnam(sys_get_temp_dir(), 'ck_st1_');
// Login as student01
$loginSt1 = makeRequest("{$baseUrl}/auth/login.php", $student1Cookie, [
    'username' => 'student01',
    'password' => 'Student@123'
]);
assert($loginSt1['status'] === 302, "Student login must redirect on success");

$st1Access = makeRequest("{$baseUrl}/student/timetable.php", $student1Cookie);
echo "  -> HTTP Status: {$st1Access['status']}\n";
assert($st1Access['status'] === 200, "Authenticated student must receive HTTP 200 OK");
assert(strpos($st1Access['body'], 'CSE-A') !== false, "Must display student's section (CSE-A)");
assert(strpos($st1Access['body'], 'Student 01') !== false, "Must display student name (Student 01)");
assert(strpos($st1Access['body'], 'DBMS') !== false, "Must display CSE-A timetable classes (DBMS)");
assert(strpos($st1Access['body'], 'ADSA') !== false, "Must display CSE-A timetable classes (ADSA)");
assert(strpos($st1Access['body'], 'Student Account Required') === false, "Must NOT show access denied to valid student");

echo "  -> Student timetable verified: displays CSE-A schedule with subjects, periods, and rooms.\n";
echo "  -> [TEST 3 PASSED]: Authorized student successfully sees their CSE-A timetable.\n\n";

// -------------------------------------------------------------
// TEST 4: Student isolation - Student from CSE-B (student02)
// -------------------------------------------------------------
echo "[TEST 4] Student isolation check (student02 - CSE-B)...\n";
$student2Cookie = tempnam(sys_get_temp_dir(), 'ck_st2_');
makeRequest("{$baseUrl}/auth/login.php", $student2Cookie, [
    'username' => 'student02',
    'password' => 'Student@123'
]);

$st2Access = makeRequest("{$baseUrl}/student/timetable.php", $student2Cookie);
echo "  -> HTTP Status: {$st2Access['status']}\n";
assert($st2Access['status'] === 200, "student02 must get 200 for their own section");
assert(strpos($st2Access['body'], 'CSE-B') !== false, "Must display student02's section (CSE-B)");
assert(strpos($st2Access['body'], 'Student 02') !== false, "Must display student02 name");
assert(strpos($st2Access['body'], 'Student 01') === false, "Must NOT display student01's details");

echo "  -> Student isolation verified: student02 only sees CSE-B and cannot see student01's details.\n";
echo "  -> [TEST 4 PASSED]: Authentication not weakened; no cross-student leakage.\n\n";

// -------------------------------------------------------------
// TEST 5: Verify success alert note and links in admin/timetable-upload.php
// -------------------------------------------------------------
echo "[TEST 5] Checking success alert note and links in admin/timetable-upload.php...\n";
$uploadSource = file_get_contents(__DIR__ . '/../admin/timetable-upload.php');
assert(strpos($uploadSource, 'href="../student/timetable.php"') !== false, "admin/timetable-upload.php must link to student timetable");
assert(strpos($uploadSource, 'href="timetable.php"') !== false, "admin/timetable-upload.php must link to HOD timetable management");
assert(strpos($uploadSource, 'Student Timetable portal is role-protected') !== false, "Must include notice about student role requirement");
echo "  -> Links and student account notes verified in timetable-upload.php.\n";
echo "  -> [TEST 5 PASSED]\n\n";

echo "=========================================================\n";
echo "ALL TESTS PASSED! Student View link & authorization verified.\n";
echo "=========================================================\n";
