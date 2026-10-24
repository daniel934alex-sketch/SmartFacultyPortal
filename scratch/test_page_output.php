<?php
// Mock session for CLI test
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'hod.cse';
$_SESSION['role'] = 'HOD';

require_once "config/database.php";

$userId = (int)$_SESSION['user_id'];

// 1. Fetch HOD faculty record
$facultySql = "
    SELECT
        f.faculty_id,
        f.faculty_code,
        f.faculty_name,
        f.designation,
        f.is_hod,
        f.department_id,
        d.department_name,
        d.department_code
    FROM faculty f
    LEFT JOIN departments d ON d.department_id = f.department_id
    WHERE f.user_id = ?
    LIMIT 1
";
$facultyStmt = $conn->prepare($facultySql);
$facultyStmt->bind_param("i", $userId);
$facultyStmt->execute();
$facultyResult = $facultyStmt->get_result();
$faculty = $facultyResult->fetch_assoc() ?? [];
$facultyStmt->close();

$facultyId   = (int)($faculty['faculty_id'] ?? 0);
$facultyName = $faculty['faculty_name'] ?? 'Faculty Member';
$facultyCode = $faculty['faculty_code'] ?? 'CSE001';
$designation = $faculty['designation'] ?? 'Professor';
if (!empty($faculty['is_hod']) && stripos($designation, 'HOD') === false && stripos($designation, 'Head') === false) {
    $designation .= ' & Head of Department';
}
$deptName = !empty($faculty['department_name']) 
    ? $faculty['department_name'] 
    : 'Computer Science and Engineering';

// 2. Fetch all periods
$periods = [];
$periodRes = $conn->query("SELECT period_id, period_number, start_time, end_time FROM periods ORDER BY period_number ASC");
if ($periodRes) {
    while ($pRow = $periodRes->fetch_assoc()) {
        $num = (int)$pRow['period_number'];
        $sTime = date('H:i', strtotime($pRow['start_time']));
        $eTime = date('H:i', strtotime($pRow['end_time']));
        $periods[$num] = [
            'period_id'     => (int)$pRow['period_id'],
            'period_number' => $num,
            'start_time'    => $sTime,
            'end_time'      => $eTime,
            'formatted'     => $sTime . ' - ' . $eTime
        ];
    }
}

$defaultTimings = [
    1 => '08:30 - 09:15',
    2 => '09:15 - 10:00',
    3 => '10:00 - 10:45',
    4 => '11:00 - 11:45',
    5 => '11:45 - 12:30',
    6 => '12:30 - 13:15',
    7 => '14:00 - 14:45',
    8 => '14:45 - 15:30',
    9 => '15:45 - 16:30'
];
for ($i = 1; $i <= 9; $i++) {
    if (!isset($periods[$i])) {
        $parts = explode(' - ', $defaultTimings[$i]);
        $periods[$i] = [
            'period_id'     => $i,
            'period_number' => $i,
            'start_time'    => $parts[0],
            'end_time'      => $parts[1],
            'formatted'     => $defaultTimings[$i]
        ];
    }
}

// 3. Sections Assigned
$selectedSectionId = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
$sectionsAssigned = [];
$secStmt = $conn->prepare("
    SELECT DISTINCT sec.section_id, sec.section_name, ac.year_number, ac.semester_number, ac.academic_year
    FROM timetable t
    JOIN sections sec ON t.section_id = sec.section_id
    JOIN academic_classes ac ON sec.class_id = ac.class_id
    WHERE t.faculty_id = ?
    ORDER BY sec.section_name
");
if ($secStmt) {
    $secStmt->bind_param("i", $facultyId);
    $secStmt->execute();
    $secRes = $secStmt->get_result();
    while ($sRow = $secRes->fetch_assoc()) {
        $sectionsAssigned[$sRow['section_id']] = $sRow;
    }
    $secStmt->close();
}

// 4. Timetable Entries
$days = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];
$grid = [];
foreach ($days as $d) {
    $grid[$d] = [];
}

