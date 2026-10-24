<?php
// Comprehensive verification test for faculty/timetable.php

$baseUrl = "http://localhost/SmartFacultyPortal";

function httpReq($url, $postData = null, $cookieFile = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }
    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'headers' => substr($raw, 0, $headerSize),
        'body' => substr($raw, $headerSize)
    ];
}

echo "=========================================================\n";
echo "1. VERIFYING PHP SYNTAX\n";
echo "=========================================================\n";
exec('"C:\\laragon\\bin\\php\\php-8.3.33-Win32-vs16-x64\\php.exe" -l faculty/timetable.php', $syntaxOut, $syntaxCode);
echo implode("\n", $syntaxOut) . "\n";
assert($syntaxCode === 0, "faculty/timetable.php syntax error");

echo "\n=========================================================\n";
echo "2. VERIFYING UNAUTHENTICATED ACCESS (SECURITY CHECK)\n";
echo "=========================================================\n";
$unauth = httpReq("{$baseUrl}/faculty/timetable.php");
echo "Unauthenticated request HTTP Code: {$unauth['code']}\n";
assert($unauth['code'] === 302, "Unauthenticated request must redirect to login");
assert(strpos($unauth['headers'], 'Location:') !== false && strpos($unauth['headers'], 'login.php') !== false, "Redirect target must be login");
echo "PASS: Unauthenticated access blocked and redirected to login.\n";

echo "\n=========================================================\n";
echo "3. VERIFYING ROLE ACCESS CONTROL (STUDENT & HOD CANNOT ACCESS)\n";
echo "=========================================================\n";
// Student
$studentCookie = __DIR__ . "/student_test_cookie.txt";
if (file_exists($studentCookie)) unlink($studentCookie);
$sLogin = httpReq("{$baseUrl}/auth/login.php", ['username' => 'student01', 'password' => 'Student@123'], $studentCookie);
$studentAccess = httpReq("{$baseUrl}/faculty/timetable.php", null, $studentCookie);
echo "Student access HTTP Code: {$studentAccess['code']}\n";
assert($studentAccess['code'] === 403, "Student must be forbidden (HTTP 403) from faculty/timetable.php");
echo "PASS: Student role forbidden (403).\n";

// HOD
$hodCookie = __DIR__ . "/hod_test_cookie.txt";
if (file_exists($hodCookie)) unlink($hodCookie);
httpReq("{$baseUrl}/auth/login.php", ['username' => 'hod.cse', 'password' => 'Admin@123'], $hodCookie);
$hodAccess = httpReq("{$baseUrl}/faculty/timetable.php", null, $hodCookie);
echo "HOD access HTTP Code: {$hodAccess['code']}\n";
assert($hodAccess['code'] === 403, "HOD must be forbidden (HTTP 403) from faculty/timetable.php");
echo "PASS: HOD role forbidden (403).\n";

echo "\n=========================================================\n";
echo "4. AUTHENTICATING AS FACULTY (faculty01: Prof. Priya Sharma)\n";
echo "=========================================================\n";
$facultyCookie = __DIR__ . "/faculty_test_cookie.txt";
if (file_exists($facultyCookie)) unlink($facultyCookie);
$facLogin = httpReq("{$baseUrl}/auth/login.php", ['username' => 'faculty01', 'password' => 'faculty@01'], $facultyCookie);
echo "Faculty login HTTP Code: {$facLogin['code']}\n";
assert($facLogin['code'] === 302, "Faculty login should redirect to dashboard");
echo "PASS: Faculty authentication successful.\n";

echo "\n=========================================================\n";
echo "5. VERIFYING FACULTY TIMETABLE PAGE CONTENT & STRUCTURE\n";
echo "=========================================================\n";
$facPage = httpReq("{$baseUrl}/faculty/timetable.php", null, $facultyCookie);
echo "Faculty timetable HTTP Code: {$facPage['code']}\n";
assert($facPage['code'] === 200, "faculty/timetable.php must return 200 OK");
$body = $facPage['body'];

