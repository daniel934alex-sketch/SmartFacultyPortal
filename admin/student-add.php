<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Add Student";

$error = "";

$register_number = "";
$student_name = "";
$email = "";
$phone = "";
$section_id = "";
$username = "";


/* ==========================================================
   GET SECTIONS
   ========================================================== */

$sections = [];

$sectionSql = "
    SELECT
        section_id,
        section_name
    FROM sections
    ORDER BY section_id ASC
";

$sectionResult = $conn->query($sectionSql);

if ($sectionResult) {

    while ($section = $sectionResult->fetch_assoc()) {

        $sections[] = $section;

    }

}


/* ==========================================================
   FORM SUBMISSION
   ========================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $register_number = trim($_POST["register_number"] ?? "");
    $student_name = trim($_POST["student_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $section_id = (int)($_POST["section_id"] ?? 0);
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";


    /* ======================================================
       BASIC VALIDATION
       ====================================================== */

    if (
        $register_number === "" ||
        $student_name === "" ||
        $section_id <= 0 ||
        $username === "" ||
        $password === ""
    ) {

        $error = "Please fill in all required fields.";

    }


    /* ======================================================
       CHECK SECTION
       ====================================================== */

    if ($error === "") {

        $sql = "
            SELECT
                section_id,
                section_name
            FROM sections
            WHERE section_id = ?
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {

            $error = "Unable to verify section.";

        } else {

            $stmt->bind_param("i", $section_id);

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 0) {

                $error = "Invalid section selected.";

            }

            $stmt->close();

        }

    }


    /* ======================================================
       CHECK SECTION CAPACITY
       MAXIMUM = 64
       ====================================================== */

    if ($error === "") {

        $sql = "
            SELECT COUNT(*) AS total_students
            FROM students
            WHERE section_id = ?
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {

            $error = "Unable to check section capacity.";

        } else {

            $stmt->bind_param("i", $section_id);

            $stmt->execute();

            $result = $stmt->get_result();

            $row = $result->fetch_assoc();

            $total_students = (int)$row["total_students"];

            $stmt->close();


            if ($total_students >= 64) {

                $error = "This section already has 64 students. You cannot add another student to this section.";

            }

        }

    }


    /* ======================================================
       CHECK DUPLICATE REGISTER NUMBER
       ====================================================== */

    if ($error === "") {

        $sql = "
            SELECT student_id
            FROM students
            WHERE register_number = ?
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {

            $error = "Unable to check register number.";

        } else {

            $stmt->bind_param("s", $register_number);

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows > 0) {

                $error = "This register number already exists.";

            }

            $stmt->close();

        }

    }


    /* ======================================================
       CHECK DUPLICATE USERNAME
       ====================================================== */

    if ($error === "") {

        $sql = "
            SELECT user_id
            FROM users
            WHERE username = ?
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {

            $error = "Unable to check username.";

        } else {

            $stmt->bind_param("s", $username);

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows > 0) {

                $error = "This username already exists.";

            }

            $stmt->close();

        }

    }


    /* ======================================================
       PASSWORD VALIDATION
       ====================================================== */

    if ($error === "") {

        if (strlen($password) < 6) {

            $error = "Password must contain at least 6 characters.";

        }

    }


    /* ======================================================
       CREATE STUDENT
       ====================================================== */

    if ($error === "") {

        $conn->begin_transaction();

        try {

            /* ----------------------------------------------
               HASH PASSWORD
               ---------------------------------------------- */

            $password_hash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            /* ----------------------------------------------
               CREATE USER
               ---------------------------------------------- */

            $userSql = "
                INSERT INTO users
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
                    'STUDENT',
                    'ACTIVE'
                )
            ";

            $stmt = $conn->prepare($userSql);

            if (!$stmt) {

                throw new Exception(
                    "Unable to prepare user creation."
                );

            }

            $stmt->bind_param(
                "ss",
                $username,
                $password_hash
            );

            if (!$stmt->execute()) {

                throw new Exception(
                    "Unable to create student login account."
                );

            }

            $user_id = $stmt->insert_id;

            $stmt->close();


            /* ----------------------------------------------
               CREATE STUDENT
               ---------------------------------------------- */

            $studentSql = "
                INSERT INTO students
                (
                    user_id,
                    register_number,
                    student_name,
                    email,
                    phone,
                    section_id
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ";

            $stmt = $conn->prepare($studentSql);

            if (!$stmt) {

                throw new Exception(
                    "Unable to prepare student creation."
                );

            }

            $stmt->bind_param(
                "issssi",
                $user_id,
                $register_number,
                $student_name,
                $email,
                $phone,
                $section_id
            );

            if (!$stmt->execute()) {

                throw new Exception(
                    "Unable to create student record."
                );

            }

            $stmt->close();


            /* ----------------------------------------------
               COMMIT
               ---------------------------------------------- */

            $conn->commit();


            header(
                "Location: students.php?success=added"
            );

            exit;


        } catch (Exception $e) {

            $conn->rollback();

            $error = "Unable to add student. Please try again.";

        }

    }

}


