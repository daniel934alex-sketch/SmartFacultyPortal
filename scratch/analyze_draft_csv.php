<?php
$lines = file(__DIR__ . '/../SmartFacultyPortal_5_Section_Conflict_Checked_Draft.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$header = str_getcsv(array_shift($lines));

$data = [];
foreach ($lines as $line) {
    $row = str_getcsv($line);
    if (count($row) >= 8) {
        $sec = $row[1];
        $sub = $row[5];
        $fac = $row[6];
        $room = $row[7];
        $data[$sec][$sub] = ($data[$sec][$sub] ?? 0) + 1;
    }
}

foreach ($data as $sec => $subs) {
    echo "\n=== $sec in 5_Section_Conflict_Checked_Draft.csv ===\n";
    $tot = 0;
    foreach ($subs as $s => $cnt) {
        echo sprintf("  %-12s: %d\n", $s, $cnt);
        $tot += $cnt;
    }
    echo "  TOTAL: $tot\n";
}
