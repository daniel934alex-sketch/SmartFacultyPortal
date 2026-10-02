<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'FACULTY') {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

$user_id = (int) $_SESSION['user_id'];


/* =========================================================
   GET CURRENT FACULTY
   ========================================================= */

$stmt = $conn->prepare("
    SELECT
        faculty_id,
        faculty_name,
        faculty_code
    FROM faculty
    WHERE user_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Faculty record not found.");
}

$faculty = $result->fetch_assoc();

$faculty_id = (int) $faculty['faculty_id'];

$stmt->close();


/* =========================================================
   GET HANDOVER REQUESTS
   ========================================================= */

$sql = "

    SELECT

        hr.request_id,

        hr.handover_id,

        hr.status AS request_status,

        hr.requested_at,

        hr.responded_at,

        ch.reason,

        ch.status AS handover_status,

        t.timetable_id,

        t.day,

        t.period_id,

        t.section_id,

        t.subject_id,

        t.classroom_id,

        t.status AS timetable_status,

        original_faculty.faculty_name
            AS original_faculty_name,

        original_faculty.faculty_code
            AS original_faculty_code

    FROM handover_requests hr

    INNER JOIN class_handover ch
        ON ch.handover_id = hr.handover_id

    INNER JOIN timetable t
        ON t.timetable_id = ch.timetable_id

    INNER JOIN faculty original_faculty
        ON original_faculty.faculty_id =
           ch.original_faculty_id

    WHERE hr.alternate_faculty_id = ?

    ORDER BY
        hr.requested_at DESC

";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $faculty_id
);

$stmt->execute();

$result = $stmt->get_result();

$requests = [];

while ($row = $result->fetch_assoc()) {

    $requests[] = $row;
}

$stmt->close();

$conn->close();

?>


<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Handover Requests</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
        }


        .container {
            width: 85%;
            max-width: 1000px;
            margin: 40px auto;
        }


        .header {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow:
                0 3px 12px rgba(0,0,0,0.08);
        }


        .header h1 {
            margin: 0 0 8px 0;
            color: #222;
        }


        .header p {
            margin: 5px 0;
            color: #666;
        }


        .request-card {
            background: white;
            padding: 22px;
            margin-bottom: 18px;
            border-radius: 10px;
            border: 1px solid #ddd;
            box-shadow:
                0 3px 10px rgba(0,0,0,0.05);
        }


        .request-card h3 {
            margin-top: 0;
            color: #222;
        }


        .details {
            line-height: 1.8;
            color: #444;
        }


        .reason {
            margin-top: 12px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #7b2cbf;
        }


        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-top: 10px;
        }


        .pending {
            background: #fff3cd;
            color: #856404;
        }


        .accepted {
            background: #d4edda;
            color: #155724;
        }


        .rejected {
            background: #f8d7da;
            color: #721c24;
        }


        .expired {
            background: #e2e3e5;
            color: #383d41;
        }


        .buttons {
            display: flex;
            gap: 10px;
            margin-top: 18px;
        }


        .buttons button {
            border: none;
            padding: 11px 20px;
            border-radius: 6px;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }


        .accept-btn {
            background: #16803c;
        }


        .accept-btn:hover {
            background: #116b31;
        }


        .reject-btn {
            background: #a71930;
        }


        .reject-btn:hover {
            background: #871426;
        }


        .no-requests {
            background: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px;
            color: #666;
        }


        .message {
            display: none;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            font-weight: bold;
        }


        .success-message {
            background: #d4edda;
            color: #155724;
        }


        .error-message {
            background: #f8d7da;
            color: #721c24;
        }


        @media (max-width: 700px) {

            .container {
                width: 95%;
            }


            .buttons {
                flex-direction: column;
            }


            .buttons button {
                width: 100%;
            }

        }

    </style>

</head>


<body>


