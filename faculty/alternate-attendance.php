<?php

session_start();

require_once("../config/db.php");


/* -------------------------------------------------
   Faculty Login Check
------------------------------------------------- */

if (
    !isset($_SESSION['user_id'])
    ||
    !isset($_SESSION['role'])
    ||
    $_SESSION['role'] !== 'FACULTY'
    ||
    !isset($_SESSION['faculty_id'])
) {

    header("Location: ../auth/login.php");
    exit;
}


/* -------------------------------------------------
   Logged-in Faculty ID
------------------------------------------------- */

$faculty_id = (int)$_SESSION['faculty_id'];


$message = "";
$message_color = "green";


/* -------------------------------------------------
   Selected Request
------------------------------------------------- */

$selected_request_id =
    isset($_GET['request_id'])
        ? (int)$_GET['request_id']
        : 0;


$selected_date =
    $_GET['date'] ?? date('Y-m-d');


/* =================================================
   GET ACCEPTED HANDOVER REQUESTS
================================================= */

$requests = [];


$request_stmt = $conn->prepare("

    SELECT

        hr.request_id,
        hr.handover_id,
        hr.alternate_faculty_id,
        hr.status,

        ch.timetable_id,
        ch.original_faculty_id,
        ch.reason,

        t.day,
        t.period_id,
        t.section_id,
        t.subject_id,
        t.status AS timetable_status,

        s.subject_code,
        s.subject_name,

        sec.section_name,

        p.period_number,
        p.start_time,
        p.end_time,

        original_faculty.faculty_name
            AS original_faculty_name,

        alternate_faculty.faculty_name
            AS alternate_faculty_name

    FROM handover_requests hr

    INNER JOIN class_handover ch
        ON hr.handover_id = ch.handover_id

    INNER JOIN timetable t
        ON ch.timetable_id = t.timetable_id

    INNER JOIN subjects s
        ON t.subject_id = s.subject_id

    INNER JOIN sections sec
        ON t.section_id = sec.section_id

    INNER JOIN periods p
        ON t.period_id = p.period_id

    INNER JOIN faculty original_faculty
        ON ch.original_faculty_id
        = original_faculty.faculty_id

    INNER JOIN faculty alternate_faculty
        ON hr.alternate_faculty_id
        = alternate_faculty.faculty_id

    WHERE hr.alternate_faculty_id = ?

      AND hr.status = 'ACCEPTED'

      AND t.status = 'PUBLISHED'

    ORDER BY
        hr.responded_at DESC

");


$request_stmt->bind_param(
    "i",
    $faculty_id
);


$request_stmt->execute();


$request_result =
    $request_stmt->get_result();


while (
    $row =
    $request_result->fetch_assoc()
) {

    $requests[] = $row;
}


/* =================================================
   ATTENDANCE MARKING
================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset(
        $_POST['request_id'],
        $_POST['student_id'],
        $_POST['status'],
        $_POST['attendance_date']
    )
) {


    $request_id =
        (int)$_POST['request_id'];


    $student_id =
        (int)$_POST['student_id'];


    $status =
        strtoupper(
            trim($_POST['status'])
        );


    $attendance_date =
        $_POST['attendance_date'];


    /* -------------------------------------------------
       Validate Attendance Status
    ------------------------------------------------- */

    if (
        $status !== "PRESENT"
        &&
        $status !== "ABSENT"
    ) {

        $message =
            "Invalid attendance status.";

        $message_color =
            "red";


    } else {


        /* -------------------------------------------------
           Validate Date
        ------------------------------------------------- */

        $today =
            date('Y-m-d');


        if (
            $attendance_date > $today
        ) {

            $message =
                "Future date attendance cannot be marked.";

            $message_color =
                "red";


        } else {


            /* -------------------------------------------------
               Get Authorization Details
            ------------------------------------------------- */

            $auth_stmt = $conn->prepare("

                SELECT

                    hr.request_id,
                    hr.alternate_faculty_id,
                    hr.status AS request_status,

                    ch.timetable_id,
                    ch.original_faculty_id,

                    t.day,
                    t.period_id,
                    t.section_id,
                    t.status AS timetable_status

                FROM handover_requests hr

                INNER JOIN class_handover ch
                    ON hr.handover_id = ch.handover_id

                INNER JOIN timetable t
                    ON ch.timetable_id = t.timetable_id

                WHERE hr.request_id = ?

            ");


            $auth_stmt->bind_param(
                "i",
                $request_id
            );


            $auth_stmt->execute();


            $auth_result =
                $auth_stmt->get_result();


            $authorization =
                $auth_result->fetch_assoc();


            /* -------------------------------------------------
               Authorization Checks
            ------------------------------------------------- */

            if (
                !$authorization
            ) {

                $message =
                    "Authorization request not found.";

                $message_color =
                    "red";


            } elseif (
                (int)$authorization[
                    'alternate_faculty_id'
                ]
                !==
                $faculty_id
            ) {

                $message =
                    "This request is not assigned to this faculty.";

                $message_color =
                    "red";


            } elseif (
                $authorization[
                    'request_status'
                ]
                !==
                "ACCEPTED"
            ) {

                $message =
                    "Handover request is not accepted.";

                $message_color =
                    "red";


            } elseif (
                $authorization[
                    'timetable_status'
                ]
                !==
                "PUBLISHED"
            ) {

                $message =
                    "Timetable is not published.";

                $message_color =
                    "red";


            } else {


                /* -------------------------------------------------
                   Validate Day
                ------------------------------------------------- */

                $day_name =
                    strtoupper(
                        date(
                            'l',
                            strtotime(
                                $attendance_date
                            )
                        )
                    );


                if (
                    $day_name
                    !==
                    strtoupper(
                        $authorization['day']
                    )
                ) {

                    $message =
                        "Selected date does not match the class timetable day.";

                    $message_color =
                        "red";


                } else {


                    /* -------------------------------------------------
                       Validate Student
                    ------------------------------------------------- */

                    $student_stmt = $conn->prepare("

                        SELECT

                            student_id,
                            section_id

                        FROM students

                        WHERE student_id = ?

                    ");


                    $student_stmt->bind_param(
                        "i",
                        $student_id
                    );


                    $student_stmt->execute();


                    $student_result =
                        $student_stmt
                        ->get_result();


                    $student =
                        $student_result
                        ->fetch_assoc();


                    if (
                        !$student
                    ) {

                        $message =
                            "Student not found.";

                        $message_color =
                            "red";


                    } elseif (
                        (int)$student['section_id']
                        !==
                        (int)$authorization['section_id']
                    ) {

                        $message =
                            "Student does not belong to this class section.";

                        $message_color =
                            "red";


                    } else {


                        /* -------------------------------------------------
                           Duplicate Attendance Check
                        ------------------------------------------------- */

                        $duplicate_stmt =
                            $conn->prepare("

                                SELECT

                                    attendance_id,
                                    status

                                FROM attendance

                                WHERE timetable_id = ?

                                  AND student_id = ?

                                  AND attendance_date = ?

                            ");


                        $duplicate_stmt->bind_param(
                            "iis",
                            $authorization[
                                'timetable_id'
                            ],
                            $student_id,
                            $attendance_date
                        );


                        $duplicate_stmt->execute();


                        $duplicate_result =
                            $duplicate_stmt
                            ->get_result();


                        if (
                            $duplicate_result->num_rows > 0
                        ) {

                            $existing =
                                $duplicate_result
                                ->fetch_assoc();


                            $message =
                                "Attendance already marked as "
                                . $existing['status']
                                . ".";


                            $message_color =
                                "red";


                        } else {


                            /* -------------------------------------------------
                               Insert Attendance
                            ------------------------------------------------- */

                            $insert_stmt =
                                $conn->prepare("

                                    INSERT INTO attendance
                                    (
                                        timetable_id,
                                        student_id,
                                        faculty_id,
                                        attendance_date,
                                        status,
                                        marked_at
                                    )

                                    VALUES
                                    (
                                        ?,
                                        ?,
                                        ?,
                                        ?,
                                        ?,
                                        NOW()
                                    )

                                ");


                            $insert_stmt->bind_param(
                                "iiiss",
                                $authorization[
                                    'timetable_id'
                                ],
                                $student_id,
                                $faculty_id,
                                $attendance_date,
                                $status
                            );


                            if (
                                $insert_stmt->execute()
                            ) {

                                $message =
                                    "Alternate faculty attendance marked as "
                                    . $status
                                    . " successfully.";

                                $message_color =
                                    "green";


                            } else {

                                $message =
                                    "Error while saving attendance.";

                                $message_color =
                                    "red";
                            }
                        }
                    }
                }
            }
        }
    }


    /* -------------------------------------------------
       Keep Selected Request / Date
    ------------------------------------------------- */

    $selected_request_id =
        $request_id;

    $selected_date =
        $attendance_date;
}


/* =================================================
   GET SELECTED REQUEST DETAILS
================================================= */

$selected_request = null;


if (
    $selected_request_id > 0
) {


    $selected_stmt = $conn->prepare("

        SELECT

            hr.request_id,
            hr.handover_id,
            hr.alternate_faculty_id,
            hr.status AS request_status,

            ch.timetable_id,
            ch.original_faculty_id,
            ch.reason,

            t.day,
            t.period_id,
            t.section_id,
            t.subject_id,
            t.status AS timetable_status,

            s.subject_code,
            s.subject_name,

            sec.section_name,

            p.period_number,
            p.start_time,
            p.end_time,

            original_faculty.faculty_name
                AS original_faculty_name,

            alternate_faculty.faculty_name
                AS alternate_faculty_name

        FROM handover_requests hr

        INNER JOIN class_handover ch
            ON hr.handover_id = ch.handover_id

        INNER JOIN timetable t
            ON ch.timetable_id = t.timetable_id

        INNER JOIN subjects s
            ON t.subject_id = s.subject_id

        INNER JOIN sections sec
            ON t.section_id = sec.section_id

        INNER JOIN periods p
            ON t.period_id = p.period_id

        INNER JOIN faculty original_faculty
            ON ch.original_faculty_id
            = original_faculty.faculty_id

        INNER JOIN faculty alternate_faculty
            ON hr.alternate_faculty_id
            = alternate_faculty.faculty_id

        WHERE hr.request_id = ?

          AND hr.alternate_faculty_id = ?

          AND hr.status = 'ACCEPTED'

    ");


    $selected_stmt->bind_param(
        "ii",
        $selected_request_id,
        $faculty_id
    );


    $selected_stmt->execute();


    $selected_request =
        $selected_stmt
        ->get_result()
        ->fetch_assoc();
}


/* =================================================
   GET STUDENTS
================================================= */

$students = [];


if (
    $selected_request
) {


    $student_list_stmt =
        $conn->prepare("

            SELECT

                student_id,
                register_number,
                student_name,
                section_id

            FROM students

            WHERE section_id = ?

            ORDER BY register_number

        ");


    $student_list_stmt->bind_param(
        "i",
        $selected_request[
            'section_id'
        ]
    );


    $student_list_stmt->execute();


    $student_list_result =
        $student_list_stmt
        ->get_result();


    while (
        $row =
        $student_list_result->fetch_assoc()
    ) {

        $students[] =
            $row;
    }
}

?>

<!DOCTYPE html>

<html>

<head>

    <title>
        Alternate Faculty Attendance
    </title>

</head>


<body>


<h1>
    Alternate Faculty Attendance
</h1>


<!-- =================================================
     FACULTY DETAILS
================================================= -->

<h2>
    Faculty Details
</h2>


<p>

    <strong>
        Logged-in Faculty:
    </strong>

    <?php
    echo htmlspecialchars(
        $_SESSION['username']
    );
    ?>

</p>


<p>

    <strong>
        Faculty ID:
    </strong>

    <?php
    echo $faculty_id;
    ?>

</p>


<?php if ($message !== ""): ?>

    <p
        style="color:
        <?php
        echo $message_color;
        ?>;"
    >

        <strong>

            <?php
            echo htmlspecialchars(
                $message
            );
            ?>

        </strong>

    </p>

<?php endif; ?>


<hr>


<!-- =================================================
     ACCEPTED HANDOVER REQUESTS
================================================= -->

<h2>
    Accepted Handover Requests
</h2>


<?php if (
    count($requests) == 0
): ?>

    <p style="color:red;">

        <strong>
            No accepted handover requests found.
        </strong>

    </p>


<?php else: ?>


    <table
        border="1"
        cellpadding="10"
    >

        <tr>

            <th>
                Request ID
            </th>

            <th>
                Subject
            </th>

            <th>
                Section
            </th>

            <th>
                Period
            </th>

            <th>
                Original Faculty
            </th>

            <th>
                Alternate Faculty
            </th>

            <th>
                Status
            </th>

            <th>
                Action
            </th>

        </tr>


        <?php foreach (
            $requests
            as $request
        ): ?>


            <tr>


                <td>

                    <?php
                    echo $request[
                        'request_id'
                    ];
                    ?>

                </td>


                <td>

                    <?php
                    echo htmlspecialchars(
                        $request[
                            'subject_code'
                        ]
                    );
                    ?>

                    -

                    <?php
                    echo htmlspecialchars(
                        $request[
                            'subject_name'
                        ]
                    );
                    ?>

                </td>


                <td>

                    <?php
                    echo htmlspecialchars(
                        $request[
                            'section_name'
                        ]
                    );
                    ?>

                </td>


                <td>

                    <?php
                    echo $request[
                        'period_number'
                    ];
                    ?>

                    <br>

                    <?php
                    echo date(
                        "H:i",
                        strtotime(
                            $request[
                                'start_time'
                            ]
                        )
                    );
                    ?>

                    -

                    <?php
                    echo date(
                        "H:i",
                        strtotime(
                            $request[
                                'end_time'
                            ]
                        )
                    );
                    ?>

                </td>


                <td>

                    <?php
                    echo htmlspecialchars(
                        $request[
                            'original_faculty_name'
                        ]
                    );
                    ?>

                </td>


                <td>

                    <?php
                    echo htmlspecialchars(
                        $request[
                            'alternate_faculty_name'
                        ]
                    );
                    ?>

                </td>


                <td>

                    <strong>

                        <?php
                        echo htmlspecialchars(
                            $request[
                                'status'
                            ]
                        );
                        ?>

                    </strong>

                </td>


                <td>

                    <a
                        href="?request_id=<?php
                        echo $request[
                            'request_id'
                        ];
                        ?>&date=<?php
                        echo urlencode(
                            $selected_date
                        );
                        ?>"
                    >

                        Open Attendance

                    </a>

                </td>


            </tr>


        <?php endforeach; ?>


    </table>


<?php endif; ?>


<hr>


<!-- =================================================
     AUTHORIZED CLASS
================================================= -->

<?php if (
    $selected_request
): ?>


    <h2>
        Authorized Class
    </h2>


    <table
        border="1"
        cellpadding="10"
    >


        <tr>

            <th>
                Date
            </th>

            <td>

                <?php
                echo htmlspecialchars(
                    $selected_date
                );
                ?>

            </td>

        </tr>


        <tr>

            <th>
                Day
            </th>

            <td>

                <?php
                echo htmlspecialchars(
                    $selected_request[
                        'day'
                    ]
                );
                ?>

            </td>

        </tr>


        <tr>

            <th>
                Subject
            </th>

            <td>

                <?php
                echo htmlspecialchars(
                    $selected_request[
                        'subject_code'
                    ]
                );
                ?>

                -

                <?php
                echo htmlspecialchars(
                    $selected_request[
                        'subject_name'
                    ]
                );
                ?>

            </td>

        </tr>


        <tr>

            <th>
                Section
            </th>

            <td>

                <?php
                echo htmlspecialchars(
                    $selected_request[
                        'section_name'
                    ]
                );
                ?>

            </td>

        </tr>


        <tr>

            <th>
                Original Faculty
            </th>

            <td>

                <?php
                echo htmlspecialchars(
                    $selected_request[
                        'original_faculty_name'
                    ]
                );
                ?>

            </td>

        </tr>


        <tr>

            <th>
                Alternate Faculty
            </th>

            <td>

                <?php
                echo htmlspecialchars(
                    $selected_request[
                        'alternate_faculty_name'
                    ]
                );
                ?>

            </td>

        </tr>


        <tr>

            <th>
                Authorization Status
            </th>

            <td>

                <strong>

                    <?php
                    echo htmlspecialchars(
                        $selected_request[
                            'request_status'
                        ]
                    );
                    ?>

                </strong>

            </td>

        </tr>


    </table>


    <hr>


    <!-- =================================================
         ATTENDANCE DATE
    ================================================== -->

    <h2>
        Attendance Date
    </h2>


    <form method="GET">


        <input
            type="hidden"
            name="request_id"
            value="<?php
            echo $selected_request_id;
            ?>"
        >


        <label>

            <strong>
                Select Date:
            </strong>

        </label>


        <input
            type="date"
            name="date"
            value="<?php
            echo htmlspecialchars(
                $selected_date
            );
            ?>"
            required
        >


        <button type="submit">

            Load Students

        </button>


    </form>


    <hr>


    <!-- =================================================
         STUDENT ATTENDANCE
    ================================================== -->

    <h2>
        Student Attendance
    </h2>


    <?php if (
        count($students) == 0
    ): ?>


        <p style="color:red;">

            <strong>

                No students found for this section.

            </strong>

        </p>


    <?php else: ?>


        <table
            border="1"
            cellpadding="10"
        >


            <tr>

                <th>
                    Student ID
                </th>

                <th>
                    Register Number
                </th>

                <th>
                    Student Name
                </th>

                <th>
                    Attendance
                </th>

            </tr>


            <?php foreach (
                $students
                as $student
            ): ?>


                <tr>


                    <td>

                        <?php
                        echo $student[
                            'student_id'
                        ];
                        ?>

                    </td>


                    <td>

                        <?php
                        echo htmlspecialchars(
                            $student[
                                'register_number'
                            ]
                        );
                        ?>

                    </td>


                    <td>

                        <?php
                        echo htmlspecialchars(
                            $student[
                                'student_name'
                            ]
                        );
                        ?>

                    </td>


                    <td>


                        <!-- =====================================
                             PRESENT
                        ====================================== -->

                        <form
                            method="POST"
                            style="display:inline;"
                        >


                            <input
                                type="hidden"
                                name="request_id"
                                value="<?php
                                echo $selected_request_id;
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="student_id"
                                value="<?php
                                echo $student[
                                    'student_id'
                                ];
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="attendance_date"
                                value="<?php
                                echo htmlspecialchars(
                                    $selected_date
                                );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="status"
                                value="PRESENT"
                            >


                            <button
                                type="submit"
                            >

                                Present

                            </button>


                        </form>


                        <!-- =====================================
                             ABSENT
                        ====================================== -->

                        <form
                            method="POST"
                            style="display:inline;"
                        >


                            <input
                                type="hidden"
                                name="request_id"
                                value="<?php
                                echo $selected_request_id;
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="student_id"
                                value="<?php
                                echo $student[
                                    'student_id'
                                ];
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="attendance_date"
                                value="<?php
                                echo htmlspecialchars(
                                    $selected_date
                                );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="status"
                                value="ABSENT"
                            >


                            <button
                                type="submit"
                            >

                                Absent

                            </button>


                        </form>


                    </td>


                </tr>


            <?php endforeach; ?>


        </table>


    <?php endif; ?>


<?php endif; ?>


</body>

</html>