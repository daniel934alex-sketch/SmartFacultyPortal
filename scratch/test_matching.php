<?php
require_once __DIR__ . '/../config/database.php';

// Load metadata
$subjects = [];
$res = $conn->query("SELECT * FROM subjects ORDER BY subject_id");
while ($r = $res->fetch_assoc()) {
    $subjects[(int)$r['subject_id']] = $r;
}

$facEligibility = [];
$res = $conn->query("SELECT faculty_id, subject_id FROM faculty_subjects");
while ($r = $res->fetch_assoc()) {
    $facEligibility[(int)$r['subject_id']][] = (int)$r['faculty_id'];
}

// Admin approved supervisors for EE-PROJECT
if (!in_array(15, $facEligibility[12] ?? [])) $facEligibility[12][] = 15;
if (!in_array(2, $facEligibility[12] ?? [])) $facEligibility[12][] = 2;

$days = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY'];
$sections = [1, 2, 3, 4, 5];

$sectionRooms = [
    1 => 1, // Room 36
    2 => 2, // Room 37
    3 => 3, // Room 38
    4 => 4, // Room 39
    5 => 5  // Room 40
];

$labRoomIds = [
    8  => [7, 8],     // ADSA-LAB: prefer LAB-2, LAB-3
    9  => [8, 7],     // KVD-LAB: prefer LAB-3, LAB-2
    10 => [6, 7],     // OS-LAB: prefer LAB-1, LAB-2
    11 => [6, 7]      // DBMS-LAB: prefer LAB-1, LAB-2
];

// Subjects per section per day
// Each section takes each of its 9 subjects once per day (5 days x 1 = 5 periods each)
$sectionDailySubjects = [
    1 => [1, 2, 3, 4, 5, 6, 7, 11, 8], // DBMS, ADSA, KVD, OS, MATHS, ENG, QUANTS, DBMS-LAB, ADSA-LAB
    2 => [1, 2, 3, 4, 5, 6, 7, 9, 10], // DBMS, ADSA, KVD, OS, MATHS, ENG, QUANTS, KVD-LAB, OS-LAB
    3 => [1, 2, 3, 4, 5, 6, 7, 11, 8], // DBMS, ADSA, KVD, OS, MATHS, ENG, QUANTS, DBMS-LAB, ADSA-LAB
    4 => [1, 2, 3, 4, 5, 6, 7, 9, 10], // DBMS, ADSA, KVD, OS, MATHS, ENG, QUANTS, KVD-LAB, OS-LAB
    5 => [1, 2, 3, 4, 5, 6, 7, 11, 9]  // DBMS, ADSA, KVD, OS, MATHS, ENG, QUANTS, DBMS-LAB, KVD-LAB
];

// We can solve each day independently because each day requires the exact same set of 9 subjects per section!
// For each day, we schedule periods P1 to P9.
// In each period P (1..9), we assign 1 subject, 1 faculty, 1 room to each of the 5 sections.