<div class="container">


    <!-- HEADER -->

    <div class="header">

        <h1>
            Handover Requests
        </h1>

        <p>
            Faculty:
            <strong>
                <?php
                echo htmlspecialchars(
                    $faculty['faculty_name']
                );
                ?>
            </strong>
        </p>

        <p>
            Faculty Code:
            <strong>
                <?php
                echo htmlspecialchars(
                    $faculty['faculty_code']
                );
                ?>
            </strong>
        </p>

    </div>


    <!-- MESSAGE -->

    <div
        id="successMessage"
        class="message success-message">
    </div>


    <div
        id="errorMessage"
        class="message error-message">
    </div>


    <?php if (count($requests) === 0): ?>


        <div class="no-requests">

            <h3>
                No Handover Requests
            </h3>

            <p>
                You currently have no handover requests.
            </p>

        </div>


    <?php else: ?>


        <?php foreach ($requests as $request): ?>


            <div
                class="request-card"
                id="request-<?php
                    echo (int)$request['request_id'];
                ?>"
            >


                <h3>
                    Handover Request #
                    <?php
                    echo (int)$request['request_id'];
                    ?>
                </h3>


                <div class="details">


                    <strong>
                        From Faculty:
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $request['original_faculty_name']
                    );
                    ?>

                    <br>


                    <strong>
                        Faculty Code:
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $request['original_faculty_code']
                    );
                    ?>

                    <br>


                    <strong>
                        Day:
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $request['day']
                    );
                    ?>

                    <br>


                    <strong>
                        Period:
                    </strong>

                    <?php
                    echo (int)$request['period_id'];
                    ?>

                    <br>


                    <strong>
                        Section ID:
                    </strong>

                    <?php
                    echo (int)$request['section_id'];
                    ?>

                    <br>


                    <strong>
                        Subject ID:
                    </strong>

                    <?php
                    echo (int)$request['subject_id'];
                    ?>

                    <br>


                    <strong>
                        Classroom ID:
                    </strong>

                    <?php
                    echo (int)$request['classroom_id'];
                    ?>

                    <br>


                    <strong>
                        Requested At:
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $request['requested_at']
                    );
                    ?>


                </div>


                <div class="reason">

                    <strong>
                        Reason:
                    </strong>

                    <br>

                    <?php
                    echo nl2br(
                        htmlspecialchars(
                            $request['reason']
                        )
                    );
                    ?>

                </div>


                <?php

                $status =
                    $request['request_status'];

                $statusClass =
                    strtolower($status);

                ?>


                <div
                    class="status <?php
                        echo $statusClass;
                    ?>"
                >

                    <?php
                    echo htmlspecialchars(
                        $status
                    );
                    ?>

                </div>


                <?php if ($status === 'PENDING'): ?>


                    <div class="buttons">


                        <button
                            type="button"
                            class="accept-btn"
                            onclick="respondRequest(
                                <?php
                                echo (int)$request['request_id'];
                                ?>,
                                'ACCEPTED'
                            )"
                        >

                            ACCEPT

                        </button>


                        <button
                            type="button"
                            class="reject-btn"
                            onclick="respondRequest(
                                <?php
                                echo (int)$request['request_id'];
                                ?>,
                                'REJECTED'
                            )"
                        >

                            REJECT

                        </button>


                    </div>


                <?php endif; ?>


            </div>


        <?php endforeach; ?>


    <?php endif; ?>


</div>


<script>


/* =========================================================
   RESPOND TO REQUEST
   ========================================================= */

async function respondRequest(
    requestId,
    responseStatus
) {


    let message;


    if (
        responseStatus === 'ACCEPTED'
    ) {

        message =
            "Are you sure you want to ACCEPT this handover request?";

    } else {

        message =
            "Are you sure you want to REJECT this handover request?";

    }


    const confirmed =
        confirm(message);


    if (!confirmed) {

        return;
    }


    const formData =
        new FormData();


    formData.append(
        "request_id",
        requestId
    );


    formData.append(
        "status",
        responseStatus
    );


    try {


        const response =
            await fetch(
                "../api/respond_handover.php",
                {
                    method: "POST",
                    body: formData
                }
            );


        const text =
            await response.text();


        console.log(
            "Respond Handover API:",
            text
        );


        let data;


        try {

            data =
                JSON.parse(text);

        } catch (error) {

            showError(
                "Invalid server response:\n\n" +
                text
            );

            return;
        }


        if (data.success) {


            showSuccess(
                data.message
            );


            setTimeout(
                function() {

                    location.reload();

                },
                1200
            );


        } else {


            showError(
                data.message ||
                "Unable to update request."
            );

        }


    } catch (error) {


        console.error(error);


        showError(
            "Unable to connect to server.\n\n" +
            error.message
        );

    }

}


/* =========================================================
   SHOW SUCCESS
   ========================================================= */

function showSuccess(message) {

    const box =
        document.getElementById(
            "successMessage"
        );


    box.innerText =
        message;


    box.style.display =
        "block";


    document.getElementById(
        "errorMessage"
    ).style.display =
        "none";

}


/* =========================================================
   SHOW ERROR
   ========================================================= */

function showError(message) {

    const box =
        document.getElementById(
            "errorMessage"
        );


    box.innerText =
        message;


    box.style.display =
        "block";


    document.getElementById(
        "successMessage"
    ).style.display =
        "none";

}


</script>


</body>

</html>