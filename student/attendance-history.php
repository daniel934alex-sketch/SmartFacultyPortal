<?php

require_once("../config/db.php");

/* --------------------------------
   Testing Student
-------------------------------- */

$student_id = 1;


/* --------------------------------
   Get Student Details
-------------------------------- */

$student_stmt = $conn->prepare("
    SELECT
        student_id,
        register_number,
        student_name
    FROM students
    WHERE student_id = ?
");

$student_stmt->bind_param(
    "i",
    $student_id
);

$student_stmt->execute();

$student = $student_stmt
    ->get_result()
    ->fetch_assoc();


/* --------------------------------
   Get Attendance History
-------------------------------- */

$history = [];

$history_stmt = $conn->prepare("
    SELECT
        a.attendance_date,
        a.status,
        s.subject_code,
        s.subject_name,
        p.period_number,
        p.start_time,
        p.end_time
    FROM attendance a

    INNER JOIN timetable t
        ON a.timetable_id = t.timetable_id

    INNER JOIN subjects s
        ON t.subject_id = s.subject_id

    INNER JOIN periods p
        ON t.period_id = p.period_id

    WHERE a.student_id = ?

    ORDER BY
        a.attendance_date DESC,
        p.period_number ASC
");

$history_stmt->bind_param(
    "i",
    $student_id
);

$history_stmt->execute();

$history_result =
    $history_stmt->get_result();

while (
    $row =
    $history_result->fetch_assoc()
) {

    $history[] = $row;
}

?>

<!DOCTYPE html>

<html>

<head>

    <title>Attendance History</title>

</head>

<body>


<h1>
    Attendance History
</h1>


<?php if (!$student): ?>


    <p style="color:red;">

        <strong>
            Student not found.
        </strong>

    </p>


<?php else: ?>


    <h2>
        Student Details
    </h2>


    <p>

        <strong>
            Register Number:
        </strong>

        <?php
        echo htmlspecialchars(
            $student['register_number']
        );
        ?>

    </p>


    <p>

        <strong>
            Student Name:
        </strong>

        <?php
        echo htmlspecialchars(
            $student['student_name']
        );
        ?>

    </p>


    <hr>


    <h2>
        Attendance Records
    </h2>


    <?php if (count($history) == 0): ?>


        <p style="color:red;">

            <strong>
                No attendance history found.
            </strong>

        </p>


    <?php else: ?>


        <table
            border="1"
            cellpadding="10"
        >

            <tr>

                <th>
                    Date
                </th>

                <th>
                    Period
                </th>

                <th>
                    Start Time
                </th>

                <th>
                    End Time
                </th>

                <th>
                    Subject Code
                </th>

                <th>
                    Subject Name
                </th>

                <th>
                    Status
                </th>

            </tr>


            <?php foreach ($history as $record): ?>


                <tr>

                    <td>

                        <?php
                        echo htmlspecialchars(
                            $record['attendance_date']
                        );
                        ?>

                    </td>


                    <td>

                        <?php
                        echo $record['period_number'];
                        ?>

                    </td>


                    <td>

                        <?php

                        echo date(
                            "H:i",
                            strtotime(
                                $record['start_time']
                            )
                        );

                        ?>

                    </td>


                    <td>

                        <?php

                        echo date(
                            "H:i",
                            strtotime(
                                $record['end_time']
                            )
                        );

                        ?>

                    </td>


                    <td>

                        <?php
                        echo htmlspecialchars(
                            $record['subject_code']
                        );
                        ?>

                    </td>


                    <td>

                        <?php
                        echo htmlspecialchars(
                            $record['subject_name']
                        );
                        ?>

                    </td>


                    <td>

                        <strong>

                            <?php
                            echo htmlspecialchars(
                                $record['status']
                            );
                            ?>

                        </strong>

                    </td>

                </tr>


            <?php endforeach; ?>


        </table>


    <?php endif; ?>


<?php endif; ?>


</body>

</html>