// A. Original Faculty Portal Shell Preservation
echo "A. Checking Portal Shell...\n";
assert(strpos($body, 'class="fd-dark fd-timetable-page"') !== false, "Body must retain fd-dark fd-timetable-page");
assert(strpos($body, 'id="faculty-sidebar"') !== false, "Sidebar with id faculty-sidebar must exist");
assert(strpos($body, 'Smart Faculty') !== false, "Smart Faculty brand must exist");
assert(strpos($body, 'class="fd-topbar') !== false, "fd-topbar must exist");
assert(strpos($body, 'faculty01') !== false, "Logged in username faculty01 must appear in topbar");
assert(strpos($body, 'class="tt-hero') !== false, "Purple gradient hero must exist");
assert(strpos($body, 'My Weekly Timetable') !== false, "Hero title must exist");
assert(strpos($body, 'class="tt-summary') !== false, "Dynamic summary section must exist");
assert(strpos($body, 'WEEKLY CLASSES') !== false, "WEEKLY CLASSES card must exist");
assert(strpos($body, 'TODAY\'S CLASSES') !== false, "TODAY'S CLASSES card must exist");
assert(strpos($body, 'TIMETABLE DAYS') !== false, "TIMETABLE DAYS card must exist");
assert(strpos($body, 'DAILY PERIODS') !== false, "DAILY PERIODS card must exist");
assert(strpos($body, 'class="fd-footer') !== false, "Footer must exist");
echo "PASS: Original Faculty portal shell completely preserved.\n";

// B. Institutional Heading & Metadata
echo "B. Checking Institutional Heading & Faculty Details...\n";
assert(strpos($body, 'DEPARTMENT OF COMPUTER SCIENCE AND ENGINEERING') !== false, "Department heading must exist");
assert(strpos($body, 'FACULTY INDIVIDUAL TIME TABLE') !== false, "Institutional subtitle must exist");
assert(strpos($body, 'Prof. Priya Sharma') !== false, "Faculty name must be Prof. Priya Sharma");
assert(strpos($body, 'CSE002') !== false, "Faculty code must be CSE002");
assert(strpos($body, 'Assistant Professor') !== false, "Designation must be Assistant Professor");
assert(strpos($body, 'Academic Year:') !== false, "Academic Year label must exist");
assert(strpos($body, '2026-2027') !== false, "Academic Year value must exist");
assert(strpos($body, 'Year / Semester / Section:') !== false, "Year/Semester/Section label must exist");
assert(strpos($body, 'CSE-A') !== false, "Section CSE-A must exist");
assert(strpos($body, 'Classroom Number:') !== false, "Classroom Number label must exist");
assert(strpos($body, 'LAB-1') !== false, "LAB-1 classroom must exist");
echo "PASS: Department heading and faculty details confirmed.\n";

// C. Grid, Days & Period Timings
echo "C. Checking Grid, Days, and P1–P9 Timings...\n";
assert(strpos($body, 'P1') !== false && strpos($body, '08:30 - 09:15') !== false, "P1 timing must exist");
assert(strpos($body, 'P2') !== false && strpos($body, '09:15 - 10:00') !== false, "P2 timing must exist");
assert(strpos($body, 'P3') !== false && strpos($body, '10:00 - 10:45') !== false, "P3 timing must exist");
assert(strpos($body, 'P4') !== false && strpos($body, '11:00 - 11:45') !== false, "P4 timing must exist");
assert(strpos($body, 'P5') !== false && strpos($body, '11:45 - 12:30') !== false, "P5 timing must exist");
assert(strpos($body, 'P6') !== false && strpos($body, '12:30 - 13:15') !== false, "P6 timing must exist");
assert(strpos($body, 'P7') !== false && strpos($body, '14:00 - 14:45') !== false, "P7 timing must exist");
assert(strpos($body, 'P8') !== false && strpos($body, '14:45 - 15:30') !== false, "P8 timing must exist");
assert(strpos($body, 'P9') !== false && strpos($body, '15:45 - 16:30') !== false, "P9 timing must exist");
assert(strpos($body, 'MONDAY') !== false, "MONDAY must exist");
assert(strpos($body, 'TUESDAY') !== false, "TUESDAY must exist");
assert(strpos($body, 'WEDNESDAY') !== false, "WEDNESDAY must exist");
assert(strpos($body, 'THURSDAY') !== false, "THURSDAY must exist");
assert(strpos($body, 'FRIDAY') !== false, "FRIDAY must exist");
assert(strpos($body, 'SATURDAY') !== false, "SATURDAY must exist");
echo "PASS: All six days and P1-P9 actual timings confirmed.\n";

