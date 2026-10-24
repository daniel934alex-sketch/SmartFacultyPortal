<?php
// Comprehensive HTTP and structural verification for admin/my-timetable.php

$baseUrl = "http://localhost/SmartFacultyPortal";
$cookieFile = __DIR__ . "/hod_cookie.txt";
if (file_exists($cookieFile)) unlink($cookieFile);

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
exec('"C:\\laragon\\bin\\php\\php-8.3.33-Win32-vs16-x64\\php.exe" -l admin/my-timetable.php', $syntaxOut, $syntaxCode);
echo implode("\n", $syntaxOut) . "\n";
assert($syntaxCode === 0, "admin/my-timetable.php syntax error");

echo "\n=========================================================\n";
echo "2. VERIFYING UNAUTHENTICATED ACCESS (SECURITY CHECK)\n";
echo "=========================================================\n";
$unauth = httpReq("{$baseUrl}/admin/my-timetable.php");
echo "Unauthenticated request HTTP Code: {$unauth['code']}\n";
assert($unauth['code'] === 302, "Unauthenticated request must redirect to login");
echo "PASS: Unauthenticated access blocked and redirected.\n";

echo "\n=========================================================\n";
echo "3. AUTHENTICATING AS HOD (username: hod.cse)\n";
echo "=========================================================\n";
$login = httpReq("{$baseUrl}/auth/login.php", [
    'username' => 'hod.cse',
    'password' => 'Admin@123'
], $cookieFile);
echo "Login HTTP Code: {$login['code']}\n";
assert($login['code'] === 302, "Login should redirect on success");
echo "PASS: HOD authentication successful.\n";

echo "\n=========================================================\n";
echo "4. VERIFYING HOD MY TIMETABLE PAGE CONTENT & STRUCTURE\n";
echo "=========================================================\n";
$page = httpReq("{$baseUrl}/admin/my-timetable.php", null, $cookieFile);
echo "Page HTTP Code: {$page['code']}\n";
assert($page['code'] === 200, "admin/my-timetable.php must return 200 OK");
$body = $page['body'];

// A. Existing Portal UI Shell
echo "Checking Portal UI Shell...\n";
assert(strpos($body, 'Smart Faculty Assistant') !== false, "Navbar logo / brand must exist");
assert(strpos($body, 'hod.cse') !== false, "Username must appear in navbar");
assert(strpos($body, 'sidebar') !== false, "Sidebar must exist");
assert(strpos($body, 'Dashboard') !== false, "Sidebar navigation link to Dashboard must exist");
assert(strpos($body, 'My Timetable') !== false, "Sidebar navigation link to My Timetable must exist");
assert(strpos($body, '<h1>My Timetable</h1>') !== false, "Page heading h1 must exist");
assert(strpos($body, 'View your assigned teaching periods and classes.') !== false, "Page subtext must exist");
echo "PASS: Existing Portal UI Shell intact.\n";

// B. Institutional Header
echo "Checking Institutional Heading...\n";
assert(strpos($body, 'DEPARTMENT OF COMPUTER SCIENCE AND ENGINEERING') !== false, "Department heading must exist");
assert(strpos($body, 'FACULTY INDIVIDUAL TIME TABLE') !== false, "Document subhead must exist");
echo "PASS: Department heading confirmed.\n";

// C. Institutional Metadata Rows
echo "Checking Metadata Rows...\n";
assert(strpos($body, 'Year / Semester / Section:') !== false, "Metadata label Year/Semester/Section must exist");
assert(strpos($body, 'II Year / III Sem / CSE-A') !== false, "Metadata value II Year / III Sem / CSE-A must exist");
assert(strpos($body, 'Classroom Number:') !== false, "Metadata label Classroom Number must exist");
assert(strpos($body, '36') !== false && strpos($body, 'LAB-2') !== false, "Classrooms 36 and LAB-2 must exist");
assert(strpos($body, 'Academic Year:') !== false, "Metadata label Academic Year must exist");
assert(strpos($body, '2026-2027') !== false, "Metadata value 2026-2027 must exist");
assert(strpos($body, 'Dr. Arun Kumar') !== false, "Faculty name must exist");
assert(strpos($body, 'CSE001') !== false, "Faculty code must exist");
echo "PASS: Metadata rows confirmed.\n";

