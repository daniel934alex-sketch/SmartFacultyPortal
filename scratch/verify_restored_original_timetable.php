<?php
/**
 * Verification of Fully Restored Original Faculty Timetable
 */

$baseUrl = "http://localhost/SmartFacultyPortal";

function makeReq($url, $cookieFile = null, $postData = null) {
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
echo "VERIFICATION: FULL RESTORATION OF ORIGINAL UI\n";
echo "=========================================================\n\n";

// 1. Unauthenticated Redirect Check
echo "[TEST 1] Unauthenticated request to faculty/timetable.php...\n";
$unauth = makeReq("{$baseUrl}/faculty/timetable.php");
echo "  -> HTTP Status: {$unauth['status']}\n";
assert($unauth['status'] === 302, "Must redirect unauthenticated user");
assert(strpos($unauth['header'], 'Location: ../auth/login.php') !== false, "Redirect header must point to login.php");
echo "  -> [PASSED] Unauthenticated redirect working.\n\n";

// 2. Role Authorization Check
echo "[TEST 2] Student role accessing faculty/timetable.php...\n";
$stCookie = tempnam(sys_get_temp_dir(), 'st_');
makeReq("{$baseUrl}/auth/login.php", $stCookie, ['username' => 'student01', 'password' => 'Student@123']);
$stRes = makeReq("{$baseUrl}/faculty/timetable.php", $stCookie);
echo "  -> HTTP Status: {$stRes['status']}\n";
assert($stRes['status'] === 403, "Student must receive HTTP 403 Forbidden");
echo "  -> [PASSED] Role authorization working.\n\n";

// 3. Faculty Login & Original UI Shell Check
echo "[TEST 3] Faculty (faculty01) login and rendering original timetable...\n";
$facCookie = tempnam(sys_get_temp_dir(), 'fac_');
makeReq("{$baseUrl}/auth/login.php", $facCookie, ['username' => 'faculty01', 'password' => 'faculty@01']);
$facRes = makeReq("{$baseUrl}/faculty/timetable.php", $facCookie);
echo "  -> HTTP Status: {$facRes['status']}\n";
assert($facRes['status'] === 200, "Faculty must receive HTTP 200 OK");
$html = $facRes['body'];

// Verify Original UI Shell
assert(strpos($html, 'class="fd-dark fd-timetable-page"') !== false, "Must have original fd-dark fd-timetable-page class");
assert(strpos($html, 'class="fd-layout"') !== false, "Must have original fd-layout");
assert(strpos($html, 'class="fd-sidebar" id="faculty-sidebar"') !== false, "Must have original fd-sidebar");
assert(strpos($html, 'class="fd-brand"') !== false, "Must have original fd-brand");
assert(strpos($html, 'class="fd-topbar"') !== false, "Must have original fd-topbar");
assert(strpos($html, 'class="tt-hero"') !== false, "Must have original tt-hero banner");
assert(strpos($html, 'class="tt-summary"') !== false, "Must have original tt-summary metric cards");
assert(strpos($html, 'WEEKLY CLASSES') !== false, "Must have WEEKLY CLASSES card");
assert(strpos($html, 'TODAY\'S CLASSES') !== false, "Must have TODAY'S CLASSES card");
assert(strpos($html, 'TIMETABLE DAYS') !== false, "Must have TIMETABLE DAYS card");
assert(strpos($html, 'DAILY PERIODS') !== false, "Must have DAILY PERIODS card");
assert(strpos($html, 'class="tt-table-wrap"') !== false, "Must have original tt-table-wrap container");
assert(strpos($html, 'class="tt-table"') !== false, "Must have original tt-table presentation");
assert(strpos($html, 'class="tt-table-foot"') !== false, "Must have original tt-table-foot legend");
assert(strpos($html, 'class="fd-footer"') !== false, "Must have original fd-footer");
echo "  -> [PASSED] All original UI elements (sidebar, topbar, hero, 4 cards, dark timetable, footer) verified.\n\n";

// 4. Verify Absence of Institutional-Style Elements
echo "[TEST 4] Verifying NO institutional elements remain...\n";
assert(strpos($html, 'timetable-paper-panel') === false, "Must NOT contain timetable-paper-panel");
assert(strpos($html, 'DEPARTMENT OF COMPUTER SCIENCE AND ENGINEERING') === false, "Must NOT contain institutional department heading");
assert(strpos($html, 'inst-meta-grid') === false, "Must NOT contain institutional meta grid");
assert(strpos($html, 'LUNCH BREAK') === false, "Must NOT contain vertical lunch break column");
assert(strpos($html, 'Course Allocation & Workload Summary') === false, "Must NOT contain workload summary");
assert(strpos($html, 'Timetable Coordinator') === false, "Must NOT contain institutional signature block");
assert(strpos($html, 'window.print()') === false, "Must NOT contain print button");
echo "  -> [PASSED] Zero institutional elements found. Clean original timetable restored.\n\n";

// 5. Verify Other Faculty Pages
echo "[TEST 5] Checking other faculty portal pages...\n";
$pages = ['dashboard.php', 'profile.php', 'notifications.php', 'requests.php', 'availability.php'];
foreach ($pages as $p) {
    $res = makeReq("{$baseUrl}/faculty/{$p}", $facCookie);
    echo "  - faculty/{$p}: HTTP {$res['status']}\n";
    assert($res['status'] === 200, "faculty/{$p} must return HTTP 200");
}
echo "  -> [PASSED] All faculty pages operational.\n\n";

echo "=========================================================\n";
echo "SUCCESS: COMPLETE RESTORATION VERIFIED AGAINST LOCAL SERVER!\n";
echo "=========================================================\n";
