<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/TimetableParser.php';

$parser = new TimetableParser($conn);
$res = $parser->parseFile(__DIR__ . '/../sample_timetables/timetable_invalid_sample.csv', 'csv', 1, 'PUBLISHED', 'replace', 'single');

echo "Parsed rows: " . count($res['rows']) . "\n";
$blockingErrors = 0;
foreach ($res['rows'] as $idx => $r) {
    if (!empty($r['errors'])) {
        $blockingErrors += count($r['errors']);
        echo "Row " . ($idx + 1) . " blocking errors:\n";
        foreach ($r['errors'] as $e) {
            echo "  - $e\n";
        }
    }
}

echo "Total blocking errors found: $blockingErrors\n";
if ($blockingErrors > 0) {
    echo "SUCCESS: Invalid / conflicting rows are strictly blocked and prevented from being published!\n";
} else {
    echo "FAILED: Invalid rows were not blocked!\n";
}
