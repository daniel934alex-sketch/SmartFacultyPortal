<?php
/**
 * HOD Attendance View — Smart Faculty Assistant Portal
 * Read-only daily attendance by section and period.
 * No INSERT, UPDATE, or DELETE statements are used here.
 */
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/role-check.php';
requireRole('HOD');
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'HOD Attendance';

function hodAttEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$sections = [];
$periods = [];
$students = [];
$lesson = null;
$error = '';
$counts = ['PRESENT' => 0, 'ABSENT' => 0, 'OD' => 0, 'UNMARKED' => 0];

$selectedSection = filter_input(INPUT_GET, 'section_id', FILTER_VALIDATE_INT) ?: 0;
$selectedPeriod = filter_input(INPUT_GET, 'period_id', FILTER_VALIDATE_INT) ?: 0;
$selectedDate = (string) ($_GET['date'] ?? date('Y-m-d'));
$parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $selectedDate);
$dateIsValid = $parsedDate !== false && $parsedDate->format('Y-m-d') === $selectedDate;
$wasSubmitted = isset($_GET['load']);

try {
    $result = $conn->query('SELECT section_id, section_name FROM sections ORDER BY section_name');
    while ($row = $result->fetch_assoc()) {
        $sections[] = $row;
    }

    $result = $conn->query('SELECT period_id, period_number, start_time, end_time FROM periods ORDER BY period_number');
    while ($row = $result->fetch_assoc()) {
        $periods[] = $row;
    }

    if ($wasSubmitted) {
        if (!$dateIsValid || $selectedSection <= 0 || $selectedPeriod <= 0) {
            $error = 'Choose a valid date, section, and period.';
        } else {
            $day = strtoupper($parsedDate->format('l'));
            $stmt = $conn->prepare(<<<'SQL'
                SELECT t.timetable_id, t.section_id, t.status,
                       s.subject_code, s.subject_name,
                       f.faculty_name, c.room_number
                FROM timetable AS t
                LEFT JOIN subjects AS s ON s.subject_id = t.subject_id
                LEFT JOIN faculty AS f ON f.faculty_id = t.faculty_id
                LEFT JOIN classrooms AS c ON c.classroom_id = t.classroom_id
                WHERE t.section_id = ? AND UPPER(t.day) = ?
                  AND t.period_id = ? AND t.status = 'PUBLISHED'
                ORDER BY t.timetable_id DESC
                LIMIT 1
                SQL
            );
            $stmt->bind_param('isi', $selectedSection, $day, $selectedPeriod);
            $stmt->execute();
            $lesson = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();

            if ($lesson !== null) {
                $timetableId = (int) $lesson['timetable_id'];
                $stmt = $conn->prepare(<<<'SQL'
                    SELECT st.student_id, st.register_number, st.student_name,
                           a.status AS attendance_status, a.marked_at
                    FROM students AS st
                    LEFT JOIN attendance AS a
                      ON a.student_id = st.student_id
                     AND a.timetable_id = ?
                     AND a.attendance_date = ?
                    WHERE st.section_id = ?
                    ORDER BY st.register_number, st.student_id
                    SQL
                );
                $stmt->bind_param('isi', $timetableId, $selectedDate, $selectedSection);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($student = $result->fetch_assoc()) {
                    $status = strtoupper(trim((string) ($student['attendance_status'] ?? '')));
                    if (!in_array($status, ['PRESENT', 'ABSENT', 'OD'], true)) {
                        $status = 'UNMARKED';
                    }
                    $student['display_status'] = $status;
                    $counts[$status]++;
                    $students[] = $student;
                }
                $stmt->close();
            }
        }
    }
} catch (Throwable $e) {
    error_log('HOD attendance error: ' . $e->getMessage());
    $error = 'Unable to load attendance. Please check the database connection and table structure.';
}

