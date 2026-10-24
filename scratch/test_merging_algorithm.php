<?php
require_once "config/database.php";

$userId = 1; // HOD user
$days = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];

// 1. Fetch periods
$periods = [];
$res = $conn->query("SELECT period_id, period_number, start_time, end_time FROM periods ORDER BY period_number ASC");
while ($r = $res->fetch_assoc()) {
    $pNum = (int)$r['period_number'];
    $s = date('H:i', strtotime($r['start_time']));
    $e = date('H:i', strtotime($r['end_time']));
    $periods[$pNum] = [
        'period_id' => (int)$r['period_id'],
        'period_number' => $pNum,
        'start_time' => $s,
        'end_time' => $e,
        'formatted' => "$s - $e"
    ];
}

// 2. Fetch HOD timetable entries
$sql = "
    SELECT
        t.timetable_id,
        t.day,
        t.status,
        p.period_number,
        p.start_time,
        p.end_time,
        sec.section_id,
        sec.section_name,
        s.subject_id,
        s.subject_code,
        s.subject_name,
        s.subject_type,
        f.faculty_id,
        f.faculty_code,
        f.faculty_name,
        c.classroom_id,
        c.room_number,
        c.room_type
    FROM faculty f
    INNER JOIN timetable t ON t.faculty_id = f.faculty_id
    INNER JOIN periods p ON p.period_id = t.period_id
    INNER JOIN sections sec ON sec.section_id = t.section_id
    INNER JOIN subjects s ON s.subject_id = t.subject_id
    INNER JOIN classrooms c ON c.classroom_id = t.classroom_id
    WHERE f.user_id = ?
    ORDER BY
        FIELD(UPPER(t.day), 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'),
        p.period_number
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();

$grid = [];
foreach ($days as $d) {
    $grid[$d] = [];
}
while ($row = $res->fetch_assoc()) {
    $d = strtoupper($row['day']);
    $p = (int)$row['period_number'];
    $grid[$d][$p] = $row;
}

echo "TIMETABLE GRID ENTRIES LOADED:\n";
foreach ($days as $d) {
    echo "$d: ";
    for ($p = 1; $p <= 9; $p++) {
        if (isset($grid[$d][$p])) {
            echo "[P$p: {$grid[$d][$p]['subject_code']}] ";
        } else {
            echo "[P$p: -] ";
        }
    }
    echo "\n";
}

echo "\n--- SIMULATING HTML TABLE ROW RENDERING ---\n";

function areEntriesEqual($a, $b) {
    if (!$a || !$b) return false;
    return ($a['subject_id'] === $b['subject_id'] &&
            $a['section_id'] === $b['section_id'] &&
            $a['classroom_id'] === $b['classroom_id']);
}

foreach ($days as $dayIdx => $dayName) {
    echo "\nRow for $dayName:\n";
    echo "  <th>$dayName</th>\n";
    
    // Block 1: P1 to P6
    $skipUntil = 0;
    for ($p = 1; $p <= 6; $p++) {
        if ($p <= $skipUntil) {
            continue;
        }
        $entry = $grid[$dayName][$p] ?? null;
        if ($entry) {
            $span = 1;
            for ($nextP = $p + 1; $nextP <= 6; $nextP++) {
                $nextEntry = $grid[$dayName][$nextP] ?? null;
                if ($nextEntry && areEntriesEqual($entry, $nextEntry)) {
                    $span++;
                    $skipUntil = $nextP;
                } else {
                    break;
                }
            }
            echo "  <td colspan='$span'> CLASS: {$entry['subject_code']} - {$entry['subject_name']} ({$entry['room_number']}) </td>\n";
        } else {
            echo "  <td colspan='1'> - </td>\n";
        }
    }
    
    // Lunch column (on row 0 with rowspan 6)
    if ($dayIdx === 0) {
        echo "  <td rowspan='6'> LUNCH BREAK </td>\n";
    }
    
    // Block 2: P7 to P9
    $skipUntil = 0;
    for ($p = 7; $p <= 9; $p++) {
        if ($p <= $skipUntil) {
            continue;
        }
        $entry = $grid[$dayName][$p] ?? null;
        if ($entry) {
            $span = 1;
            for ($nextP = $p + 1; $nextP <= 9; $nextP++) {
                $nextEntry = $grid[$dayName][$nextP] ?? null;
                if ($nextEntry && areEntriesEqual($entry, $nextEntry)) {
                    $span++;
                    $skipUntil = $nextP;
                } else {
                    break;
                }
            }
            echo "  <td colspan='$span'> CLASS: {$entry['subject_code']} - {$entry['subject_name']} ({$entry['room_number']}) </td>\n";
        } else {
            echo "  <td colspan='1'> - </td>\n";
        }
    }
}