function solveDay($dayIndex, $sectionDailySubjects, $facEligibility, $sectionRooms, $labRoomIds, $subjects) {
    // We want to assign a permutation of the 9 subjects to P1..P9 for each section.
    // Let's use backtracking to find a valid schedule for the 9 periods of this day.
    
    // To make solving fast and deterministic, we can stagger lab periods across sections.
    // For example:
    // Sec 1 has labs at P8, P9
    // Sec 2 has labs at P1, P2 (or P4, P5)
    // Sec 3 has labs at P6, P7
    // Sec 4 has labs at P8, P9
    // Sec 5 has labs at P3, P4
    
    // Let's implement a general backtracking solver for period p = 1..9
    $schedule = []; // [p => [secId => ['subject_id' => S, 'faculty_id' => F, 'classroom_id' => R]]]
    $assignedToday = []; // [secId => [subject_id => true]]
    foreach ([1, 2, 3, 4, 5] as $s) {
        $assignedToday[$s] = [];
    }

    $facWorkloadToday = []; // faculty_id => count

    // Let's pre-define valid subject shifts for the 5 days to ensure variety across days
    $orderTemplate = [
        // Day 0 (Monday)
        0 => [
            1 => [1, 2, 3, 4, 5, 6, 7, 11, 8],
            2 => [2, 3, 4, 5, 1, 7, 6, 9, 10],
            3 => [3, 4, 5, 1, 2, 6, 7, 8, 11],
            4 => [4, 5, 1, 2, 3, 7, 6, 10, 9],
            5 => [5, 1, 2, 3, 4, 11, 9, 6, 7]
        ],
        // Day 1 (Tuesday)
        1 => [
            1 => [2, 3, 4, 5, 1, 6, 7, 8, 11],
            2 => [3, 4, 5, 1, 2, 7, 6, 10, 9],
            3 => [4, 5, 1, 2, 3, 6, 7, 11, 8],
            4 => [5, 1, 2, 3, 4, 7, 6, 9, 10],
            5 => [1, 2, 3, 4, 5, 9, 11, 7, 6]
        ],
        // Day 2 (Wednesday)
        2 => [
            1 => [3, 4, 5, 1, 2, 6, 7, 11, 8],
            2 => [4, 5, 1, 2, 3, 7, 6, 9, 10],
            3 => [5, 1, 2, 3, 4, 6, 7, 8, 11],
            4 => [1, 2, 3, 4, 5, 7, 6, 10, 9],
            5 => [2, 3, 4, 5, 1, 11, 9, 6, 7]
        ],
        // Day 3 (Thursday)
        3 => [
            1 => [4, 5, 1, 2, 3, 6, 7, 8, 11],
            2 => [5, 1, 2, 3, 4, 7, 6, 10, 9],
            3 => [1, 2, 3, 4, 5, 6, 7, 11, 8],
            4 => [2, 3, 4, 5, 1, 7, 6, 9, 10],
            5 => [3, 4, 5, 1, 2, 9, 11, 7, 6]
        ],
        // Day 4 (Friday)
        4 => [
            1 => [5, 1, 2, 3, 4, 6, 7, 11, 8],
            2 => [1, 2, 3, 4, 5, 7, 6, 9, 10],
            3 => [2, 3, 4, 5, 1, 6, 7, 8, 11],
            4 => [3, 4, 5, 1, 2, 7, 6, 10, 9],
            5 => [4, 5, 1, 2, 3, 11, 9, 6, 7]
        ]
    ];

    // Let's test if we can assign valid faculty and lab rooms for this template
    $dayPlan = $orderTemplate[$dayIndex];
    $solved = [];

    for ($p = 1; $p <= 9; $p++) {
        // Subjects for this period:
        $pSubs = [];
        foreach ([1, 2, 3, 4, 5] as $sec) {
            $pSubs[$sec] = $dayPlan[$sec][$p - 1];
        }

        // Now find a matching of eligible faculty and available rooms for these 5 subjects
        $match = matchPeriod($pSubs, $facEligibility, $sectionRooms, $labRoomIds, $subjects);
        if ($match === false) {
            return false;
        }
        $solved[$p] = $match;
    }

    return $solved;
}

function matchPeriod($pSubs, $facEligibility, $sectionRooms, $labRoomIds, $subjects) {
    // $pSubs: [1 => subId, 2 => subId, ...]
    $secs = array_keys($pSubs);
    $assignment = [];
    $usedFaculty = [];
    $usedRooms = [];

    $backtrack = function($idx) use (&$backtrack, $secs, $pSubs, $facEligibility, $sectionRooms, $labRoomIds, $subjects, &$assignment, &$usedFaculty, &$usedRooms) {
        if ($idx === count($secs)) {
            return true;
        }

        $sec = $secs[$idx];
        $subId = $pSubs[$sec];
        $isLab = ($subjects[$subId]['subject_type'] === 'LAB');

        // Determine room options
        $roomOptions = [];
        if (!$isLab) {
            $roomOptions[] = $sectionRooms[$sec];
        } else {
            // Lab rooms
            $allowedRooms = [6, 7, 8]; // LAB-1, LAB-2, LAB-3
            foreach ($allowedRooms as $r) {
                if (!isset($usedRooms[$r])) {
                    $roomOptions[] = $r;
                }
            }
        }

        if (empty($roomOptions)) {
            return false;
        }

        // Faculty options
        $eligibleFaculty = $facEligibility[$subId] ?? [];
        foreach ($eligibleFaculty as $facId) {
            if (isset($usedFaculty[$facId])) {
                continue;
            }

            foreach ($roomOptions as $rId) {
                if (isset($usedRooms[$rId])) {
                    continue;
                }

                // Choose this fac and room
                $assignment[$sec] = [
                    'subject_id' => $subId,
                    'faculty_id' => $facId,
                    'classroom_id' => $rId
                ];
                $usedFaculty[$facId] = true;
                $usedRooms[$rId] = true;

                if ($backtrack($idx + 1)) {
                    return true;
                }

                // Backtrack
                unset($assignment[$sec]);
                unset($usedFaculty[$facId]);
                unset($usedRooms[$rId]);
            }
        }

        return false;
    };

    if ($backtrack(0)) {
        return $assignment;
    }
    return false;
}

echo "Testing template solver across 5 days...\n";
$fullWeek = [];
$allOk = true;
for ($d = 0; $d < 5; $d++) {
    $res = solveDay($d, $sectionDailySubjects, $facEligibility, $sectionRooms, $labRoomIds, $subjects);
    if ($res === false) {
        echo "Day {$days[$d]} failed to solve with current template!\n";
        $allOk = false;
    } else {
        echo "Day {$days[$d]} solved successfully!\n";
        $fullWeek[$days[$d]] = $res;
    }
}

if ($allOk) {
    echo "SUCCESS: All 5 weekdays solved conflict-free!\n";
}
