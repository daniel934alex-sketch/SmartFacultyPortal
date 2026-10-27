<?php
$lines = file(__DIR__ . '/../SmartFacultyPortal_5_Section_Conflict_Checked_Draft.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$header = str_getcsv(array_shift($lines));

$unassigned = [];
$assigned = 0;
foreach ($lines as $idx => $line) {
    $row = str_getcsv($line);
    $sec = $row[1] ?? '';
    $day = $row[2] ?? '';
    $period = $row[3] ?? '';
    $sub = $row[5] ?? '';
    $fac = $row[6] ?? '';
    $room = $row[7] ?? '';
    $notes = $row[9] ?? '';

    if (strpos($fac, 'UNASSIGNED') !== false || strpos($room, 'UNASSIGNED') !== false) {
        $unassigned[] = [
            'line' => $idx + 2,
            'sec' => $sec,
            'day' => $day,
            'period' => $period,
            'sub' => $sub,
            'fac' => $fac,
            'room' => $room,
            'notes' => $notes
        ];
    } else {
        $assigned++;
    }
}

echo "Total rows: " . count($lines) . "\n";
echo "Assigned: $assigned\n";
echo "Unassigned: " . count($unassigned) . "\n";
echo "\n--- UNASSIGNED DETAILS ---\n";
foreach ($unassigned as $u) {
    echo "Line {$u['line']}: {$u['sec']} | {$u['day']} {$u['period']} | {$u['sub']} | Fac: {$u['fac']} | Room: {$u['room']}\n";
}
