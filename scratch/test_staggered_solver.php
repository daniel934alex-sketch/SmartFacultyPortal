<?php
require_once __DIR__ . '/../scratch/test_matching.php';

// Staggered lab assignments per section:
// Sec 1 (CSE-A): Labs at P8 (DBMS-LAB 11), P9 (ADSA-LAB 8)
// Sec 3 (CSE-C): Labs at P8 (ADSA-LAB 8), P9 (DBMS-LAB 11)
// Sec 2 (CSE-B): Labs at P6 (KVD-LAB 9), P7 (OS-LAB 10)
// Sec 4 (CSE-D): Labs at P6 (OS-LAB 10), P7 (KVD-LAB 9)
// Sec 5 (CSE-E): Labs at P4 (DBMS-LAB 11), P5 (KVD-LAB 9)

// For the other 7 periods of each section, we assign the 7 theory subjects:
// 1: DBMS, 2: ADSA, 3: KVD, 4: OS, 5: MATHS, 6: ENG, 7: QUANTS
// Each period has theory subjects distributed across the sections that are not in lab!

// Let's write a dynamic backtracking search that finds a valid daily plan for all 5 days!

function generateDaySchedule($dayIndex, $facEligibility, $sectionRooms, $labRoomIds, $subjects) {
    // Subject pools for each section
    $secSubjects = [
        1 => [1, 2, 3, 4, 5, 6, 7, 11, 8],
        2 => [1, 2, 3, 4, 5, 6, 7, 9, 10],
        3 => [1, 2, 3, 4, 5, 6, 7, 11, 8],
        4 => [1, 2, 3, 4, 5, 6, 7, 9, 10],
        5 => [1, 2, 3, 4, 5, 6, 7, 11, 9]
    ];

    // Fixed lab positions to avoid lab room bottlenecks
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

    // Remaining theory subjects to distribute for each section
    $theoryPool = [];
    foreach ([1, 2, 3, 4, 5] as $sec) {
        $theoryPool[$sec] = [1, 2, 3, 4, 5, 6, 7];
    }

    // Rotate theory subjects based on dayIndex so schedules differ each day
    foreach ([1, 2, 3, 4, 5] as $sec) {
        $shift = ($dayIndex * 2 + $sec) % 7;
        $theoryPool[$sec] = array_merge(
            array_slice($theoryPool[$sec], $shift),
            array_slice($theoryPool[$sec], 0, $shift)
        );
    }

    // Now populate each period
    // Backtrack across periods 1 to 9
    $dayPlan = []; // [sec => [p => subId]]
    foreach ([1, 2, 3, 4, 5] as $sec) {
        $dayPlan[$sec] = [];
    }

    // Search for valid theory assignments
    $solvePeriods = function($p) use (&$solvePeriods, &$dayPlan, $fixedSlots, $theoryPool, $facEligibility, $sectionRooms, $labRoomIds, $subjects) {
        if ($p > 9) {
            return true;
        }

        // Determine sections that have fixed lab slots in period $p
        $pSubs = [];
        $unassignedSecs = [];
        foreach ([1, 2, 3, 4, 5] as $sec) {
            if (isset($fixedSlots[$sec][$p])) {
                $pSubs[$sec] = $fixedSlots[$sec][$p];
            } else {
                $unassignedSecs[] = $sec;
            }
        }

        // Permute available theory subjects for $unassignedSecs
        $searchTheory = function($uIdx) use (&$searchTheory, $p, &$pSubs, $unassignedSecs, &$dayPlan, $theoryPool, $solvePeriods, $facEligibility, $sectionRooms, $labRoomIds, $subjects) {
            if ($uIdx === count($unassignedSecs)) {
                // Check if this full period pSubs can be matched with faculty and rooms
                $m = matchPeriod($pSubs, $facEligibility, $sectionRooms, $labRoomIds, $subjects);
                if ($m !== false) {
                    // Try next period
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
            // Available theory subjects for this section that haven't been assigned yet today
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
                // Try assigning candSub
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

echo "Running staggered solver for all 5 days...\n";
$fullSchedule = [];
for ($d = 0; $d < 5; $d++) {
    $plan = generateDaySchedule($d, $facEligibility, $sectionRooms, $labRoomIds, $subjects);
    if ($plan === false) {
        echo "Day {$days[$d]} FAILED to solve!\n";
    } else {
        echo "Day {$days[$d]} SOLVED successfully!\n";
        $fullSchedule[$days[$d]] = $plan;
    }
}

if (count($fullSchedule) === 5) {
    echo "EXCELLENT: All 5 weekdays solved 100% successfully!\n";
}
