<?php

session_start();

require_once "../config/database.php";
require_once "../config/config.php";


// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {

    if ($_SESSION['role'] === 'HOD') {
        header("Location: ../admin/dashboard.php");
        exit;
    }

    if ($_SESSION['role'] === 'FACULTY') {
        header("Location: ../faculty/dashboard.php");
        exit;
    }

    if ($_SESSION['role'] === 'STUDENT') {
        header("Location: ../student/dashboard.php");
        exit;
    }
}


$error = "";


// ========================================
// LOGIN FORM SUBMISSION
// ========================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';


    // Basic validation
    if ($username === "" || $password === "") {

        $error = "Please enter username and password.";

    } else {

        // Find user
        $stmt = $conn->prepare("
            SELECT
                user_id,
                username,
                password,
                role,
                status
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $username);

        $stmt->execute();

        $result = $stmt->get_result();


        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();


            // Check account status
            if ($user['status'] !== 'ACTIVE') {

                $error = "Your account is inactive.";

            }

            // Verify password
            elseif (password_verify($password, $user['password'])) {

                // Regenerate session ID
                session_regenerate_id(true);


                // Store login information
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];


                // Redirect based on role
                if ($user['role'] === 'HOD') {

                    header("Location: ../admin/dashboard.php");
                    exit;

                } elseif ($user['role'] === 'FACULTY') {

                    header("Location: ../faculty/dashboard.php");
                    exit;

                } elseif ($user['role'] === 'STUDENT') {

                    header("Location: ../student/dashboard.php");
                    exit;
                }

            } else {

                $error = "Invalid username or password.";
            }

        } else {

            $error = "Invalid username or password.";
        }

        $stmt->close();
    }
}

$conn->close();

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Login - Smart Faculty Portal</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {

            margin: 0;

            font-family: Arial, sans-serif;

            background: #f4f6f9;

            display: flex;

            justify-content: center;

            align-items: center;

            min-height: 100vh;
        }

        .login-container {

            width: 380px;

            background: white;

            padding: 35px;

            border-radius: 12px;

            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        h1 {

            text-align: center;

            margin-bottom: 8px;
        }

        .subtitle {

            text-align: center;

            color: #666;

            margin-bottom: 25px;
        }

        label {

            display: block;

            margin-bottom: 6px;

            font-weight: bold;
        }

        input {

            width: 100%;

            padding: 12px;

            margin-bottom: 18px;

            border: 1px solid #ccc;

            border-radius: 6px;
        }

        button {

            width: 100%;

            padding: 12px;

            border: none;

            border-radius: 6px;

            background: #2563eb;

            color: white;

            font-size: 16px;

            cursor: pointer;
        }

        button:hover {

            background: #1d4ed8;
        }

        .error {

            background: #fee2e2;

            color: #b91c1c;

            padding: 10px;

            border-radius: 6px;

            margin-bottom: 15px;

            text-align: center;
        }

    </style>

</head>

<body>

<div class="login-container">

    <h1>Smart Faculty Portal</h1>

    <div class="subtitle">
        Login
    </div>


    <?php if ($error !== ""): ?>

        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <form method="POST">

        <label for="username">
            Username
        </label>

        <input
            type="text"
            id="username"
            name="username"
            placeholder="Enter username"
            required
        >


        <label for="password">
            Password
        </label>

        <input
            type="password"
            id="password"
            name="password"
            placeholder="Enter password"
            required
        >


        <button type="submit">
            Login
        </button>

    </form>

</div>

</body>

</html>