/* ==========================================================
   HEADER
   ========================================================== */

require_once "../includes/header.php";
require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";

?>


<!-- ==========================================================
     MAIN CONTENT
     ========================================================== -->

<div
    class="main-content"
    style="
        box-sizing:border-box;
        width:auto;
        min-height:100vh;
        overflow-x:hidden;
    "
>

    <div
        style="
            width:100%;
            max-width:900px;
            margin:0 auto;
            box-sizing:border-box;
        "
    >


        <!-- ==================================================
             PAGE HEADER
             ================================================== -->

        <div
            style="
                background:#ffffff;

                border-radius:10px;

                padding:22px 25px;

                margin-bottom:20px;

                box-sizing:border-box;
            "
        >

            <h2
                style="
                    margin:0 0 6px 0;

                    color:#1e293b;

                    font-size:26px;
                "
            >
                Add Student
            </h2>

            <p
                style="
                    margin:0;

                    color:#64748b;

                    font-size:15px;
                "
            >
                Add a student to a CSE section.
            </p>

        </div>


        <!-- ==================================================
             ERROR
             ================================================== -->

        <?php if ($error !== ""): ?>

            <div
                style="
                    background:#fee2e2;

                    color:#991b1b;

                    padding:14px 16px;

                    border-radius:7px;

                    margin-bottom:20px;

                    font-size:14px;
                "
            >

                <?php
                echo htmlspecialchars($error);
                ?>

            </div>

        <?php endif; ?>


        <!-- ==================================================
             FORM
             ================================================== -->

        <div
            style="
                background:#ffffff;

                border-radius:10px;

                padding:30px;

                box-sizing:border-box;
            "
        >

            <form
                method="POST"
                action=""
            >


                <!-- REGISTER NUMBER -->

                <div
                    style="
                        margin-bottom:20px;
                    "
                >

                    <label
                        for="register_number"
                        style="
                            display:block;

                            margin-bottom:7px;

                            color:#1e293b;

                            font-weight:600;
                        "
                    >
                        Register Number *
                    </label>

                    <input
                        type="text"
                        id="register_number"
                        name="register_number"
                        value="<?php
                            echo htmlspecialchars(
                                $register_number
                            );
                        ?>"
                        required
                        maxlength="30"
                        placeholder="Example: 714025104042"
                        style="
                            width:100%;

                            padding:11px;

                            border:1px solid #cbd5e1;

                            border-radius:6px;

                            font-size:15px;

                            box-sizing:border-box;
                        "
                    >

                </div>


                <!-- STUDENT NAME -->

                <div
                    style="
                        margin-bottom:20px;
                    "
                >

                    <label
                        for="student_name"
                        style="
                            display:block;

                            margin-bottom:7px;

                            color:#1e293b;

                            font-weight:600;
                        "
                    >
                        Student Name *
                    </label>

                    <input
                        type="text"
                        id="student_name"
                        name="student_name"
                        value="<?php
                            echo htmlspecialchars(
                                $student_name
                            );
                        ?>"
                        required
                        maxlength="100"
                        placeholder="Enter student name"
                        style="
                            width:100%;

                            padding:11px;

                            border:1px solid #cbd5e1;

                            border-radius:6px;

                            font-size:15px;

                            box-sizing:border-box;
                        "
                    >

                </div>


                <!-- EMAIL -->

                <div
                    style="
                        margin-bottom:20px;
                    "
                >

                    <label
                        for="email"
                        style="
                            display:block;

                            margin-bottom:7px;

                            color:#1e293b;

                            font-weight:600;
                        "
                    >
                        Email
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?php
                            echo htmlspecialchars(
                                $email
                            );
                        ?>"
                        maxlength="100"
                        placeholder="student@example.com"
                        style="
                            width:100%;

                            padding:11px;

                            border:1px solid #cbd5e1;

                            border-radius:6px;

                            font-size:15px;

                            box-sizing:border-box;
                        "
                    >

                </div>


                <!-- PHONE -->

                <div
                    style="
                        margin-bottom:20px;
                    "
                >

                    <label
                        for="phone"
                        style="
                            display:block;

                            margin-bottom:7px;

                            color:#1e293b;

                            font-weight:600;
                        "
                    >
                        Phone
                    </label>

                    <input
                        type="text"
                        id="phone"
                        name="phone"
                        value="<?php
                            echo htmlspecialchars(
                                $phone
                            );
                        ?>"
                        maxlength="20"
                        placeholder="Enter phone number"
                        style="
                            width:100%;

                            padding:11px;

                            border:1px solid #cbd5e1;

                            border-radius:6px;

                            font-size:15px;

                            box-sizing:border-box;
                        "
                    >

                </div>


                <!-- SECTION -->

                <div
                    style="
                        margin-bottom:20px;
                    "
                >

                    <label
                        for="section_id"
                        style="
                            display:block;

                            margin-bottom:7px;

                            color:#1e293b;

                            font-weight:600;
                        "
                    >
                        Section *
                    </label>

                    <select
                        id="section_id"
                        name="section_id"
                        required
                        style="
                            width:100%;

                            padding:11px;

                            border:1px solid #cbd5e1;

                            border-radius:6px;

                            font-size:15px;

                            background:#ffffff;

                            box-sizing:border-box;
                        "
                    >

                        <option value="">
                            Select Section
                        </option>


                        <?php foreach (
                            $sections as $section
                        ): ?>

                            <option
                                value="<?php
                                    echo (int)
                                        $section["section_id"];
                                ?>"
                                <?php
                                if (
                                    (int)$section_id ===
                                    (int)$section["section_id"]
                                ) {
                                    echo "selected";
                                }
                                ?>
                            >
                                <?php
                                echo htmlspecialchars(
                                    $section["section_name"]
                                );
                                ?>
                            </option>

                        <?php endforeach; ?>

                    </select>


                    <small
                        style="
                            display:block;

                            margin-top:6px;

                            color:#64748b;

                            font-size:13px;
                        "
                    >
                        Maximum 64 students are allowed in each
                        section.
                    </small>

                </div>


                <!-- USERNAME -->

                <div
                    style="
                        margin-bottom:20px;
                    "
                >

                    <label
                        for="username"
                        style="
                            display:block;

                            margin-bottom:7px;

                            color:#1e293b;

                            font-weight:600;
                        "
                    >
                        Student Username *
                    </label>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        value="<?php
                            echo htmlspecialchars(
                                $username
                            );
                        ?>"
                        required
                        maxlength="50"
                        placeholder="Example: 714025104042"
                        style="
                            width:100%;

                            padding:11px;

                            border:1px solid #cbd5e1;

                            border-radius:6px;

                            font-size:15px;

                            box-sizing:border-box;
                        "
                    >

                </div>


                <!-- PASSWORD -->

                <div
                    style="
                        margin-bottom:25px;
                    "
                >

                    <label
                        for="password"
                        style="
                            display:block;

                            margin-bottom:7px;

                            color:#1e293b;

                            font-weight:600;
                        "
                    >
                        Password *
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        minlength="6"
                        placeholder="Minimum 6 characters"
                        style="
                            width:100%;

                            padding:11px;

                            border:1px solid #cbd5e1;

                            border-radius:6px;

                            font-size:15px;

                            box-sizing:border-box;
                        "
                    >

                </div>


                <!-- BUTTONS -->

                <div
                    style="
                        display:flex;

                        gap:10px;

                        align-items:center;
                    "
                >

                    <button
                        type="submit"
                        style="
                            background:#2563eb;

                            color:#ffffff;

                            border:none;

                            padding:11px 20px;

                            border-radius:6px;

                            font-size:15px;

                            font-weight:600;

                            cursor:pointer;
                        "
                    >
                        Add Student
                    </button>


                    <a
                        href="students.php"
                        style="
                            background:#64748b;

                            color:#ffffff;

                            padding:11px 20px;

                            border-radius:6px;

                            text-decoration:none;

                            font-size:15px;

                            font-weight:600;
                        "
                    >
                        Cancel
                    </a>

                </div>

            </form>

        </div>

    </div>

</div>


<?php

require_once "../includes/footer.php";

?>