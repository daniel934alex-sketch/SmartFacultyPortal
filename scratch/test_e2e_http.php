<?php
/**
 * End-to-End HTTP Test for Timetable Conflict Policies
 */

require_once __DIR__ . '/../config/database.php';

$cookieFile = __DIR__ . '/cookie_hod.txt';
@unlink($cookieFile);

$baseUrl = "http://localhost/SmartFacultyPortal";

function httpRequest($url, $postFields = null, $customHeaders = []) {
    global $cookieFile;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    if (!empty($customHeaders)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $customHeaders);
    }
    if ($postFields !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    }
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return ['status' => $info['http_code'], 'body' => $response];
}

echo "=== STARTING HTTP END-TO-END CONFLICT POLICY TESTS ===\n\n";

// 1. Authenticate as HOD
echo "[STEP 1] Logging in as HOD (username: hod.cse)...\n";
$loginRes = httpRequest("{$baseUrl}/auth/login.php", [
    'username' => 'hod.cse',
    'password' => 'Admin@123'
]);

$dashRes = httpRequest("{$baseUrl}/admin/timetable-upload.php");
if ($dashRes['status'] !== 200 || strpos($dashRes['body'], 'Timetable Management') === false) {
    die("FAIL: Could not log in as HOD.\n");
}
echo "  -> Logged in successfully.\n\n";

// 2. Fetch upload page to get CSRF token
echo "[STEP 2] Fetching timetable-upload.php to obtain CSRF token...\n";
$pageRes = httpRequest("{$baseUrl}/admin/timetable-upload.php");
assert($pageRes['status'] === 200, "Should get 200 from timetable-upload.php");
preg_match('/name="csrf_token" value="([a-f0-9]+)"/i', $pageRes['body'], $m);
if (empty($m[1])) {
    die("FAIL: CSRF token not found in upload page.\n");
}
$csrfToken = $m[1];
echo "  -> CSRF token: " . substr($csrfToken, 0, 10) . "...\n\n";

// 3. Test Upload with conflict_mode = 'skip'
echo "[STEP 3] Uploading timetable_template.csv with conflict_mode = 'skip'...\n";
$filePath = realpath(__DIR__ . '/../timetable_template.csv');
$postData = [
    'action' => 'upload_file',
    'csrf_token' => $csrfToken,
    'section_id' => '1',
    'conflict_mode' => 'skip',
    'default_status' => 'PUBLISHED',
    'timetable_file' => new CURLFile($filePath, 'text/csv', 'timetable_template.csv')
];

$uploadResSkip = httpRequest("{$baseUrl}/admin/timetable-upload.php", $postData);
assert($uploadResSkip['status'] === 200, "Upload request must succeed with HTTP 200");
$htmlSkip = $uploadResSkip['body'];

// Verify 5 KPI counters
preg_match('/id="kpi-total-val"[^>]*>(\d+)</', $htmlSkip, $mTotal);
preg_match('/id="kpi-ready-val"[^>]*>(\d+)</', $htmlSkip, $mReady);
preg_match('/id="kpi-skipped-val"[^>]*>(\d+)</', $htmlSkip, $mSkipped);
preg_match('/id="kpi-warning-val"[^>]*>(\d+)</', $htmlSkip, $mWarning);
preg_match('/id="kpi-error-val"[^>]*>(\d+)</', $htmlSkip, $mError);

echo "  -> KPI Total:   " . ($mTotal[1] ?? 'NOT FOUND') . " (Expected: 35)\n";
echo "  -> KPI Ready:   " . ($mReady[1] ?? 'NOT FOUND') . " (Expected: 25)\n";
echo "  -> KPI Skipped: " . ($mSkipped[1] ?? 'NOT FOUND') . " (Expected: 10)\n";
echo "  -> KPI Warning: " . ($mWarning[1] ?? 'NOT FOUND') . " (Expected: 0)\n";
echo "  -> KPI Error:   " . ($mError[1] ?? 'NOT FOUND') . " (Expected: 0)\n";

