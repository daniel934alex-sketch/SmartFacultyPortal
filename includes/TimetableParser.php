<?php
/**
 * TimetableParser
 * 
 * Multi-format parser for Timetable Upload:
 * - Excel (.xlsx, .xls)
 * - CSV (.csv)
 * - PDF (.pdf) (Digital via Smalot PDF Parser, Scanned via Tesseract OCR if available)
 * - Images (.png, .jpg, .jpeg) via Tesseract OCR if available
 *
 * Normalizes extracted entries and maps them to database IDs in smartfacultyportal.
 */

if (!class_exists('ComposerAutoloaderInit')) {
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }
}

class TimetableParser
{
    private mysqli $conn;
    private array $sections = [];
    private array $periods = [];
    private array $subjects = [];
    private array $faculty = [];
    private array $classrooms = [];

    // Lookup caches (lowercase string -> ID)
    private array $subjectCodeMap = [];
    private array $subjectNameMap = [];
    private array $facultyCodeMap = [];
    private array $facultyNameMap = [];
    private array $facultyUsernameMap = [];
    private array $classroomMap = [];
    private array $periodNumMap = [];
    // Valid faculty-subject combinations from faculty_subjects ("facultyId_subjectId" => true).
    private array $facultySubjectMap = [];
    private bool $facultySubjectMapLoaded = false;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
        $this->loadReferenceData();
    }

    /**
     * Load reference tables from database
     */
    private function loadReferenceData(): void
    {
        // 1. Sections
        $res = $this->conn->query("SELECT section_id, section_name FROM sections ORDER BY section_name");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $id = (int)$row['section_id'];
                $this->sections[$id] = $row;
            }
        }

        // 2. Periods
        $res = $this->conn->query("SELECT period_id, period_number, start_time, end_time FROM periods ORDER BY period_number");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $id = (int)$row['period_id'];
                $pNum = (int)$row['period_number'];
                $this->periods[$id] = $row;
                $this->periodNumMap[$pNum] = $id;
            }
        }

        // 3. Subjects
        $res = $this->conn->query("SELECT subject_id, subject_code, subject_name, subject_type, weekly_periods, continuous_periods FROM subjects ORDER BY subject_code");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $id = (int)$row['subject_id'];
                $this->subjects[$id] = $row;
                $codeKey = strtolower(trim($row['subject_code']));
                $nameKey = strtolower(trim($row['subject_name']));
                $this->subjectCodeMap[$codeKey] = $id;
                $this->subjectNameMap[$nameKey] = $id;
            }
        }

        // 4. Faculty
        $res = $this->conn->query("
            SELECT f.faculty_id, f.faculty_code, f.faculty_name, u.username 
            FROM faculty f 
            LEFT JOIN users u ON u.user_id = f.user_id 
            ORDER BY f.faculty_name
        ");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $id = (int)$row['faculty_id'];
                $this->faculty[$id] = $row;
                $codeKey = strtolower(trim($row['faculty_code']));
                $nameKey = strtolower(trim($row['faculty_name']));
                $this->facultyCodeMap[$codeKey] = $id;
                $this->facultyNameMap[$nameKey] = $id;
                if (!empty($row['username'])) {
                    $userKey = strtolower(trim($row['username']));
                    $this->facultyUsernameMap[$userKey] = $id;
                }
            }
        }

        // 4b. Faculty-subject assignments. Keep imports aligned with the database mapping.
        $res = $this->conn->query("SELECT faculty_id, subject_id FROM faculty_subjects");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $key = (int)$row['faculty_id'] . '_' . (int)$row['subject_id'];
                $this->facultySubjectMap[$key] = true;
            }
            $this->facultySubjectMapLoaded = true;
        }

        // 5. Classrooms
        $res = $this->conn->query("SELECT classroom_id, room_number, room_type, status FROM classrooms ORDER BY room_number");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $id = (int)$row['classroom_id'];
                $this->classrooms[$id] = $row;
                $roomKey = strtolower(trim($row['room_number']));
                $this->classroomMap[$roomKey] = $id;
            }
        }
    }

    /**
     * Get loaded reference data for frontend dropdowns
     */
    public function getReferenceData(): array
    {
        return [
            'sections' => $this->sections,
            'periods' => $this->periods,
            'subjects' => $this->subjects,
            'faculty' => $this->faculty,
            'classrooms' => $this->classrooms,
            'days' => ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY']
        ];
    }

    /**
     * Check if Tesseract OCR is installed and available
     */
    public static function getTesseractBinary(): ?string
    {
        $commonPaths = [
            'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
            'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
            'C:\\laragon\\bin\\tesseract\\tesseract.exe'
        ];

        foreach ($commonPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Check PATH
        $output = [];
        $returnVar = 0;
        @exec('where tesseract 2>NUL', $output, $returnVar);
        if ($returnVar === 0 && !empty($output[0]) && file_exists(trim($output[0]))) {
            return trim($output[0]);
        }

        return null;
    }

    public static function isTesseractAvailable(): bool
    {
        return self::getTesseractBinary() !== null;
    }

    /**
     * Parse uploaded file into raw timetable rows
     *
     * @param string $filePath
     * @param string $extension
     * @param int $defaultSectionId
     * @param string $defaultStatus
     * @return array [ 'success' => bool, 'rows' => array, 'message' => string, 'format' => string ]
     */
    public function parseFile(string $filePath, string $extension, int $defaultSectionId = 0, string $defaultStatus = 'PUBLISHED', string $conflictMode = 'replace', string $uploadMode = 'single'): array
    {
        $ext = strtolower(trim($extension));
        $rawRows = [];

        try {
            switch ($ext) {
                case 'xlsx':
                case 'xls':
                case 'csv':
                    $result = $this->parseSpreadsheet($filePath, $ext, $uploadMode);
                    if (!$result['success']) {
                        return $result;
                    }
                    $rawRows = $result['rows'];
                    break;

                case 'pdf':
                    $result = $this->parsePdfFile($filePath);
                    if (!$result['success']) {
                        return $result;
                    }
                    $rawRows = $result['rows'];
                    break;

                case 'png':
                case 'jpg':
                case 'jpeg':
                    $result = $this->parseImageFile($filePath);
                    if (!$result['success']) {
                        return $result;
                    }
                    $rawRows = $result['rows'];
                    break;

                default:
                    return [
                        'success' => false,
                        'rows' => [],
                        'message' => "Unsupported file format (.$ext). Supported formats: .xlsx, .xls, .csv, .pdf, .png, .jpg, .jpeg"
                    ];
            }
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'rows' => [],
                'message' => "File parsing failed: " . $e->getMessage()
            ];
        }

        if (empty($rawRows)) {
            return [
                'success' => false,
                'rows' => [],
                'message' => "No timetable entries could be extracted from this file. Please verify the format or use our downloadable template."
            ];
        }

        // Normalize and map to DB entities
        $mappedRows = [];
        $rowIndex = 1;
        foreach ($rawRows as $raw) {
            $mapped = $this->normalizeAndMapRow($raw, $rowIndex++, $defaultSectionId, $defaultStatus, $uploadMode);
            if ($mapped !== null) {
                $mappedRows[] = $mapped;
            }
        }

        if (empty($mappedRows)) {
            return [
                'success' => false,
                'rows' => [],
                'message' => "Rows were found in the file, but none contained valid timetable data (e.g. days or periods)."
            ];
        }

        // Perform conflict checks within the batch and with the database
        $analyzedRows = $this->detectConflicts($mappedRows, $defaultSectionId, $conflictMode, $uploadMode);

        return [
            'success' => true,
            'rows' => $analyzedRows,
            'message' => "Successfully parsed " . count($analyzedRows) . " timetable entries.",
            'format' => strtoupper($ext)
        ];
    }

    /**
     * Parse Spreadsheet (Excel / CSV)
     */
    private function parseSpreadsheet(string $filePath, string $ext, string $uploadMode = 'single'): array
    {
        if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            return [
                'success' => false,
                'rows' => [],
                'message' => "PhpSpreadsheet library is not available. Please install dependencies."
            ];
        }

        try {
            $spreadsheet = null;
            if ($ext === 'xlsx') {
                try {
                    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                    $spreadsheet = $reader->load($filePath);
                } catch (\Throwable $e) {
                    $spreadsheet = null;
                }
            } elseif ($ext === 'xls') {
                try {
                    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
                    $spreadsheet = $reader->load($filePath);
                } catch (\Throwable $e) {
                    $spreadsheet = null;
                }
            }
            if ($spreadsheet === null) {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            }
            $sheetCount = $spreadsheet->getSheetCount();

            // Support multi-sheet workbooks where each sheet represents a section (e.g., CSE-A, CSE-B, etc.)
            if ($sheetCount > 1) {
                $allRows = [];
                $foundAnyValidSheet = false;
                $skipTitles = ['readme', 'read me', 'instructions', 'notes', 'help', 'info', 'guide', 'about'];
                foreach ($spreadsheet->getAllSheets() as $sheet) {
                    $sheetTitle = trim($sheet->getTitle());
                    if (in_array(strtolower($sheetTitle), $skipTitles, true)) {
                        continue;
                    }
                    $data = $sheet->toArray(null, true, true, false);
                    if (empty($data)) continue;

                    $res = $this->parseTableOrGridData($data, $sheetTitle);
                    if ($res['success'] && !empty($res['rows'])) {
                        $foundAnyValidSheet = true;
                        foreach ($res['rows'] as $r) {
                            if (empty($r['section']) && $this->matchSection($sheetTitle) > 0) {
                                $r['section'] = $sheetTitle;
                            }
                            $allRows[] = $r;
                        }
                    }
                }
                if ($foundAnyValidSheet && !empty($allRows)) {
                    return ['success' => true, 'rows' => $allRows];
                }
            }

            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'rows' => [],
                'message' => "Unable to read spreadsheet: " . $e->getMessage()
            ];
        }

        if (empty($data)) {
            return [
                'success' => false,
                'rows' => [],
                'message' => "Spreadsheet is empty."
            ];
        }

        return $this->parseTableOrGridData($data);
    }

    /**
     * Detect whether sheet data is Tabular (Day, Period, Subject, Faculty, Room)
     * or Matrix/Grid (Days as rows, Periods as columns)
     */
    private function parseTableOrGridData(array $rows, string $defaultSectionTitle = ''): array
    {
        // Filter out empty rows
        $cleanRows = [];
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $hasContent = false;
            foreach ($r as $c) {
                if ($c !== null && trim((string)$c) !== '') {
                    $hasContent = true;
                    break;
                }
            }
            if ($hasContent) {
                $cleanRows[] = array_map(function($v) {
                    return $v === null ? '' : trim((string)$v);
                }, $r);
            }
        }

        if (empty($cleanRows)) {
            return ['success' => false, 'rows' => [], 'message' => "Spreadsheet contains no readable rows."];
        }

        // Find header row
        $headerRowIdx = -1;
        $isTabular = false;
        $isGrid = false;
        $colMap = [];

        foreach ($cleanRows as $idx => $row) {
            $rowStr = strtolower(implode(' ', $row));
            // Check for tabular headers
            if (
                (strpos($rowStr, 'day') !== false || strpos($rowStr, 'period') !== false) &&
                (strpos($rowStr, 'subject') !== false || strpos($rowStr, 'course') !== false)
            ) {
                $headerRowIdx = $idx;
                $isTabular = true;
                break;
            }

            // Check for grid headers: period headers like P1, P2, P3 or 1, 2, 3, 4, 5...
            $periodHeaderCount = 0;
            foreach ($row as $cIdx => $cell) {
                $cleaned = trim(strtolower($cell));
                if (preg_match('/^(p[1-9]|period\s*[1-9]|[1-9])$/i', $cleaned)) {
                    $periodHeaderCount++;
                }
            }
            if ($periodHeaderCount >= 3) {
                $headerRowIdx = $idx;
                $isGrid = true;
                break;
            }
        }

        if ($isTabular && $headerRowIdx !== -1) {
            $header = $cleanRows[$headerRowIdx];
            foreach ($header as $cIdx => $name) {
                $norm = strtolower(trim($name));
                if (strpos($norm, 'day') !== false) $colMap['day'] = $cIdx;
                elseif (strpos($norm, 'period') !== false) $colMap['period'] = $cIdx;
                elseif (strpos($norm, 'start') !== false) $colMap['start_time'] = $cIdx;
                elseif (strpos($norm, 'end') !== false) $colMap['end_time'] = $cIdx;
                elseif (strpos($norm, 'subject') !== false || strpos($norm, 'course') !== false) $colMap['subject'] = $cIdx;
                elseif (strpos($norm, 'faculty') !== false || strpos($norm, 'teacher') !== false || strpos($norm, 'instructor') !== false) $colMap['faculty'] = $cIdx;
                elseif (strpos($norm, 'section') !== false || $norm === 'sec' || $norm === 'class') $colMap['section'] = $cIdx;
                elseif (strpos($norm, 'room') !== false || strpos($norm, 'classroom') !== false || strpos($norm, 'hall') !== false || strpos($norm, 'lab') !== false) $colMap['classroom'] = $cIdx;
                elseif (strpos($norm, 'status') !== false) $colMap['status'] = $cIdx;
            }

            $extracted = [];
            for ($i = $headerRowIdx + 1; $i < count($cleanRows); $i++) {
                $r = $cleanRows[$i];
                $dayVal = isset($colMap['day']) ? ($r[$colMap['day']] ?? '') : '';
                $periodVal = isset($colMap['period']) ? ($r[$colMap['period']] ?? '') : '';
                $subjectVal = isset($colMap['subject']) ? ($r[$colMap['subject']] ?? '') : '';
                $facultyVal = isset($colMap['faculty']) ? ($r[$colMap['faculty']] ?? '') : '';
                $roomVal = isset($colMap['classroom']) ? ($r[$colMap['classroom']] ?? '') : '';
                $statusVal = isset($colMap['status']) ? ($r[$colMap['status']] ?? '') : '';
                $sectionVal = isset($colMap['section']) ? ($r[$colMap['section']] ?? '') : $defaultSectionTitle;

                // Skip completely empty lines
                if (empty($dayVal) && empty($periodVal) && empty($subjectVal)) {
                    continue;
                }

                $extracted[] = [
                    'day' => $dayVal,
                    'period' => $periodVal,
                    'subject' => $subjectVal,
                    'faculty' => $facultyVal,
                    'classroom' => $roomVal,
                    'status' => $statusVal,
                    'section' => $sectionVal
                ];
            }

            return ['success' => true, 'rows' => $extracted];
        }

        if ($isGrid && $headerRowIdx !== -1) {
            return $this->parseGridRows($cleanRows, $headerRowIdx);
        }

        // If no explicit header recognized, try auto-inspecting first row as tabular
        if (count($cleanRows) > 1) {
            $first = $cleanRows[0];
            if (count($first) >= 4) {
                // Guess columns: Day, Period, Subject, Faculty, Classroom
                $extracted = [];
                // Check if row 0 is a header or data
                $startIdx = 0;
                if (!self::isDayName($first[0])) {
                    $startIdx = 1;
                }
                for ($i = $startIdx; $i < count($cleanRows); $i++) {
                    $r = $cleanRows[$i];
                    if (empty($r[0]) && empty($r[1]) && empty($r[2])) continue;
                    $extracted[] = [
                        'day' => $r[0] ?? '',
                        'period' => $r[1] ?? '',
                        'subject' => $r[2] ?? '',
                        'faculty' => $r[3] ?? '',
                        'classroom' => $r[4] ?? '',
                        'status' => $r[5] ?? ''
                    ];
                }
                if (!empty($extracted)) {
                    return ['success' => true, 'rows' => $extracted];
                }
            }
        }

        return [
            'success' => false,
            'rows' => [],
            'message' => "Could not identify timetable structure. Ensure columns for Day, Period, Subject, Faculty, and Room are present."
        ];
    }

    /**
     * Parse Matrix/Grid layout
     */
    private function parseGridRows(array $cleanRows, int $headerRowIdx): array
    {
        $header = $cleanRows[$headerRowIdx];
        $periodColMap = []; // colIndex => periodNumber (1..9)

        foreach ($header as $cIdx => $cell) {
            if (preg_match('/(?:p|period)?\s*([1-9])/i', $cell, $m)) {
                $periodColMap[$cIdx] = (int)$m[1];
            }
        }

        if (empty($periodColMap)) {
            return ['success' => false, 'rows' => [], 'message' => "No period columns (P1-P9) detected in grid header."];
        }

        $extracted = [];
        $validDays = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];

        for ($i = $headerRowIdx + 1; $i < count($cleanRows); $i++) {
            $row = $cleanRows[$i];
            $dayCell = trim($row[0] ?? '');
            $matchedDay = self::normalizeDay($dayCell);
            if (!$matchedDay) {
                continue;
            }

            foreach ($periodColMap as $cIdx => $pNum) {
                $cellVal = trim($row[$cIdx] ?? '');
                if (empty($cellVal) || $cellVal === '-' || strtolower($cellVal) === 'nil' || strtolower($cellVal) === 'free') {
                    continue;
                }

                // Cell content may contain subject, faculty, room separated by slash, comma, or newline
                $parsedCell = $this->parseCellComponents($cellVal);
                $extracted[] = [
                    'day' => $matchedDay,
                    'period' => (string)$pNum,
                    'subject' => $parsedCell['subject'],
                    'faculty' => $parsedCell['faculty'],
                    'classroom' => $parsedCell['classroom'],
                    'status' => 'PUBLISHED',
                    'section' => $cleanRows[$i]['section'] ?? ''
                ];
            }
        }

        return ['success' => true, 'rows' => $extracted];
    }

    /**
     * Parse cell components from a grid cell string, e.g. "DBMS / Prof. Priya Sharma / 36"
     */
    private function parseCellComponents(string $cellText): array
    {
        $lines = preg_split('/[\r\n|\/]+/', $cellText);
        $lines = array_values(array_filter(array_map('trim', $lines)));

        $subject = $lines[0] ?? '';
        $faculty = $lines[1] ?? '';
        $classroom = $lines[2] ?? '';

        // If only 1 line, check if it matches a known subject
        if (count($lines) === 1) {
            return ['subject' => $cellText, 'faculty' => '', 'classroom' => ''];
        }

        // If 2 lines, check if second line is a room or a faculty
        if (count($lines) === 2) {
            $testRoomKey = strtolower(trim($lines[1]));
            if (isset($this->classroomMap[$testRoomKey]) || preg_match('/^(lab|\d+)/i', $testRoomKey)) {
                $classroom = $lines[1];
                $faculty = '';
            }
        }

        return [
            'subject' => $subject,
            'faculty' => $faculty,
            'classroom' => $classroom
        ];
    }

    /**
     * Parse text-based PDF or scanned PDF
     */
    private function parsePdfFile(string $filePath): array
    {
        if (!class_exists('\\Smalot\\PdfParser\\Parser')) {
            return [
                'success' => false,
                'rows' => [],
                'message' => "Smalot PDF Parser is not available. Please install composer dependencies."
            ];
        }

        $text = '';
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'rows' => [],
                'message' => "PDF parsing error: " . $e->getMessage()
            ];
        }

        // Check if text is extractable or if it's a scanned PDF
        if (strlen(trim($text)) < 25) {
            // Check if OCR is available
            if (self::isTesseractAvailable()) {
                // If Tesseract is available, we could attempt rasterization if pdftoppm/ImageMagick is installed
                return [
                    'success' => false,
                    'rows' => [],
                    'message' => "This PDF contains scanned images without selectable text. Direct PDF OCR requires rasterization tools (pdftoppm). Please convert the PDF page to a PNG or JPG image, or upload an Excel (.xlsx, .xls) or CSV file."
                ];
            } else {
                return [
                    'success' => false,
                    'rows' => [],
                    'message' => "This PDF appears to be a scanned document with no selectable text. OCR engine (Tesseract) is not configured on this server to parse scanned documents. Please upload an Excel (.xlsx, .xls), CSV (.csv), or digital PDF file."
                ];
            }
        }

        return $this->parseTextLines($text);
    }

    /**
     * Parse Image (.png, .jpg, .jpeg) using Tesseract OCR
     */
    private function parseImageFile(string $filePath): array
    {
        $tesseractPath = self::getTesseractBinary();
        if (!$tesseractPath) {
            return [
                'success' => false,
                'rows' => [],
                'message' => "OCR engine (Tesseract) is not detected on this system. Image parsing (.png, .jpg, .jpeg) requires Tesseract OCR to be installed. Please upload an Excel (.xlsx, .xls), CSV (.csv), or digital PDF file."
            ];
        }

        if (!class_exists('\\thiagoalessio\\TesseractOCR\\TesseractOCR')) {
            return [
                'success' => false,
                'rows' => [],
                'message' => "Tesseract OCR PHP wrapper is missing. Please check composer dependencies."
            ];
        }

        try {
            $ocr = new \thiagoalessio\TesseractOCR\TesseractOCR($filePath);
            $ocr->executable($tesseractPath);
            $ocrText = $ocr->run();
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'rows' => [],
                'message' => "OCR extraction failed: " . $e->getMessage() . ". Please ensure the image is clear or use Excel/CSV."
            ];
        }

        if (empty(trim($ocrText))) {
            return [
                'success' => false,
                'rows' => [],
                'message' => "OCR could not detect any readable text in this image. Please upload a clearer image or use an Excel/CSV file."
            ];
        }

        return $this->parseTextLines($ocrText);
    }

    /**
     * Parse arbitrary extracted text lines from PDF or OCR
     */
    private function parseTextLines(string $text): array
    {
        $rawLines = preg_split('/[\r\n]+/', $text);
        $cleanLines = array_values(array_filter(array_map('trim', $rawLines)));

        $extracted = [];
        $currentDay = null;

        foreach ($cleanLines as $line) {
            // Check for Day line
            $dayMatch = self::normalizeDay($line);
            if ($dayMatch) {
                $currentDay = $dayMatch;
                continue;
            }

            // Check if line contains a Day + Period + details:
            // e.g. "Monday, P1, DBMS, Prof. Priya Sharma, 36"
            // or "MONDAY P1 DBMS Dr. Arun Kumar 36"
            // or comma / tab / pipe delimited
            $parts = preg_split('/[,|\t]+/', $line);
            if (count($parts) >= 3) {
                $dayPart = trim($parts[0]);
                $matchedDay = self::normalizeDay($dayPart) ?? $currentDay;

                if ($matchedDay) {
                    $periodPart = trim($parts[1]);
                    $subjectPart = trim($parts[2] ?? '');
                    $facultyPart = trim($parts[3] ?? '');
                    $roomPart = trim($parts[4] ?? '');
                    $statusPart = 'PUBLISHED';

                    // If period and subject were grouped together, e.g. "1 DBMS"
                    if (preg_match('/^(?:p|period)?\s*([1-9])\s+(.+)$/i', $periodPart, $splitPM)) {
                        $periodPart = $splitPM[1];
                        $roomPart = $facultyPart;
                        $facultyPart = $subjectPart;
                        $subjectPart = trim($splitPM[2]);
                    }

                    // If room part contains status, e.g. "36 PUBLISHED"
                    if (preg_match('/^(.*?)\s+(PUBLISHED|DRAFT)$/i', $roomPart, $rmm)) {
                        $roomPart = trim($rmm[1]);
                        $statusPart = strtoupper($rmm[2]);
                    } elseif (preg_match('/^(.*?)\s+(PUBLISHED|DRAFT)$/i', $facultyPart, $rmm) && empty($roomPart)) {
                        $facultyPart = trim($rmm[1]);
                        $statusPart = strtoupper($rmm[2]);
                    }

                    // Extract period number
                    if (preg_match('/(?:p|period)?\s*([1-9])/i', $periodPart, $pm)) {
                        $extracted[] = [
                            'day' => $matchedDay,
                            'period' => $pm[1],
                            'subject' => $subjectPart,
                            'faculty' => $facultyPart,
                            'classroom' => $roomPart,
                            'status' => $statusPart
                        ];
                        continue;
                    }
                }
            }

            // If we have a current day context, look for period markers:
            if ($currentDay) {
                // e.g., "P1: DBMS - Prof. Priya Sharma - Room 36"
                // or "Period 1: OS - Prof. Naveen Kumar"
                if (preg_match('/^(?:p|period)?\s*([1-9])\s*[:\-\s]+(.*)$/i', $line, $m)) {
                    $pNum = $m[1];
                    $rest = trim($m[2]);

                    $restParts = preg_split('/[\-–|\/]+/', $rest);
                    $sub = trim($restParts[0] ?? '');
                    $fac = trim($restParts[1] ?? '');
                    $room = trim($restParts[2] ?? '');

                    $extracted[] = [
                        'day' => $currentDay,
                        'period' => $pNum,
                        'subject' => $sub,
                        'faculty' => $fac,
                        'classroom' => $room,
                        'status' => 'PUBLISHED'
                    ];
                }
            }
        }

        if (empty($extracted)) {
            return [
                'success' => false,
                'rows' => [],
                'message' => "Could not extract structured timetable entries from text. Please ensure lines specify Day, Period (P1-P9), Subject, Faculty, and Classroom."
            ];
        }

        return ['success' => true, 'rows' => $extracted];
    }

    /**
     * Normalize a single extracted row and map text to database IDs
     */
    private function normalizeAndMapRow(array $raw, int $index, int $defaultSectionId, string $defaultStatus, string $uploadMode = 'single'): ?array
    {
        $rawDay = trim((string)($raw['day'] ?? ''));
        $rawPeriod = trim((string)($raw['period'] ?? ''));
        $rawSubject = trim((string)($raw['subject'] ?? ''));
        $rawFaculty = trim((string)($raw['faculty'] ?? ''));
        $rawClassroom = trim((string)($raw['classroom'] ?? ''));
        $rawStatus = strtoupper(trim((string)($raw['status'] ?? '')));
        $rawSection = trim((string)($raw['section'] ?? ''));

        // If completely empty, ignore
        if ($rawDay === '' && $rawPeriod === '' && $rawSubject === '') {
            return null;
        }

        $errors = [];
        $warnings = [];

        // 1. Day
        $day = self::normalizeDay($rawDay);
        if (!$day) {
            $errors[] = "Invalid day: '{$rawDay}' (must be Monday to Saturday)";
            $day = 'MONDAY'; // fallback placeholder
        }

        // 2. Period
        $periodId = 0;
        $periodNumber = 0;
        $startTime = '';
        $endTime = '';

        if (preg_match('/(?:p|period)?\s*([1-9])/i', $rawPeriod, $pm)) {
            $periodNumber = (int)$pm[1];
            if (isset($this->periodNumMap[$periodNumber])) {
                $periodId = $this->periodNumMap[$periodNumber];
                $startTime = $this->periods[$periodId]['start_time'] ?? '';
                $endTime = $this->periods[$periodId]['end_time'] ?? '';
            } else {
                $errors[] = "Period P{$periodNumber} is not defined in the periods table.";
            }
        } else {
            $errors[] = "Invalid period number: '{$rawPeriod}' (must be 1 to 9)";
        }

        // 3. Section
        $sectionId = $defaultSectionId;
        if (!empty($rawSection)) {
            $matchedSecId = $this->matchSection($rawSection);
            if ($matchedSecId > 0 && isset($this->sections[$matchedSecId])) {
                $sectionId = $matchedSecId;
            } else {
                $errors[] = "Invalid section: '{$rawSection}'. Accepted sections: CSE-A, CSE-B, CSE-C, CSE-D, CSE-E.";
            }
        } elseif ($uploadMode === 'all') {
            $errors[] = "Section not specified in row.";
        } elseif ($sectionId <= 0) {
            $errors[] = "Section not specified.";
        }

        // 4. Subject
        $subjectId = $this->matchSubject($rawSubject);
        if ($subjectId === 0) {
            $warnings[] = "Subject '{$rawSubject}' could not be matched with database subjects.";
        }

        // 5. Faculty
        $facultyId = $this->matchFaculty($rawFaculty);
        if ($facultyId === 0) {
            $warnings[] = "Faculty '{$rawFaculty}' could not be matched with database faculty.";
        }

        // 5. Validate that the selected faculty is assigned to the selected subject.
        // Do not guess when either value failed to map; the mapping warning above remains visible.
        if ($subjectId > 0 && $facultyId > 0 && $this->facultySubjectMapLoaded) {
            $assignmentKey = $facultyId . '_' . $subjectId;
            if (!isset($this->facultySubjectMap[$assignmentKey])) {
                $facultyName = $this->faculty[$facultyId]['faculty_name'] ?? $rawFaculty;
                $subjectCode = $this->subjects[$subjectId]['subject_code'] ?? $rawSubject;
                $errors[] = "{$facultyName} is not assigned to subject {$subjectCode} in faculty_subjects.";
            }
        }

        // 6. Classroom
        $classroomId = $this->matchClassroom($rawClassroom);
        if ($classroomId === 0) {
            $warnings[] = "Classroom/Lab '{$rawClassroom}' could not be matched with database rooms.";
        }

        // 7. Status
        $status = in_array($rawStatus, ['PUBLISHED', 'DRAFT'], true) ? $rawStatus : $defaultStatus;

        return [
            'row_index' => $index,
            'day' => $day,
            'period_id' => $periodId,
            'period_number' => $periodNumber,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'section_id' => $sectionId,
            'subject_id' => $subjectId,
            'faculty_id' => $facultyId,
            'classroom_id' => $classroomId,
            'status' => $status,
            'raw_day' => $rawDay,
            'raw_period' => $rawPeriod,
            'raw_subject' => $rawSubject,
            'raw_faculty' => $rawFaculty,
            'raw_classroom' => $rawClassroom,
            'raw_status' => $rawStatus,
            'raw_section' => $rawSection,
            'errors' => $errors,
            'warnings' => $warnings,
            'conflict' => null
        ];
    }

    /**
     * Detect conflicts:
     * 1. Internal duplicates within batch (same section/day/period, same faculty/day/period across sections, same room/day/period across sections)
     * 2. Database collisions (faculty or room booked by another section)
     * 3. Existing section entries in DB (for replacement prompt)
     */
    public function detectConflicts(array $rows, int $targetSectionId, string $conflictMode = 'replace', string $uploadMode = 'single'): array
    {
        $seenSectionSlots = []; // "SECID_DAY_P" => row_index
        $seenFacultySlots = []; // "FACID_DAY_P" => ['row_index' => X, 'section_id' => Y, 'section_name' => Z]
        $seenRoomSlots    = []; // "ROOMID_DAY_P" => ['row_index' => X, 'section_id' => Y, 'section_name' => Z]

        // Track all slots defined in this batch: "SECID_DAY_PID" => row
        $batchSlotsBySection = [];
        foreach ($rows as $r) {
            $sId = $r['section_id'] > 0 ? $r['section_id'] : $targetSectionId;
            $d = $r['day'];
            $pId = $r['period_id'];
            if ($sId > 0 && $d && $pId > 0) {
                $batchSlotsBySection["{$sId}_{$d}_{$pId}"] = $r;
            }
        }

        // First pass: internal batch conflicts
        foreach ($rows as $k => $r) {
            $day = $r['day'];
            $pNum = $r['period_number'];
            $secId = $r['section_id'] > 0 ? $r['section_id'] : $targetSectionId;
            $secName = $this->sections[$secId]['section_name'] ?? ($r['raw_section'] ?: ($secId > 0 ? "Sec #{$secId}" : "Unknown Section"));

            if (!$day || !$pNum) continue;

            // Section duplicate within batch
            if ($secId > 0) {
                $slotKey = "{$secId}_{$day}_{$pNum}";
                if (isset($seenSectionSlots[$slotKey])) {
                    $prevIdx = $seenSectionSlots[$slotKey];
                    $rows[$k]['errors'][] = "Duplicate slot in file: {$secName} {$day} Period {$pNum} already defined in Row {$prevIdx}.";
                } else {
                    $seenSectionSlots[$slotKey] = $r['row_index'];
                }
            }

            // Faculty duplicate within batch across any sections
            if ($r['faculty_id'] > 0) {
                $facKey = "{$r['faculty_id']}_{$day}_{$pNum}";
                if (isset($seenFacultySlots[$facKey])) {
                    $prev = $seenFacultySlots[$facKey];
                    $facName = $this->faculty[$r['faculty_id']]['faculty_name'] ?? 'Faculty';
                    $rows[$k]['errors'][] = "Faculty double-booked in file: {$facName} already assigned to {$prev['section_name']} at {$day} P{$pNum} in Row {$prev['row_index']}.";
                } else {
                    $seenFacultySlots[$facKey] = [
                        'row_index' => $r['row_index'],
                        'section_id' => $secId,
                        'section_name' => $secName
                    ];
                }
            }

            // Room duplicate within batch across any sections
            if ($r['classroom_id'] > 0) {
                $roomKey = "{$r['classroom_id']}_{$day}_{$pNum}";
                if (isset($seenRoomSlots[$roomKey])) {
                    $prev = $seenRoomSlots[$roomKey];
                    $roomNum = $this->classrooms[$r['classroom_id']]['room_number'] ?? 'Room';
                    $rows[$k]['errors'][] = "Classroom double-booked in file: Room {$roomNum} already assigned to {$prev['section_name']} at {$day} P{$pNum} in Row {$prev['row_index']}.";
                } else {
                    $seenRoomSlots[$roomKey] = [
                        'row_index' => $r['row_index'],
                        'section_id' => $secId,
                        'section_name' => $secName
                    ];
                }
            }
        }

        // Second pass: check against MySQL database
        foreach ($rows as $k => $r) {
            $day = $r['day'];
            $periodId = $r['period_id'];
            $secId = $r['section_id'] > 0 ? $r['section_id'] : $targetSectionId;
            $facId = $r['faculty_id'];
            $roomId = $r['classroom_id'];

            if (!$day || !$periodId) continue;

            // 1. Check if this section already has an entry in DB
            if ($secId > 0) {
                $stmt = $this->conn->prepare("
                    SELECT t.timetable_id, s.subject_code, s.subject_name, f.faculty_name, c.room_number, t.status
                    FROM timetable t
                    LEFT JOIN subjects s ON s.subject_id = t.subject_id
                    LEFT JOIN faculty f ON f.faculty_id = t.faculty_id
                    LEFT JOIN classrooms c ON c.classroom_id = t.classroom_id
                    WHERE t.section_id = ? AND t.day = ? AND t.period_id = ?
                    LIMIT 1
                ");
                if ($stmt) {
                    $stmt->bind_param("isi", $secId, $day, $periodId);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res && $res->num_rows > 0) {
                        $existing = $res->fetch_assoc();
                        $existingSub = !empty($existing['subject_code']) ? $existing['subject_code'] : 'Class';
                        $existingFac = !empty($existing['faculty_name']) ? $existing['faculty_name'] : 'Faculty';
                        $existingRm = !empty($existing['room_number']) ? $existing['room_number'] : 'Room';
                        $existingStat = !empty($existing['status']) ? $existing['status'] : 'PUBLISHED';

                        if ($conflictMode === 'skip') {
                            $rows[$k]['is_skipped'] = true;
                            $rows[$k]['conflict'] = [
                                'type' => 'existing_entry_skip',
                                'is_blocking' => false,
                                'timetable_id' => (int)$existing['timetable_id'],
                                'existing_subject' => $existingSub,
                                'existing_faculty' => $existingFac,
                                'existing_room' => $existingRm,
                                'existing_status' => $existingStat,
                                'message' => "Slot occupied: Section already has {$existingSub} ({$existingStat}) with {$existingFac} in Room {$existingRm}. Slot will be SKIPPED."
                            ];
                        } else {
                            $rows[$k]['is_skipped'] = false;
                            $rows[$k]['conflict'] = [
                                'type' => 'existing_entry_replace',
                                'is_blocking' => false,
                                'timetable_id' => (int)$existing['timetable_id'],
                                'existing_subject' => $existingSub,
                                'existing_faculty' => $existingFac,
                                'existing_room' => $existingRm,
                                'existing_status' => $existingStat,
                                'message' => "Will replace existing {$existingSub} ({$existingStat}) with {$existingFac} in Room {$existingRm}."
                            ];
                        }
                    }
                    $stmt->close();
                }
            }

            // If this slot already exists in DB and conflictMode is 'skip', it is marked skipped
            // and will NOT be imported. Do not check external collisions with other sections for this slot.
            if (!empty($rows[$k]['is_skipped'])) {
                continue;
            }

            // 2. Check if faculty is busy teaching another section in DB
            if ($facId > 0) {
                $stmt = $this->conn->prepare("
                    SELECT t.timetable_id, t.section_id, sec.section_name, s.subject_code
                    FROM timetable t
                    INNER JOIN sections sec ON sec.section_id = t.section_id
                    INNER JOIN subjects s ON s.subject_id = t.subject_id
                    WHERE t.faculty_id = ? AND t.day = ? AND t.period_id = ? AND t.section_id != ?
                    LIMIT 1
                ");
                if ($stmt) {
                    $stmt->bind_param("isii", $facId, $day, $periodId, $secId);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res && $res->num_rows > 0) {
                        $busy = $res->fetch_assoc();
                        $otherSecId = (int)$busy['section_id'];

                        // Check if this other section slot in DB is being replaced by the current batch
                        $otherSlotReplaced = false;
                        if ($conflictMode === 'replace' && isset($batchSlotsBySection["{$otherSecId}_{$day}_{$periodId}"])) {
                            $otherBatchRow = $batchSlotsBySection["{$otherSecId}_{$day}_{$periodId}"];
                            if ($otherBatchRow['faculty_id'] !== $facId) {
                                $otherSlotReplaced = true;
                            }
                        }

                        if (!$otherSlotReplaced) {
                            $facName = $this->faculty[$facId]['faculty_name'] ?? 'Faculty';
                            $rows[$k]['errors'][] = "Faculty double-booked: {$facName} is already teaching {$busy['subject_code']} for Section {$busy['section_name']} at {$day} P{$r['period_number']}.";
                        }
                    }
                    $stmt->close();
                }
            }

            // 3. Check if classroom is occupied by another section in DB
            if ($roomId > 0) {
                $stmt = $this->conn->prepare("
                    SELECT t.timetable_id, t.section_id, sec.section_name, s.subject_code
                    FROM timetable t
                    INNER JOIN sections sec ON sec.section_id = t.section_id
                    INNER JOIN subjects s ON s.subject_id = t.subject_id
                    WHERE t.classroom_id = ? AND t.day = ? AND t.period_id = ? AND t.section_id != ?
                    LIMIT 1
                ");
                if ($stmt) {
                    $stmt->bind_param("isii", $roomId, $day, $periodId, $secId);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res && $res->num_rows > 0) {
                        $occ = $res->fetch_assoc();
                        $otherSecId = (int)$occ['section_id'];

                        // Check if this other section slot in DB is being replaced by the current batch
                        $otherSlotReplaced = false;
                        if ($conflictMode === 'replace' && isset($batchSlotsBySection["{$otherSecId}_{$day}_{$periodId}"])) {
                            $otherBatchRow = $batchSlotsBySection["{$otherSecId}_{$day}_{$periodId}"];
                            if ($otherBatchRow['classroom_id'] !== $roomId) {
                                $otherSlotReplaced = true;
                            }
                        }

                        if (!$otherSlotReplaced) {
                            $roomNum = $this->classrooms[$roomId]['room_number'] ?? 'Room';
                            $rows[$k]['errors'][] = "Classroom collision: Room {$roomNum} is already occupied by Section {$occ['section_name']} ({$occ['subject_code']}) at {$day} P{$r['period_number']}.";
                        }
                    }
                    $stmt->close();
                }
            }
        }

        return $rows;
    }

    /**
     * Matching Helpers
     */
    public function matchSubject(string $input): int
    {
        $raw = strtolower(trim($input));
        if ($raw === '') return 0;

        // Exact code match
        if (isset($this->subjectCodeMap[$raw])) {
            return $this->subjectCodeMap[$raw];
        }

        // Exact name match
        if (isset($this->subjectNameMap[$raw])) {
            return $this->subjectNameMap[$raw];
        }

        // Substring / prefix check
        foreach ($this->subjects as $id => $s) {
            $code = strtolower($s['subject_code']);
            $name = strtolower($s['subject_name']);
            if (strpos($raw, $code) !== false || strpos($code, $raw) !== false) {
                return $id;
            }
            if (strpos($raw, $name) !== false || strpos($name, $raw) !== false) {
                return $id;
            }
        }

        return 0;
    }

    public function matchFaculty(string $input): int
    {
        $raw = strtolower(trim($input));
        if ($raw === '') return 0;

        // Strip prefixes like Dr., Prof., Mr., Mrs., Ms.
        $clean = preg_replace('/^(dr\.|prof\.|mr\.|mrs\.|ms\.)\s*/i', '', $raw);

        // Code match
        if (isset($this->facultyCodeMap[$raw])) {
            return $this->facultyCodeMap[$raw];
        }
        if (isset($this->facultyCodeMap[$clean])) {
            return $this->facultyCodeMap[$clean];
        }

        // Exact name match
        if (isset($this->facultyNameMap[$raw])) {
            return $this->facultyNameMap[$raw];
        }
        if (isset($this->facultyNameMap[$clean])) {
            return $this->facultyNameMap[$clean];
        }

        // Username match (e.g. from faculty_username column)
        if (isset($this->facultyUsernameMap[$raw])) {
            return $this->facultyUsernameMap[$raw];
        }
        if (isset($this->facultyUsernameMap[$clean])) {
            return $this->facultyUsernameMap[$clean];
        }

        // Substring check
        foreach ($this->faculty as $id => $f) {
            $fCode = strtolower($f['faculty_code']);
            $fName = strtolower(preg_replace('/^(dr\.|prof\.|mr\.|mrs\.|ms\.)\s*/i', '', $f['faculty_name']));
            if ($clean === $fName || strpos($fName, $clean) !== false || strpos($clean, $fName) !== false) {
                return $id;
            }
            if ($raw === $fCode) {
                return $id;
            }
        }

        return 0;
    }

    public function matchClassroom(string $input): int
    {
        $raw = strtolower(trim($input));
        if ($raw === '') return 0;

        // Direct room number match
        if (isset($this->classroomMap[$raw])) {
            return $this->classroomMap[$raw];
        }

        // Strip "room", "hall", "lab" variations
        $clean = trim(preg_replace('/^(room|hall|lab|classroom)\s*/i', '', $raw));
        if (isset($this->classroomMap[$clean])) {
            return $this->classroomMap[$clean];
        }

        // Check with "LAB-" or "LAB "
        if (preg_match('/lab\s*([0-9]+)/i', $raw, $m)) {
            $formatted = 'lab-' . $m[1];
            if (isset($this->classroomMap[$formatted])) {
                return $this->classroomMap[$formatted];
            }
        }

        foreach ($this->classrooms as $id => $c) {
            $num = strtolower($c['room_number']);
            if ($num === $raw || $num === $clean) {
                return $id;
            }
        }

        return 0;
    }

    public function matchSection(string $input): int
    {
        $raw = strtolower(trim($input));
        if ($raw === '') return 0;

        foreach ($this->sections as $id => $sec) {
            $sName = strtolower($sec['section_name']);
            if ($sName === $raw || strpos($raw, $sName) !== false) {
                return $id;
            }
            // Match "CSE A" -> "CSE-A"
            $hyphenated = str_replace(' ', '-', $raw);
            if ($sName === $hyphenated) {
                return $id;
            }
            // Match "CSE_A" -> "CSE-A"
            $underscored = str_replace('_', '-', $raw);
            if ($sName === $underscored) {
                return $id;
            }
            // If section is "CSE-A", allow matching "A", "Section A", "Sec A", "Sec-A"
            if (preg_match('/^cse-([a-e])$/i', $sName, $sm)) {
                $letter = $sm[1];
                if ($raw === $letter || $raw === "section $letter" || $raw === "sec $letter" || $raw === "sec-$letter") {
                    return $id;
                }
            }
        }

        return 0;
    }

    public static function normalizeDay(string $input): ?string
    {
        $val = strtoupper(trim($input));
        $map = [
            'MON' => 'MONDAY',
            'MONDAY' => 'MONDAY',
            'TUE' => 'TUESDAY',
            'TUES' => 'TUESDAY',
            'TUESDAY' => 'TUESDAY',
            'WED' => 'WEDNESDAY',
            'WEDNESDAY' => 'WEDNESDAY',
            'THU' => 'THURSDAY',
            'THUR' => 'THURSDAY',
            'THURS' => 'THURSDAY',
            'THURSDAY' => 'THURSDAY',
            'FRI' => 'FRIDAY',
            'FRIDAY' => 'FRIDAY',
            'SAT' => 'SATURDAY',
            'SATURDAY' => 'SATURDAY'
        ];

        return $map[$val] ?? null;
    }

    public static function isDayName(string $input): bool
    {
        return self::normalizeDay($input) !== null;
    }
}
