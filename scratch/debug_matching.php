<?php
require_once __DIR__ . '/../scratch/test_matching.php';

$dayPlan = [
    1 => [1, 2, 3, 4, 5, 6, 7, 11, 8],
    2 => [2, 3, 4, 5, 1, 7, 6, 9, 10],
    3 => [3, 4, 5, 1, 2, 6, 7, 8, 11],
    4 => [4, 5, 1, 2, 3, 7, 6, 10, 9],
    5 => [5, 1, 2, 3, 4, 11, 9, 6, 7]
];

for ($p = 1; $p <= 9; $p++) {
    $pSubs = [];
    foreach ([1, 2, 3, 4, 5] as $sec) {
        $pSubs[$sec] = $dayPlan[$sec][$p - 1];
    }
    echo "Period $p subjects: " . implode(', ', array_map(function($s) use ($subjects) { return $subjects[$s]['subject_code']; }, $pSubs)) . "\n";
    $m = matchPeriod($pSubs, $facEligibility, $sectionRooms, $labRoomIds, $subjects);
    if ($m === false) {
        echo "  -> Period $p FAILED to match!\n";
    } else {
        echo "  -> Period $p OK!\n";
    }
}
