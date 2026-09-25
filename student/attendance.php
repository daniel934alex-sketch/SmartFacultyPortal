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
        student_name,
        section_id
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
   Get Attendance Records
-------------------------------- */

$attendance_stmt = $conn->prepare("
    SELECT
        a.attendance_date,
        a.status,
        s.subject_code,
        s.subject_name
    FROM attendance a

    INNER JOIN timetable t
        ON a.timetable_id = t.timetable_id

    INNER JOIN subjects s
        ON t.subject_id = s.subject_id

    WHERE a.student_id = ?

    ORDER BY a.attendance_date DESC
");

$attendance_stmt->bind_param(
    "i",
    $student_id
);

$attendance_stmt->execute();

$attendance_result =
    $attendance_stmt->get_result();


/* --------------------------------
   Overall Attendance
-------------------------------- */

$total_classes = 0;
$present_classes = 0;

$records = [];

while (
    $row =
    $attendance_result->fetch_assoc()
) {

    $records[] = $row;

    $total_classes++;

    if ($row['status'] === 'PRESENT') {

        $present_classes++;
    }
}


/* --------------------------------
   Overall Percentage
-------------------------------- */

$percentage = 0;

if ($total_classes > 0) {

    $percentage =
        ($present_classes / $total_classes) * 100;
}


/* --------------------------------
   Subject-wise Attendance
-------------------------------- */

$subject_attendance = [];

foreach ($records as $record) {

    $subject_code =
        $record['subject_code'];

    if (
        !isset(
            $subject_attendance[$subject_code]
        )
    ) {

        $subject_attendance[$subject_code] = [
            'subject_name' =>
                $record['subject_name'],

            'total' => 0,

            'present' => 0
        ];
    }


    $subject_attendance[$subject_code]['total']++;


    if (
        $record['status'] === 'PRESENT'
    ) {

        $subject_attendance[$subject_code]['present']++;
    }
}

?>

<!DOCTYPE html>

<html>

<head>

    <title>Student Attendance</title>

</head>


<body>

<h1>Student Attendance</h1>


<?php if ($student): ?>


    <!-- --------------------------------
         Student Details
    --------------------------------- -->

    <h2>

        <?php
        echo htmlspecialchars(
            $student['student_name']
        );
        ?>

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
            Section:
        </strong>

        <?php
        echo $student['section_id'];
        ?>

    </p>


    <hr>


    <!-- --------------------------------
         Attendance Summary
    --------------------------------- -->

    <h2>
        Attendance Summary
    </h2>


    <p>

        <strong>
            Total Classes:
        </strong>

        <?php
        echo $total_classes;
        ?>

    </p>


    <p>

        <strong>
            Present:
        </strong>

        <?php
        echo $present_classes;
        ?>

    </p>


    <p>

        <strong>
            Attendance Percentage:
        </strong>

        <?php
        echo number_format(
            $percentage,
            2
        );
        ?>%

    </p>


    <hr>


    <!-- --------------------------------
         Subject-wise Attendance
    --------------------------------- -->

    <h2>
        Subject-wise Attendance
    </h2>


    <?php if (
        count($subject_attendance) == 0
    ): ?>

        <p>
            No subject attendance records found.
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
                    Percentage
                </th>

            </tr>


            <?php
            foreach (
                $subject_attendance
                as $code => $subject
            ):
            ?>


                <?php

                $subject_percentage = 0;

                if (
                    $subject['total'] > 0
                ) {

                    $subject_percentage =
                        (
                            $subject['present']
                            /
                            $subject['total']
                        ) * 100;
                }

                ?>


                <tr>

                    <td>

                        <?php
                        echo htmlspecialchars(
                            $code
                        );
                        ?>

                    </td>


                    <td>

                        <?php
                        echo htmlspecialchars(
                            $subject['subject_name']
                        );
                        ?>

                    </td>


                    <td>

                        <?php
                        echo $subject['total'];
                        ?>

                    </td>


                    <td>

                        <?php
                        echo $subject['present'];
                        ?>

                    </td>


                    <td>

                        <?php
                        echo number_format(
                            $subject_percentage,
                            2
                        );
                        ?>%

                    </td>

                </tr>


            <?php endforeach; ?>

        </table>


    <?php endif; ?>


    <hr>


    <!-- --------------------------------
         Attendance History
    --------------------------------- -->

    <h2>
        Attendance History
    </h2>


    <?php if (
        count($records) == 0
    ): ?>

        <p>
            No attendance records found.
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
                    Subject Code
                </th>

                <th>
                    Subject Name
                </th>

                <th>
                    Status
                </th>

            </tr>


            <?php
            foreach (
                $records as $record
            ):
            ?>


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


<?php else: ?>


    <p style="color:red;">

        <strong>
            Student not found.
        </strong>

    </p>


<?php endif; ?>


</body>

</html>