$ttSql = "
    SELECT
        t.timetable_id,
        t.day,
        t.status,
        p.period_number,
        p.start_time,
        p.end_time,
        sec.section_id,
        sec.section_name,
        ac.year_number,
        ac.semester_number,
        ac.academic_year,
        s.subject_id,
        s.subject_code,
        s.subject_name,
        s.subject_type,
        c.classroom_id,
        c.room_number,
        c.room_type
    FROM timetable t
    INNER JOIN periods p ON p.period_id = t.period_id
    INNER JOIN sections sec ON sec.section_id = t.section_id
    INNER JOIN academic_classes ac ON ac.class_id = sec.class_id
    INNER JOIN subjects s ON s.subject_id = t.subject_id
    INNER JOIN classrooms c ON c.classroom_id = t.classroom_id
    WHERE t.faculty_id = ?
      " . ($selectedSectionId > 0 ? "AND t.section_id = ?" : "") . "
    ORDER BY
        FIELD(UPPER(t.day), 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'),
        p.period_number
";

$ttStmt = $conn->prepare($ttSql);
if ($selectedSectionId > 0) {
    $ttStmt->bind_param("ii", $facultyId, $selectedSectionId);
} else {
    $ttStmt->bind_param("i", $facultyId);
}
$ttStmt->execute();
$ttRes = $ttStmt->get_result();

$timetableRows = [];
$classroomsList = [];
$academicYearsList = [];
$yearSemSecList = [];
$subjectSummary = [];

while ($row = $ttRes->fetch_assoc()) {
    $timetableRows[] = $row;
    $day = strtoupper($row['day']);
    $pNum = (int)$row['period_number'];
    if (isset($grid[$day])) {
        $grid[$day][$pNum] = $row;
    }

    if (!in_array($row['room_number'], $classroomsList)) {
        $classroomsList[] = $row['room_number'];
    }
    if (!empty($row['academic_year']) && !in_array($row['academic_year'], $academicYearsList)) {
        $academicYearsList[] = $row['academic_year'];
    }

    $romanYears = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV'];
    $romanSem = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII'];
    $yStr = isset($romanYears[$row['year_number']]) ? $romanYears[$row['year_number']] . ' Year' : 'Year ' . $row['year_number'];
    $sStr = isset($romanSem[$row['semester_number']]) ? $romanSem[$row['semester_number']] . ' Sem' : 'Sem ' . $row['semester_number'];
    $combo = $yStr . ' / ' . $sStr . ' / ' . $row['section_name'];
    if (!in_array($combo, $yearSemSecList)) {
        $yearSemSecList[] = $combo;
    }

    $sCode = $row['subject_code'];
    if (!isset($subjectSummary[$sCode])) {
        $subjectSummary[$sCode] = [
            'code'     => $row['subject_code'],
            'name'     => $row['subject_name'],
            'type'     => $row['subject_type'],
            'sections' => [],
            'rooms'    => [],
            'hours'    => 0
        ];
    }
    $subjectSummary[$sCode]['hours']++;
    if (!in_array($row['section_name'], $subjectSummary[$sCode]['sections'])) {
        $subjectSummary[$sCode]['sections'][] = $row['section_name'];
    }
    if (!in_array($row['room_number'], $subjectSummary[$sCode]['rooms'])) {
        $subjectSummary[$sCode]['rooms'][] = $row['room_number'];
    }
}
$ttStmt->close();

$academicYearDisplay = !empty($academicYearsList) ? implode(', ', $academicYearsList) : '2026-2027';
$yearSemSecDisplay = !empty($yearSemSecList) ? implode('; ', $yearSemSecList) : 'II Year / III Sem / CSE-A';
$classroomDisplay = !empty($classroomsList) ? implode(', ', $classroomsList) : '36 / LAB-2';

function areConsecutiveEntriesEqual($a, $b) {
    if (!$a || !$b) return false;
    return ($a['subject_id'] === $b['subject_id'] &&
            $a['section_id'] === $b['section_id'] &&
            $a['classroom_id'] === $b['classroom_id']);
}

echo "Everything compiled successfully. Total rows: " . count($timetableRows) . "\n";
