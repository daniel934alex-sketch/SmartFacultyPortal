<?php
session_start();

require_once("../config/db.php");

/* ---------- Faculty Login ---------- */
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'FACULTY' ||
    !isset($_SESSION['faculty_id'])
) {
    header("Location: ../auth/login.php");
    exit;
}

$faculty_id = (int) $_SESSION['faculty_id'];
$username = $_SESSION['username'] ?? 'Faculty';

$selected_date = $_GET['date'] ?? date('Y-m-d');
$period_id = isset($_GET['period_id'])
    ? (int) $_GET['period_id']
    : 1;

$message = '';
$message_type = 'success';
$timetable = null;
$students = [];
$existing_statuses = [];
$periods = [];
$valid_date = false;

/* ---------- Validate Date ---------- */
$date_obj = DateTime::createFromFormat('!Y-m-d', $selected_date);

if (
    !$date_obj ||
    $date_obj->format('Y-m-d') !== $selected_date
) {
    $selected_date = date('Y-m-d');
    $date_obj = new DateTime($selected_date);
}

$valid_date = $selected_date <= date('Y-m-d');

/* ---------- Load Periods ---------- */
$period_result = $conn->query("
    SELECT period_id, period_number, start_time, end_time
    FROM periods
    ORDER BY period_number
");

if ($period_result) {
    while ($period = $period_result->fetch_assoc()) {
        $periods[] = $period;
    }
}

/* ---------- Find Published Timetable ---------- */
if ($valid_date && $period_id > 0) {
    $day_name = strtoupper($date_obj->format('l'));

    $stmt = $conn->prepare("
        SELECT
            t.timetable_id,
            t.section_id,
            t.subject_id,
            t.faculty_id,
            t.classroom_id,
            s.subject_name
        FROM timetable t
        LEFT JOIN subjects s ON s.subject_id = t.subject_id
        WHERE UPPER(t.day) = ?
          AND t.period_id = ?
          AND t.faculty_id = ?
          AND t.status = 'PUBLISHED'
        LIMIT 1
    ");

    $stmt->bind_param(
        "sii",
        $day_name,
        $period_id,
        $faculty_id
    );

    $stmt->execute();
    $timetable = $stmt->get_result()->fetch_assoc();
}

/* ---------- Load Students and Existing Status ---------- */
if ($timetable) {
    $timetable_id = (int) $timetable['timetable_id'];
    $section_id = (int) $timetable['section_id'];

    $student_stmt = $conn->prepare("
        SELECT student_id, register_number, student_name, section_id
        FROM students
        WHERE section_id = ?
        ORDER BY student_id
    ");

    $student_stmt->bind_param("i", $section_id);
    $student_stmt->execute();

    $student_result = $student_stmt->get_result();

    while ($student = $student_result->fetch_assoc()) {
        $students[] = $student;
    }

    $status_stmt = $conn->prepare("
        SELECT student_id, status
        FROM attendance
        WHERE timetable_id = ?
          AND attendance_date = ?
    ");

    $status_stmt->bind_param(
        "is",
        $timetable_id,
        $selected_date
    );

    $status_stmt->execute();
    $status_result = $status_stmt->get_result();

    while ($row = $status_result->fetch_assoc()) {
        $existing_statuses[(int)$row['student_id']] =
            strtoupper($row['status']);
    }
}

/* ---------- Save Attendance ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$valid_date) {
        $message = 'Future date attendance cannot be marked.';
        $message_type = 'error';

    } elseif (!$timetable) {
        $message = 'No published timetable found for this faculty, date and period.';
        $message_type = 'error';

    } elseif (empty($students)) {
        $message = 'No students found for this section.';
        $message_type = 'error';

    } elseif (
        !isset($_POST['attendance']) ||
        !is_array($_POST['attendance'])
    ) {
        $message = 'Please select attendance for the students.';
        $message_type = 'error';

    } else {
        $attendance_data = $_POST['attendance'];
        $allowed_ids = [];

        foreach ($students as $student) {
            $allowed_ids[] = (int)$student['student_id'];
        }

        $valid_submission = true;

        foreach ($attendance_data as $student_id => $status) {
            if (
                !ctype_digit((string)$student_id) ||
                !in_array((int)$student_id, $allowed_ids, true) ||
                !in_array($status, ['PRESENT', 'ABSENT'], true)
            ) {
                $valid_submission = false;
                break;
            }
        }

        if (
            !$valid_submission ||
            count($attendance_data) !== count($students)
        ) {
            $message = 'Invalid attendance data. Please reload the page and try again.';
            $message_type = 'error';

        } else {
            try {
                $conn->begin_transaction();

                $check_stmt = $conn->prepare("
                    SELECT attendance_id
                    FROM attendance
                    WHERE timetable_id = ?
                      AND student_id = ?
                      AND attendance_date = ?
                    LIMIT 1
                ");

                $update_stmt = $conn->prepare("
                    UPDATE attendance
                    SET status = ?, faculty_id = ?, marked_at = NOW()
                    WHERE attendance_id = ?
                ");

                $insert_stmt = $conn->prepare("
                    INSERT INTO attendance
                    (
                        timetable_id,
                        student_id,
                        faculty_id,
                        attendance_date,
                        status,
                        marked_at
                    )
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");

                foreach ($students as $student) {
                    $student_id = (int)$student['student_id'];
                    $status = $attendance_data[$student_id];

                    $check_stmt->bind_param(
                        "iis",
                        $timetable_id,
                        $student_id,
                        $selected_date
                    );

                    $check_stmt->execute();
                    $found = $check_stmt->get_result()->fetch_assoc();

                    if ($found) {
                        $attendance_id = (int)$found['attendance_id'];

                        $update_stmt->bind_param(
                            "sii",
                            $status,
                            $faculty_id,
                            $attendance_id
                        );

                        $update_stmt->execute();

                    } else {
                        $insert_stmt->bind_param(
                            "iiiss",
                            $timetable_id,
                            $student_id,
                            $faculty_id,
                            $selected_date,
                            $status
                        );

                        $insert_stmt->execute();
                    }
                }

                $conn->commit();

                $message = 'Attendance saved successfully!';
                $message_type = 'success';

                /* Refresh saved statuses */
                $existing_statuses = [];

                $status_stmt = $conn->prepare("
                    SELECT student_id, status
                    FROM attendance
                    WHERE timetable_id = ?
                      AND attendance_date = ?
                ");

                $status_stmt->bind_param(
                    "is",
                    $timetable_id,
                    $selected_date
                );

                $status_stmt->execute();
                $status_result = $status_stmt->get_result();

                while ($row = $status_result->fetch_assoc()) {
                    $existing_statuses[(int)$row['student_id']] =
                        strtoupper($row['status']);
                }

            } catch (Throwable $e) {
                $conn->rollback();
                error_log($e->getMessage());

                $message = 'Unable to save attendance. Please check the database configuration.';
                $message_type = 'error';
            }
        }
    }
}

$total_students = count($students);
$present_count = 0;
$absent_count = 0;

foreach ($students as $student) {
    $sid = (int)$student['student_id'];

    $status = $existing_statuses[$sid] ?? 'PRESENT';

    if ($status === 'PRESENT') {
        $present_count++;
    } elseif ($status === 'ABSENT') {
        $absent_count++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Faculty Attendance | Smart Faculty Portal</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fc;
            color: #1e293b;
        }

        .topbar {
            background: #123c80;
            color: white;
            padding: 20px 6%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .topbar h1 {
            margin: 0;
            font-size: 23px;
        }

        .topbar p {
            margin: 6px 0 0;
            color: #dbeafe;
            font-size: 14px;
        }

        .back-link {
            color: white;
            text-decoration: none;
            border: 1px solid #ffffff80;
            padding: 10px 15px;
            border-radius: 8px;
        }

        .container {
            max-width: 1150px;
            margin: 30px auto;
            padding: 0 20px 40px;
        }

        .card {
            background: white;
            padding: 24px;
            border-radius: 14px;
            margin-bottom: 22px;
            box-shadow: 0 4px 18px #1e293b0c;
        }

        .card h2 {
            margin-top: 0;
            font-size: 20px;
        }

        .filters {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 18px;
            align-items: end;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }

        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
            background: white;
        }

        .btn {
            display: inline-block;
            padding: 12px 18px;
            border: 0;
            border-radius: 8px;
            background: #1769e0;
            color: white;
            text-decoration: none;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn:hover {
            background: #1253b5;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 22px;
        }

        .stat {
            padding: 20px;
            border-radius: 12px;
            background: white;
            box-shadow: 0 4px 18px #1e293b0c;
        }

        .stat span {
            color: #64748b;
            font-size: 14px;
        }

        .stat strong {
            display: block;
            font-size: 27px;
            margin-top: 8px;
        }

        .present-number {
            color: #15803d;
        }

        .absent-number {
            color: #dc2626;
        }

        .notice {
            padding: 14px 16px;
            border-radius: 9px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th, td {
            padding: 15px 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #f8fafc;
            color: #475569;
            font-size: 13px;
            text-transform: uppercase;
        }

        .student-name {
            font-weight: bold;
        }

        .status-select {
            min-width: 135px;
            font-weight: bold;
        }

        .status-select.present {
            color: #15803d;
            background: #f0fdf4;
            border-color: #86efac;
        }

        .status-select.absent {
            color: #b91c1c;
            background: #fef2f2;
            border-color: #fca5a5;
        }

        .save-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 22px;
        }

        .hint {
            color: #64748b;
            font-size: 14px;
            line-height: 1.5;
        }

        .empty {
            text-align: center;
            padding: 35px 15px;
            color: #64748b;
        }

        @media (max-width: 700px) {
            .filters {
                grid-template-columns: 1fr;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .topbar {
                padding: 18px 20px;
            }

            .container {
                padding: 0 12px 30px;
            }

            .card {
                padding: 17px;
            }
        }
    </style>
</head>

<body>

<header class="topbar">
    <div>
        <h1>📝 Faculty Attendance</h1>
        <p>Smart Faculty Assistant Portal</p>
    </div>

    <a class="back-link" href="dashboard.php">
        ← Faculty Dashboard
    </a>
</header>

<main class="container">

    <section class="card">
        <h2>Welcome, <?= htmlspecialchars($username) ?></h2>
        <p class="hint">
            Select the class date and period from your published timetable.
        </p>

        <?php if ($message !== ''): ?>
            <div class="notice <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!$valid_date): ?>
            <div class="notice error">
                Future date attendance cannot be marked.
            </div>
        <?php endif; ?>

        <form method="GET" class="filters">
            <div>
                <label for="date">Attendance Date</label>
                <input
                    type="date"
                    id="date"
                    name="date"
                    value="<?= htmlspecialchars($selected_date) ?>"
                    max="<?= date('Y-m-d') ?>"
                    required
                >
            </div>

            <div>
                <label for="period_id">Select Period</label>
                <select id="period_id" name="period_id" required>
                    <?php foreach ($periods as $period): ?>
                        <option
                            value="<?= (int)$period['period_id'] ?>"
                            <?= (int)$period['period_id'] === $period_id ? 'selected' : '' ?>
                        >
                            Period <?= (int)$period['period_number'] ?>
                            (<?= htmlspecialchars($period['start_time']) ?>
                            - <?= htmlspecialchars($period['end_time']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn">Load Class</button>
        </form>
    </section>

    <?php if (!$valid_date): ?>

        <section class="card empty">
            <h2>Invalid Attendance Date</h2>
            <p>Please select today or a previous date.</p>
        </section>

    <?php elseif (!$timetable): ?>

        <section class="card empty">
            <h2>No Published Class Found</h2>
            <p>
                Your published timetable does not contain a class
                for this date and period.
            </p>
            <p class="hint">
                Check the timetable day, selected period, faculty assignment,
                and PUBLISHED status.
            </p>
        </section>

    <?php elseif (empty($students)): ?>

        <section class="card empty">
            <h2>No Students Found</h2>
            <p>There are no students assigned to this section.</p>
        </section>

    <?php else: ?>

        <section class="card">
            <h2>Class Details</h2>

            <p>
                <strong>Subject:</strong>
                <?= htmlspecialchars($timetable['subject_name'] ?? 'Subject') ?>
            </p>

            <p>
                <strong>Section ID:</strong>
                <?= (int)$timetable['section_id'] ?>
            </p>

            <p>
                <strong>Date:</strong>
                <?= htmlspecialchars($selected_date) ?>
            </p>

            <p>
                <strong>Period:</strong>
                <?= (int)$period_id ?>
            </p>
        </section>

        <section class="stats">
            <div class="stat">
                <span>Total Students</span>
                <strong><?= $total_students ?></strong>
            </div>

            <div class="stat">
                <span>Present</span>
                <strong class="present-number" id="presentCount">
                    <?= $present_count ?>
                </strong>
            </div>

            <div class="stat">
                <span>Absent</span>
                <strong class="absent-number" id="absentCount">
                    <?= $absent_count ?>
                </strong>
            </div>
        </section>

        <form method="POST"
              action="?date=<?= urlencode($selected_date) ?>&period_id=<?= $period_id ?>"
              class="card"
              onsubmit="return confirm('Save attendance for this class and period?');">

            <h2>Student Attendance</h2>

            <p class="hint">
                Students are marked Present by default if no attendance
                has been saved for this period. Change only absent students
                to Absent, then click Save Attendance.
            </p>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Register Number</th>
                            <th>Student Name</th>
                            <th>Attendance Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($students as $index => $student): ?>
                            <?php
                                $sid = (int)$student['student_id'];
                                $status = $existing_statuses[$sid] ?? 'PRESENT';
                            ?>

                            <tr>
                                <td><?= $index + 1 ?></td>

                                <td>
                                    <?= htmlspecialchars($student['register_number'] ?? '') ?>
                                </td>

                                <td class="student-name">
                                    <?= htmlspecialchars($student['student_name'] ?? '') ?>
                                </td>

                                <td>
                                    <select
                                        class="status-select <?= strtolower($status) ?>"
                                        name="attendance[<?= $sid ?>]"
                                        onchange="updateCounts()"
                                        required
                                    >
                                        <option value="PRESENT"
                                            <?= $status === 'PRESENT' ? 'selected' : '' ?>>
                                            Present
                                        </option>

                                        <option value="ABSENT"
                                            <?= $status === 'ABSENT' ? 'selected' : '' ?>>
                                            Absent
                                        </option>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="save-row">
                <span class="hint">
                    Attendance is saved separately for each timetable period.
                </span>

                <button type="submit" class="btn">
                    Save Attendance
                </button>
            </div>
        </form>

    <?php endif; ?>

</main>

<script>
function updateCounts() {
    const selects = document.querySelectorAll('.status-select');

    let present = 0;
    let absent = 0;

    selects.forEach(function(select) {
        if (select.value === 'PRESENT') {
            present++;
            select.classList.add('present');
            select.classList.remove('absent');
        } else {
            absent++;
            select.classList.add('absent');
            select.classList.remove('present');
        }
    });

    document.getElementById('presentCount').textContent = present;
    document.getElementById('absentCount').textContent = absent;
}

updateCounts();
</script>

</body>
</html>