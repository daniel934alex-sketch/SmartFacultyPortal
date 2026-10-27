<?php
/**
 * SmartFacultyPortal - Full Timetable Generator & Inserter
 * 
 * Generates all 270 slots for CSE-A, CSE-B, CSE-C, CSE-D, CSE-E:
 * - 45 weekday periods per section (Monday-Friday P1-P9)
 * - 9 Saturday periods per section (Saturday P1-P9 EE-PROJECT)
 * - Strict conflict validation before database insert
 * - Sets status = 'PUBLISHED'
 */

require_once __DIR__ . '/../config/database.php';

// 1. Load reference data
$sections = [];
$res = $conn->query("SELECT * FROM sections ORDER BY section_id");
while ($r = $res->fetch_assoc()) $sections[(int)$r['section_id']] = $r;

$classrooms = [];
$res = $conn->query("SELECT * FROM classrooms ORDER BY classroom_id");
while ($r = $res->fetch_assoc()) $classrooms[(int)$r['classroom_id']] = $r;

$periods = [];
$res = $conn->query("SELECT * FROM periods ORDER BY period_number");
while ($r = $res->fetch_assoc()) $periods[(int)$r['period_number']] = $r;

$subjects = [];
$res = $conn->query("SELECT * FROM subjects ORDER BY subject_id");
while ($r = $res->fetch_assoc()) $subjects[(int)$r['subject_id']] = $r;

$faculty = [];
$res = $conn->query("SELECT * FROM faculty ORDER BY faculty_id");
while ($r = $res->fetch_assoc()) $faculty[(int)$r['faculty_id']] = $r;

$facEligibility = [];
$res = $conn->query("SELECT faculty_id, subject_id FROM faculty_subjects");
while ($r = $res->fetch_assoc()) {
    $facEligibility[(int)$r['subject_id']][] = (int)$r['faculty_id'];
}

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

