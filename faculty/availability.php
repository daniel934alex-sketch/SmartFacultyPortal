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

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Faculty Availability</title>


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
            width: 80%;
            max-width: 1000px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }


        h1 {
            margin-top: 0;
            color: #222;
        }


        .description {
            color: #555;
            margin-bottom: 25px;
        }


        .date-section {
            margin-bottom: 25px;
        }


        .date-section label {
            font-weight: bold;
            margin-right: 10px;
        }


        #availabilityDate {
            padding: 9px 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }


        .period {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f8f9fa;
            border: 1px solid #e1e4e8;
            padding: 18px;
            margin-bottom: 12px;
            border-radius: 8px;
        }


        .period-info {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }


        .period-number {
            font-size: 17px;
            font-weight: bold;
            color: #222;
        }


        .period-time {
            font-size: 14px;
            color: #666;
        }


        .buttons {
            display: flex;
            gap: 8px;
        }


        .buttons button {
            border: none;
            padding: 10px 14px;
            border-radius: 5px;
            color: white;
            cursor: pointer;
            font-weight: bold;
            font-size: 12px;
        }


        .attend-btn {
            background: #16803c;
        }


        .attend-btn:hover {
            background: #116b31;
        }


        .cant-btn {
            background: #a71930;
        }


        .cant-btn:hover {
            background: #871426;
        }


        .leave-btn {
            background: #a36b00;
        }


        .leave-btn:hover {
            background: #855700;
        }


        .class-box {
            margin-top: 25px;
            padding: 20px;
            border-radius: 8px;
            background: #eef6ff;
            border: 1px solid #b8d8ff;
            display: none;
        }


        .class-box h3 {
            margin-top: 0;
            color: #174a7e;
        }


        .class-details {
            line-height: 1.8;
            color: #333;
        }


        .handover-btn {
            margin-top: 15px;
            padding: 11px 18px;
            background: #7b2cbf;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }


        .handover-btn:hover {
            background: #60209a;
        }


        .faculty-box {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 8px;
            display: none;
        }


        .faculty-box h3 {
            margin-top: 0;
        }


        .faculty-item {
            background: white;
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 6px;
        }


        .faculty-item strong {
            color: #222;
        }


        .select-btn {
            margin-top: 10px;
            padding: 8px 14px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }


        .select-btn:hover {
            background: #1d4ed8;
        }


        .send-btn {
            margin-top: 15px;
            padding: 11px 18px;
            background: #16803c;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }


        .send-btn:hover {
            background: #116b31;
        }


        .send-btn:disabled {
            background: #777;
            cursor: not-allowed;
        }


        .selected-faculty {
            margin-top: 15px;
            padding: 12px;
            background: #e8f5e9;
            border: 1px solid #a5d6a7;
            border-radius: 5px;
            font-weight: bold;
        }


        @media (max-width: 700px) {

            .container {
                width: 95%;
                padding: 20px;
            }


            .period {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }


            .buttons {
                width: 100%;
                flex-wrap: wrap;
            }


            .buttons button {
                flex: 1;
            }

        }

    </style>

</head>


<body>


<div class="container">


    <h1>
        Faculty Availability
    </h1>


    <p class="description">
        Select your availability for each period.
    </p>


    <!-- DATE -->

    <div class="date-section">

        <label for="availabilityDate">
            Date:
        </label>

        <input
            type="date"
            id="availabilityDate"
            value="<?php echo date('Y-m-d'); ?>"
        >

    </div>


    <!-- PERIOD 1 -->

    <div class="period">

        <div class="period-info">

            <div class="period-number">
                Period 1
            </div>

            <div class="period-time">
                08:30 AM - 09:15 AM
            </div>

        </div>


        <div class="buttons">

            <button
                type="button"
                class="attend-btn"
                onclick="setAvailability(1, 'AVAILABLE')">

                CAN ATTEND

            </button>


            <button
                type="button"
                class="cant-btn"
                onclick="setAvailability(1, 'UNAVAILABLE')">

                CAN'T GO

            </button>


            <button
                type="button"
                class="leave-btn"
                onclick="setAvailability(1, 'LEAVE')">

                LEAVE

            </button>

        </div>

    </div>


    <!-- PERIOD 2 -->

    <div class="period">

        <div class="period-info">

            <div class="period-number">
                Period 2
            </div>

            <div class="period-time">
                09:15 AM - 10:00 AM
            </div>

        </div>


        <div class="buttons">

            <button
                type="button"
                class="attend-btn"
                onclick="setAvailability(2, 'AVAILABLE')">

                CAN ATTEND

            </button>


            <button
                type="button"
                class="cant-btn"
                onclick="setAvailability(2, 'UNAVAILABLE')">

                CAN'T GO

            </button>


            <button
                type="button"
                class="leave-btn"
                onclick="setAvailability(2, 'LEAVE')">

                LEAVE

            </button>

        </div>

    </div>


    <!-- PERIOD 3 -->

    <div class="period">

        <div class="period-info">

            <div class="period-number">
                Period 3
            </div>

            <div class="period-time">
                10:00 AM - 10:45 AM
            </div>

        </div>


        <div class="buttons">

            <button
                type="button"
                class="attend-btn"
                onclick="setAvailability(3, 'AVAILABLE')">

                CAN ATTEND

            </button>


            <button
                type="button"
                class="cant-btn"
                onclick="setAvailability(3, 'UNAVAILABLE')">

                CAN'T GO

            </button>


            <button
                type="button"
                class="leave-btn"
                onclick="setAvailability(3, 'LEAVE')">

                LEAVE

            </button>

        </div>

    </div>


    <!-- PERIOD 4 -->

    <div class="period">

        <div class="period-info">

            <div class="period-number">
                Period 4
            </div>

            <div class="period-time">
                11:00 AM - 11:45 AM
            </div>

        </div>


        <div class="buttons">

            <button
                type="button"
                class="attend-btn"
                onclick="setAvailability(4, 'AVAILABLE')">

                CAN ATTEND

            </button>


            <button
                type="button"
                class="cant-btn"
                onclick="setAvailability(4, 'UNAVAILABLE')">

                CAN'T GO

            </button>


            <button
                type="button"
                class="leave-btn"
                onclick="setAvailability(4, 'LEAVE')">

                LEAVE

            </button>

        </div>

    </div>


    <!-- PERIOD 5 -->

    <div class="period">

        <div class="period-info">

            <div class="period-number">
                Period 5
            </div>

            <div class="period-time">
                11:45 AM - 12:30 PM
            </div>

        </div>


        <div class="buttons">

            <button
                type="button"
                class="attend-btn"
                onclick="setAvailability(5, 'AVAILABLE')">

                CAN ATTEND

            </button>


            <button
                type="button"
                class="cant-btn"
                onclick="setAvailability(5, 'UNAVAILABLE')">

                CAN'T GO

            </button>


            <button
                type="button"
                class="leave-btn"
                onclick="setAvailability(5, 'LEAVE')">

                LEAVE

            </button>

        </div>

    </div>


    <!-- PERIOD 6 -->

    <div class="period">

        <div class="period-info">

            <div class="period-number">
                Period 6
            </div>

            <div class="period-time">
                12:30 PM - 01:15 PM
            </div>

        </div>


        <div class="buttons">

            <button
                type="button"
                class="attend-btn"
                onclick="setAvailability(6, 'AVAILABLE')">

                CAN ATTEND

            </button>


            <button
                type="button"
                class="cant-btn"
                onclick="setAvailability(6, 'UNAVAILABLE')">

                CAN'T GO

            </button>


            <button
                type="button"
                class="leave-btn"
                onclick="setAvailability(6, 'LEAVE')">

                LEAVE

            </button>

        </div>

    </div>


    <!-- PERIOD 7 -->

    <div class="period">

        <div class="period-info">

            <div class="period-number">
                Period 7
            </div>

            <div class="period-time">
                02:00 PM - 02:45 PM
            </div>

        </div>


        <div class="buttons">

            <button
                type="button"
                class="attend-btn"
                onclick="setAvailability(7, 'AVAILABLE')">

                CAN ATTEND

            </button>


            <button
                type="button"
                class="cant-btn"
                onclick="setAvailability(7, 'UNAVAILABLE')">

                CAN'T GO

            </button>


            <button
                type="button"
                class="leave-btn"
                onclick="setAvailability(7, 'LEAVE')">

                LEAVE

            </button>

        </div>

    </div>


    <!-- PERIOD 8 -->

    <div class="period">

        <div class="period-info">

            <div class="period-number">
                Period 8
            </div>

            <div class="period-time">
                02:45 PM - 03:30 PM
            </div>

        </div>


        <div class="buttons">

            <button
                type="button"
                class="attend-btn"
                onclick="setAvailability(8, 'AVAILABLE')">

                CAN ATTEND

            </button>


            <button
                type="button"
                class="cant-btn"
                onclick="setAvailability(8, 'UNAVAILABLE')">

                CAN'T GO

            </button>


            <button
                type="button"
                class="leave-btn"
                onclick="setAvailability(8, 'LEAVE')">

                LEAVE

            </button>

        </div>

    </div>


    <!-- PERIOD 9 -->

    <div class="period">

        <div class="period-info">

            <div class="period-number">
                Period 9
            </div>

            <div class="period-time">
                03:45 PM - 04:30 PM
            </div>

        </div>


        <div class="buttons">

            <button
                type="button"
                class="attend-btn"
                onclick="setAvailability(9, 'AVAILABLE')">

                CAN ATTEND

            </button>


            <button
                type="button"
                class="cant-btn"
                onclick="setAvailability(9, 'UNAVAILABLE')">

                CAN'T GO

            </button>


            <button
                type="button"
                class="leave-btn"
                onclick="setAvailability(9, 'LEAVE')">

                LEAVE

            </button>

        </div>

    </div>


    <!-- CLASS FOUND -->

    <div
        id="classBox"
        class="class-box">


        <h3>
            Class Found
        </h3>


        <div
            id="classDetails"
            class="class-details">
        </div>


        <button
            type="button"
            class="handover-btn"
            onclick="startHandover()">

            FIND ALTERNATE FACULTY

        </button>


    </div>


    <!-- ALTERNATE FACULTY -->

    <div
        id="facultyBox"
        class="faculty-box">


        <h3>
            Eligible Alternate Faculty
        </h3>


        <div id="facultyList"></div>


        <div
            id="selectedFacultyBox"
            class="selected-faculty"
            style="display:none;">
        </div>


        <button
            id="sendRequestButton"
            type="button"
            class="send-btn"
            style="display:none;"
            onclick="sendHandoverRequest()">

            SEND HANDOVER REQUEST

        </button>


    </div>


</div>


<script>


/* =========================================================
   GLOBAL VARIABLES
   ========================================================= */

let selectedTimetableId = null;

let selectedPeriodId = null;

let selectedAlternateFacultyId = null;

let selectedAlternateFacultyName = "";


/* =========================================================
   SAVE AVAILABILITY
   ========================================================= */

async function setAvailability(
    periodId,
    status
) {

    const date =
        document.getElementById(
            "availabilityDate"
        ).value;


    if (!date) {

        alert(
            "Please select a date."
        );

        return;
    }


    let reason = "";


    if (
        status === "UNAVAILABLE"
    ) {

        reason = prompt(
            "Why can't you attend this period?"
        );


        if (
            reason === null ||
            reason.trim() === ""
        ) {

            alert(
                "Please provide a reason."
            );

            return;
        }

    }


    const formData =
        new FormData();


    formData.append(
        "date",
        date
    );


    formData.append(
        "period_id",
        periodId
    );


    formData.append(
        "status",
        status
    );


    formData.append(
        "reason",
        reason
    );


    try {

        const response =
            await fetch(
                "../api/availability.php",
                {
                    method: "POST",
                    body: formData
                }
            );


        const text =
            await response.text();


        console.log(
            "Availability API:",
            text
        );


        let data;


        try {

            data =
                JSON.parse(text);

        } catch (error) {

            alert(
                "Invalid server response.\n\n" +
                text
            );

            return;
        }


        if (
            data.success
        ) {

            alert(
                data.message
            );


            if (
                status === "UNAVAILABLE"
            ) {

                await checkClass(
                    date,
                    periodId
                );

            }

        } else {

            alert(
                data.message ||
                "Something went wrong."
            );

        }


    } catch (error) {

        console.error(
            error
        );


        alert(
            "Unable to connect to server.\n\n" +
            error.message
        );

    }

}


/* =========================================================
   CHECK CLASS
   ========================================================= */

async function checkClass(
    date,
    periodId
) {

    const formData =
        new FormData();


    formData.append(
        "date",
        date
    );


    formData.append(
        "period_id",
        periodId
    );


    try {

        const response =
            await fetch(
                "../api/check_class.php",
                {
                    method: "POST",
                    body: formData
                }
            );


        const text =
            await response.text();


        console.log(
            "Class Check API:",
            text
        );


        let data;


        try {

            data =
                JSON.parse(text);

        } catch (error) {

            alert(
                "Invalid class response.\n\n" +
                text
            );

            return;
        }


        if (
            data.success &&
            data.class_found
        ) {

            const c =
                data.class;


            selectedTimetableId =
                c.timetable_id;


            selectedPeriodId =
                periodId;


            document.getElementById(
                "classDetails"
            ).innerHTML =

                "<strong>Date:</strong> " +
                date +
                "<br>" +

                "<strong>Day:</strong> " +
                data.day +
                "<br>" +

                "<strong>Timetable ID:</strong> " +
                c.timetable_id +
                "<br>" +

                "<strong>Period:</strong> " +
                c.period_id +
                "<br>" +

                "<strong>Section ID:</strong> " +
                c.section_id +
                "<br>" +

                "<strong>Subject ID:</strong> " +
                c.subject_id +
                "<br>" +

                "<strong>Classroom ID:</strong> " +
                c.classroom_id;


            document.getElementById(
                "classBox"
            ).style.display =
                "block";


            document.getElementById(
                "facultyBox"
            ).style.display =
                "none";


        } else {

            selectedTimetableId =
                null;


            selectedPeriodId =
                null;


            document.getElementById(
                "classBox"
            ).style.display =
                "none";


            alert(
                "No class is scheduled for this faculty in this period."
            );

        }


    } catch (error) {

        console.error(
            error
        );


        alert(
            "Unable to check class.\n\n" +
            error.message
        );

    }

}


/* =========================================================
   FIND ALTERNATE FACULTY
   ========================================================= */

async function startHandover() {

    if (
        !selectedTimetableId
    ) {

        alert(
            "No class selected."
        );

        return;
    }


    const date =
        document.getElementById(
            "availabilityDate"
        ).value;


    if (
        !selectedPeriodId
    ) {

        alert(
            "Period information is missing."
        );

        return;
    }


    const formData =
        new FormData();


    formData.append(
        "date",
        date
    );


    formData.append(
        "period_id",
        selectedPeriodId
    );


    try {

        const response =
            await fetch(
                "../api/find_alternate_faculty.php",
                {
                    method: "POST",
                    body: formData
                }
            );


        const text =
            await response.text();


        console.log(
            "Alternate Faculty API:",
            text
        );


        let data;


        try {

            data =
                JSON.parse(text);

        } catch (error) {

            alert(
                "Invalid alternate faculty response.\n\n" +
                text
            );

            return;
        }


        if (
            !data.success
        ) {

            alert(
                data.message ||
                "Unable to find alternate faculty."
            );

            return;
        }


        const facultyList =
            data.faculty || [];


        const facultyBox =
            document.getElementById(
                "facultyBox"
            );


        const facultyListDiv =
            document.getElementById(
                "facultyList"
            );


        facultyListDiv.innerHTML =
            "";


        selectedAlternateFacultyId =
            null;


        selectedAlternateFacultyName =
            "";


        document.getElementById(
            "selectedFacultyBox"
        ).style.display =
            "none";


        document.getElementById(
            "sendRequestButton"
        ).style.display =
            "none";


        if (
            facultyList.length === 0
        ) {

            facultyListDiv.innerHTML =

                "<p>No eligible alternate faculty found.</p>";


            facultyBox.style.display =
                "block";


            return;
        }


        /* =============================================
           DISPLAY FACULTY LIST
           ============================================= */

        facultyList.forEach(
            function(faculty) {


                const item =
                    document.createElement(
                        "div"
                    );


                item.className =
                    "faculty-item";


                item.innerHTML =

                    "<strong>" +
                    escapeHtml(
                        faculty.faculty_name
                    ) +
                    "</strong>" +

                    "<br>" +

                    "Faculty Code: " +
                    escapeHtml(
                        faculty.faculty_code
                    ) +

                    "<br>" +

                    "Email: " +
                    escapeHtml(
                        faculty.email
                    ) +

                    "<br>" +

                    "Designation: " +
                    escapeHtml(
                        faculty.designation ||
                        "N/A"
                    ) +

                    "<br>" +

                    "<button " +

                    "type='button' " +

                    "class='select-btn' " +

                    "onclick='selectFaculty(" +

                    faculty.faculty_id +

                    ", " +

                    JSON.stringify(
                        faculty.faculty_name
                    ) +

                    ")'>" +

                    "SELECT" +

                    "</button>";


                facultyListDiv.appendChild(
                    item
                );

            }
        );


        facultyBox.style.display =
            "block";

    } catch (error) {

        console.error(
            "Alternate Faculty Error:",
            error
        );


        alert(
            "Unable to find alternate faculty.\n\n" +
            error.message
        );

    }

}


/* =========================================================
   SELECT FACULTY
   ========================================================= */

function selectFaculty(
    facultyId,
    facultyName
) {

    selectedAlternateFacultyId =
        facultyId;


    selectedAlternateFacultyName =
        facultyName;


    const selectedBox =
        document.getElementById(
            "selectedFacultyBox"
        );


    selectedBox.innerHTML =

        "Selected Faculty: " +
        escapeHtml(
            facultyName
        );


    selectedBox.style.display =
        "block";


    document.getElementById(
        "sendRequestButton"
    ).style.display =
        "inline-block";


    alert(
        facultyName +
        " selected successfully."
    );

}


/* =========================================================
   SEND HANDOVER REQUEST
   ========================================================= */

async function sendHandoverRequest() {

    if (
        !selectedTimetableId
    ) {

        alert(
            "No class selected."
        );

        return;
    }


    if (
        !selectedAlternateFacultyId
    ) {

        alert(
            "Please select an alternate faculty."
        );

        return;
    }


    const reason =
        prompt(
            "Enter the reason for handover:"
        );


    if (
        reason === null ||
        reason.trim() === ""
    ) {

        alert(
            "Please provide a handover reason."
        );

        return;
    }


    const confirmSend =
        confirm(
            "Send handover request to " +
            selectedAlternateFacultyName +
            "?"
        );


    if (!confirmSend) {

        return;
    }


    const formData =
        new FormData();


    formData.append(
        "timetable_id",
        selectedTimetableId
    );


    formData.append(
        "alternate_faculty_id",
        selectedAlternateFacultyId
    );


    formData.append(
        "reason",
        reason
    );


    try {

        const response =
            await fetch(
                "../api/create_handover.php",
                {
                    method: "POST",
                    body: formData
                }
            );


        const text =
            await response.text();


        console.log(
            "Create Handover API:",
            text
        );


        let data;


        try {

            data =
                JSON.parse(text);

        } catch (error) {

            alert(
                "Invalid handover response.\n\n" +
                text
            );

            return;
        }


        if (
            data.success
        ) {

            alert(

                "Handover request sent successfully!\n\n" +

                "Alternate Faculty: " +
                data.alternate_faculty +

                "\n\n" +

                "Request ID: " +
                data.request_id

            );


            const button =
                document.getElementById(
                    "sendRequestButton"
                );


            button.disabled =
                true;


            button.innerText =
                "REQUEST SENT";


        } else {

            alert(
                data.message ||
                "Unable to create handover request."
            );

        }


    } catch (error) {

        console.error(
            "Handover Request Error:",
            error
        );


        alert(
            "Unable to send handover request.\n\n" +
            error.message
        );

    }

}


/* =========================================================
   HTML ESCAPE
   ========================================================= */

function escapeHtml(value) {

    if (
        value === null ||
        value === undefined
    ) {

        return "";

    }


    return String(value)

        .replace(
            /&/g,
            "&amp;"
        )

        .replace(
            /</g,
            "&lt;"
        )

        .replace(
            />/g,
            "&gt;"
        )

        .replace(
            /"/g,
            "&quot;"
        )

        .replace(
            /'/g,
            "&#039;"
        );

}

</script>

</body>

</html>