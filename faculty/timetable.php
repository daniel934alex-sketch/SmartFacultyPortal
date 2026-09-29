<?php
require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole('FACULTY');

require_once "../config/database.php";

$user_id = $_SESSION['user_id'];

$faculty_sql = "
    SELECT faculty_id
    FROM faculty
    WHERE user_id = ?
    LIMIT 1
";

$faculty_stmt = $conn->prepare($faculty_sql);
$faculty_stmt->bind_param("i", $user_id);
$faculty_stmt->execute();

$faculty_result = $faculty_stmt->get_result();

if ($faculty_result->num_rows === 0) {
    die("Faculty record not found.");
}

$faculty = $faculty_result->fetch_assoc();
$faculty_id = $faculty['faculty_id'];

$faculty_stmt->close();

$sql = "
    SELECT
        t.day,
        t.period_id,
        t.subject_id,
        t.faculty_id,
        s.subject_name,
        t.status
    FROM timetable t
    LEFT JOIN subjects s
        ON t.subject_id = s.subject_id
    WHERE t.faculty_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();

$result = $stmt->get_result();

$timetable = [];

while ($row = $result->fetch_assoc()) {
    $day = strtoupper($row['day']);
    $period = (int)$row['period_id'];

    $timetable[$day][$period] = $row['subject_name'] ?? "Class";
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Timetable</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 20px;
        }

        h1 {
            text-align: center;
        }

        .welcome {
            text-align: center;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th {
            background: #0866e8;
            color: white;
            padding: 12px;
        }

        td {
            border: 1px solid #ccc;
            padding: 14px;
            text-align: center;
        }

        td:first-child {
            font-weight: bold;
            background: #0866e8;
            color: white;
        }

        .class {
            font-weight: bold;
            color: #222;
        }

        .back {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            color: #0866e8;
        }
    </style>
</head>

<body>

<h1>My Weekly Timetable</h1>

<div class="welcome">
    Welcome, <?= htmlspecialchars($_SESSION['username']) ?>
</div>

<table>

    <thead>
        <tr>
            <th>Day</th>
            <th>P1</th>
            <th>P2</th>
            <th>P3</th>
            <th>P4</th>
            <th>P5</th>
            <th>P6</th>
            <th>P7</th>
            <th>P8</th>
            <th>P9</th>
        </tr>
    </thead>

    <tbody>

    <?php
    $days = [
        "MONDAY",
        "TUESDAY",
        "WEDNESDAY",
        "THURSDAY",
        "FRIDAY",
        "SATURDAY"
    ];

    foreach ($days as $day):
    ?>

        <tr>

            <td>
                <?= ucfirst(strtolower($day)) ?>
            </td>

            <?php for ($period = 1; $period <= 9; $period++): ?>

                <td class="class">

                    <?=
                        htmlspecialchars(
                            $timetable[$day][$period] ?? "-"
                        )
                    ?>

                </td>

            <?php endfor; ?>

        </tr>

    <?php endforeach; ?>

    </tbody>

</table>

<a class="back" href="dashboard.php">
    ← Back to Dashboard
</a>

</body>
</html>