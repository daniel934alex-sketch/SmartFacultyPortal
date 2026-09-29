<?php
require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole('FACULTY');

require_once "../config/database.php";

$user_id = $_SESSION['user_id'];

/* Handle faculty response */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $notification_id = intval($_POST['notification_id'] ?? 0);
    $response = $_POST['response'] ?? '';

    $allowed_responses = [
        'I CAN ATTEND',
        "I CAN'T GO",
        'LEAVE'
    ];

    if (
        $notification_id > 0 &&
        in_array($response, $allowed_responses, true)
    ) {

        $update_sql = "
            UPDATE notifications
            SET response = ?
            WHERE notification_id = ?
            AND user_id = ?
        ";

        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param(
            "sii",
            $response,
            $notification_id,
            $user_id
        );

        $update_stmt->execute();
        $update_stmt->close();
    }
}

/* Get notifications */
$sql = "
    SELECT
        notification_id,
        title,
        message,
        notification_type,
        response,
        is_read,
        created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Faculty Notifications</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .notification {
            border: 1px solid #ccc;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            background: #e8f1ff;
        }

        .response-buttons {
            margin-top: 15px;
        }

        .response-buttons button {
            padding: 10px 15px;
            margin: 5px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }

        .attend {
            background: #198754;
            color: white;
        }

        .cannot {
            background: #dc3545;
            color: white;
        }

        .leave {
            background: #ffc107;
            color: black;
        }

        .saved-response {
            margin-top: 15px;
            font-weight: bold;
        }
    </style>

</head>

<body>

<h1>My Notifications</h1>

<p>
    Welcome,
    <?= htmlspecialchars($_SESSION['username']) ?>
</p>

<?php if ($result->num_rows === 0): ?>

    <p>No notifications available.</p>

<?php else: ?>

    <?php while ($notification = $result->fetch_assoc()): ?>

        <div class="notification">

            <h3>
                <?= htmlspecialchars($notification['title']) ?>
            </h3>

            <p>
                <?= htmlspecialchars($notification['message']) ?>
            </p>

            <small>
                Type:
                <?= htmlspecialchars(
                    $notification['notification_type'] ?? 'General'
                ) ?>

                |

                <?= htmlspecialchars($notification['created_at']) ?>
            </small>

            <?php if (!$notification['is_read']): ?>

                <strong style="color: blue;">
                    NEW
                </strong>

            <?php endif; ?>


            <!-- Response section -->

            <?php if (empty($notification['response'])): ?>

                <form method="POST" class="response-buttons">

                    <input
                        type="hidden"
                        name="notification_id"
                        value="<?= $notification['notification_id'] ?>"
                    >

                    <button
                        type="submit"
                        name="response"
                        value="I CAN ATTEND"
                        class="attend"
                    >
                        ✓ I CAN ATTEND
                    </button>

                    <button
                        type="submit"
                        name="response"
                        value="I CAN'T GO"
                        class="cannot"
                    >
                        ✕ I CAN'T GO
                    </button>

                    <button
                        type="submit"
                        name="response"
                        value="LEAVE"
                        class="leave"
                    >
                        🏖 LEAVE
                    </button>

                </form>

            <?php else: ?>

                <div class="saved-response">
                    Your response:
                    <?= htmlspecialchars($notification['response']) ?>
                </div>

            <?php endif; ?>

        </div>

    <?php endwhile; ?>

<?php endif; ?>

<br>

<a href="dashboard.php">
    ← Back to Dashboard
</a>

</body>

</html>