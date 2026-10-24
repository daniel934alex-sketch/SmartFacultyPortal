<?php
require_once __DIR__ . '/../config/database.php';

$userId = 2; // user_id for faculty01

// Faculty query
$stmt = $conn->prepare("
    SELECT
        f.faculty_id,
        f.faculty_code,
        f.faculty_name,
        f.designation,
        f.email,
        f.phone,
        d.department_name,
        d.department_code
    FROM faculty f
    LEFT JOIN departments d ON d.department_id = f.department_id
    WHERE f.user_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$faculty = $stmt->get_result()->fetch_assoc();
$facultyId = (int)$faculty['faculty_id'];
$stmt->close();

echo "Faculty: {$faculty['faculty_name']} ({$faculty['faculty_code']}) - {$faculty['designation']} - {$faculty['department_name']}\n";

// Academic info query
$academicStmt = $conn->prepare("
    SELECT DISTINCT
        ac.academic_year,
        ac.semester_number,
        ac.year_number
    FROM timetable t
    INNER JOIN sections sec ON sec.section_id = t.section_id
    INNER JOIN academic_classes ac ON ac.class_id = sec.class_id
    WHERE t.faculty_id = ?
    LIMIT 1
");
$academicStmt->bind_param("i", $facultyId);
$academicStmt->execute();
$academic = $academicStmt->get_result()->fetch_assoc();
$academicStmt->close();

if (!$academic) {
    $fallbackRes = $conn->query("SELECT academic_year, semester_number, year_number FROM academic_classes ORDER BY class_id DESC LIMIT 1");
    $academic = $fallbackRes->fetch_assoc();
}

echo "Academic: AY {$academic['academic_year']}, Sem {$academic['semester_number']}, Year {$academic['year_number']}\n";

// Periods
$periods = [];
$res = $conn->query("SELECT period_id, period_number, start_time, end_time FROM periods ORDER BY period_number ASC");
while ($row = $res->fetch_assoc()) {
    $periods[(int)$row['period_number']] = [
        'period_id' => (int)$row['period_id'],
        'period_number' => (int)$row['period_number'],
        'start_time' => $row['start_time'],
        'end_time' => $row['end_time'],
        'formatted_time' => date('H:i', strtotime($row['start_time'])) . '–' . date('H:i', strtotime($row['end_time']))
    ];
}

echo "Periods count: " . count($periods) . "\n";

// Timetable
$days = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];
$grid = [];
foreach ($days as $d) {
    $grid[$d] = [];
}

$stmt = $conn->prepare("
    SELECT
        t.timetable_id,
        t.day,
        t.period_id,
        t.status,
        p.period_number,
        p.start_time,
        p.end_time,
        s.subject_id,
        s.subject_code,
        s.subject_name,
        s.subject_type,
        sec.section_id,
        sec.section_name,
        c.classroom_id,
        c.room_number,
        c.room_type,
        c.floor
    FROM timetable t
    INNER JOIN periods p ON p.period_id = t.period_id
    INNER JOIN subjects s ON s.subject_id = t.subject_id
    INNER JOIN sections sec ON sec.section_id = t.section_id
    INNER JOIN classrooms c ON c.classroom_id = t.classroom_id
    WHERE t.faculty_id = ?
      AND t.status = 'PUBLISHED'
    ORDER BY
        FIELD(t.day, 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'),
        p.period_number
");
$stmt->bind_param("i", $facultyId);
$stmt->execute();
$res = $stmt->get_result();

$assignedCount = 0;
$subjectSummary = [];

while ($row = $res->fetch_assoc()) {
    $day = strtoupper($row['day']);
    $pNum = (int)$row['period_number'];
    $grid[$day][$pNum] = $row;
    $assignedCount++;
    
    $sCode = $row['subject_code'];
    if (!isset($subjectSummary[$sCode])) {
        $subjectSummary[$sCode] = [
            'subject_code' => $row['subject_code'],
            'subject_name' => $row['subject_name'],
            'subject_type' => $row['subject_type'],
            'sections' => [],
            'rooms' => [],
            'count' => 0
        ];
    }
    $subjectSummary[$sCode]['count']++;
    if (!in_array($row['section_name'], $subjectSummary[$sCode]['sections'])) {
        $subjectSummary[$sCode]['sections'][] = $row['section_name'];
    }
    if (!in_array($row['room_number'], $subjectSummary[$sCode]['rooms'])) {
        $subjectSummary[$sCode]['rooms'][] = $row['room_number'];
    }
}
$stmt->close();

echo "Assigned Slots Count: {$assignedCount}\n";
echo "Subject Summary:\n";
print_r($subjectSummary);
