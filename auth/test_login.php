<?php

require_once "../config/database.php";

$username = "faculty01";
$password = "Faculty@1234";

$stmt = $conn->prepare("
    SELECT user_id, username, password, role, status
    FROM users
    WHERE username = ?
    LIMIT 1
");

$stmt->bind_param("s", $username);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 1) {

    $user = $result->fetch_assoc();

    echo "User found<br>";
    echo "Username: " . htmlspecialchars($user['username']) . "<br>";
    echo "Role: " . htmlspecialchars($user['role']) . "<br>";
    echo "Status: " . htmlspecialchars($user['status']) . "<br><br>";

    if (password_verify($password, $user['password'])) {

        echo "<b style='color:green;'>PASSWORD MATCHES ✅</b>";

    } else {

        echo "<b style='color:red;'>PASSWORD DOES NOT MATCH ❌</b>";
    }

} else {

    echo "<b style='color:red;'>USER NOT FOUND ❌</b>";
}

$stmt->close();
$conn->close();

?>