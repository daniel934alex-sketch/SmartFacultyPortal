<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Faculty Availability</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 1000px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
        }

        h1 {
            margin-bottom: 10px;
        }

        .date-box {
            margin: 20px 0;
        }

        .period {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px;
            margin-bottom: 12px;
            background: #f8f8f8;
            border-radius: 8px;
        }

        .period-info {
            flex: 1;
        }

        .period-number {
            font-weight: bold;
            font-size: 18px;
        }

        .time {
            color: #666;
            margin-top: 5px;
        }

        button {
            border: none;
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
            margin-left: 5px;
            font-weight: bold;
        }

        .attend {
            background: #28a745;
            color: white;
        }

        .cannot {
            background: #dc3545;
            color: white;
        }

        .leave {
            background: #f0ad4e;
            color: white;
        }

        button:hover {
            opacity: 0.85;
        }

        .status {
            margin-top: 20px;
            padding: 12px;
            display: none;
            background: #e8f5e9;
            border-radius: 6px;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Faculty Availability</h1>

    <p>Select your availability for each period.</p>

    <!-- Date -->
    <div class="date-box">

        <label>
            <strong>Date:</strong>
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

            <div class="time">
                08:30 AM - 09:15 AM
            </div>

        </div>

        <div>

            <button class="attend"
                onclick="setAvailability(1, 'AVAILABLE')">
                CAN ATTEND
            </button>

            <button class="cannot"
                onclick="setAvailability(1, 'UNAVAILABLE')">
                CAN'T GO
            </button>

            <button class="leave"
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

            <div class="time">
                09:15 AM - 10:00 AM
            </div>

        </div>

        <div>

            <button class="attend"
                onclick="setAvailability(2, 'AVAILABLE')">
                CAN ATTEND
            </button>

            <button class="cannot"
                onclick="setAvailability(2, 'UNAVAILABLE')">
                CAN'T GO
            </button>

            <button class="leave"
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

            <div class="time">
                10:00 AM - 10:45 AM
            </div>

        </div>

        <div>

            <button class="attend"
                onclick="setAvailability(3, 'AVAILABLE')">
                CAN ATTEND
            </button>

            <button class="cannot"
                onclick="setAvailability(3, 'UNAVAILABLE')">
                CAN'T GO
            </button>

            <button class="leave"
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

            <div class="time">
                11:00 AM - 11:45 AM
            </div>

        </div>

        <div>

            <button class="attend"
                onclick="setAvailability(4, 'AVAILABLE')">
                CAN ATTEND
            </button>

            <button class="cannot"
                onclick="setAvailability(4, 'UNAVAILABLE')">
                CAN'T GO
            </button>

            <button class="leave"
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

            <div class="time">
                11:45 AM - 12:30 PM
            </div>

        </div>

        <div>

            <button class="attend"
                onclick="setAvailability(5, 'AVAILABLE')">
                CAN ATTEND
            </button>

            <button class="cannot"
                onclick="setAvailability(5, 'UNAVAILABLE')">
                CAN'T GO
            </button>

            <button class="leave"
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

            <div class="time">
                12:30 PM - 01:15 PM
            </div>

        </div>

        <div>

            <button class="attend"
                onclick="setAvailability(6, 'AVAILABLE')">
                CAN ATTEND
            </button>

            <button class="cannot"
                onclick="setAvailability(6, 'UNAVAILABLE')">
                CAN'T GO
            </button>

            <button class="leave"
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

            <div class="time">
                02:00 PM - 02:45 PM
            </div>

        </div>

        <div>

            <button class="attend"
                onclick="setAvailability(7, 'AVAILABLE')">
                CAN ATTEND
            </button>

            <button class="cannot"
                onclick="setAvailability(7, 'UNAVAILABLE')">
                CAN'T GO
            </button>

            <button class="leave"
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

            <div class="time">
                02:45 PM - 03:30 PM
            </div>

        </div>

        <div>

            <button class="attend"
                onclick="setAvailability(8, 'AVAILABLE')">
                CAN ATTEND
            </button>

            <button class="cannot"
                onclick="setAvailability(8, 'UNAVAILABLE')">
                CAN'T GO
            </button>

            <button class="leave"
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

            <div class="time">
                03:45 PM - 04:30 PM
            </div>

        </div>

        <div>

            <button class="attend"
                onclick="setAvailability(9, 'AVAILABLE')">
                CAN ATTEND
            </button>

            <button class="cannot"
                onclick="setAvailability(9, 'UNAVAILABLE')">
                CAN'T GO
            </button>

            <button class="leave"
                onclick="setAvailability(9, 'LEAVE')">
                LEAVE
            </button>

        </div>

    </div>


    <!-- MESSAGE -->
    <div id="statusMessage" class="status"></div>

</div>


<script>

function setAvailability(period, status) {

    const date =
        document.getElementById("availabilityDate").value;

    if (!date) {
        alert("Please select a date.");
        return;
    }

    let reason = "";

    // Ask reason for CAN'T GO
    if (status === "UNAVAILABLE") {

        reason = prompt(
            "Why can't you attend this period?"
        );

        if (reason === null || reason.trim() === "") {

            alert("Reason is required.");

            return;
        }
    }

    // Create data
    const formData = new FormData();

    formData.append("date", date);
    formData.append("period_id", period);
    formData.append("status", status);
    formData.append("reason", reason);


    // Send data to PHP API
    fetch("../api/availability.php", {

        method: "POST",

        body: formData

    })

    .then(response => response.json())

    .then(data => {

        if (data.success) {

            showMessage(
                "Period " +
                period +
                " - " +
                status +
                " saved successfully."
            );

        } else {

            alert(data.message);

        }

    })

    .catch(error => {

        console.error(error);

        alert(
            "Something went wrong. Please try again."
        );

    });
}


function showMessage(message) {

    const box =
        document.getElementById("statusMessage");

    box.innerHTML = message;

    box.style.display = "block";

}

</script>

</body>
</html>ss