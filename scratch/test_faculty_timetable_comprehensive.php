<?php
/**
 * Comprehensive Automated Test Suite for Restored Original UI + Institutional Timetable
 */

$baseUrl = "http://localhost/SmartFacultyPortal";

function makeHttpRequest($url, $cookieFile = null, $postData = null) {
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
echo "TEST SUITE: RESTORED ORIGINAL UI + INSTITUTIONAL TIMETABLE\n";
echo "=========================================================\n\n";

// TEST 1: Unauthenticated access redirect
echo "[TEST 1] Unauthenticated request to faculty/timetable.php...\n";
$unauth = makeHttpRequest("{$baseUrl}/faculty/timetable.php");
echo "  -> HTTP Status: {$unauth['status']}\n";
assert($unauth['status'] === 302, "Must redirect unauthenticated user");
assert(strpos($unauth['header'], 'Location: ../auth/login.php') !== false, "Redirect header must point to login.php");
echo "  -> [TEST 1 PASSED]: Redirects to login.php correctly.\n\n";

// TEST 2: Student accessing faculty/timetable.php (Forbidden)
echo "[TEST 2] Student (student01) requesting faculty/timetable.php...\n";
$studentCookie = tempnam(sys_get_temp_dir(), 'ck_st_');
makeHttpRequest("{$baseUrl}/auth/login.php", $studentCookie, [
    'username' => 'student01',
    'password' => 'Student@123'
]);
$stAccess = makeHttpRequest("{$baseUrl}/faculty/timetable.php", $studentCookie);
echo "  -> HTTP Status: {$stAccess['status']}\n";
assert($stAccess['status'] === 403, "Student accessing faculty timetable must return 403 Forbidden");
echo "  -> [TEST 2 PASSED]: Role enforcement intact.\n\n";

// TEST 3: Authenticated Faculty (faculty01 / Prof. Priya Sharma)
echo "[TEST 3] Authenticated Faculty (faculty01) login and timetable render...\n";
$facCookie = tempnam(sys_get_temp_dir(), 'ck_fac_');
$login = makeHttpRequest("{$baseUrl}/auth/login.php", $facCookie, [
    'username' => 'faculty01',
    'password' => 'faculty@01'
]);
assert($login['status'] === 302, "Faculty login must redirect on success");

$facPage = makeHttpRequest("{$baseUrl}/faculty/timetable.php", $facCookie);
echo "  -> HTTP Status: {$facPage['status']}\n";
assert($facPage['status'] === 200, "Faculty must receive HTTP 200 OK");
$html = $facPage['body'];

// 1. Verify Original UI Shell Elements (Commit 04f512d)
assert(strpos($html, 'class="fd-dark fd-timetable-page"') !== false, "Body must have 'fd-dark fd-timetable-page' classes");
assert(strpos($html, 'class="fd-layout"') !== false, "Must have 'fd-layout' wrapper");
assert(strpos($html, 'class="fd-sidebar" id="faculty-sidebar"') !== false, "Must have original 'fd-sidebar'");
assert(strpos($html, 'class="fd-brand"') !== false, "Must have original SFA brand header");
assert(strpos($html, 'Smart Faculty') !== false, "Must contain 'Smart Faculty' in brand");
assert(strpos($html, 'ASSISTANT PORTAL') !== false, "Must contain 'ASSISTANT PORTAL' subtitle");
assert(strpos($html, 'href="dashboard.php" class="fd-nav-link"') !== false, "Must contain Dashboard nav link");
assert(strpos($html, 'href="timetable.php" class="fd-nav-link active"') !== false, "Must contain active My Timetable nav link");
assert(strpos($html, 'href="notifications.php" class="fd-nav-link"') !== false, "Must contain Notifications nav link");
assert(strpos($html, 'href="profile.php" class="fd-nav-link"') !== false, "Must contain Profile nav link");
assert(strpos($html, 'href="availability.php" class="fd-nav-link"') !== false, "Must contain Availability nav link");
assert(strpos($html, 'href="requests.php" class="fd-nav-link"') !== false, "Must contain Handover Requests nav link");
assert(strpos($html, 'class="fd-side-help"') !== false, "Must have original help card 'Your teaching hub'");
assert(strpos($html, 'class="fd-side-logout"') !== false, "Must have original logout button");
assert(strpos($html, 'class="fd-topbar"') !== false, "Must have original topbar");
assert(strpos($html, 'FACULTY / SCHEDULE') !== false, "Must have breadcrumb 'FACULTY / SCHEDULE'");
assert(strpos($html, 'class="tt-hero"') !== false, "Must have original purple gradient hero banner");
assert(strpos($html, '✦ YOUR TEACHING SCHEDULE') !== false, "Must have hero eyebrow '✦ YOUR TEACHING SCHEDULE'");
assert(strpos($html, 'class="tt-summary"') !== false, "Must have original 4 metric summary stat cards");
assert(strpos($html, 'WEEKLY CLASSES') !== false, "Must have 'WEEKLY CLASSES' metric");
assert(strpos($html, 'TODAY\'S CLASSES') !== false, "Must have 'TODAY\'S CLASSES' metric");
assert(strpos($html, 'TIMETABLE DAYS') !== false, "Must have 'TIMETABLE DAYS' metric");
assert(strpos($html, 'DAILY PERIODS') !== false, "Must have 'DAILY PERIODS' metric");
assert(strpos($html, 'class="fd-footer"') !== false, "Must have original footer");
echo "  -> [ORIGINAL UI SHELL VERIFIED]: All original sidebar, topbar, hero, 4 metric cards, footer, and navigation confirmed.\n";

// 2. Verify Institutional Timetable Inside tt-section
assert(strpos($html, 'class="timetable-paper-panel"') !== false, "Must contain white paper panel inside tt-section");
assert(strpos($html, 'DEPARTMENT OF COMPUTER SCIENCE AND ENGINEERING') !== false, "Must display 'DEPARTMENT OF COMPUTER SCIENCE AND ENGINEERING'");
assert(strpos($html, 'Prof. Priya Sharma') !== false, "Must display faculty name 'Prof. Priya Sharma'");
assert(strpos($html, 'CSE002') !== false, "Must display faculty code 'CSE002'");
assert(strpos($html, 'Assistant Professor') !== false, "Must display designation 'Assistant Professor'");
assert(strpos($html, '2026-2027') !== false, "Must display academic year '2026-2027'");
assert(strpos($html, 'Semester 3') !== false, "Must display semester 'Semester 3'");
echo "  -> [INSTITUTIONAL HEADER VERIFIED]: Department, Name, Code, Designation, AY, Semester all present.\n";

// 3. Verify Table Structure & Days
foreach (['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'] as $day) {
    assert(strpos($html, $day) !== false, "Grid must contain day row: $day");
}
echo "  -> [DAYS VERIFIED]: Monday through Saturday present.\n";

// 4. Verify Periods & Timings
for ($p = 1; $p <= 9; $p++) {
    assert(strpos($html, "P{$p}") !== false, "Grid must contain period header: P{$p}");
}
assert(strpos($html, '08:30–09:15') !== false, "Must contain P1 timing 08:30–09:15");
assert(strpos($html, '09:15–10:00') !== false, "Must contain P2 timing 09:15–10:00");
assert(strpos($html, '10:00–10:45') !== false, "Must contain P3 timing 10:00–10:45");
assert(strpos($html, '11:00–11:45') !== false, "Must contain P4 timing 11:00–11:45");
assert(strpos($html, '11:45–12:30') !== false, "Must contain P5 timing 11:45–12:30");
assert(strpos($html, '12:30–13:15') !== false, "Must contain P6 timing 12:30–13:15");
assert(strpos($html, '13:15–14:00') !== false, "Must contain Lunch timing 13:15–14:00");
assert(strpos($html, '14:00–14:45') !== false, "Must contain P7 timing 14:00–14:45");
assert(strpos($html, '14:45–15:30') !== false, "Must contain P8 timing 14:45–15:30");
assert(strpos($html, '15:45–16:30') !== false, "Must contain P9 timing 15:45–16:30");
echo "  -> [PERIODS & TIMINGS VERIFIED]: P1-P9 and Lunch timings exactly match database.\n";

// 5. Verify Distinct Vertical Lunch Break column
assert(strpos($html, 'rowspan="6" class="td-lunch-vertical"') !== false, "Must have vertical lunch column with rowspan=6");
assert(strpos($html, 'LUNCH BREAK') !== false, "Must display 'LUNCH BREAK' in distinct vertical column");
echo "  -> [LUNCH BREAK COLUMN VERIFIED]: Merged vertical column with rowspan=6 between P6 and P7.\n";

// 6. Verify Classes Assigned to faculty01 (Prof. Priya Sharma)
assert(strpos($html, 'ADSA') !== false, "Must display ADSA subject code");
assert(strpos($html, 'Advanced Data Structures and Algorithms') !== false, "Must display ADSA subject name");
assert(strpos($html, 'CSE-A') !== false, "Must display section CSE-A");
assert(strpos($html, 'Rm 36') !== false, "Must display room 36");

assert(strpos($html, 'ADSA-LAB') !== false, "Must display ADSA-LAB subject code");
assert(strpos($html, 'Rm LAB-1') !== false, "Must display room LAB-1");

assert(strpos($html, 'DBMS') !== false, "Must display DBMS subject code");
assert(strpos($html, 'CSE-B') !== false, "Must display section CSE-B");
assert(strpos($html, 'Rm 37') !== false, "Must display room 37");

// 7. Verify Empty / Free Period Dash
assert(strpos($html, '<div class="free-period-dash">') !== false, "Must have free-period-dash container");
assert(strpos($html, '<span>—</span>') !== false, "Must display dash (—) for free periods");
echo "  -> [CLASS CELLS & FREE PERIODS VERIFIED]: Correct classes, sections, rooms and dashes present.\n";

// 8. Verify Workload Summary Table & Signatures
assert(strpos($html, 'Course Allocation & Workload Summary') !== false, "Must have Workload Summary table");
assert(strpos($html, 'TOTAL WEEKLY TEACHING WORKLOAD') !== false, "Must calculate Total Weekly Teaching Workload");
assert(strpos($html, '8 Periods') !== false, "Must show 8 Periods load for Prof. Priya Sharma");
assert(strpos($html, 'Faculty Signature') !== false, "Must have Faculty Signature line");
assert(strpos($html, 'Timetable Coordinator') !== false, "Must have Timetable Coordinator signature line");
assert(strpos($html, 'Head of Department (HOD)') !== false, "Must have HOD signature line");
assert(strpos($html, 'Principal') !== false, "Must have Principal signature line");
echo "  -> [WORKLOAD & SIGNATURES VERIFIED]: Summary table and 4 signature lines present.\n";

// 9. Verify Controls & Print Button
assert(strpos($html, 'onclick="window.print()"') !== false, "Must contain window.print() print button");
assert(strpos($html, '@media print') !== false, "Must contain print media query");
echo "  -> [PRINT & CONTROLS VERIFIED]: Functional Print Timetable button and print stylesheet confirmed.\n\n";

echo "=========================================================\n";
echo "ALL TESTS PASSED! Original UI Shell + Institutional Timetable 100% Verified.\n";
echo "=========================================================\n";
