<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole('HOD');

require_once "../config/database.php";

/*
|--------------------------------------------------------------------------
| Get only ACCEPTED Handover Requests
|--------------------------------------------------------------------------
|
| HOD will see only the faculty member who accepted the handover.
|
*/

$sql = "
    SELECT
        ch.handover_id,
        ch.timetable_id,
        ch.reason,
        ch.status AS handover_status,
        ch.created_at,

        tf.faculty_name AS original_faculty,

        hr.request_id,
        hr.status AS request_status,
        hr.requested_at,
        hr.responded_at,

        af.faculty_name AS accepted_faculty,

        tt.day,
        tt.period_id

    FROM class_handover ch

    INNER JOIN timetable tt
        ON tt.timetable_id = ch.timetable_id

    INNER JOIN faculty tf
        ON tf.faculty_id = ch.original_faculty_id

    INNER JOIN handover_requests hr
        ON hr.handover_id = ch.handover_id
        AND hr.status = 'ACCEPTED'

    INNER JOIN faculty af
        ON af.faculty_id = hr.alternate_faculty_id

    ORDER BY ch.created_at DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("Database error: " . $conn->error);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Accepted Handovers</title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <style>

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
        }

        .content {
            padding: 30px;
        }

        h1 {
            color: #17233c;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 25px;
        }

        .table-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #17233c;
            color: white;
            padding: 13px;
            text-align: left;
            white-space: nowrap;
        }

        td {
            padding: 13px;
            border-bottom: 1px solid #ddd;
            white-space: nowrap;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .accepted {
            display: inline-block;
            background: #d1e7dd;
            color: #0f5132;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
        }

        .assigned {
            display: inline-block;
            background: #cfe2ff;
            color: #084298;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
        }

        .no-data {
            text-align: center;
            padding: 35px;
            color: #666;
            font-size: 16px;
        }

        .back {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 16px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }

        .back:hover {
            background: #5c636a;
        }

        .accepted-faculty {
            font-weight: bold;
        }

    </style>

<link rel="stylesheet" href="../assets/css/handover-dark.css">
</head>

<body class="sf-handover-page">

<div class="content">

    <h1>Accepted Handover Requests</h1>

    <div class="subtitle">
        View the faculty members who accepted class handover requests.
    </div>


    <div class="table-container">

        <?php if ($result->num_rows === 0): ?>

            <div class="no-data">
                No accepted handover requests available.
            </div>

        <?php else: ?>

            <table>

                <thead>

                    <tr>

                        <th>Handover ID</th>

                        <th>Original Faculty</th>

                        <th>Day</th>

                        <th>Period</th>

                        <th>Reason</th>

                        <th>Accepted Faculty</th>

                        <th>Request Status</th>

                        <th>Handover Status</th>

                        <th>Accepted At</th>

                    </tr>

                </thead>


                <tbody>

                <?php while ($row = $result->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                $row['handover_id']
                            ) ?>
                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $row['original_faculty']
                            ) ?>
                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $row['day']
                            ) ?>
                        </td>


                        <td>
                            Period
                            <?= htmlspecialchars(
                                $row['period_id']
                            ) ?>
                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $row['reason'] ?? 'N/A'
                            ) ?>
                        </td>


                        <td class="accepted-faculty">

                            <?= htmlspecialchars(
                                $row['accepted_faculty']
                            ) ?>

                        </td>


                        <td>

                            <span class="accepted">
                                ACCEPTED
                            </span>

                        </td>


                        <td>

                            <span class="assigned">
                                <?= htmlspecialchars(
                                    strtoupper(
                                        $row['handover_status']
                                    )
                                ) ?>
                            </span>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $row['responded_at']
                                ?? $row['created_at']
                            ) ?>

                        </td>

                    </tr>

                <?php endwhile; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>


    <a
        href="dashboard.php"
        class="back"
    >
        ← Back to HOD Dashboard
    </a>

</div>

</body>

</html>

<?php

$conn->close();

?>