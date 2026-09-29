<?php

require_once("../config/db.php");

$selected_faculty = isset($_GET['faculty_id'])
    ? (int)$_GET['faculty_id']
    : 0;

$faculties = [];

$faculty_result = $conn->query("
    SELECT
        faculty_id,
        faculty_code,
        faculty_name
    FROM faculty
    ORDER BY faculty_name
");

if ($faculty_result) {

    while ($row = $faculty_result->fetch_assoc()) {

        $faculties[] = $row;

    }

}


/* --------------------------------
   Faculty Report
-------------------------------- */

$report = [];

if ($selected_faculty > 0) {

    $stmt = $conn->prepare("
        SELECT
            s.subject_code,
            s.subject_name,

            COUNT(a.attendance_id) AS total_classes,

            SUM(
                CASE
                    WHEN a.status = 'PRESENT'
                    THEN 1
                    ELSE 0
                END
            ) AS present_count,

            SUM(
                CASE
                    WHEN a.status = 'ABSENT'
                    THEN 1
                    ELSE 0
                END
            ) AS absent_count

        FROM attendance a

        INNER JOIN timetable t
            ON a.timetable_id = t.timetable_id

        INNER JOIN subjects s
            ON t.subject_id = s.subject_id

        WHERE a.faculty_id = ?

        GROUP BY
            s.subject_id,
            s.subject_code,
            s.subject_name

        ORDER BY
            s.subject_code
    ");

    $stmt->bind_param(
        "i",
        $selected_faculty
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $total = (int)$row['total_classes'];

        $present = (int)$row['present_count'];

        $absent = (int)$row['absent_count'];

        if ($total > 0) {

            $percentage =
                ($present / $total) * 100;

        } else {

            $percentage = 0;

        }

        $row['percentage'] = $percentage;

        $report[] = $row;

    }

}

?>

<!DOCTYPE html>

<html>

<head>

    <title>Faculty-wise Attendance Report</title>

</head>

<body>

<h1>
    Faculty-wise Attendance Report
</h1>


<!-- --------------------------------
     Faculty Selection
--------------------------------- -->

<form method="GET">

    <label>

        <strong>
            Select Faculty:
        </strong>

    </label>


    <select
        name="faculty_id"
        required
    >

        <option value="">

            -- Select Faculty --

        </option>


        <?php foreach ($faculties as $faculty): ?>

            <option
                value="<?php
                echo $faculty['faculty_id'];
                ?>"
                <?php

                if (
                    $selected_faculty
                    == $faculty['faculty_id']
                ) {

                    echo "selected";

                }

                ?>
            >

                <?php
                echo htmlspecialchars(
                    $faculty['faculty_code']
                );
                ?>

                -

                <?php
                echo htmlspecialchars(
                    $faculty['faculty_name']
                );
                ?>

            </option>

        <?php endforeach; ?>

    </select>


    <button type="submit">

        Generate Report

    </button>

</form>


<hr>


<?php if ($selected_faculty > 0): ?>


    <h2>
        Faculty Attendance Report
    </h2>


    <?php if (count($report) == 0): ?>

        <p style="color:red;">

            <strong>

                No attendance records found
                for this faculty.

            </strong>

        </p>


    <?php else: ?>


        <table
            border="1"
            cellpadding="10"
        >

            <tr>

                <th>
                    Subject Code
                </th>

                <th>
                    Subject Name
                </th>

                <th>
                    Total Classes
                </th>

                <th>
                    Present
                </th>

                <th>
                    Absent
                </th>

                <th>
                    Attendance Percentage
                </th>

            </tr>


            <?php foreach ($report as $row): ?>


                <tr>

                    <td>

                        <?php
                        echo htmlspecialchars(
                            $row['subject_code']
                        );
                        ?>

                    </td>


                    <td>

                        <?php
                        echo htmlspecialchars(
                            $row['subject_name']
                        );
                        ?>

                    </td>


                    <td>

                        <?php
                        echo $row['total_classes'];
                        ?>

                    </td>


                    <td>

                        <?php
                        echo $row['present_count'];
                        ?>

                    </td>


                    <td>

                        <?php
                        echo $row['absent_count'];
                        ?>

                    </td>


                    <td>

                        <strong>

                            <?php
                            echo number_format(
                                $row['percentage'],
                                2
                            );
                            ?>%

                        </strong>

                    </td>

                </tr>


            <?php endforeach; ?>


        </table>


    <?php endif; ?>


<?php endif; ?>


</body>

</html>