function matchPeriodSlots($pSubs, $facEligibility, $sectionRooms, $subjects) {
    $secs = array_keys($pSubs);
    $assignment = [];
    $usedFaculty = [];
    $usedRooms = [];

    $backtrack = function($idx) use (&$backtrack, $secs, $pSubs, $facEligibility, $sectionRooms, $subjects, &$assignment, &$usedFaculty, &$usedRooms) {
        if ($idx === count($secs)) {
            return true;
        }

        $sec = $secs[$idx];
        $subId = $pSubs[$sec];
        $isLab = ($subjects[$subId]['subject_type'] === 'LAB');

        $roomOptions = [];
        if (!$isLab) {
            $roomOptions[] = $sectionRooms[$sec];
        } else {
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

        $eligibleFaculty = $facEligibility[$subId] ?? [];
        foreach ($eligibleFaculty as $facId) {
            if (isset($usedFaculty[$facId])) {
                continue;
            }

            foreach ($roomOptions as $rId) {
                if (isset($usedRooms[$rId])) {
                    continue;
                }

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

function solveWeekdaySchedule($dayIndex, $facEligibility, $sectionRooms, $subjects) {
    $fixedSlots = [];
    // Sec 1 labs
    $fixedSlots[1][8] = 11; // DBMS-LAB
    $fixedSlots[1][9] = 8;  // ADSA-LAB
    // Sec 3 labs
    $fixedSlots[3][8] = 8;  // ADSA-LAB
    $fixedSlots[3][9] = 11; // DBMS-LAB
    // Sec 2 labs
    $fixedSlots[2][6] = 9;  // KVD-LAB
    $fixedSlots[2][7] = 10; // OS-LAB
    // Sec 4 labs
    $fixedSlots[4][6] = 10; // OS-LAB
    $fixedSlots[4][7] = 9;  // KVD-LAB
    // Sec 5 labs
    $fixedSlots[5][4] = 11; // DBMS-LAB
    $fixedSlots[5][5] = 9;  // KVD-LAB

    $theoryPool = [];
    foreach ([1, 2, 3, 4, 5] as $sec) {
        $theoryPool[$sec] = [1, 2, 3, 4, 5, 6, 7];
        $shift = ($dayIndex * 2 + $sec) % 7;
        $theoryPool[$sec] = array_merge(
            array_slice($theoryPool[$sec], $shift),
            array_slice($theoryPool[$sec], 0, $shift)
        );
    }

    $dayPlan = [];
    foreach ([1, 2, 3, 4, 5] as $sec) {
        $dayPlan[$sec] = [];
    }

    $solvePeriods = function($p) use (&$solvePeriods, &$dayPlan, $fixedSlots, $theoryPool, $facEligibility, $sectionRooms, $subjects) {
        if ($p > 9) {
            return true;
        }

        $pSubs = [];
        $unassignedSecs = [];
        foreach ([1, 2, 3, 4, 5] as $sec) {
            if (isset($fixedSlots[$sec][$p])) {
                $pSubs[$sec] = $fixedSlots[$sec][$p];
            } else {
                $unassignedSecs[] = $sec;
            }
        }

        $searchTheory = function($uIdx) use (&$searchTheory, $p, &$pSubs, $unassignedSecs, &$dayPlan, $theoryPool, $solvePeriods, $facEligibility, $sectionRooms, $subjects) {
            if ($uIdx === count($unassignedSecs)) {
                $m = matchPeriodSlots($pSubs, $facEligibility, $sectionRooms, $subjects);
                if ($m !== false) {
                    foreach ($pSubs as $s => $sub) {
                        $dayPlan[$s][$p] = [
                            'subject_id' => $sub,
                            'faculty_id' => $m[$s]['faculty_id'],
                            'classroom_id' => $m[$s]['classroom_id']
                        ];
                    }
                    if ($solvePeriods($p + 1)) {
                        return true;
                    }
                }
                return false;
            }

            $sec = $unassignedSecs[$uIdx];
            $alreadyAssignedInSec = [];
            for ($prev = 1; $prev < $p; $prev++) {
                if (isset($dayPlan[$sec][$prev])) {
                    $alreadyAssignedInSec[$dayPlan[$sec][$prev]['subject_id']] = true;
                }
            }

            foreach ($theoryPool[$sec] as $candSub) {
                if (isset($alreadyAssignedInSec[$candSub])) {
                    continue;
                }
                $pSubs[$sec] = $candSub;
                if ($searchTheory($uIdx + 1)) {
                    return true;
                }
                unset($pSubs[$sec]);
            }

            return false;
        };

        return $searchTheory(0);
    };

    if ($solvePeriods(1)) {
        return $dayPlan;
    }
    return false;
}

// Generate the 6 days
$days = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];
$allRows = [];

// Weekdays (Monday to Friday)
for ($dIdx = 0; $dIdx < 5; $dIdx++) {
    $dayName = $days[$dIdx];
    $dayPlan = solveWeekdaySchedule($dIdx, $facEligibility, $sectionRooms, $subjects);
    if ($dayPlan === false) {
        die("FATAL: Failed to solve weekday $dayName!\n");
    }

    for ($p = 1; $p <= 9; $p++) {
        foreach ([1, 2, 3, 4, 5] as $secId) {
            $slot = $dayPlan[$secId][$p];
            $allRows[] = [
                'day' => $dayName,
                'period_id' => $periods[$p]['period_id'],
                'period_number' => $p,
                'section_id' => $secId,
                'subject_id' => $slot['subject_id'],
                'faculty_id' => $slot['faculty_id'],
                'classroom_id' => $slot['classroom_id'],
                'status' => 'PUBLISHED'
            ];
        }
    }
}

// Saturday (EE-PROJECT)
// The 5 supervisors: 1 (Dr. Arun Kumar), 6 (Prof. Meena R), 11 (Prof. Sanjay R), 2 (Prof. Priya Sharma), 15 (Prof. Manoj S)
$satSupervisors = [1, 6, 11, 2, 15];
for ($p = 1; $p <= 9; $p++) {
    for ($sIdx = 0; $sIdx < 5; $sIdx++) {
        $secId = $sIdx + 1;
        $facIdx = ($sIdx + ($p - 1)) % 5;
        $facId = $satSupervisors[$facIdx];
        $allRows[] = [
            'day' => 'SATURDAY',
            'period_id' => $periods[$p]['period_id'],
            'period_number' => $p,
            'section_id' => $secId,
            'subject_id' => 12, // EE-PROJECT
            'faculty_id' => $facId,
            'classroom_id' => $sectionRooms[$secId],
            'status' => 'PUBLISHED'
        ];
    }
}

echo "Generated total slots: " . count($allRows) . " (Expected: 270)\n";

// STRICT VALIDATION
echo "\n=== STRICT CONFLICT VALIDATION ===\n";
$conflicts = [];
$seenSecSlot = [];
$seenFacSlot = [];
$seenRoomSlot = [];

foreach ($allRows as $idx => $r) {
    $sec = $r['section_id'];
    $day = $r['day'];
    $p = $r['period_number'];
    $fac = $r['faculty_id'];
    $room = $r['classroom_id'];
    $sub = $r['subject_id'];

    // 1. Section duplicate
    $secKey = "{$sec}_{$day}_{$p}";
    if (isset($seenSecSlot[$secKey])) {
        $conflicts[] = "Duplicate Section Slot: Section $sec on $day P$p";
    }
    $seenSecSlot[$secKey] = true;

    // 2. Faculty double-booking
    $facKey = "{$fac}_{$day}_{$p}";
    if (isset($seenFacSlot[$facKey])) {
        $conflicts[] = "Faculty Double Booking: Faculty $fac on $day P$p assigned to multiple sections";
    }
    $seenFacSlot[$facKey] = true;

    // 3. Room double-booking
    $roomKey = "{$room}_{$day}_{$p}";
    if (isset($seenRoomSlot[$roomKey])) {
        $conflicts[] = "Room Double Booking: Room $room on $day P$p assigned to multiple sections";
    }
    $seenRoomSlot[$roomKey] = true;

    // 4. Faculty eligibility
    if (!in_array($fac, $facEligibility[$sub] ?? [])) {
        $conflicts[] = "Ineligible Faculty: Faculty $fac cannot teach Subject $sub";
    }
}

if (!empty($conflicts)) {
    echo "VALIDATION FAILED! Conflicts found:\n";
    foreach ($conflicts as $c) echo "  - $c\n";
    die("ABORTING: Will not insert invalid timetable!\n");
}

echo "VALIDATION PASSED: 0 faculty conflicts, 0 room conflicts, 0 duplicate slots, 100% eligible faculty!\n";

// DATABASE INSERTION
$conn->begin_transaction();
try {
    $insertStmt = $conn->prepare("
        INSERT INTO timetable (day, period_id, section_id, subject_id, faculty_id, classroom_id, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $inserted = 0;
    foreach ($allRows as $r) {
        $insertStmt->bind_param("siiiiis",
            $r['day'],
            $r['period_id'],
            $r['section_id'],
            $r['subject_id'],
            $r['faculty_id'],
            $r['classroom_id'],
            $r['status']
        );
        $insertStmt->execute();
        $inserted++;
    }
    $insertStmt->close();

    $conn->commit();
    echo "SUCCESS: Inserted $inserted validated rows into timetable table with status PUBLISHED!\n";

} catch (Exception $e) {
    $conn->rollback();
    die("INSERTION ERROR: " . $e->getMessage() . "\n");
}
