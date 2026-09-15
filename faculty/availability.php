<?php
require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
require_once "../config/database.php";

requireRole('FACULTY');

$user_id = $_SESSION['user_id'];
$message = "";

/*
 * Find faculty_id using the logged-in user_id
 */
$stmt = $conn->prepare("
    SELECT faculty_id
    FROM faculty
    WHERE user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$faculty = $result->fetch_assoc();

if (!$faculty) {
    die("Faculty record not found.");
}

$faculty_id = $faculty['faculty_id'];


/*
 * Handle availability submission
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $status = $_POST['status'] ?? '';

    $allowed_statuses = [
        'I CAN ATTEND',
        "I CAN'T GO",
        'LEAVE'
    ];

    if (!in_array($status, $allowed_statuses, true)) {

        $message = "Invalid availability status.";

    } else {

        /*
         * Check whether this faculty already has
         * an availability record.
         */
        $check = $conn->prepare("
            SELECT availability_id
            FROM faculty_availability
            WHERE faculty_id = ?
            LIMIT 1
        ");

        $check->bind_param("i", $faculty_id);
        $check->execute();

        $existing = $check->get_result()->fetch_assoc();

        if ($existing) {

            /*
             * Update existing availability
             */
            $update = $conn->prepare("
                UPDATE faculty_availability
                SET status = ?
                WHERE faculty_id = ?
            ");

            $update->bind_param("si", $status, $faculty_id);
            $update->execute();

            $message = "Availability updated successfully.";

        } else {

            /*
             * Insert first availability record
             */
            $insert = $conn->prepare("
                INSERT INTO faculty_availability
                (faculty_id, status)
                VALUES (?, ?)
            ");

            $insert->bind_param("is", $faculty_id, $status);
            $insert->execute();

            $message = "Availability saved successfully.";
        }
    }
}


/*
 * Get current availability
 */
$current_status = "Not Set";

$current = $conn->prepare("
    SELECT status
    FROM faculty_availability
    WHERE faculty_id = ?
    LIMIT 1
");

$current->bind_param("i", $faculty_id);
$current->execute();

$current_result = $current->get_result();
$current_data = $current_result->fetch_assoc();

if ($current_data) {
    $current_status = $current_data['status'];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Faculty Availability</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 700px;
            margin: 50px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        h1 {
            margin-bottom: 10px;
        }

        .welcome {
            color: #555;
            margin-bottom: 25px;
        }

        .current-status {
            background: #f8f9fa;
            padding: 18px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .current-status strong {
            font-size: 18px;
        }

        .buttons {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 25px;
        }

        button {
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 17px;
            font-weight: bold;
            cursor: pointer;
        }

        .attend {
            background: #198754;
            color: white;
        }

        .not-attend {
            background: #dc3545;
            color: white;
        }

        .leave {
            background: #ffc107;
            color: black;
        }

        button:hover {
            opacity: 0.85;
        }

        .message {
            background: #d1e7dd;
            color: #0f5132;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .back {
            display: inline-block;
            margin-top: 25px;
            text-decoration: none;
            color: #0d6efd;
            font-weight: bold;
        }

    </style>

</head>

<body>

<div class="container">

    <h1>📢 Faculty Availability</h1>

    <div class="welcome">

        Welcome,
        <strong>
            <?= htmlspecialchars($_SESSION['username']) ?>
        </strong>

    </div>


    <?php if ($message): ?>

        <div class="message">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <div class="current-status">

        Current Availability:

        <strong>
            <?= htmlspecialchars($current_status) ?>
        </strong>

    </div>


    <p>Select your availability status:</p>


    <div class="buttons">

        <form method="POST">

            <input type="hidden"
                   name="status"
                   value="I CAN ATTEND">

            <button type="submit"
                    class="attend">

                ✓ I CAN ATTEND

            </button>

        </form>


        <form method="POST">

            <input type="hidden"
                   name="status"
                   value="I CAN'T GO">

            <button type="submit"
                    class="not-attend">

                ✕ I CAN'T GO

            </button>

        </form>


        <form method="POST">

            <input type="hidden"
                   name="status"
                   value="LEAVE">

            <button type="submit"
                    class="leave">

                🏖 LEAVE

            </button>

        </form>

    </div>


    <a class="back"
       href="dashboard.php">

        ← Back to Dashboard

    </a>

</div>

</body>

</html>