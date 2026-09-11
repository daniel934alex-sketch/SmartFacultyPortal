<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole('HOD');

require_once "../config/database.php";
require_once "../config/config.php";

$pageTitle = "Add Faculty";

$error = "";
$success = "";


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $faculty_code = trim($_POST['faculty_code']);
    $faculty_name = trim($_POST['faculty_name']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $designation = trim($_POST['designation']);

    $is_hod = isset($_POST['is_hod']) ? 1 : 0;


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if (
        $faculty_code === "" ||
        $faculty_name === "" ||
        $username === "" ||
        $password === ""
    ) {

        $error = "Please fill all required fields.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | Check duplicate username
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare(
            "SELECT user_id
             FROM users
             WHERE username = ?"
        );

        $stmt->bind_param(
            "s",
            $username
        );

        $stmt->execute();

        $userResult = $stmt->get_result();


        /*
        |--------------------------------------------------------------------------
        | Check duplicate faculty code
        |--------------------------------------------------------------------------
        */

        $stmt2 = $conn->prepare(
            "SELECT faculty_id
             FROM faculty
             WHERE faculty_code = ?"
        );

        $stmt2->bind_param(
            "s",
            $faculty_code
        );

        $stmt2->execute();

        $facultyResult = $stmt2->get_result();


        if ($userResult->num_rows > 0) {

            $error = "Username already exists.";

        } elseif ($facultyResult->num_rows > 0) {

            $error = "Faculty code already exists.";

        } else {


            /*
            |--------------------------------------------------------------------------
            | Start transaction
            |--------------------------------------------------------------------------
            */

            $conn->begin_transaction();


            try {


                /*
                |--------------------------------------------------------------------------
                | Password Hash
                |--------------------------------------------------------------------------
                */

                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );


                /*
                |--------------------------------------------------------------------------
                | User Role
                |--------------------------------------------------------------------------
                */

                $role = $is_hod ? "HOD" : "FACULTY";


                /*
                |--------------------------------------------------------------------------
                | Create User
                |--------------------------------------------------------------------------
                */

                $stmt = $conn->prepare(
                    "INSERT INTO users
                    (
                        username,
                        password,
                        role,
                        status
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        'ACTIVE'
                    )"
                );

                $stmt->bind_param(
                    "sss",
                    $username,
                    $passwordHash,
                    $role
                );

                $stmt->execute();


                $user_id = $conn->insert_id;


                /*
                |--------------------------------------------------------------------------
                | Create Faculty
                |--------------------------------------------------------------------------
                */

                $department_id = 1;


                $stmt = $conn->prepare(
                    "INSERT INTO faculty
                    (
                        user_id,
                        faculty_code,
                        faculty_name,
                        email,
                        phone,
                        department_id,
                        designation,
                        is_hod
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )"
                );


                $stmt->bind_param(
                    "issssisi",
                    $user_id,
                    $faculty_code,
                    $faculty_name,
                    $email,
                    $phone,
                    $department_id,
                    $designation,
                    $is_hod
                );


                $stmt->execute();


                /*
                |--------------------------------------------------------------------------
                | Commit
                |--------------------------------------------------------------------------
                */

                $conn->commit();


                $success =
                    "Faculty member added successfully.";


                /*
                |--------------------------------------------------------------------------
                | Clear form
                |--------------------------------------------------------------------------
                */

                $_POST = [];


            } catch (Exception $e) {

                $conn->rollback();

                $error =
                    "Failed to add faculty: "
                    . $e->getMessage();
            }
        }
    }
}

?>

<?php require_once "../includes/header.php"; ?>

<?php require_once "../includes/navbar.php"; ?>

<?php require_once "../includes/sidebar.php"; ?>

<main>

    <h1>Add Faculty</h1>

    <p>
        Create a new faculty member and login account.
    </p>

    <br>


    <?php if ($error): ?>

        <div class="form-error">

            <?php
            echo htmlspecialchars($error);
            ?>

        </div>

    <?php endif; ?>


    <?php if ($success): ?>

        <div class="form-success">

            <?php
            echo htmlspecialchars($success);
            ?>

        </div>

    <?php endif; ?>


    <div class="table-container">

        <form method="POST">


            <!-- Faculty Code -->

            <label>
                Faculty Code *
            </label>

            <input
                type="text"
                name="faculty_code"
                placeholder="CSE016"
                value="<?php
                    echo htmlspecialchars(
                        $_POST['faculty_code'] ?? ''
                    );
                ?>"
                required
            >

            <br><br>


            <!-- Faculty Name -->

            <label>
                Faculty Name *
            </label>

            <input
                type="text"
                name="faculty_name"
                placeholder="Prof. Example Kumar"
                value="<?php
                    echo htmlspecialchars(
                        $_POST['faculty_name'] ?? ''
                    );
                ?>"
                required
            >

            <br><br>


            <!-- Username -->

            <label>
                Username *
            </label>

            <input
                type="text"
                name="username"
                placeholder="faculty15"
                value="<?php
                    echo htmlspecialchars(
                        $_POST['username'] ?? ''
                    );
                ?>"
                required
            >

            <br><br>


            <!-- Password -->

            <label>
                Password *
            </label>

            <input
                type="password"
                name="password"
                required
            >

            <br><br>


            <!-- Email -->

            <label>
                Email
            </label>

            <input
                type="email"
                name="email"
                placeholder="faculty@example.com"
                value="<?php
                    echo htmlspecialchars(
                        $_POST['email'] ?? ''
                    );
                ?>"
            >

            <br><br>


            <!-- Phone -->

            <label>
                Phone
            </label>

            <input
                type="text"
                name="phone"
                placeholder="9876543210"
                value="<?php
                    echo htmlspecialchars(
                        $_POST['phone'] ?? ''
                    );
                ?>"
            >

            <br><br>


            <!-- Designation -->

            <label>
                Designation
            </label>

            <input
                type="text"
                name="designation"
                placeholder="Assistant Professor"
                value="<?php
                    echo htmlspecialchars(
                        $_POST['designation'] ?? ''
                    );
                ?>"
            >

            <br><br>


            <!-- HOD -->

            <label>

                <input
                    type="checkbox"
                    name="is_hod"
                    value="1"
                    <?php
                    echo isset($_POST['is_hod'])
                        ? 'checked'
                        : '';
                    ?>
                >

                HOD

            </label>

            <br><br>


            <!-- Submit -->

            <button
                type="submit"
                class="btn btn-success"
            >
                Add Faculty
            </button>


            <a
                href="faculty.php"
                class="btn"
            >
                Cancel
            </a>

        </form>

    </div>

</main>

<?php require_once "../includes/footer.php"; ?>