$sectionLabel = 'Selected section';
foreach ($sections as $section) {
    if ((int) $section['section_id'] === $selectedSection) {
        $sectionLabel = $section['section_name'];
        break;
    }
}
$periodLabel = 'Selected period';
foreach ($periods as $period) {
    if ((int) $period['period_id'] === $selectedPeriod) {
        $periodLabel = 'Period ' . $period['period_number'];
        break;
    }
}
$total = count($students);
$marked = $counts['PRESENT'] + $counts['ABSENT'] + $counts['OD'];
$completion = $total > 0 ? round(100 * $marked / $total) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>HOD Attendance | Smart Faculty Portal</title>
<style>
:root{color-scheme:dark;--bg:#0c1220;--nav:#111a2b;--panel:#172235;--panel2:#1d2a40;--line:#30415c;--ink:#f0f4ff;--muted:#a9b7cf;--accent:#9a83ff}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.55 'Segoe UI',Arial,sans-serif}a{color:inherit}
.layout{display:grid;grid-template-columns:242px minmax(0,1fr);min-height:100vh}
.sidebar{background:var(--nav);border-right:1px solid #27344a;padding:26px 18px}.brand{display:flex;gap:12px;align-items:center;font-size:17px;font-weight:800;line-height:1.3;padding:8px 10px 32px}.brand-icon{width:40px;height:40px;display:grid;place-items:center;border-radius:12px;background:#4d3c85;color:white;font-size:21px}.side-label{padding:0 12px;color:#7e8da9;font-size:11px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;margin-bottom:10px}.sidebar nav{display:grid;gap:5px}.sidebar nav a{display:block;padding:12px 14px;border-radius:10px;color:#abbad0;text-decoration:none;font-weight:600}.sidebar nav a:hover,.sidebar nav a.active{background:#30294f;color:#e0d5ff}.side-note{border-top:1px solid #2c3b52;padding:18px 12px;margin-top:22px;color:#8191ac;font-size:12px}
.main{min-width:0}.topbar{min-height:81px;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:17px 38px;border-bottom:1px solid #27344a}.eyebrow{font-size:11px;color:#a18af8;font-weight:800;letter-spacing:.15em;text-transform:uppercase}.topbar strong{font-size:17px}.topbar a{font-size:13px;text-decoration:none;border:1px solid #485775;border-radius:9px;padding:9px 13px;color:#d8e2f5}.content{max-width:1390px;padding:34px 38px 60px;margin:0 auto}.hero{background:linear-gradient(115deg,#23224a,#3b306c 65%,#172235);border:1px solid #544581;border-radius:20px;padding:30px 34px;margin-bottom:24px}.hero h1{margin:6px 0;font-size:30px;line-height:1.25}.hero p{margin:0;color:#cac9e8}.hero .eyebrow{color:#beadff}
.card{border:1px solid var(--line);border-radius:17px;background:var(--panel);padding:24px;margin-bottom:22px;box-shadow:0 12px 26px #060b160f}.card-title{margin:0 0 16px;font-size:19px;letter-spacing:-.02em}.hint{color:var(--muted);font-size:13px;margin:4px 0 19px}.filters{display:grid;grid-template-columns:1fr 1fr 1fr auto;align-items:end;gap:14px}.field label{display:block;margin-bottom:7px;color:#c5d2e8;font-weight:650;font-size:13px}input,select{width:100%;min-height:44px;padding:10px 12px;border:1px solid #4a5c77;background:#101b2b;color:var(--ink);border-radius:10px;outline:none;font:inherit}input:focus,select:focus{border-color:#b7a4ff;box-shadow:0 0 0 3px #9a83ff24}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:10px 20px;border:0;border-radius:10px;background:var(--accent);color:#101225;font-weight:800;font:inherit;font-weight:750;cursor:pointer}.btn:hover{background:#b29eff}.notice{border-radius:12px;padding:14px 16px;border:1px solid #814a5c;background:#3d202e;color:#ffd4de;margin-bottom:20px}
.lesson{display:flex;justify-content:space-between;flex-wrap:wrap;gap:15px;align-items:center;padding:17px 20px;background:var(--panel2);border:1px solid #35465e;border-radius:13px;margin-bottom:22px}.lesson strong{display:block;font-size:18px}.lesson small{color:var(--muted)}.lesson .pill{font-size:12px;border:1px solid #6d5d9f;color:#d5c7ff;padding:7px 11px;border-radius:999px}.stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:13px;margin-bottom:22px}.stat{border-radius:14px;border:1px solid #36465e;background:var(--panel);padding:18px}.stat .k{font-size:12px;color:var(--muted);font-weight:650}.stat .v{display:block;font-size:31px;font-weight:800;margin-top:4px}.stat.present .v{color:#76e0a4}.stat.absent .v{color:#ffa1ad}.stat.od .v{color:#ffda86}.stat.unmarked .v{color:#bcadff}
.table-wrap{overflow-x:auto}table{border-collapse:collapse;width:100%;min-width:620px}th,td{padding:13px 15px;text-align:left;border-bottom:1px solid #324159}th{background:#24324b;color:#c9d4e8;font-size:12px;text-transform:uppercase;letter-spacing:.04em}td{color:#e1e9f6}tbody tr:hover{background:#1f304b}tbody tr:last-child td{border-bottom:0}.status{display:inline-flex;padding:5px 11px;border-radius:999px;font-size:12px;font-weight:800;letter-spacing:.02em}.status.present{background:#123b31;color:#8aecb5}.status.absent{background:#4a2632;color:#ffadbe}.status.od{background:#443b25;color:#ffde91}.status.unmarked{background:#303451;color:#c6bcff}.empty{text-align:center;padding:50px 18px;color:var(--muted)}.empty strong{display:block;color:#e7eeff;font-size:19px;margin-bottom:7px}.empty-icon{font-size:34px;margin-bottom:9px}
@media(max-width:1100px){.stats{grid-template-columns:repeat(3,minmax(0,1fr))}.filters{grid-template-columns:repeat(2,minmax(0,1fr))}.filters .btn{width:100%}}
@media(max-width:760px){.layout{display:block}.sidebar{padding:14px 18px;border-right:0;border-bottom:1px solid #27344a}.brand{padding:0 0 10px}.sidebar nav{display:flex;flex-wrap:wrap}.sidebar nav a{padding:7px 12px}.side-label,.side-note{display:none}.topbar{padding:16px 20px}.content{padding:22px 15px}.hero{padding:23px 22px}.hero h1{font-size:24px}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.filters{grid-template-columns:1fr}.card{padding:18px}}
</style>
</head>
<body>
<div class="layout">
    <aside class="sidebar" aria-label="HOD navigation">
        <div class="brand"><span class="brand-icon">SF</span><span>Smart Faculty<br>Portal</span></div>
        <div class="side-label">HOD Workspace</div>
        <nav>
            <a href="dashboard.php">Overview</a>
            <a href="attendance.php" class="active" aria-current="page">Student Attendance</a>
            <a href="reports.php">Attendance Reports</a>
        </nav>
        <div class="side-note">Read-only attendance monitoring. Faculty members record attendance in their own portal.</div>
    </aside>
    <div class="main">
        <header class="topbar"><div><div class="eyebrow">HOD / Attendance</div><strong>Student Attendance</strong></div><a href="dashboard.php">Back to Dashboard</a></header>
        <main class="content">
            <section class="hero"><div class="eyebrow">Monitor classes</div><h1>Student Attendance</h1><p>Review marked attendance for a section, date, and period.</p></section>
            <section class="card">
                <h2 class="card-title">Find Attendance</h2>
                <p class="hint">Choose a published class to see its students and saved attendance statuses.</p>
                <form method="get" class="filters">
                    <div class="field"><label for="section_id">Section</label><select id="section_id" name="section_id" required><option value="">Select section</option>
                    <?php foreach ($sections as $s): ?><option value="<?= (int) $s['section_id'] ?>" <?= $selectedSection === (int) $s['section_id'] ? 'selected' : '' ?>><?= hodAttEscape($s['section_name']) ?></option><?php endforeach; ?>
                    </select></div>
                    <div class="field"><label for="date">Attendance Date</label><input type="date" id="date" name="date" value="<?= hodAttEscape($dateIsValid ? $selectedDate : date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>" required></div>
                    <div class="field"><label for="period_id">Period</label><select id="period_id" name="period_id" required><option value="">Select period</option>
                    <?php foreach ($periods as $p): ?><option value="<?= (int) $p['period_id'] ?>" <?= $selectedPeriod === (int) $p['period_id'] ? 'selected' : '' ?>>P<?= (int) $p['period_number'] ?> (<?= hodAttEscape(substr((string) $p['start_time'], 0, 5)) ?>–<?= hodAttEscape(substr((string) $p['end_time'], 0, 5)) ?>)</option><?php endforeach; ?>
                    </select></div>
                    <button type="submit" name="load" value="1" class="btn">Load Attendance</button>
                </form>
            </section>
            <?php if ($error !== ''): ?>
                <div class="notice" role="alert"><?= hodAttEscape($error) ?></div>
            <?php elseif (!$wasSubmitted): ?>
                <section class="card empty"><div class="empty-icon">▦</div><strong>Ready to review attendance</strong><div>Select a section, date, and period above to get started.</div></section>
            <?php elseif ($lesson === null): ?>
                <section class="card empty"><div class="empty-icon">◇</div><strong>No published class found</strong><div>No published timetable entry matches <?= hodAttEscape($sectionLabel) ?>, <?= hodAttEscape($selectedDate) ?>, <?= hodAttEscape($periodLabel) ?>.</div></section>
            <?php else: ?>
                <div class="lesson"><div><small><?= hodAttEscape($sectionLabel) ?> · <?= hodAttEscape($periodLabel) ?> · <?= hodAttEscape($selectedDate) ?></small><strong><?= hodAttEscape($lesson['subject_name'] ?: ($lesson['subject_code'] ?: 'Subject not set')) ?></strong><small>Faculty: <?= hodAttEscape($lesson['faculty_name'] ?: 'Not assigned') ?> · Room: <?= hodAttEscape($lesson['room_number'] ?: 'Not assigned') ?></small></div><span class="pill">Published class</span></div>
                <section class="stats" aria-label="Attendance summary">
                    <div class="stat"><span class="k">Total Students</span><span class="v"><?= $total ?></span></div>
                    <div class="stat present"><span class="k">Present</span><span class="v"><?= $counts['PRESENT'] ?></span></div>
                    <div class="stat absent"><span class="k">Absent</span><span class="v"><?= $counts['ABSENT'] ?></span></div>
                    <div class="stat od"><span class="k">On Duty (OD)</span><span class="v"><?= $counts['OD'] ?></span></div>
                    <div class="stat unmarked"><span class="k">Not Marked</span><span class="v"><?= $counts['UNMARKED'] ?></span></div>
                </section>
                <section class="card"><h2 class="card-title">Student Attendance Register</h2><p class="hint"><?= (int) $marked ?> of <?= (int) $total ?> students marked (<?= (int) $completion ?>%). Unmarked students are not counted as present.</p>
                <?php if (!$students): ?><div class="empty"><strong>No students found</strong><div>This section has no assigned students.</div></div>
                <?php else: ?><div class="table-wrap"><table><thead><tr><th>#</th><th>Register Number</th><th>Student Name</th><th>Status</th><th>Marked At</th></tr></thead><tbody>
                <?php foreach ($students as $i => $student): ?>
                    <tr><td><?= $i + 1 ?></td><td><?= hodAttEscape($student['register_number']) ?></td><td><?= hodAttEscape($student['student_name']) ?></td><td><span class="status <?= strtolower($student['display_status']) ?>"><?= $student['display_status'] === 'UNMARKED' ? 'Not Marked' : hodAttEscape($student['display_status']) ?></span></td><td><?= hodAttEscape($student['marked_at'] ?: '—') ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div><?php endif; ?></section>
            <?php endif; ?>
        </main>
    </div>
</div>
</body>
</html>
