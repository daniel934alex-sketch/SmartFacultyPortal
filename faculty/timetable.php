<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole('FACULTY');

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Timetable</title>

    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body>

    <h1>My Weekly Timetable</h1>

    <p>Welcome, <?= htmlspecialchars($_SESSION['username']) ?></p>

    <table border="1" cellpadding="10">

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

            <tr>
                <td>Monday</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
            </tr>

            <tr>
                <td>Tuesday</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
            </tr>

            <tr>
                <td>Wednesday</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
            </tr>

            <tr>
                <td>Thursday</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
            </tr>

            <tr>
                <td>Friday</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
            </tr>

            <tr>
                <td>Saturday</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td>EE Project</td>
                <td>EE Project</td>
                <td>EE Project</td>
                <td>-</td>
                <td>-</td>
            </tr>

        </tbody>

    </table>

    <br>

    <a href="dashboard.php">← Back to Dashboard</a>

</body>

</html>