// D. Grid & Period Headers
echo "Checking Grid & Period Headings...\n";
assert(strpos($body, 'P1') !== false && strpos($body, '08:30 - 09:15') !== false, "P1 header must exist with timings");
assert(strpos($body, 'P2') !== false && strpos($body, '09:15 - 10:00') !== false, "P2 header must exist with timings");
assert(strpos($body, 'P3') !== false && strpos($body, '10:00 - 10:45') !== false, "P3 header must exist with timings");
assert(strpos($body, 'P4') !== false && strpos($body, '11:00 - 11:45') !== false, "P4 header must exist with timings");
assert(strpos($body, 'P5') !== false && strpos($body, '11:45 - 12:30') !== false, "P5 header must exist with timings");
assert(strpos($body, 'P6') !== false && strpos($body, '12:30 - 13:15') !== false, "P6 header must exist with timings");
assert(strpos($body, 'P7') !== false && strpos($body, '14:00 - 14:45') !== false, "P7 header must exist with timings");
assert(strpos($body, 'P8') !== false && strpos($body, '14:45 - 15:30') !== false, "P8 header must exist with timings");
assert(strpos($body, 'P9') !== false && strpos($body, '15:45 - 16:30') !== false, "P9 header must exist with timings");
echo "PASS: P1-P9 headers with start/end times confirmed.\n";

// E. Vertically Merged Lunch Column
echo "Checking Vertical Lunch Column...\n";
assert(strpos($body, 'th-lunch') !== false, "Lunch header column must exist");
assert(strpos($body, 'rowspan="6"') !== false, "Lunch column must have rowspan=6 across Monday-Saturday");
assert(strpos($body, 'LUNCH BREAK') !== false, "Lunch text must exist");
assert(strpos($body, '13:15–14:00') !== false || strpos($body, '13:15 - 14:00') !== false, "Lunch timings must exist");
echo "PASS: Vertically merged Lunch Break column confirmed.\n";

// F. Merged Consecutive Periods (Horizontal)
echo "Checking Merged Consecutive Periods for Lab...\n";
assert(strpos($body, 'colspan="3"') !== false, "Wednesday continuous lab must span 3 periods with colspan=3");
assert(strpos($body, 'DBMS-LAB') !== false, "DBMS-LAB subject code must exist");
assert(strpos($body, 'DBMS Lab') !== false, "DBMS Lab subject name must exist");
assert(strpos($body, 'LAB-2') !== false, "LAB-2 room must exist");
echo "PASS: Merged consecutive period (colspan=3) confirmed.\n";

// G. Days
echo "Checking Days Rows...\n";
assert(strpos($body, 'MONDAY') !== false, "Monday row must exist");
assert(strpos($body, 'TUESDAY') !== false, "Tuesday row must exist");
assert(strpos($body, 'WEDNESDAY') !== false, "Wednesday row must exist");
assert(strpos($body, 'THURSDAY') !== false, "Thursday row must exist");
assert(strpos($body, 'FRIDAY') !== false, "Friday row must exist");
assert(strpos($body, 'SATURDAY') !== false, "Saturday row must exist");
echo "PASS: Monday through Saturday rows confirmed.\n";

// H. Summary & Signatures
echo "Checking Course Allocation Summary & Signatures...\n";
assert(strpos($body, 'Course Allocation &amp; Workload Summary') !== false, "Workload summary must exist");
assert(strpos($body, 'Total Assigned Teaching Hours:') !== false, "Total hours must exist");
assert(strpos($body, 'Timetable Coordinator') !== false, "Timetable coordinator signature line must exist");
assert(strpos($body, 'Principal') !== false, "Principal signature line must exist");
echo "PASS: Summary and Signatures confirmed.\n";

echo "\n=========================================================\n";
echo "5. VERIFYING UNMODIFIED PAGES (ISOLATION CHECK)\n";
echo "=========================================================\n";
$dash = httpReq("{$baseUrl}/admin/dashboard.php", null, $cookieFile);
assert($dash['code'] === 200, "Admin dashboard must return 200 OK");
echo "PASS: admin/dashboard.php returns 200 OK.\n";

$ttAdmin = httpReq("{$baseUrl}/admin/timetable.php", null, $cookieFile);
assert($ttAdmin['code'] === 200, "Admin timetable management must return 200 OK");
echo "PASS: admin/timetable.php returns 200 OK.\n";

echo "\nALL AUTOMATED TESTS PASSED SUCCESSFULLY!\n";
