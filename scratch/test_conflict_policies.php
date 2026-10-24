<?php
/**
 * Test Conflict Policy Behavior in Timetable Upload
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/TimetableParser.php';

echo "=== STARTING TIMETABLE CONFLICT POLICY TEST ===\n\n";

// 1. Check current DB state for Section 1 (CSE-A)
$res = $conn->query("
    SELECT t.timetable_id, t.day, p.period_number, s.subject_code, f.faculty_name, c.room_number, t.status 
    FROM timetable t
    JOIN periods p ON p.period_id = t.period_id
    JOIN subjects s ON s.subject_id = t.subject_id
    JOIN faculty f ON f.faculty_id = t.faculty_id
    JOIN classrooms c ON c.classroom_id = t.classroom_id
    WHERE t.section_id = 1
    ORDER BY t.day, p.period_number
");
$initialRows = $res->fetch_all(MYSQLI_ASSOC);
$initialCount = count($initialRows);
echo "[DB STATE] Section 1 (CSE-A) currently has {$initialCount} published timetable rows in MySQL.\n";
foreach ($initialRows as $row) {
    echo "  - {$row['day']} P{$row['period_number']}: {$row['subject_code']} | {$row['faculty_name']} | Room {$row['room_number']} ({$row['status']})\n";
}
echo "\n";

$parser = new TimetableParser($conn);
$testFile = __DIR__ . '/../timetable_template.csv';

// -------------------------------------------------------------
// TEST A: Parse with conflict_mode = 'skip'
// -------------------------------------------------------------
echo "[TEST A] Testing conflict_mode = 'skip'...\n";
$skipResult = $parser->parseFile($testFile, 'csv', 1, 'PUBLISHED', 'skip');

if (!$skipResult['success']) {
    die("FAIL: Parse failed for skip mode: " . $skipResult['message'] . "\n");
}

$rowsA = $skipResult['rows'];
$totalA = count($rowsA);
$skippedA = 0;
$errorsA = 0;
$warningsA = 0;
$readyA = 0;

foreach ($rowsA as $r) {
    $hasError = !empty($r['errors']);
    $hasExisting = !empty($r['conflict']) && in_array($r['conflict']['type'] ?? '', ['existing_entry_skip', 'existing_entry_replace'], true);
    $hasWarning = !empty($r['warnings']) || ($r['subject_id'] === 0 || $r['faculty_id'] === 0 || $r['classroom_id'] === 0);

    if ($hasError) {
        $errorsA++;
    } elseif ($hasExisting) {
        $skippedA++;
    } else {
        if ($hasWarning) $warningsA++;
        else $readyA++;
    }
}

echo "  -> Total Extracted: {$totalA} (Expected: 35)\n";
echo "  -> Ready to Import: {$readyA} (Expected: 25)\n";
echo "  -> Skipped Slots:   {$skippedA} (Expected: 10)\n";
echo "  -> Warnings/Notice: {$warningsA} (Expected: 0)\n";
echo "  -> Blocking Errors: {$errorsA} (Expected: 0)\n";

assert($totalA === 35, "Total extracted rows should be 35");
assert($skippedA === 10, "Skipped count should be exactly 10");
assert($readyA === 25, "Ready count should be exactly 25");
assert($errorsA === 0, "Blocking error count should be 0");

// Check reason details for skipped rows
$sampleSkipped = null;
foreach ($rowsA as $r) {
    if (!empty($r['is_skipped'])) {
        $sampleSkipped = $r;
        break;
    }
}
echo "  -> Sample Skipped Row Message: " . ($sampleSkipped['conflict']['message'] ?? 'NONE') . "\n";
assert(!empty($sampleSkipped['conflict']['message']), "Skipped row must have descriptive message");
assert(strpos($sampleSkipped['conflict']['message'], 'Slot will be SKIPPED') !== false, "Message must mention slot is skipped");
echo "  -> [TEST A PASSED]\n\n";

// -------------------------------------------------------------
// TEST B: Parse with conflict_mode = 'replace'
// -------------------------------------------------------------
echo "[TEST B] Testing conflict_mode = 'replace'...\n";
$replaceResult = $parser->parseFile($testFile, 'csv', 1, 'PUBLISHED', 'replace');

if (!$replaceResult['success']) {
    die("FAIL: Parse failed for replace mode: " . $replaceResult['message'] . "\n");
}

$rowsB = $replaceResult['rows'];
$totalB = count($rowsB);
$skippedB = 0;
$errorsB = 0;
$warningsB = 0;
$readyB = 0;

foreach ($rowsB as $r) {
    $hasError = !empty($r['errors']);
    $hasExisting = !empty($r['conflict']) && in_array($r['conflict']['type'] ?? '', ['existing_entry_skip', 'existing_entry_replace'], true);
    $hasWarning = !empty($r['warnings']) || ($r['subject_id'] === 0 || $r['faculty_id'] === 0 || $r['classroom_id'] === 0);

    if ($hasError) {
        $errorsB++;
    } elseif ($hasExisting) {
        $warningsB++; // Replace notice
        if (!$hasWarning) $readyB++;
    } else {
        if ($hasWarning) $warningsB++;
        else $readyB++;
    }
}

echo "  -> Total Extracted: {$totalB} (Expected: 35)\n";
echo "  -> Ready to Import: {$readyB} (Expected: 35)\n";
echo "  -> Skipped Slots:   {$skippedB} (Expected: 0)\n";
echo "  -> Warnings/Notice: {$warningsB} (Expected: 10)\n";
echo "  -> Blocking Errors: {$errorsB} (Expected: 0)\n";

assert($totalB === 35, "Total extracted rows should be 35");
assert($skippedB === 0, "Skipped count should be 0");
assert($readyB === 35, "Ready count should be 35 in replace mode");
assert($warningsB === 10, "Warnings/Notice count should be 10 for replace notices");
assert($errorsB === 0, "Blocking error count should be 0");

$sampleReplaced = null;
foreach ($rowsB as $r) {
    if (!empty($r['conflict']) && $r['conflict']['type'] === 'existing_entry_replace') {
        $sampleReplaced = $r;
        break;
    }
}
echo "  -> Sample Replace Row Message: " . ($sampleReplaced['conflict']['message'] ?? 'NONE') . "\n";
assert(strpos($sampleReplaced['conflict']['message'], 'Will replace existing') !== false, "Message must state will replace");
echo "  -> [TEST B PASSED]\n\n";

// -------------------------------------------------------------
// TEST C: Verify DB was NOT touched during preview/parse
// -------------------------------------------------------------
echo "[TEST C] Verifying Live MySQL DB was NOT touched during preview/parse...\n";
$afterCheck = $conn->query("SELECT COUNT(*) AS cnt FROM timetable WHERE section_id = 1")->fetch_assoc();
echo "  -> Row count after parse calls: {$afterCheck['cnt']} (Initial: {$initialCount})\n";
assert((int)$afterCheck['cnt'] === $initialCount, "Preview must NEVER modify database rows");
echo "  -> [TEST C PASSED]\n\n";

echo "=== ALL UNIT / PARSER CONFLICT POLICY TESTS PASSED! ===\n";
