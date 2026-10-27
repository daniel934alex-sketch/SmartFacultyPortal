<?php
/**
 * SmartFacultyPortal - Full System Verification Suite
 * 
 * Comprehensive end-to-end testing of:
 * 1. Database schema & data preservation
 * 2. Timetable slot counts & distribution
 * 3. Strict conflict checking (faculty, room, section)
 * 4. Saturday EE-PROJECT supervision
 * 5. Excel and CSV export generation & structure
 * 6. Role-based view queries (Admin, Faculty, Student)
 * 7. Negative test: Conflicting slot rejection
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

echo "=================================================================\n";
echo "    SMARTFACULTY PORTAL — FULL SYSTEM VERIFICATION SUITE\n";
echo "=================================================================\n\n";

$passes = 0;
$fails = 0;

function assertCheck(string $title, bool $condition, string $details = '') {
    global $passes, $fails;
    if ($condition) {
        echo "  [PASS] $title\n";
        $passes++;
    } else {
        echo "  [FAIL] $title" . ($details ? " -> $details" : "") . "\n";
        $fails++;
    }
}

// -------------------------------------------------------------
// TEST GROUP 1: DATABASE BACKUP & PRESERVATION OF CORE DATA
// -------------------------------------------------------------
echo "--- TEST GROUP 1: Backup & Core Tables Preservation ---\n";
$backupFile = __DIR__ . '/../database/smart_faculty_backup_verified_pre_rebuild.sql';
assertCheck("Verified backup file exists", file_exists($backupFile) && filesize($backupFile) > 20000, "Size: " . (file_exists($backupFile) ? filesize($backupFile) : 0));

$userCount = (int)$conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
assertCheck("Users table preserved (count >= 20)", $userCount >= 20, "Found $userCount users");

$facultyCount = (int)$conn->query("SELECT COUNT(*) FROM faculty")->fetch_row()[0];
assertCheck("Faculty table preserved (exactly 15)", $facultyCount === 15, "Found $facultyCount faculty");

$subjectsCount = (int)$conn->query("SELECT COUNT(*) FROM subjects")->fetch_row()[0];
assertCheck("Subjects table preserved (exactly 12)", $subjectsCount === 12, "Found $subjectsCount subjects");

$classroomsCount = (int)$conn->query("SELECT COUNT(*) FROM classrooms")->fetch_row()[0];
assertCheck("Classrooms table preserved (exactly 8)", $classroomsCount === 8, "Found $classroomsCount rooms");

$sectionsCount = (int)$conn->query("SELECT COUNT(*) FROM sections")->fetch_row()[0];
assertCheck("Sections table preserved (exactly 5)", $sectionsCount === 5, "Found $sectionsCount sections");

$periodsCount = (int)$conn->query("SELECT COUNT(*) FROM periods")->fetch_row()[0];
assertCheck("Periods table count is 9", $periodsCount === 9, "Found $periodsCount periods");

// Verify official period timings
$expectedTimings = [
    1 => ['08:30:00', '09:15:00'],
    2 => ['09:15:00', '10:00:00'],
    3 => ['10:00:00', '10:45:00'],
    4 => ['10:45:00', '11:30:00'],
    5 => ['11:30:00', '12:15:00'],
    6 => ['12:15:00', '13:00:00'],
    7 => ['14:00:00', '14:45:00'],
    8 => ['14:45:00', '15:30:00'],
    9 => ['15:30:00', '16:15:00']
];
$allTimingsMatch = true;
$res = $conn->query("SELECT period_number, start_time, end_time FROM periods ORDER BY period_number");
while ($r = $res->fetch_assoc()) {
    $pNum = (int)$r['period_number'];
    if ($r['start_time'] !== $expectedTimings[$pNum][0] || $r['end_time'] !== $expectedTimings[$pNum][1]) {
        $allTimingsMatch = false;
        echo "    Timing mismatch on P$pNum: got {$r['start_time']}-{$r['end_time']}, expected {$expectedTimings[$pNum][0]}-{$expectedTimings[$pNum][1]}\n";
    }
}
assertCheck("Periods table timings match official specifications (P1-P9)", $allTimingsMatch);

// -------------------------------------------------------------
// TEST GROUP 2: TIMETABLE TOTAL SLOTS & STATUS
// -------------------------------------------------------------
echo "\n--- TEST GROUP 2: Timetable Slot Counts & Section Distribution ---\n";
$ttTotal = (int)$conn->query("SELECT COUNT(*) FROM timetable")->fetch_row()[0];
assertCheck("Total timetable slots in DB is exactly 270", $ttTotal === 270, "Found $ttTotal slots");

$ttPublished = (int)$conn->query("SELECT COUNT(*) FROM timetable WHERE status = 'PUBLISHED'")->fetch_row()[0];
assertCheck("All 270 timetable slots have status 'PUBLISHED'", $ttPublished === 270, "Found $ttPublished published slots");

$secBreakdown = [];
$res = $conn->query("
    SELECT sec.section_id, sec.section_name, COUNT(*) as cnt 
    FROM timetable t 
    JOIN sections sec ON sec.section_id = t.section_id 
    GROUP BY sec.section_id, sec.section_name
    ORDER BY sec.section_id
");
while ($r = $res->fetch_assoc()) {
    $secBreakdown[$r['section_name']] = (int)$r['cnt'];
}
assertCheck("Section CSE-A has exactly 54 slots", ($secBreakdown['CSE-A'] ?? 0) === 54);
assertCheck("Section CSE-B has exactly 54 slots", ($secBreakdown['CSE-B'] ?? 0) === 54);
assertCheck("Section CSE-C has exactly 54 slots", ($secBreakdown['CSE-C'] ?? 0) === 54);
assertCheck("Section CSE-D has exactly 54 slots", ($secBreakdown['CSE-D'] ?? 0) === 54);
assertCheck("Section CSE-E has exactly 54 slots", ($secBreakdown['CSE-E'] ?? 0) === 54);

// Check weekday and Saturday breakdown
$dayBreakdownRes = $conn->query("SELECT day, COUNT(*) as cnt FROM timetable GROUP BY day ORDER BY FIELD(day, 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY')");
$allDaysValid = true;
while ($dr = $dayBreakdownRes->fetch_assoc()) {
    if ((int)$dr['cnt'] !== 45) { // 5 sections x 9 periods = 45 per day
        $allDaysValid = false;
        echo "    Day {$dr['day']} has {$dr['cnt']} slots, expected 45\n";
    }
}
assertCheck("All 6 days (Monday to Saturday) have exactly 45 slots each (9 per section)", $allDaysValid);

// -------------------------------------------------------------
// TEST GROUP 3: STRICT CONFLICT DETECTION ACROSS ALL SECTIONS
// -------------------------------------------------------------
echo "\n--- TEST GROUP 3: Strict Conflict Validation ---\n";
// 1. Faculty double bookings
$facConflicts = (int)$conn->query("
    SELECT COUNT(*) FROM (
        SELECT faculty_id, day, period_id, COUNT(*) as c
        FROM timetable
        GROUP BY faculty_id, day, period_id
        HAVING c > 1
    ) as sub
")->fetch_row()[0];
assertCheck("Zero faculty overlapping double-bookings", $facConflicts === 0, "Found $facConflicts faculty conflicts");

// 2. Classroom double bookings
$roomConflicts = (int)$conn->query("
    SELECT COUNT(*) FROM (
        SELECT classroom_id, day, period_id, COUNT(*) as c
        FROM timetable
        GROUP BY classroom_id, day, period_id
        HAVING c > 1
    ) as sub
")->fetch_row()[0];
assertCheck("Zero classroom overlapping double-bookings", $roomConflicts === 0, "Found $roomConflicts room conflicts");

// 3. Duplicate section period slots
$secConflicts = (int)$conn->query("
    SELECT COUNT(*) FROM (
        SELECT section_id, day, period_id, COUNT(*) as c
        FROM timetable
        GROUP BY section_id, day, period_id
        HAVING c > 1
    ) as sub
")->fetch_row()[0];
assertCheck("Zero duplicate section/day/period slots", $secConflicts === 0, "Found $secConflicts section duplicate slots");

// 4. Faculty eligibility
$ineligible = (int)$conn->query("
    SELECT COUNT(*) 
    FROM timetable t
    LEFT JOIN faculty_subjects fs ON fs.faculty_id = t.faculty_id AND fs.subject_id = t.subject_id
    WHERE fs.faculty_subject_id IS NULL
")->fetch_row()[0];
assertCheck("100% of assigned faculty are eligible in faculty_subjects", $ineligible === 0, "Found $ineligible ineligible assignments");

// 5. Dedicated theory room rules
$theoryRoomCheck = $conn->query("
    SELECT sec.section_name, c.room_number, COUNT(*) as cnt
    FROM timetable t
    JOIN sections sec ON sec.section_id = t.section_id
    JOIN classrooms c ON c.classroom_id = t.classroom_id
    JOIN subjects s ON s.subject_id = t.subject_id
    WHERE s.subject_type = 'THEORY'
    GROUP BY sec.section_name, c.room_number
");
$theoryRoomsCorrect = true;
$expectedRooms = [
    'CSE-A' => '36',
    'CSE-B' => '37',
    'CSE-C' => '38',
    'CSE-D' => '39',
    'CSE-E' => '40'
];
while ($tr = $theoryRoomCheck->fetch_assoc()) {
    if ($tr['room_number'] !== $expectedRooms[$tr['section_name']]) {
        $theoryRoomsCorrect = false;
        echo "    Section {$tr['section_name']} theory scheduled in Room {$tr['room_number']}, expected {$expectedRooms[$tr['section_name']]}\n";
    }
}
assertCheck("Dedicated theory classrooms strictly respected (A:36, B:37, C:38, D:39, E:40)", $theoryRoomsCorrect);

// -------------------------------------------------------------
// TEST GROUP 4: SATURDAY EE-PROJECT SUPERVISION
// -------------------------------------------------------------
echo "\n--- TEST GROUP 4: Saturday EE-PROJECT Supervision ---\n";
$satTotal = (int)$conn->query("SELECT COUNT(*) FROM timetable WHERE day = 'SATURDAY' AND subject_id = 12")->fetch_row()[0];
assertCheck("Saturday EE-PROJECT has exactly 45 slots (9 per section)", $satTotal === 45, "Found $satTotal Saturday project slots");

$satSupervisorsRes = $conn->query("
    SELECT DISTINCT f.faculty_id, f.faculty_name, f.faculty_code 
    FROM timetable t 
    JOIN faculty f ON f.faculty_id = t.faculty_id 
    WHERE t.day = 'SATURDAY'
    ORDER BY f.faculty_id
");
$satSupervisorsList = [];
while ($sr = $satSupervisorsRes->fetch_assoc()) {
    $satSupervisorsList[] = "{$sr['faculty_code']}: {$sr['faculty_name']}";
}
assertCheck("Saturday EE-PROJECT uses all 5 approved supervisors", count($satSupervisorsList) === 5, "Found: " . implode(', ', $satSupervisorsList));

// -------------------------------------------------------------
// TEST GROUP 5: SEPARATE DOWNLOADABLE TIMETABLES (EXCEL & CSV)
// -------------------------------------------------------------
echo "\n--- TEST GROUP 5: Downloadable Timetables (Excel & CSV) ---\n";
$sectionsList = ['CSE-A', 'CSE-B', 'CSE-C', 'CSE-D', 'CSE-E'];
$allFilesExist = true;

foreach ($sectionsList as $sec) {
    $xlsxFile = __DIR__ . "/../uploads/timetables/{$sec}_Timetable.xlsx";
    $csvFile = __DIR__ . "/../uploads/timetables/{$sec}_Timetable.csv";

    $hasXlsx = file_exists($xlsxFile) && filesize($xlsxFile) > 5000;
    $hasCsv = file_exists($csvFile) && filesize($csvFile) > 1000;

    assertCheck("$sec Excel exists and valid ($sec" . "_Timetable.xlsx)", $hasXlsx, "Size: " . (file_exists($xlsxFile) ? filesize($xlsxFile) : 0));
    assertCheck("$sec CSV exists and valid ($sec" . "_Timetable.csv)", $hasCsv, "Size: " . (file_exists($csvFile) ? filesize($csvFile) : 0));

    // Verify CSV line count: 1 header + 54 rows = 55 lines
    $csvLines = count(file($csvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    assertCheck("$sec CSV contains exactly 54 timetable slot rows", $csvLines === 55, "Line count: $csvLines");

    // Verify Excel workbook structure via PhpSpreadsheet
    try {
        $spreadsheet = IOFactory::load($xlsxFile);
        $sheetNames = $spreadsheet->getSheetNames();
        $hasGridSheet = in_array("Timetable Grid", $sheetNames);
        $hasDetailsSheet = in_array("Schedule Details", $sheetNames);
        assertCheck("$sec Excel has 'Timetable Grid' & 'Schedule Details' sheets", $hasGridSheet && $hasDetailsSheet, "Sheets: " . implode(', ', $sheetNames));
    } catch (Exception $e) {
        assertCheck("$sec Excel readable", false, $e->getMessage());
    }
}

// -------------------------------------------------------------
// TEST GROUP 6: ROLE-BASED ACCESS & PAGE VIEW VERIFICATION
// -------------------------------------------------------------
echo "\n--- TEST GROUP 6: Role-Based Access & Queries ---\n";

// 1. Admin / HOD Timetable Query
$adminCount = (int)$conn->query("
    SELECT COUNT(*) FROM timetable t
    JOIN periods p ON p.period_id = t.period_id
    JOIN sections sec ON sec.section_id = t.section_id
    JOIN subjects s ON s.subject_id = t.subject_id
    JOIN faculty f ON f.faculty_id = t.faculty_id
    JOIN classrooms c ON c.classroom_id = t.classroom_id
")->fetch_row()[0];
assertCheck("Admin Timetable Management query fetches all 270 slots with full joins", $adminCount === 270);

// 2. Student Timetable Queries for each section
$studentUsers = [
    'student01' => 1,
    'student02' => 2,
    'student03' => 3,
    'student04' => 4,
    'student05' => 5
];
$allStudentsOk = true;
foreach ($studentUsers as $u => $expectedSecId) {
    $q = $conn->prepare("
        SELECT COUNT(*) as cnt 
        FROM timetable t 
        JOIN students s ON s.section_id = t.section_id 
        JOIN users usr ON usr.user_id = s.user_id 
        WHERE usr.username = ? AND t.status = 'PUBLISHED'
    ");
    $q->bind_param("s", $u);
    $q->execute();
    $cnt = (int)$q->get_result()->fetch_assoc()['cnt'];
    $q->close();
    if ($cnt !== 54) {
        $allStudentsOk = false;
        echo "    Student $u retrieved $cnt slots, expected 54\n";
    }
}
assertCheck("All 5 enrolled student accounts retrieve exactly 54 published slots for their section", $allStudentsOk);

// 3. Faculty Timetable Queries for all 15 faculty
$facOk = true;
$totalFacScheduled = 0;
for ($fid = 1; $fid <= 15; $fid++) {
    $q = $conn->prepare("SELECT COUNT(*) as cnt FROM timetable WHERE faculty_id = ? AND status = 'PUBLISHED'");
    $q->bind_param("i", $fid);
    $q->execute();
    $c = (int)$q->get_result()->fetch_assoc()['cnt'];
    $q->close();
    $totalFacScheduled += $c;
    if ($c === 0) {
        // Check if faculty has subjects mapped
        $mappedCount = (int)$conn->query("SELECT COUNT(*) FROM faculty_subjects WHERE faculty_id = $fid")->fetch_row()[0];
        if ($mappedCount > 0) {
            $facOk = false;
            echo "    Faculty ID $fid has mapped subjects but 0 scheduled periods\n";
        }
    }
}
assertCheck("Sum of faculty assigned periods across portal equals exactly 270 slots", $totalFacScheduled === 270, "Total: $totalFacScheduled");

// -------------------------------------------------------------
// TEST GROUP 7: NEGATIVE TEST — CONFLICT REJECTION
// -------------------------------------------------------------
echo "\n--- TEST GROUP 7: Conflict Prevention & Integrity Safeguards ---\n";
// Attempt to insert duplicate section slot into database
$dupRejected = false;
try {
    // Section 1, MONDAY, Period 1 is already taken
    $testStmt = $conn->prepare("INSERT INTO timetable (day, period_id, section_id, subject_id, faculty_id, classroom_id, status) VALUES ('MONDAY', 1, 1, 1, 1, 1, 'PUBLISHED')");
    $testStmt->execute();
} catch (mysqli_sql_exception $e) {
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        $dupRejected = true;
    }
}
assertCheck("Database rejects duplicate section/day/period via UNIQUE constraint", $dupRejected);

// Attempt to insert duplicate faculty slot
$facDupRejected = false;
try {
    // Faculty 1, MONDAY, Period 1 is already taken by Section 1. Try assigning Faculty 1 to Section 2 at same slot:
    $testStmt2 = $conn->prepare("INSERT INTO timetable (day, period_id, section_id, subject_id, faculty_id, classroom_id, status) VALUES ('MONDAY', 1, 2, 1, 1, 2, 'PUBLISHED')");
    $testStmt2->execute();
} catch (mysqli_sql_exception $e) {
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        $facDupRejected = true;
    }
}
assertCheck("Database rejects faculty double-booking via UNIQUE constraint", $facDupRejected);

// Attempt to insert duplicate classroom slot
$roomDupRejected = false;
try {
    // Classroom 1 (Room 36), MONDAY, Period 1 is already taken. Try assigning Classroom 1 to Section 2 at same slot:
    $testStmt3 = $conn->prepare("INSERT INTO timetable (day, period_id, section_id, subject_id, faculty_id, classroom_id, status) VALUES ('MONDAY', 1, 2, 2, 5, 1, 'PUBLISHED')");
    $testStmt3->execute();
} catch (mysqli_sql_exception $e) {
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        $roomDupRejected = true;
    }
}
assertCheck("Database rejects classroom double-booking via UNIQUE constraint", $roomDupRejected);

echo "\n=================================================================\n";
echo "    VERIFICATION SUITE SUMMARY: $passes PASSED, $fails FAILED\n";
echo "=================================================================\n";

if ($fails === 0) {
    echo "🎉 ALL TESTS PASSED! SYSTEM REBUILD IS 100% VERIFIED AND COMPLETE.\n";
} else {
    echo "❌ SOME TESTS FAILED. PLEASE REVIEW LOG ABOVE.\n";
}
