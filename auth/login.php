<?php

session_start();

require_once "../config/database.php";
require_once "../config/config.php";


/* -------------------------------------------------
   If already logged in, redirect to dashboard
------------------------------------------------- */

if (isset($_SESSION['user_id'])) {

    if ($_SESSION['role'] === 'HOD') {

        header("Location: ../admin/dashboard.php");
        exit;

    } elseif ($_SESSION['role'] === 'FACULTY') {

        header("Location: ../faculty/dashboard.php");
        exit;

    } elseif ($_SESSION['role'] === 'STUDENT') {

        header("Location: ../student/dashboard.php");
        exit;
    }
}


/* -------------------------------------------------
   Variables
------------------------------------------------- */

$error = "";

$username = "";


/* -------------------------------------------------
   Login Form Submitted
------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? "");
    $password = $_POST['password'] ?? "";


    /* -------------------------------------------------
       Basic Validation
    ------------------------------------------------- */

    if ($username === "" || $password === "") {

        $error = "Please enter username and password.";

    } else {


        /* -------------------------------------------------
           Find User
        ------------------------------------------------- */

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

        if (!$stmt) {

            $error = "Database query error.";

        } else {

            $stmt->bind_param(
                "s",
                $username
            );

            $stmt->execute();

            $result = $stmt->get_result();


            /* -------------------------------------------------
               Check User
            ------------------------------------------------- */

            if ($result->num_rows === 1) {

                $user = $result->fetch_assoc();


                /* -------------------------------------------------
                   Check Account Status
                ------------------------------------------------- */

                $user_status = strtoupper(
                    trim($user['status'] ?? '')
                );


                if (
                    $user_status !== 'ACTIVE'
                    &&
                    $user_status !== '1'
                ) {

                    $error =
                        "Your account is inactive.";

                } elseif (
                    password_verify(
                        $password,
                        $user['password']
                    )
                ) {


                    /* -------------------------------------------------
                       Regenerate Session ID
                    ------------------------------------------------- */

                    session_regenerate_id(true);


                    /* -------------------------------------------------
                       Store Login Information
                    ------------------------------------------------- */

                    $_SESSION['user_id'] =
                        (int)$user['user_id'];

                    $_SESSION['username'] =
                        $user['username'];

                    $_SESSION['role'] =
                        strtoupper($user['role']);


                    /* -------------------------------------------------
                       Get Faculty ID
                    ------------------------------------------------- */

                    if (
                        $_SESSION['role'] === 'FACULTY'
                    ) {

                        $faculty_stmt = $conn->prepare("
                            SELECT
                                faculty_id
                            FROM faculty
                            WHERE user_id = ?
                            LIMIT 1
                        ");


                        if ($faculty_stmt) {

                            $faculty_stmt->bind_param(
                                "i",
                                $user['user_id']
                            );

                            $faculty_stmt->execute();

                            $faculty_result =
                                $faculty_stmt->get_result();


                            if (
                                $faculty_result->num_rows === 1
                            ) {

                                $faculty_row =
                                    $faculty_result
                                    ->fetch_assoc();


                                $_SESSION['faculty_id'] =
                                    (int)$faculty_row['faculty_id'];

                            } else {

                                /*
                                 * Faculty account exists in users
                                 * but matching faculty record not found.
                                 */

                                unset(
                                    $_SESSION['faculty_id']
                                );

                                $error =
                                    "Faculty record not found.";

                            }

                            $faculty_stmt->close();

                        } else {

                            $error =
                                "Unable to load faculty information.";
                        }
                    }


                    /* -------------------------------------------------
                       Redirect if no error
                    ------------------------------------------------- */

                    if ($error === "") {

                        if (
                            $_SESSION['role'] === 'HOD'
                        ) {

                            header(
                                "Location: ../admin/dashboard.php"
                            );

                            exit;


                        } elseif (
                            $_SESSION['role'] === 'FACULTY'
                        ) {

                            header(
                                "Location: ../faculty/dashboard.php"
                            );

                            exit;


                        } elseif (
                            $_SESSION['role'] === 'STUDENT'
                        ) {

                            header(
                                "Location: ../student/dashboard.php"
                            );

                            exit;


                        } else {

                            $error =
                                "Invalid user role.";

                            session_unset();
                            session_destroy();
                        }
                    }


                } else {

                    $error =
                        "Invalid username or password.";
                }


            } else {

                $error =
                    "Invalid username or password.";
            }


            $stmt->close();
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Smart Faculty Portal - Login
    </title>

    <style>

        body {

            font-family: Arial, sans-serif;

            background: #f4f6f8;

            margin: 0;

            padding: 0;

        }


        .login-container {

            width: 380px;

            margin: 100px auto;

            background: white;

            padding: 30px;

            border-radius: 8px;

            box-shadow:
                0 0 10px
                rgba(0,0,0,0.1);

        }


        h1 {

            text-align: center;

            margin-bottom: 25px;

        }


        label {

            display: block;

            margin-top: 15px;

            margin-bottom: 5px;

            font-weight: bold;

        }


        input[type="text"],
        input[type="password"] {

            width: 100%;

            padding: 10px;

            box-sizing: border-box;

            border: 1px solid #ccc;

            border-radius: 4px;

        }


        button {

            width: 100%;

            margin-top: 20px;

            padding: 11px;

            border: none;

            border-radius: 4px;

            background: #2563eb;

            color: white;

            font-size: 16px;

            cursor: pointer;

        }


        button:hover {

            background: #1d4ed8;

        }


        .error {

            color: red;

            background: #ffe5e5;

            padding: 10px;

            border-radius: 4px;

            margin-bottom: 15px;

        }


        .info {

            margin-top: 20px;

            font-size: 13px;

            color: #666;

            text-align: center;

        }

    </style>

</head>


<body>


<div class="login-container">


    <h1>
        Smart Faculty Portal
    </h1>


    <?php if ($error !== ""): ?>

        <div class="error">

            <?php
            echo htmlspecialchars($error);
            ?>

        </div>

    <?php endif; ?>


    <form
        method="POST"
        action=""
    >


        <label for="username">

            Username

        </label>


        <input
            type="text"
            id="username"
            name="username"
            value="<?php
            echo htmlspecialchars($username);
            ?>"
            required
        >


        <label for="password">

            Password

        </label>


        <input
            type="password"
            id="password"
            name="password"
            required
        >


        <button type="submit">

            Login

        </button>


    </form>


    <div class="info">

        Smart Faculty Assistant & Period-Wise
        Attendance Management Portal

    </div>


</div>


</body>

</html>