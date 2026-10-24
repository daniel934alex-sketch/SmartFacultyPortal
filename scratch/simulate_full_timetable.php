<?php
require_once __DIR__ . '/../config/database.php';

// Proposed 19 slots to fill the gaps
$proposedSlots = [
    // TUESDAY P7-P9: OS-LAB (3 periods continuous)
    ['day' => 'TUESDAY',   'period' => 7, 'subject_code' => 'OS-LAB',     'faculty_name' => 'Prof. Naveen Kumar', 'room_number' => 'LAB-1', 'status' => 'PUBLISHED'],
    ['day' => 'TUESDAY',   'period' => 8, 'subject_code' => 'OS-LAB',     'faculty_name' => 'Prof. Naveen Kumar', 'room_number' => 'LAB-1', 'status' => 'PUBLISHED'],
    ['day' => 'TUESDAY',   'period' => 9, 'subject_code' => 'OS-LAB',     'faculty_name' => 'Prof. Naveen Kumar', 'room_number' => 'LAB-1', 'status' => 'PUBLISHED'],

    // WEDNESDAY P7-P9: Theory classes
    ['day' => 'WEDNESDAY', 'period' => 7, 'subject_code' => 'KVD',        'faculty_name' => 'Prof. Naveen Kumar', 'room_number' => '36',    'status' => 'PUBLISHED'],
    ['day' => 'WEDNESDAY', 'period' => 8, 'subject_code' => 'ADSA',       'faculty_name' => 'Prof. Priya Sharma', 'room_number' => '36',    'status' => 'PUBLISHED'],
    ['day' => 'WEDNESDAY', 'period' => 9, 'subject_code' => 'ENG',        'faculty_name' => 'Prof. Meena R',      'room_number' => '36',    'status' => 'PUBLISHED'],

    // THURSDAY P6-P9: Theory classes
    ['day' => 'THURSDAY',  'period' => 6, 'subject_code' => 'DBMS',       'faculty_name' => 'Dr. Arun Kumar',     'room_number' => '36',    'status' => 'PUBLISHED'],
    ['day' => 'THURSDAY',  'period' => 7, 'subject_code' => 'KVD',        'faculty_name' => 'Prof. Naveen Kumar', 'room_number' => '36',    'status' => 'PUBLISHED'],
    ['day' => 'THURSDAY',  'period' => 8, 'subject_code' => 'MATHS',      'faculty_name' => 'Prof. Divya S',      'room_number' => '36',    'status' => 'PUBLISHED'],
    ['day' => 'THURSDAY',  'period' => 9, 'subject_code' => 'QUANTS',     'faculty_name' => 'Prof. Rahul P',      'room_number' => '36',    'status' => 'PUBLISHED'],

    // FRIDAY P7-P9: Theory classes
    ['day' => 'FRIDAY',    'period' => 7, 'subject_code' => 'OS',         'faculty_name' => 'Prof. Karthik Raj',  'room_number' => '36',    'status' => 'PUBLISHED'],
    ['day' => 'FRIDAY',    'period' => 8, 'subject_code' => 'ADSA',       'faculty_name' => 'Prof. Priya Sharma', 'room_number' => '36',    'status' => 'PUBLISHED'],
    ['day' => 'FRIDAY',    'period' => 9, 'subject_code' => 'MATHS',      'faculty_name' => 'Prof. Divya S',      'room_number' => '36',    'status' => 'PUBLISHED'],

    // SATURDAY P4-P9: EE-PROJECT (Completing Saturday as reserved project day)
    ['day' => 'SATURDAY',  'period' => 4, 'subject_code' => 'EE-PROJECT', 'faculty_name' => 'Prof. Meena R',      'room_number' => 'LAB-1', 'status' => 'PUBLISHED'],
    ['day' => 'SATURDAY',  'period' => 5, 'subject_code' => 'EE-PROJECT', 'faculty_name' => 'Prof. Meena R',      'room_number' => 'LAB-1', 'status' => 'PUBLISHED'],
    ['day' => 'SATURDAY',  'period' => 6, 'subject_code' => 'EE-PROJECT', 'faculty_name' => 'Prof. Meena R',      'room_number' => 'LAB-1', 'status' => 'PUBLISHED'],
    ['day' => 'SATURDAY',  'period' => 7, 'subject_code' => 'EE-PROJECT', 'faculty_name' => 'Prof. Meena R',      'room_number' => 'LAB-1', 'status' => 'PUBLISHED'],
    ['day' => 'SATURDAY',  'period' => 8, 'subject_code' => 'EE-PROJECT', 'faculty_name' => 'Prof. Meena R',      'room_number' => 'LAB-1', 'status' => 'PUBLISHED'],
    ['day' => 'SATURDAY',  'period' => 9, 'subject_code' => 'EE-PROJECT', 'faculty_name' => 'Prof. Meena R',      'room_number' => 'LAB-1', 'status' => 'PUBLISHED'],
];

echo "=== SIMULATING PROPOSED FULL TIMETABLE FOR CSE-A ===\n\n";

// Map lookup tables
$subMap = [];
$res = $conn->query("SELECT subject_id, subject_code, subject_name, subject_type, weekly_periods, continuous_periods FROM subjects");
while ($r = $res->fetch_assoc()) $subMap[$r['subject_code']] = $r;

$facMap = [];
$res = $conn->query("SELECT faculty_id, faculty_name, faculty_code FROM faculty");
while ($r = $res->fetch_assoc()) $facMap[$r['faculty_name']] = $r;

