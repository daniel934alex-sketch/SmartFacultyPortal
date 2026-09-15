<?php
require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole('FACULTY');

require_once "../config/database.php";

$user_id = $_SESSION['user_id'];

$sql = "
    SELECT 
        faculty_code,
        faculty_name,
        email,
        phone,
        designation
    FROM faculty
    WHERE user_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$faculty = $result->fetch_assoc();

if (!$faculty) {
    die("Faculty profile not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Faculty Profile</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .profile-container {
            max-width: 700px;
            margin: 40px auto;
        }

        .profile-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .profile-card h1 {
            text-align: center;
            margin-bottom: 30px;
        }

        .profile-row {
            display: flex;
            justify-content: space-between;
            padding: 15px;
            border-bottom: 1px solid #ddd;
        }

        .profile-label {
            font-weight: bold;
        }

        .back {
            display: inline-block;
            margin-top: 25px;
            text-decoration: none;
            color: #0d6efd;
        }
    </style>
</head>

<body>

<div class="profile-container">

    <div class="profile-card">

        <h1>👤 Faculty Profile</h1>

        <div class="profile-row">
            <span class="profile-label">Faculty Code</span>
            <span>
                <?= htmlspecialchars($faculty['faculty_code']) ?>
            </span>
        </div>

        <div class="profile-row">
            <span class="profile-label">Name</span>
            <span>
                <?= htmlspecialchars($faculty['faculty_name']) ?>
            </span>
        </div>

        <div class="profile-row">
            <span class="profile-label">Email</span>
            <span>
                <?= htmlspecialchars($faculty['email']) ?>
            </span>
        </div>

        <div class="profile-row">
            <span class="profile-label">Phone</span>
            <span>
                <?= htmlspecialchars($faculty['phone']) ?>
            </span>
        </div>

        <div class="profile-row">
            <span class="profile-label">Designation</span>
            <span>
                <?= htmlspecialchars($faculty['designation']) ?>
            </span>
        </div>

        <a class="back" href="dashboard.php">
            ← Back to Dashboard
        </a>

    </div>

</div>

</body>
</html>