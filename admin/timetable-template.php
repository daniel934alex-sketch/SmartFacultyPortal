<?php
/**
 * Timetable Template Downloader
 * Generates sample CSV and Excel (.xlsx) templates for Admin/HOD.
 */

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole("HOD");

require_once "../config/database.php";
require_once "../vendor/autoload.php";

$format = strtolower(trim($_GET['format'] ?? 'csv'));

// Sample rows pre-populated with real database references
$headers = ["Day", "Period", "Subject", "Faculty", "Classroom", "Status"];

$sampleRows = [
    ["MONDAY", "1", "DBMS", "Dr. Arun Kumar", "36", "PUBLISHED"],
    ["MONDAY", "2", "ADSA", "Prof. Priya Sharma", "36", "PUBLISHED"],
    ["MONDAY", "3", "OS", "Prof. Karthik Raj", "36", "PUBLISHED"],
    ["MONDAY", "4", "MATHS", "Prof. Divya S", "36", "PUBLISHED"],
    ["MONDAY", "5", "KVD", "Prof. Naveen Kumar", "36", "PUBLISHED"],
    ["MONDAY", "6", "ENG", "Prof. Meena R", "36", "PUBLISHED"],
    ["MONDAY", "7", "ADSA-LAB", "Prof. Priya Sharma", "LAB-1", "PUBLISHED"],
    ["MONDAY", "8", "ADSA-LAB", "Prof. Priya Sharma", "LAB-1", "PUBLISHED"],
    ["MONDAY", "9", "ADSA-LAB", "Prof. Priya Sharma", "LAB-1", "PUBLISHED"],
    ["TUESDAY", "1", "MATHS", "Prof. Divya S", "36", "PUBLISHED"],
    ["TUESDAY", "2", "DBMS", "Dr. Arun Kumar", "36", "PUBLISHED"],
    ["TUESDAY", "3", "KVD", "Prof. Naveen Kumar", "36", "PUBLISHED"],
    ["TUESDAY", "4", "OS", "Prof. Karthik Raj", "36", "PUBLISHED"],
    ["TUESDAY", "5", "ADSA", "Prof. Priya Sharma", "36", "PUBLISHED"],
    ["TUESDAY", "6", "QUANTS", "Prof. Rahul P", "36", "PUBLISHED"],
    ["WEDNESDAY", "1", "OS", "Prof. Karthik Raj", "36", "PUBLISHED"],
    ["WEDNESDAY", "2", "MATHS", "Prof. Divya S", "36", "PUBLISHED"],
    ["WEDNESDAY", "3", "DBMS", "Dr. Arun Kumar", "36", "PUBLISHED"],
    ["WEDNESDAY", "4", "DBMS-LAB", "Dr. Arun Kumar", "LAB-2", "PUBLISHED"],
    ["WEDNESDAY", "5", "DBMS-LAB", "Dr. Arun Kumar", "LAB-2", "PUBLISHED"],
    ["WEDNESDAY", "6", "DBMS-LAB", "Dr. Arun Kumar", "LAB-2", "PUBLISHED"],
    ["THURSDAY", "1", "KVD", "Prof. Naveen Kumar", "36", "PUBLISHED"],
    ["THURSDAY", "2", "OS", "Prof. Karthik Raj", "36", "PUBLISHED"],
    ["THURSDAY", "3", "ADSA", "Prof. Priya Sharma", "36", "PUBLISHED"],
    ["THURSDAY", "4", "MATHS", "Prof. Divya S", "36", "PUBLISHED"],
    ["THURSDAY", "5", "DBMS", "Dr. Arun Kumar", "36", "PUBLISHED"],
    ["FRIDAY", "1", "DBMS", "Dr. Arun Kumar", "36", "PUBLISHED"],
    ["FRIDAY", "2", "ADSA", "Prof. Priya Sharma", "36", "PUBLISHED"],
    ["FRIDAY", "3", "MATHS", "Prof. Divya S", "36", "PUBLISHED"],
    ["FRIDAY", "4", "KVD-LAB", "Prof. Naveen Kumar", "LAB-3", "PUBLISHED"],
    ["FRIDAY", "5", "KVD-LAB", "Prof. Naveen Kumar", "LAB-3", "PUBLISHED"],
    ["FRIDAY", "6", "KVD-LAB", "Prof. Naveen Kumar", "LAB-3", "PUBLISHED"],
    ["SATURDAY", "1", "EE-PROJECT", "Prof. Meena R", "LAB-1", "PUBLISHED"],
    ["SATURDAY", "2", "EE-PROJECT", "Prof. Meena R", "LAB-1", "PUBLISHED"],
    ["SATURDAY", "3", "EE-PROJECT", "Prof. Meena R", "LAB-1", "PUBLISHED"]
];

if ($format === 'xlsx') {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle("Timetable Template");

    // Write header
    foreach ($headers as $cIdx => $h) {
        $sheet->setCellValue([$cIdx + 1, 1], $h);
        $sheet->getStyle([$cIdx + 1, 1])->getFont()->setBold(true);
    }

    // Write rows
    foreach ($sampleRows as $rIdx => $row) {
        foreach ($row as $cIdx => $val) {
            $sheet->setCellValue([$cIdx + 1, $rIdx + 2], $val);
        }
    }

    foreach (range(1, count($headers)) as $col) {
        $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="timetable_template.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} else {
    // Default CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="timetable_template.csv"');
    header('Cache-Control: max-age=0');

    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    foreach ($sampleRows as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}
