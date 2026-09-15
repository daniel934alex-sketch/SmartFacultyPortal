<?php
require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole('FACULTY');

require_once "../config/database.php";

$user_id = $_SESSION['user_id'];

$sql = "
    SELECT notification_id, title, message, notification_type, is_read, created_at
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
</head>

<body>

<h1>My Notifications</h1>

<p>Welcome, <?= htmlspecialchars($_SESSION['username']) ?></p>

<?php if ($result->num_rows === 0): ?>

    <p>No notifications available.</p>

<?php else: ?>

    <?php while ($notification = $result->fetch_assoc()): ?>

        <div style="
            border: 1px solid #ccc;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            background: <?= $notification['is_read'] ? '#f5f5f5' : '#e8f1ff' ?>;
        ">

            <h3>
                <?= htmlspecialchars($notification['title']) ?>
            </h3>

            <p>
                <?= htmlspecialchars($notification['message']) ?>
            </p>

            <small>
                Type:
                <?= htmlspecialchars($notification['notification_type'] ?? 'General') ?>
                |
                <?= htmlspecialchars($notification['created_at']) ?>
            </small>

            <?php if (!$notification['is_read']): ?>
                <strong style="color: blue;"> NEW</strong>
            <?php endif; ?>

        </div>

    <?php endwhile; ?>

<?php endif; ?>

<br>

<a href="dashboard.php">← Back to Dashboard</a>

</body>
</html>