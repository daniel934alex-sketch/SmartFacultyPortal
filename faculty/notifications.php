<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole('FACULTY');

require_once "../config/database.php";

$user_id = $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Get Faculty Notifications
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        notification_id,
        title,
        message,
        notification_type,
        is_read,
        created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

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

        body {
            font-family: Arial, sans-serif;
            padding: 30px;
            background: #f5f7fb;
        }

        h1 {
            margin-bottom: 5px;
        }

        .welcome {
            color: #555;
            margin-bottom: 25px;
        }

        .notification {
            background: white;
            border: 1px solid #ddd;
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }

        .notification.new {
            border-left: 5px solid #0d6efd;
        }

        .notification h3 {
            margin-top: 0;
            margin-bottom: 10px;
        }

        .notification p {
            margin: 10px 0;
            line-height: 1.5;
        }

        .notification-info {
            color: #666;
            font-size: 13px;
            margin-top: 10px;
        }

        .new-badge {
            display: inline-block;
            background: #0d6efd;
            color: white;
            padding: 4px 8px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 8px;
        }

        .handover-button {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 16px;
            background: #198754;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
        }

        .handover-button:hover {
            background: #146c43;
        }

        .empty {
            background: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            color: #666;
        }

        .back-button {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 15px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }

        .back-button:hover {
            background: #5c636a;
        }

    </style>

</head>

<body>

    <h1>My Notifications</h1>

    <div class="welcome">
        Welcome,
        <strong>
            <?= htmlspecialchars($_SESSION['username']) ?>
        </strong>
    </div>


    <?php if ($result->num_rows === 0): ?>

        <div class="empty">
            No notifications available.
        </div>

    <?php else: ?>

        <?php while ($notification = $result->fetch_assoc()): ?>

            <div class="notification <?= !$notification['is_read'] ? 'new' : '' ?>">

                <h3>

                    <?= htmlspecialchars($notification['title']) ?>

                    <?php if (!$notification['is_read']): ?>

                        <span class="new-badge">
                            NEW
                        </span>

                    <?php endif; ?>

                </h3>


                <p>
                    <?= htmlspecialchars($notification['message']) ?>
                </p>


                <div class="notification-info">

                    Type:
                    <?= htmlspecialchars(
                        $notification['notification_type'] ?? 'General'
                    ) ?>

                    <br>

                    Date:
                    <?= htmlspecialchars($notification['created_at']) ?>

                </div>


                <?php
                /*
                 * Handover requests are handled from requests.php.
                 * The notification only informs the faculty.
                 */

                if (
                    isset($notification['notification_type']) &&
                    strtoupper($notification['notification_type']) === 'HANDOVER'
                ):
                ?>

                    <a
                        href="requests.php"
                        class="handover-button"
                    >
                        View Handover Request
                    </a>

                <?php endif; ?>

            </div>

        <?php endwhile; ?>

    <?php endif; ?>


    <a
        href="dashboard.php"
        class="back-button"
    >
        ← Back to Dashboard
    </a>

</body>

</html>

<?php

$stmt->close();

?>