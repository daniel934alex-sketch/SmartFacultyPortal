<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$file = __DIR__ . '/../faculty_subject_assignments.xlsx';
if (!file_exists($file)) {
    die("File does not exist\n");
}

$spreadsheet = IOFactory::load($file);
$sheetCount = $spreadsheet->getSheetCount();
echo "Sheet count: $sheetCount\n";

for ($i = 0; $i < $sheetCount; $i++) {
    $sheet = $spreadsheet->getSheet($i);
    echo "\n--- Sheet: " . $sheet->getTitle() . " ---\n";
    $rows = $sheet->toArray();
    foreach (array_slice($rows, 0, 30) as $r) {
        echo implode(" | ", array_map(function($v) { return $v ?? ''; }, $r)) . "\n";
    }
}
