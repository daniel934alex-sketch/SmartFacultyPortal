<?php
require_once __DIR__ . '/../config/database.php';

echo "=======================================================\n";
echo "SUBJECTS & CURRICULUM CONSTRAINTS\n";
echo "=======================================================\n";
$subjects = [];
$res = $conn->query("SELECT * FROM subjects ORDER BY subject_id");
$totalRequiredWeekly = 0;
while ($r = $res->fetch_assoc()) {
    $subjects[$r['subject_id']] = $r;
    $totalRequiredWeekly += (int)$r['weekly_periods'];
    printf("ID: %-2d | %-12s | %-8s | weekly: %-2d | cont: %-2d | %s\n",
        $r['subject_id'], $r['subject_code'], $r['subject_type'], $r['weekly_periods'], $r['continuous_periods'], $r['subject_name']
    );
}
echo "Total defined weekly_periods across all subjects: {$totalRequiredWeekly} (Available slots in 6 days x 9 periods = 54)\n\n";

echo "=======================================================\n";
echo "FACULTY ELIGIBILITY (faculty_subjects mapping)\n";
echo "=======================================================\n";
$facSubjects = [];
$res = $conn->query("
    SELECT fs.subject_id, s.subject_code, fs.faculty_id, f.faculty_name, f.faculty_code
    FROM faculty_subjects fs
    JOIN subjects s ON s.subject_id = fs.subject_id
    JOIN faculty f ON f.faculty_id = fs.faculty_id
    ORDER BY fs.subject_id, fs.faculty_id
");
while ($r = $res->fetch_assoc()) {
    $facSubjects[$r['subject_id']][] = $r;
}
foreach ($subjects as $sId => $s) {
    echo "Subject: [{$s['subject_code']}] {$s['subject_name']} ({$s['subject_type']})\n";
    if (isset($facSubjects[$sId])) {
        foreach ($facSubjects[$sId] as $f) {
            echo "   -> Faculty ID {$f['faculty_id']}: {$f['faculty_name']} ({$f['faculty_code']})\n";
        }
    } else {
        echo "   -> No faculty assigned!\n";
    }
}

echo "\n=======================================================\n";
echo "CURRENT CSE-A TIMETABLE SLOTS & MISSING SLOTS\n";
echo "=======================================================\n";
$days = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];
$existingSlots = [];
$subjectPeriodCounts = [];
$res = $conn->query("
    SELECT t.timetable_id, t.day, p.period_number, t.subject_id, s.subject_code, s.subject_type, t.faculty_id, f.faculty_name, t.classroom_id, c.room_number, c.room_type, t.status
    FROM timetable t
    JOIN periods p ON p.period_id = t.period_id
    JOIN subjects s ON s.subject_id = t.subject_id
    JOIN faculty f ON f.faculty_id = t.faculty_id
    JOIN classrooms c ON c.classroom_id = t.classroom_id
    WHERE t.section_id = 1
");
while ($r = $res->fetch_assoc()) {
    $existingSlots[$r['day']][(int)$r['period_number']] = $r;
    $sId = (int)$r['subject_id'];
    $subjectPeriodCounts[$sId] = ($subjectPeriodCounts[$sId] ?? 0) + 1;
}

echo "Current assigned periods count vs required weekly_periods for CSE-A:\n";
foreach ($subjects as $sId => $s) {
    $assigned = $subjectPeriodCounts[$sId] ?? 0;
    $required = (int)$s['weekly_periods'];
    $diff = $required - $assigned;
    printf("  %-12s (%-7s): Assigned: %-2d / Required: %-2d (Remaining to schedule: %2d)\n",
        $s['subject_code'], $s['subject_type'], $assigned, $required, $diff
    );
}

echo "\nTimetable Grid for CSE-A (6 Days x 9 Periods = 54 Slots):\n";
echo "Day        | P1        | P2        | P3        | P4        | P5        | P6        | P7        | P8        | P9        \n";
echo "-----------+-----------+-----------+-----------+-----------+-----------+-----------+-----------+-----------+-----------\n";

$missingSlots = [];
foreach ($days as $day) {
    printf("%-10s | ", $day);
    for ($p = 1; $p <= 9; $p++) {
        if (isset($existingSlots[$day][$p])) {
            $code = $existingSlots[$day][$p]['subject_code'];
            printf("%-9s | ", substr($code, 0, 9));
        } else {
            printf("%-9s | ", "-- EMPTY --");
            $missingSlots[] = ['day' => $day, 'period' => $p];
        }
    }
    echo "\n";
}

echo "\nTotal existing slots: " . (54 - count($missingSlots)) . " / 54\n";
echo "Total missing slots:  " . count($missingSlots) . " / 54\n";
echo "\nDetailed List of Missing Slots for CSE-A:\n";
foreach ($missingSlots as $m) {
    echo "  - {$m['day']} Period {$m['period']} (P{$m['period']})\n";
}

echo "\n=======================================================\n";
echo "CLASSROOMS AVAILABLE\n";
echo "=======================================================\n";
$res = $conn->query("SELECT * FROM classrooms ORDER BY classroom_id");
while ($r = $res->fetch_assoc()) {
    echo "Room ID {$r['classroom_id']}: {$r['room_number']} ({$r['room_type']}) - Status: {$r['status']}\n";
}

echo "\n=======================================================\n";
echo "FACULTY STATUS\n";
echo "=======================================================\n";
$res = $conn->query("SELECT * FROM faculty ORDER BY faculty_id");
while ($r = $res->fetch_assoc()) {
    echo "Faculty ID {$r['faculty_id']}: {$r['faculty_name']} ({$r['faculty_code']}) - Status: " . ($r['status'] ?? 'N/A') . "\n";
}
