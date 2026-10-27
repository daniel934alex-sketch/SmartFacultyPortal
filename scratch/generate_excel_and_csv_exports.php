<?php
/**
 * SmartFacultyPortal - Generate Downloadable Timetable Workbooks & CSVs
 * 
 * Generates official Excel (.xlsx) workbooks and CSV files for:
 * - CSE-A_Timetable.xlsx / .csv
 * - CSE-B_Timetable.xlsx / .csv
 * - CSE-C_Timetable.xlsx / .csv
 * - CSE-D_Timetable.xlsx / .csv
 * - CSE-E_Timetable.xlsx / .csv
 * 
 * Sourced 100% from actual saved database timetable data.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Ensure output directories exist
$dirs = [
    __DIR__ . '/../uploads/timetables',
    __DIR__ . '/../exports'
];
foreach ($dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

$sections = [
    1 => 'CSE-A',
    2 => 'CSE-B',
    3 => 'CSE-C',
    4 => 'CSE-D',
    5 => 'CSE-E'
];

$days = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];

$periodHeaders = [
    1 => 'P1 (08:30–09:15)',
    2 => 'P2 (09:15–10:00)',
    3 => 'P3 (10:00–10:45)',
    4 => 'P4 (10:45–11:30)',
    5 => 'P5 (11:30–12:15)',
    6 => 'P6 (12:15–13:00)',
    7 => 'P7 (14:00–14:45)',
    8 => 'P8 (14:45–15:30)',
    9 => 'P9 (15:30–16:15)'
];

foreach ($sections as $sectionId => $sectionName) {
    echo "Generating exports for Section: $sectionName ...\n";

    // 1. Fetch saved records from database
    $query = "
        SELECT 
            t.timetable_id,
            t.day,
            p.period_number,
            p.start_time,
            p.end_time,
            s.subject_code,
            s.subject_name,
            s.subject_type,
            f.faculty_name,
            f.faculty_code,
            c.room_number,
            c.room_type,
            t.status
        FROM timetable t
        JOIN periods p ON p.period_id = t.period_id
        JOIN subjects s ON s.subject_id = t.subject_id
        JOIN faculty f ON f.faculty_id = t.faculty_id
        JOIN classrooms c ON c.classroom_id = t.classroom_id
        WHERE t.section_id = ?
        ORDER BY FIELD(t.day, 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'), p.period_number
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $sectionId);
    $stmt->execute();
    $res = $stmt->get_result();

    $gridData = [];
    $detailsRows = [];

    while ($r = $res->fetch_assoc()) {
        $d = strtoupper($r['day']);
        $p = (int)$r['period_number'];
        $gridData[$d][$p] = $r;
        $detailsRows[] = $r;
    }
    $stmt->close();

    if (count($detailsRows) !== 54) {
        die("ERROR: Expected 54 rows for $sectionName, but found " . count($detailsRows) . "!\n");
    }

    // 2. Build Excel Spreadsheet
    $spreadsheet = new Spreadsheet();

    // Sheet 1: Timetable Grid
    $sheet1 = $spreadsheet->getActiveSheet();
    $sheet1->setTitle("Timetable Grid");

    // Title Block
    $sheet1->setCellValue("A1", "SMART FACULTY PORTAL — OFFICIAL CLASS TIMETABLE");
    $sheet1->mergeCells("A1:J1");
    $sheet1->getStyle("A1")->getFont()->setBold(true)->setSize(16)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF1E293B'));
    $sheet1->getStyle("A1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $theoryRoom = ($sectionId === 1 ? '36' : ($sectionId === 2 ? '37' : ($sectionId === 3 ? '38' : ($sectionId === 4 ? '39' : '40'))));
    $sheet1->setCellValue("A2", "SECTION: $sectionName | Class Room: $theoryRoom | Academic Year: 2026-2027 | Status: PUBLISHED");
    $sheet1->mergeCells("A2:J2");
    $sheet1->getStyle("A2")->getFont()->setBold(true)->setSize(11)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF475569'));
    $sheet1->getStyle("A2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Header Row (Row 4)
    $sheet1->setCellValue("A4", "Day / Period");
    $sheet1->getStyle("A4")->getFont()->setBold(true);
    $sheet1->getStyle("A4")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E293B');
    $sheet1->getStyle("A4")->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
    $sheet1->getStyle("A4")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

    $colLetters = ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
    foreach ($periodHeaders as $pNum => $pHdr) {
        $col = $colLetters[$pNum - 1];
        $sheet1->setCellValue("{$col}4", $pHdr);
        $sheet1->getStyle("{$col}4")->getFont()->setBold(true)->setSize(10);
        $sheet1->getStyle("{$col}4")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2563EB');
        $sheet1->getStyle("{$col}4")->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
        $sheet1->getStyle("{$col}4")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet1->getColumnDimension($col)->setWidth(20);
    }
    $sheet1->getColumnDimension('A')->setWidth(16);
    $sheet1->getRowDimension(4)->setRowHeight(32);

    // Fill Days (Rows 5 to 10)
    $currRow = 5;
    foreach ($days as $day) {
        $sheet1->setCellValue("A{$currRow}", $day);
        $sheet1->getStyle("A{$currRow}")->getFont()->setBold(true)->setSize(11);
        $isSat = ($day === 'SATURDAY');
        $dayBg = $isSat ? 'FFFEF3C7' : 'FFF1F5F9';
        $sheet1->getStyle("A{$currRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($dayBg);
        $sheet1->getStyle("A{$currRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        for ($p = 1; $p <= 9; $p++) {
            $col = $colLetters[$p - 1];
            $cellCoord = "{$col}{$currRow}";
            if (isset($gridData[$day][$p])) {
                $item = $gridData[$day][$p];
                $cellText = "{$item['subject_code']}\n{$item['faculty_name']}\n[Room: {$item['room_number']}]";
                $sheet1->setCellValue($cellCoord, $cellText);

                // Styling
                $sheet1->getStyle($cellCoord)->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $isLab = ($item['subject_type'] === 'LAB');
                $isProject = ($item['subject_type'] === 'PROJECT');

                if ($isProject) {
                    $sheet1->getStyle($cellCoord)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFF6FF');
                } elseif ($isLab) {
                    $sheet1->getStyle($cellCoord)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0FDF4');
                } else {
                    $sheet1->getStyle($cellCoord)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFFFF');
                }
            } else {
                $sheet1->setCellValue($cellCoord, "—");
                $sheet1->getStyle($cellCoord)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            }
        }
        $sheet1->getRowDimension($currRow)->setRowHeight(60);
        $currRow++;
    }

    // Grid borders
    $styleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FFCBD5E1'],
            ],
        ],
    ];
    $sheet1->getStyle("A4:J10")->applyFromArray($styleArray);

    // Sheet 2: Schedule Details
    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle("Schedule Details");

    $detailsHeaders = [
        "Day", "Period", "Start Time", "End Time", "Subject Code", "Subject Name", "Subject Type", "Faculty Name", "Faculty Code", "Classroom/Lab", "Status"
    ];

    $colLetters11 = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'];
    foreach ($detailsHeaders as $idx => $h) {
        $coord = $colLetters11[$idx] . '1';
        $sheet2->setCellValue($coord, $h);
        $sheet2->getStyle($coord)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
        $sheet2->getStyle($coord)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E293B');
        $sheet2->getStyle($coord)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    $dRow = 2;
    foreach ($detailsRows as $row) {
        $sheet2->setCellValue("A{$dRow}", $row['day']);
        $sheet2->setCellValue("B{$dRow}", "P" . $row['period_number']);
        $sheet2->setCellValue("C{$dRow}", substr($row['start_time'], 0, 5));
        $sheet2->setCellValue("D{$dRow}", substr($row['end_time'], 0, 5));
        $sheet2->setCellValue("E{$dRow}", $row['subject_code']);
        $sheet2->setCellValue("F{$dRow}", $row['subject_name']);
        $sheet2->setCellValue("G{$dRow}", $row['subject_type']);
        $sheet2->setCellValue("H{$dRow}", $row['faculty_name']);
        $sheet2->setCellValue("I{$dRow}", $row['faculty_code']);
        $sheet2->setCellValue("J{$dRow}", $row['room_number']);
        $sheet2->setCellValue("K{$dRow}", $row['status']);
        $dRow++;
    }

    foreach (range('A', 'K') as $c) {
        $sheet2->getColumnDimension($c)->setAutoSize(true);
    }
    $sheet2->getStyle("A1:K" . ($dRow - 1))->applyFromArray($styleArray);

    // Save Excel (.xlsx) file in uploads/timetables/ and exports/
    $excelFilename = "{$sectionName}_Timetable.xlsx";
    $excelPath1 = __DIR__ . "/../uploads/timetables/{$excelFilename}";
    $excelPath2 = __DIR__ . "/../exports/{$excelFilename}";

    $writer = new Xlsx($spreadsheet);
    $writer->save($excelPath1);
    copy($excelPath1, $excelPath2);
    echo "  Saved Excel: $excelPath1\n";

    // 3. Build CSV File
    $csvFilename = "{$sectionName}_Timetable.csv";
    $csvPath1 = __DIR__ . "/../uploads/timetables/{$csvFilename}";
    $csvPath2 = __DIR__ . "/../exports/{$csvFilename}";

    $fp = fopen($csvPath1, 'w');
    fputcsv($fp, ['Section', 'Day', 'Period', 'Time', 'Subject_Code', 'Subject_Name', 'Subject_Type', 'Faculty_Name', 'Faculty_Code', 'Classroom', 'Status']);
    foreach ($detailsRows as $row) {
        $timeStr = substr($row['start_time'], 0, 5) . '-' . substr($row['end_time'], 0, 5);
        fputcsv($fp, [
            $sectionName,
            $row['day'],
            "P" . $row['period_number'],
            $timeStr,
            $row['subject_code'],
            $row['subject_name'],
            $row['subject_type'],
            $row['faculty_name'],
            $row['faculty_code'],
            $row['room_number'],
            $row['status']
        ]);
    }
    fclose($fp);
    copy($csvPath1, $csvPath2);
    echo "  Saved CSV:   $csvPath1\n";
}

echo "\nAll 5 Excel files and CSV files generated successfully!\n";