$roomMap = [];
$res = $conn->query("SELECT classroom_id, room_number, room_type FROM classrooms");
while ($r = $res->fetch_assoc()) $roomMap[$r['room_number']] = $r;

// Load existing 35 slots for Section 1
$allSlots = [];
$res = $conn->query("
    SELECT t.day, p.period_number, s.subject_code, s.subject_name, s.subject_type, f.faculty_name, c.room_number, t.status, t.faculty_id, t.classroom_id
    FROM timetable t
    JOIN periods p ON p.period_id = t.period_id
    JOIN subjects s ON s.subject_id = t.subject_id
    JOIN faculty f ON f.faculty_id = t.faculty_id
    JOIN classrooms c ON c.classroom_id = t.classroom_id
    WHERE t.section_id = 1
");
while ($r = $res->fetch_assoc()) {
    $allSlots[$r['day']][(int)$r['period_number']] = [
        'source' => 'EXISTING',
        'subject_code' => $r['subject_code'],
        'faculty_name' => $r['faculty_name'],
        'room_number' => $r['room_number'],
        'status' => $r['status'],
        'faculty_id' => (int)$r['faculty_id'],
        'classroom_id' => (int)$r['classroom_id']
    ];
}

// Add the 19 proposed slots
foreach ($proposedSlots as $p) {
    $d = $p['day'];
    $num = $p['period'];
    if (isset($allSlots[$d][$num])) {
        die("ERROR: Proposed slot collision on {$d} P{$num} with existing slot!\n");
    }
    $fId = $facMap[$p['faculty_name']]['faculty_id'] ?? 0;
    $rId = $roomMap[$p['room_number']]['classroom_id'] ?? 0;
    $allSlots[$d][$num] = [
        'source' => 'PROPOSED',
        'subject_code' => $p['subject_code'],
        'faculty_name' => $p['faculty_name'],
        'room_number' => $p['room_number'],
        'status' => $p['status'],
        'faculty_id' => $fId,
        'classroom_id' => $rId
    ];
}

// Load other sections' slots to detect external collisions
$otherCollisions = [];
$res = $conn->query("
    SELECT t.section_id, sec.section_name, t.day, p.period_number, t.faculty_id, f.faculty_name, t.classroom_id, c.room_number
    FROM timetable t
    JOIN sections sec ON sec.section_id = t.section_id
    JOIN periods p ON p.period_id = t.period_id
    JOIN faculty f ON f.faculty_id = t.faculty_id
    JOIN classrooms c ON c.classroom_id = t.classroom_id
    WHERE t.section_id != 1
");
$busyFaculty = [];
$busyClassroom = [];
while ($r = $res->fetch_assoc()) {
    $busyFaculty["{$r['faculty_id']}_{$r['day']}_{$r['period_number']}"] = "Section {$r['section_name']}";
    $busyClassroom["{$r['classroom_id']}_{$r['day']}_{$r['period_number']}"] = "Section {$r['section_name']}";
}

// Validation loop
$days = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];
$totalSlots = 0;
$emptyCount = 0;
$collisionCount = 0;
$subjectTally = [];

foreach ($days as $day) {
    for ($p = 1; $p <= 9; $p++) {
        $totalSlots++;
        if (!isset($allSlots[$day][$p])) {
            echo "EMPTY PERIOD DETECTED: {$day} Period {$p}\n";
            $emptyCount++;
            continue;
        }
        $slot = $allSlots[$day][$p];
        $subCode = $slot['subject_code'];
        $subjectTally[$subCode] = ($subjectTally[$subCode] ?? 0) + 1;

        // Check external faculty collision
        $facKey = "{$slot['faculty_id']}_{$day}_{$p}";
        if (isset($busyFaculty[$facKey])) {
            echo "FACULTY COLLISION on {$day} P{$p}: {$slot['faculty_name']} already busy teaching {$busyFaculty[$facKey]}!\n";
            $collisionCount++;
        }

        // Check external room collision
        $rmKey = "{$slot['classroom_id']}_{$day}_{$p}";
        if (isset($busyClassroom[$rmKey])) {
            echo "ROOM COLLISION on {$day} P{$p}: Room {$slot['room_number']} already occupied by {$busyClassroom[$rmKey]}!\n";
            $collisionCount++;
        }
    }
}

echo "Total verified slots: {$totalSlots} (Expected: 54)\n";
echo "Empty period count:   {$emptyCount} (Must be: 0)\n";
echo "Collision count:      {$collisionCount} (Must be: 0)\n\n";

echo "Subject Period Distribution in Full 54-Slot Schedule:\n";
foreach ($subMap as $code => $sub) {
    $cnt = $subjectTally[$code] ?? 0;
    $req = (int)$sub['weekly_periods'];
    printf("  %-12s (%-7s): Scheduled: %-2d | Base Curriculum Requirement: %-2d\n",
        $code, $sub['subject_type'], $cnt, $req
    );
}

echo "\nFull Timetable Grid for CSE-A:\n";
echo "Day        | P1        | P2        | P3        | P4        | P5        | P6        | P7        | P8        | P9        \n";
echo "-----------+-----------+-----------+-----------+-----------+-----------+-----------+-----------+-----------+-----------\n";

foreach ($days as $day) {
    printf("%-10s | ", $day);
    for ($p = 1; $p <= 9; $p++) {
        $s = $allSlots[$day][$p];
        printf("%-9s | ", substr($s['subject_code'], 0, 9));
    }
    echo "\n";
}

echo "\nSIMULATION STATUS: " . ($emptyCount === 0 && $collisionCount === 0 ? "SUCCESS (PERFECT ZERO-CONFLICT FULL SCHEDULE)" : "FAILED") . "\n";
