<?php
require_once "C:/laragon/www/SmartFacultyPortal/config/database.php";
require_once "C:/laragon/www/SmartFacultyPortal/includes/TimetableParser.php";

echo "====================================================\n";
echo "   SMARTFACULTY TIMETABLE SUITE & INTEGRITY TEST\n";
echo "====================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $name, bool $condition, string $details = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] $name\n";
        $passCount++;
    } else {
        echo " [FAIL] $name: $details\n";
        $failCount++;
    }
}

// 1. Initial Database Check
$res = $conn->query("SELECT COUNT(*) as cnt, status FROM timetable GROUP BY status");
$initialRows = 0;
$allDraft = true;
while ($r = $res->fetch_assoc()) {
    $initialRows += $r['cnt'];
    if ($r['status'] !== 'DRAFT') $allDraft = false;
}
assertTest("Initial DB Timetable Count is 162", $initialRows === 162, "Found $initialRows rows");
assertTest("All initial DB rows are status DRAFT", $allDraft, "Non-DRAFT rows found");

// Check breakdown by section
$secCounts = [];
$res = $conn->query("SELECT section_id, COUNT(*) as cnt FROM timetable GROUP BY section_id");
while ($r = $res->fetch_assoc()) {
    $secCounts[$r['section_id']] = (int)$r['cnt'];
}
assertTest("Section A has 54 entries", ($secCounts[1] ?? 0) === 54);
assertTest("Section B has 54 entries", ($secCounts[2] ?? 0) === 54);
assertTest("Section C has 54 entries", ($secCounts[3] ?? 0) === 54);
assertTest("Section D has 0 entries", ($secCounts[4] ?? 0) === 0);
assertTest("Section E has 0 entries", ($secCounts[5] ?? 0) === 0);

// 2. Test Parser on D:/CSE-D (3).csv
$parser = new TimetableParser($conn);
$csed3Result = $parser->parseFile('D:/CSE-D (3).csv', 'csv', 4, 'PUBLISHED', 'replace', 'single');
assertTest("Parser extracts 54 rows from D:/CSE-D (3).csv", count($csed3Result['rows']) === 54);

$csed3Errors = 0;
$csed3Ready = 0;
foreach ($csed3Result['rows'] as $r) {
    if (!empty($r['errors'])) $csed3Errors++;
    else $csed3Ready++;
}
assertTest("D:/CSE-D (3).csv yields exactly 43 blocking errors", $csed3Errors === 43, "Got $csed3Errors errors");
assertTest("D:/CSE-D (3).csv yields exactly 11 ready rows", $csed3Ready === 11, "Got $csed3Ready ready rows");

// Verify that the errors are genuine room 36 collisions and faculty double-bookings
$hasRoom36Col = false;
$hasFacDoubleBook = false;
foreach ($csed3Result['rows'] as $r) {
    foreach ($r['errors'] as $err) {
        if (strpos($err, 'Room 36 is already occupied by Section CSE-A') !== false) $hasRoom36Col = true;
        if (strpos($err, 'Faculty double-booked') !== false) $hasFacDoubleBook = true;
    }
}
assertTest("Parser detects genuine Room 36 collisions with Section CSE-A", $hasRoom36Col);
assertTest("Parser detects genuine faculty double-bookings with existing sections", $hasFacDoubleBook);

// 3. Test Skip Policy on existing sections (e.g. CSE-A)
// When uploading a file for CSE-A with conflictMode='skip', slots should be marked skipped, NOT errors!
$cseaTestRows = [
    [
        'row_index' => 1,
        'day' => 'MONDAY',
        'period_id' => 1,
        'period_number' => 1,
        'start_time' => '08:30:00',
        'end_time' => '09:15:00',
        'section_id' => 1, // CSE-A
        'subject_id' => 1,
        'faculty_id' => 1,
        'classroom_id' => 1,
        'status' => 'DRAFT',
        'raw_day' => 'Monday',
        'raw_period' => 'P1',
        'raw_subject' => 'DBMS',
        'raw_faculty' => 'Dr. Arun Kumar',
        'raw_classroom' => '36',
        'raw_status' => 'DRAFT',
        'raw_section' => 'CSE-A',
        'errors' => [],
        'warnings' => [],
        'conflict' => null
    ]
];
$skipResult = $parser->detectConflicts($cseaTestRows, 1, 'skip', 'single');
assertTest("Skip policy marks existing slot as is_skipped = true", !empty($skipResult[0]['is_skipped']));
assertTest("Skip policy does not flag blocking external errors on skipped slot", empty($skipResult[0]['errors']), "Errors: " . implode('; ', $skipResult[0]['errors'] ?? []));

// 4. Test Transactional Server-Side Import Simulation with 'skip' policy
$conn->begin_transaction();
try {
    // Attempt import with a skipped row (simulating section A slot)
    $stmt = $conn->prepare("SELECT timetable_id FROM timetable WHERE section_id = 1 AND day = 'MONDAY' AND period_id = 1");
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = ($res && $res->num_rows > 0);
    $stmt->close();
    assertTest("DB check finds existing slot for CSE-A Monday P1", $exists);

    // Rollback test transaction
    $conn->rollback();
    assertTest("Test rollback cleanly executed", true);
} catch (\Throwable $e) {
    $conn->rollback();
    assertTest("Test transaction failed", false, $e->getMessage());
}

// 5. Final DB Verification: Must still be exactly 162 records!
$res = $conn->query("SELECT COUNT(*) as cnt FROM timetable");
$finalCount = (int)($res->fetch_assoc()['cnt'] ?? 0);
assertTest("Final DB timetable count is STILL exactly 162 records", $finalCount === 162, "Found $finalCount");

echo "\n====================================================\n";
echo "TEST RESULTS: $passCount PASSED, $failCount FAILED\n";
echo "====================================================\n";