// D. Lunch Break Column
echo "D. Checking Lunch Break Column...\n";
assert(strpos($body, 'th-lunch') !== false, "Lunch th must exist");
assert(strpos($body, '13:15–14:00') !== false || strpos($body, '13:15 - 14:00') !== false, "Lunch timings must exist");
assert(strpos($body, 'rowspan="6"') !== false, "Lunch must span 6 rows with rowspan=6");
assert(strpos($body, 'LUNCH BREAK') !== false, "LUNCH BREAK text must exist");
echo "PASS: Lunch Break column with rowspan=6 confirmed.\n";

// E. Faculty-Specific Published Entries & Merged Lab Session
echo "E. Checking Faculty-Specific Published Entries & Merged Lab...\n";
assert(strpos($body, 'ADSA') !== false, "ADSA subject must exist");
assert(strpos($body, 'Advanced Data Structures and Algorithms') !== false, "ADSA name must exist");
assert(strpos($body, 'ADSA-LAB') !== false, "ADSA-LAB subject must exist");
assert(strpos($body, 'colspan="3"') !== false, "Continuous Monday lab session (P7,P8,P9) must merge with colspan=3");
assert(strpos($body, 'badge-lab') !== false, "LAB badge must exist");
echo "PASS: Faculty published entries and horizontal continuous lab merging confirmed.\n";

// F. Workload Summary, Signatures, and Print Controls
echo "F. Checking Workload Summary, Signatures, and Print Button...\n";
assert(strpos($body, 'Course Allocation &amp; Workload Summary') !== false, "Workload summary must exist");
assert(strpos($body, 'Total Assigned Teaching Hours:') !== false, "Total teaching hours must exist");
assert(strpos($body, 'Faculty Member') !== false, "Faculty Member signature must exist");
assert(strpos($body, 'Timetable Coordinator') !== false, "Timetable Coordinator signature must exist");
assert(strpos($body, 'Head of Department (HOD)') !== false, "HOD signature must exist");
assert(strpos($body, 'Principal') !== false, "Principal signature must exist");
assert(strpos($body, 'window.print()') !== false, "Print button with window.print() must exist");
assert(strpos($body, '@page') !== false && strpos($body, 'landscape') !== false, "Landscape A4 print CSS must exist");
echo "PASS: Workload summary, signatures, and landscape print styles confirmed.\n";

// G. Verification of another faculty member isolation (user_id = 3: Prof. Karthik Raj)
echo "G. Checking Isolation: Simulating session for faculty member (Prof. Karthik Raj, User ID: 3)...\n";
exec('"C:\\laragon\\bin\\php\\php-8.3.33-Win32-vs16-x64\\php.exe" scratch/simulate_faculty2_render.php', $fac2OutLines);
$fac2Body = implode("\n", $fac2OutLines);
assert(strpos($fac2Body, 'Prof. Karthik Raj') !== false, "Must see Prof. Karthik Raj");
assert(strpos($fac2Body, 'CSE003') !== false, "Must see CSE003");
assert(strpos($fac2Body, 'Prof. Priya Sharma') === false, "Must NOT see Prof. Priya Sharma");
echo "PASS: Strict faculty isolation verified.\n";

echo "\n=========================================================\n";
echo "6. VERIFYING UNMODIFIED PAGES (ISOLATION CHECK)\n";
echo "=========================================================\n";
$facDash = httpReq("{$baseUrl}/faculty/dashboard.php", null, $facultyCookie);
assert($facDash['code'] === 200, "faculty/dashboard.php must return 200 OK");
echo "PASS: faculty/dashboard.php returns 200 OK.\n";

$facProf = httpReq("{$baseUrl}/faculty/profile.php", null, $facultyCookie);
assert($facProf['code'] === 200, "faculty/profile.php must return 200 OK");
echo "PASS: faculty/profile.php returns 200 OK.\n";

$hodTT = httpReq("{$baseUrl}/admin/my-timetable.php", null, $hodCookie);
assert($hodTT['code'] === 200, "admin/my-timetable.php must return 200 OK");
assert(strpos($hodTT['body'], 'Dr. Arun Kumar') !== false, "HOD timetable must still display Dr. Arun Kumar");
echo "PASS: admin/my-timetable.php returns 200 OK and remains unaffected.\n";

echo "\nALL 6 VERIFICATION TEST SUITES PASSED SUCCESSFULLY!\n";