assert(($mTotal[1] ?? '') === '35', "KPI Total must be 35");
assert(($mReady[1] ?? '') === '25', "KPI Ready must be 25 under Skip");
assert(($mSkipped[1] ?? '') === '10', "KPI Skipped must be 10 under Skip");
assert(($mWarning[1] ?? '') === '0', "KPI Warning must be 0");
assert(($mError[1] ?? '') === '0', "KPI Error must be 0");

// Verify policy is preserved
assert(strpos($htmlSkip, 'name="conflict_mode" id="conflict_mode_input" value="skip"') !== false, "Hidden input must preserve 'skip'");
assert(strpos($htmlSkip, 'value="skip" selected') !== false, "Dropdown must have 'skip' selected");

// Verify badges & reasons
assert(strpos($htmlSkip, 'tt-badge-skipped') !== false, "Must render tt-badge-skipped");
assert(strpos($htmlSkip, '⏭ SKIPPED') !== false, "Must render ⏭ SKIPPED badge text");
assert(strpos($htmlSkip, 'Will be SKIPPED') !== false, "Must render exact skipped reason");

echo "  -> [STEP 3 SKIP POLICY PREVIEW PASSED]\n\n";

// 4. Test Upload with conflict_mode = 'replace'
echo "[STEP 4] Uploading timetable_template.csv with conflict_mode = 'replace'...\n";
$postDataReplace = [
    'action' => 'upload_file',
    'csrf_token' => $csrfToken,
    'section_id' => '1',
    'conflict_mode' => 'replace',
    'default_status' => 'PUBLISHED',
    'timetable_file' => new CURLFile($filePath, 'text/csv', 'timetable_template.csv')
];

$uploadResReplace = httpRequest("{$baseUrl}/admin/timetable-upload.php", $postDataReplace);
assert($uploadResReplace['status'] === 200, "Upload request must succeed with HTTP 200");
$htmlReplace = $uploadResReplace['body'];

preg_match('/id="kpi-total-val"[^>]*>(\d+)</', $htmlReplace, $mTotalR);
preg_match('/id="kpi-ready-val"[^>]*>(\d+)</', $htmlReplace, $mReadyR);
preg_match('/id="kpi-skipped-val"[^>]*>(\d+)</', $htmlReplace, $mSkippedR);
preg_match('/id="kpi-warning-val"[^>]*>(\d+)</', $htmlReplace, $mWarningR);
preg_match('/id="kpi-error-val"[^>]*>(\d+)</', $htmlReplace, $mErrorR);

echo "  -> KPI Total:   " . ($mTotalR[1] ?? 'NOT FOUND') . " (Expected: 35)\n";
echo "  -> KPI Ready:   " . ($mReadyR[1] ?? 'NOT FOUND') . " (Expected: 35)\n";
echo "  -> KPI Skipped: " . ($mSkippedR[1] ?? 'NOT FOUND') . " (Expected: 0)\n";
echo "  -> KPI Warning: " . ($mWarningR[1] ?? 'NOT FOUND') . " (Expected: 10)\n";
echo "  -> KPI Error:   " . ($mErrorR[1] ?? 'NOT FOUND') . " (Expected: 0)\n";

assert(($mTotalR[1] ?? '') === '35', "KPI Total must be 35");
assert(($mReadyR[1] ?? '') === '35', "KPI Ready must be 35 under Replace");
assert(($mSkippedR[1] ?? '') === '0', "KPI Skipped must be 0 under Replace");
assert(($mWarningR[1] ?? '') === '10', "KPI Warning must be 10 under Replace");
assert(($mErrorR[1] ?? '') === '0', "KPI Error must be 0");

// Verify policy is preserved
assert(strpos($htmlReplace, 'name="conflict_mode" id="conflict_mode_input" value="replace"') !== false, "Hidden input must preserve 'replace'");
assert(strpos($htmlReplace, 'value="replace" selected') !== false, "Dropdown must have 'replace' selected");

