<?php

require_once("../config/db.php");

/* --------------------------------
   Testing Alternate Faculty
-------------------------------- */

$faculty_id = 2;

$authorization_id = isset($_GET['request_id'])
    ? (int)$_GET['request_id']
    : 0;


/* --------------------------------
   Get Accepted Handover Requests
-------------------------------- */

$requests = [];

$stmt = $conn->prepare("
    SELECT
        hr.request_id,
        hr.handover_id,
        hr.alternate_faculty_id,
        hr.status,
        hr.requested_at,
        hr.responded_at,

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

    ORDER BY hr.responded_at DESC
");

$stmt->bind_param(
    "i",
    $faculty_id
);

$stmt->execute();

$result = $stmt->get_result();

while (
    $row = $result->fetch_assoc()
) {

    $requests[] = $row;
}


/* --------------------------------
   Authorization Check
-------------------------------- */

$selected_request = null;
$authorization_passed = false;
$authorization_message = "";


if ($authorization_id > 0) {

    $check_stmt = $conn->prepare("
        SELECT
            hr.request_id,
            hr.handover_id,
            hr.alternate_faculty_id,
            hr.status,

            ch.timetable_id,
            ch.original_faculty_id,

            t.status AS timetable_status

        FROM handover_requests hr

        INNER JOIN class_handover ch
            ON hr.handover_id = ch.handover_id

        INNER JOIN timetable t
            ON ch.timetable_id = t.timetable_id

        WHERE hr.request_id = ?
    ");

    $check_stmt->bind_param(
        "i",
        $authorization_id
    );

    $check_stmt->execute();

    $check_result =
        $check_stmt->get_result();

    $selected_request =
        $check_result->fetch_assoc();


    if (!$selected_request) {

        $authorization_message =
            "Authorization request not found.";

    } elseif (
        (int)$selected_request['alternate_faculty_id']
        !== $faculty_id
    ) {

        $authorization_message =
            "This request is not assigned to this faculty.";

    } elseif (
        $selected_request['status']
        !== "ACCEPTED"
    ) {

        $authorization_message =
            "Alternate faculty authorization is not accepted.";

    } elseif (
        $selected_request['timetable_status']
        !== "PUBLISHED"
    ) {

        $authorization_message =
            "Timetable is not published.";

    } else {

        $authorization_passed = true;

        $authorization_message =
            "Alternate faculty authorization verified successfully.";
    }
}

?>

<!DOCTYPE html>

<html>

<head>

    <title>
        Handover Authorization
    </title>

</head>

<body>


<h1>
    Alternate Faculty Authorization
</h1>


<h2>
    Faculty Details
</h2>


<p>

    <strong>
        Testing Faculty ID:
    </strong>

    <?php
    echo $faculty_id;
    ?>

</p>


<hr>


<h2>
    Accepted Handover Requests
</h2>


<?php if (count($requests) == 0): ?>

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
                Day
            </th>

            <th>
                Period
            </th>

            <th>
                Subject
            </th>

            <th>
                Section
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
                Authorization
            </th>

        </tr>


        <?php foreach ($requests as $request): ?>


            <tr>

                <td>

                    <?php
                    echo $request['request_id'];
                    ?>

                </td>


                <td>

                    <?php
                    echo htmlspecialchars(
                        $request['day']
                    );
                    ?>

                </td>


                <td>

                    <?php
                    echo $request['period_number'];
                    ?>

                    <br>

                    <?php

                    echo date(
                        "H:i",
                        strtotime(
                            $request['start_time']
                        )
                    );

                    ?>

                    -

                    <?php

                    echo date(
                        "H:i",
                        strtotime(
                            $request['end_time']
                        )
                    );

                    ?>

                </td>


                <td>

                    <?php
                    echo htmlspecialchars(
                        $request['subject_code']
                    );
                    ?>

                    -

                    <?php
                    echo htmlspecialchars(
                        $request['subject_name']
                    );
                    ?>

                </td>


                <td>

                    <?php
                    echo htmlspecialchars(
                        $request['section_name']
                    );
                    ?>

                </td>


                <td>

                    <?php
                    echo htmlspecialchars(
                        $request['original_faculty_name']
                    );
                    ?>

                </td>


                <td>

                    <?php
                    echo htmlspecialchars(
                        $request['alternate_faculty_name']
                    );
                    ?>

                </td>


                <td>

                    <strong>

                        <?php
                        echo htmlspecialchars(
                            $request['status']
                        );
                        ?>

                    </strong>

                </td>


                <td>

                    <a
                        href="?request_id=<?php
                        echo $request['request_id'];
                        ?>"
                    >

                        Check Authorization

                    </a>

                </td>

            </tr>


        <?php endforeach; ?>


    </table>


<?php endif; ?>


<hr>


<?php if ($authorization_id > 0): ?>


    <h2>
        Authorization Result
    </h2>


    <?php if ($authorization_passed): ?>


        <p style="color:green;">

            <strong>

                AUTHORIZATION VERIFIED

            </strong>

        </p>


        <p>

            <?php
            echo htmlspecialchars(
                $authorization_message
            );
            ?>

        </p>


        <p>

            Alternate faculty is authorized
            for this handover.

        </p>


    <?php else: ?>


        <p style="color:red;">

            <strong>

                AUTHORIZATION FAILED

            </strong>

        </p>


        <p>

            <?php
            echo htmlspecialchars(
                $authorization_message
            );
            ?>

        </p>


    <?php endif; ?>


<?php endif; ?>


<hr>


<h2>
    Authorization Rules
</h2>


<ul>

    <li>
        Request must exist
    </li>

    <li>
        Request must belong to the alternate faculty
    </li>

    <li>
        Request status must be ACCEPTED
    </li>

    <li>
        Related timetable must be PUBLISHED
    </li>

</ul>


</body>

</html>