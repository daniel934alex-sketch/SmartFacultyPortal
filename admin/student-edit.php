<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Edit Student";

$error = "";

$student_id = 0;

$register_number = "";
$student_name = "";
$email = "";
$phone = "";
$section_id = "";
$username = "";


/* ==========================================================
   CHECK STUDENT ID
   ========================================================== */

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {

    header("Location: students.php?error=invalid_id");
    exit;
}

$student_id = (int)$_GET["id"];


/* ==========================================================
   GET STUDENT
   ========================================================== */

$sql = "
    SELECT
        s.student_id,
        s.user_id,
        s.register_number,
        s.student_name,
        s.email,
        s.phone,
        s.section_id,
        u.username,
        u.status
    FROM students s

    INNER JOIN users u
        ON s.user_id = u.user_id

    WHERE s.student_id = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database query failed.");
}

$stmt->bind_param("i", $student_id);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    header("Location: students.php?error=student_not_found");
    exit;
}

$student = $result->fetch_assoc();

$stmt->close();


/* ==========================================================
   STORE USER ID
   ========================================================== */

$user_id = (int)$student["user_id"];


/* ==========================================================
   INITIAL VALUES
   ========================================================== */

$register_number = $student["register_number"];
$student_name = $student["student_name"];
$email = $student["email"] ?? "";
$phone = $student["phone"] ?? "";
$section_id = (int)$student["section_id"];
$username = $student["username"];


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

    $register_number = trim(
        $_POST["register_number"] ?? ""
    );

    $student_name = trim(
        $_POST["student_name"] ?? ""
    );

    $email = trim(
        $_POST["email"] ?? ""
    );

    $phone = trim(
        $_POST["phone"] ?? ""
    );

    $section_id = (int)(
        $_POST["section_id"] ?? 0
    );

    $username = trim(
        $_POST["username"] ?? ""
    );

    $password = $_POST["password"] ?? "";


    /* ======================================================
       REQUIRED FIELD VALIDATION
       ====================================================== */

    if (
        $register_number === "" ||
        $student_name === "" ||
        $section_id <= 0 ||
        $username === ""
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

            $stmt->bind_param(
                "i",
                $section_id
            );

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
       ====================================================== */

    if ($error === "") {

        /*
         * Get current student's section.
         */

        $oldSectionSql = "
            SELECT section_id
            FROM students
            WHERE student_id = ?
        ";

        $stmt = $conn->prepare(
            $oldSectionSql
        );

        $stmt->bind_param(
            "i",
            $student_id
        );

        $stmt->execute();

        $oldSectionResult = $stmt->get_result();

        $oldSectionRow =
            $oldSectionResult->fetch_assoc();

        $stmt->close();

        $old_section_id =
            (int)$oldSectionRow["section_id"];


        /*
         * Only check capacity if the student
         * is being moved to another section.
         */

        if ($section_id !== $old_section_id) {

            $capacitySql = "
                SELECT COUNT(*) AS total_students
                FROM students
                WHERE section_id = ?
            ";

            $stmt = $conn->prepare(
                $capacitySql
            );

            if (!$stmt) {

                $error =
                    "Unable to check section capacity.";

            } else {

                $stmt->bind_param(
                    "i",
                    $section_id
                );

                $stmt->execute();

                $capacityResult =
                    $stmt->get_result();

                $capacityRow =
                    $capacityResult->fetch_assoc();

                $total_students =
                    (int)$capacityRow["total_students"];

                $stmt->close();


                if ($total_students >= 64) {

                    $error =
                        "The selected section already has 64 students. This student cannot be moved to that section.";

                }

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
            AND student_id != ?
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {

            $error =
                "Unable to check register number.";

        } else {

            $stmt->bind_param(
                "si",
                $register_number,
                $student_id
            );

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows > 0) {

                $error =
                    "This register number already belongs to another student.";

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
            AND user_id != ?
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {

            $error =
                "Unable to check username.";

        } else {

            $stmt->bind_param(
                "si",
                $username,
                $user_id
            );

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows > 0) {

                $error =
                    "This username already belongs to another account.";

            }

            $stmt->close();

        }

    }


    /* ======================================================
       PASSWORD VALIDATION
       ====================================================== */

    if (
        $error === "" &&
        $password !== ""
    ) {

        if (strlen($password) < 6) {

            $error =
                "New password must contain at least 6 characters.";

        }

    }


    /* ======================================================
       UPDATE STUDENT
       ====================================================== */

    if ($error === "") {

        $conn->begin_transaction();

        try {

            /* ----------------------------------------------
               UPDATE STUDENT
               ---------------------------------------------- */

            $studentSql = "
                UPDATE students
                SET
                    register_number = ?,
                    student_name = ?,
                    email = ?,
                    phone = ?,
                    section_id = ?
                WHERE student_id = ?
            ";

            $stmt = $conn->prepare(
                $studentSql
            );

            if (!$stmt) {

                throw new Exception(
                    "Unable to prepare student update."
                );

            }

            $stmt->bind_param(
                "ssssii",
                $register_number,
                $student_name,
                $email,
                $phone,
                $section_id,
                $student_id
            );

            if (!$stmt->execute()) {

                throw new Exception(
                    "Unable to update student."
                );

            }

            $stmt->close();


            /* ----------------------------------------------
               UPDATE USERNAME
               ---------------------------------------------- */

            $userSql = "
                UPDATE users
                SET username = ?
                WHERE user_id = ?
            ";

            $stmt = $conn->prepare(
                $userSql
            );

            if (!$stmt) {

                throw new Exception(
                    "Unable to prepare account update."
                );

            }

            $stmt->bind_param(
                "si",
                $username,
                $user_id
            );

            if (!$stmt->execute()) {

                throw new Exception(
                    "Unable to update username."
                );

            }

            $stmt->close();


            /* ----------------------------------------------
               UPDATE PASSWORD ONLY IF ENTERED
               ---------------------------------------------- */

            if ($password !== "") {

                $password_hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $passwordSql = "
                    UPDATE users
                    SET password = ?
                    WHERE user_id = ?
                ";

                $stmt = $conn->prepare(
                    $passwordSql
                );

                if (!$stmt) {

                    throw new Exception(
                        "Unable to prepare password update."
                    );

                }

                $stmt->bind_param(
                    "si",
                    $password_hash,
                    $user_id
                );

                if (!$stmt->execute()) {

                    throw new Exception(
                        "Unable to update password."
                    );

                }

                $stmt->close();

            }


            /* ----------------------------------------------
               COMMIT
               ---------------------------------------------- */

            $conn->commit();


            header(
                "Location: students.php?success=updated"
            );

            exit;


        } catch (Exception $e) {

            $conn->rollback();

            $error =
                "Unable to update student. Please try again.";

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
                Edit Student
            </h2>

            <p
                style="
                    margin:0;

                    color:#64748b;

                    font-size:15px;
                "
            >
                Update student information and section.
            </p>

        </div>


        <!-- ==================================================
             ERROR MESSAGE
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

                <div style="margin-bottom:20px;">

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

                <div style="margin-bottom:20px;">

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

                <div style="margin-bottom:20px;">

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

                <div style="margin-bottom:20px;">

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

                <div style="margin-bottom:20px;">

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
                        Each section can contain a maximum of
                        64 students.
                    </small>

                </div>


                <!-- USERNAME -->

                <div style="margin-bottom:20px;">

                    <label
                        for="username"
                        style="
                            display:block;
                            margin-bottom:7px;
                            color:#1e293b;
                            font-weight:600;
                        "
                    >
                        Username *
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

                <div style="margin-bottom:25px;">

                    <label
                        for="password"
                        style="
                            display:block;
                            margin-bottom:7px;
                            color:#1e293b;
                            font-weight:600;
                        "
                    >
                        New Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="6"
                        placeholder="Leave blank to keep current password"
                        style="
                            width:100%;
                            padding:11px;
                            border:1px solid #cbd5e1;
                            border-radius:6px;
                            font-size:15px;
                            box-sizing:border-box;
                        "
                    >

                    <small
                        style="
                            display:block;
                            margin-top:6px;
                            color:#64748b;
                            font-size:13px;
                        "
                    >
                        Leave blank if you do not want to change
                        the password.
                    </small>

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
                        Update Student
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