// Verify badges & reasons
assert(strpos($htmlReplace, 'tt-badge-replace') !== false, "Must render tt-badge-replace");
assert(strpos($htmlReplace, '↻ REPLACE') !== false, "Must render ↻ REPLACE badge text");
assert(strpos($htmlReplace, 'Will be REPLACED') !== false, "Must render exact replace reason");

echo "  -> [STEP 4 REPLACE POLICY PREVIEW PASSED]\n\n";

// 5. Test Confirm Import with conflict_mode = 'skip'
echo "[STEP 5] Testing Confirm Import execution with conflict_mode = 'skip'...\n";

// Extract CSRF token from preview HTML
preg_match('/name="csrf_token" value="([a-f0-9]+)"/i', $htmlSkip, $mCsrfPreview);
$csrfPreview = $mCsrfPreview[1] ?? $csrfToken;

// Extract form row inputs from preview HTML
preg_match_all('/<input[^>]+name="rows\[(\d+)\]\[([^\]]+)\]"[^>]+value="([^"]*)"/i', $htmlSkip, $hiddenRows, PREG_SET_ORDER);
// Extract select values
preg_match_all('/<select[^>]+name="rows\[(\d+)\]\[([^\]]+)\]"[^>]*>(.*?)<\/select>/is', $htmlSkip, $selectRows, PREG_SET_ORDER);

$postConfirm = [
    'action' => 'confirm_import',
    'csrf_token' => $csrfPreview,
    'section_id' => '1',
    'conflict_mode' => 'skip',
    'default_status' => 'PUBLISHED'
];

foreach ($selectRows as $sr) {
    $idx = $sr[1];
    $field = $sr[2];
    $optionsHtml = $sr[3];
    if (preg_match('/<option[^>]+value="([^"]*)"[^>]*selected/i', $optionsHtml, $om)) {
        $val = $om[1];
    } elseif (preg_match('/<option[^>]+value="([^"]*)"/i', $optionsHtml, $om)) {
        $val = $om[1];
    } else {
        $val = '';
    }
    $postConfirm["rows[{$idx}][{$field}]"] = $val;
}

$confirmRes = httpRequest("{$baseUrl}/admin/timetable-upload.php", $postConfirm);
assert($confirmRes['status'] === 200, "Confirm import must return 200");
$confirmBody = $confirmRes['body'];

assert(strpos($confirmBody, 'Timetable Import Successful') !== false, "Must show import success alert");
assert(strpos($confirmBody, 'Successfully imported <strong>25</strong> timetable entries') !== false, "Must report 25 entries imported");
assert(strpos($confirmBody, '10 conflicting slots skipped') !== false, "Must report 10 conflicting slots skipped");

echo "  -> Import Response: Success confirmed! 25 entries imported, 10 conflicting slots skipped.\n";

// Verify DB entries for section 1
$cntCheck = $conn->query("SELECT COUNT(*) AS cnt FROM timetable WHERE section_id = 1")->fetch_assoc();
echo "  -> Total timetable entries in MySQL for CSE-A after import: {$cntCheck['cnt']} (Expected: 35)\n";
assert((int)$cntCheck['cnt'] === 35, "Total rows in DB should now be 35 (10 original + 25 new)");

// Clean up the 25 imported rows so initial 10 are preserved for user
echo "[CLEANUP] Cleaning up imported test rows, restoring original 10 entries...\n";
// The 10 original were MONDAY P1-P9 and TUESDAY P1
$conn->query("DELETE FROM timetable WHERE section_id = 1 AND (day != 'MONDAY' OR period_id > 9) AND NOT (day = 'TUESDAY' AND period_id = (SELECT period_id FROM periods WHERE period_number = 1))");
$finalCnt = $conn->query("SELECT COUNT(*) AS cnt FROM timetable WHERE section_id = 1")->fetch_assoc();
echo "  -> Final row count restored to: {$finalCnt['cnt']} (Original: 10)\n";

echo "\n=== ALL HTTP END-TO-END TESTS COMPLETED SUCCESSFULLY! ===\n";
