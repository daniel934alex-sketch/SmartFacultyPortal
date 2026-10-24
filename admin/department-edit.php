
<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Edit Department";

$error = "";

/* ======================================================
   VALIDATE DEPARTMENT ID
   ====================================================== */

if (
    !isset($_GET["id"]) ||
    !is_numeric($_GET["id"])
) {
    header("Location: departments.php?error=invalid_request");
    exit;
}

$department_id = (int)$_GET["id"];


/* ======================================================
   GET DEPARTMENT
   ====================================================== */

$sql = "
    SELECT
        department_id,
        department_code,
        department_name
    FROM departments
    WHERE department_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $department_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    header("Location: departments.php?error=not_found");
    exit;
}

$department = $result->fetch_assoc();

$stmt->close();


/* ======================================================
   UPDATE DEPARTMENT
   ====================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $department_code =
        strtoupper(trim($_POST["department_code"] ?? ""));

    $department_name =
        trim($_POST["department_name"] ?? "");

    if (
        $department_code === "" ||
        $department_name === ""
    ) {

        $error = "All fields are required.";

    } else {

        /* CHECK DUPLICATE DEPARTMENT CODE */

        $sql = "
            SELECT department_id
            FROM departments
            WHERE department_code = ?
            AND department_id != ?
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "si",
            $department_code,
            $department_id
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {

            $error = "Department code already exists.";

        } else {

            /* CHECK DUPLICATE DEPARTMENT NAME */

            $sql = "
                SELECT department_id
                FROM departments
                WHERE department_name = ?
                AND department_id != ?
            ";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                "si",
                $department_name,
                $department_id
            );

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows > 0) {

                $error = "Department name already exists.";

            } else {

                /* UPDATE DEPARTMENT */

                $sql = "
                    UPDATE departments
                    SET
                        department_code = ?,
                        department_name = ?
                    WHERE department_id = ?
                ";

                $stmt = $conn->prepare($sql);

                $stmt->bind_param(
                    "ssi",
                    $department_code,
                    $department_name,
                    $department_id
                );

                if ($stmt->execute()) {

                    header(
                        "Location: departments.php?success=updated"
                    );

                    exit;

                } else {

                    $error = "Failed to update department.";
                }
            }
        }

        $stmt->close();
    }

    /* KEEP ENTERED VALUES ON VALIDATION ERROR */

    $department["department_code"] = $department_code;
    $department["department_name"] = $department_name;
}

?>

<?php require_once "../includes/header.php"; ?>
<?php require_once "../includes/navbar.php"; ?>
<?php require_once "../includes/sidebar.php"; ?>

<style>
/* ================================================
   EDIT DEPARTMENT - PREMIUM DARK HOD THEME
   STYLES ARE SCOPED TO THIS PAGE
   ================================================ */

main.department-edit-page {
    min-height: 100vh;
    color: #edf1fb;
    box-sizing: border-box;
}

.department-edit-page *,
.department-edit-page *::before,
.department-edit-page *::after {
    box-sizing: border-box;
}

/* CONTENT WRAPPER */

.department-edit-page .edit-wrapper {
    width: 100%;
    max-width: 950px;
    margin: 0 auto;
}

/* PAGE HEADER */

.department-edit-page .edit-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;

    padding: 28px 30px;
    margin-bottom: 24px;

    border: 1px solid #51478c;
    border-radius: 18px;

    background: linear-gradient(
        115deg,
        #28244e 0%,
        #39316e 60%,
        #574898 100%
    );
}

.department-edit-page .edit-header h1 {
    margin: 0 0 9px;

    color: #ffffff;
    font-size: 30px;
    font-weight: 800;
    line-height: 1.25;
}

.department-edit-page .edit-header p {
    margin: 0;

    color: #ddd5f6;
    font-size: 13px;
    line-height: 1.6;
}

/* BACK BUTTON */

.department-edit-page .edit-back-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-height: 44px;
    padding: 11px 19px;

    border: 1px solid #9183bd;
    border-radius: 10px;

    background: rgba(255,255,255,0.09);
    color: #ffffff;

    font-size: 12px;
    font-weight: 750;
    text-decoration: none;
    white-space: nowrap;
}

.department-edit-page .edit-back-btn:hover {
    background: rgba(255,255,255,0.18);
}

/* FORM CARD */

.department-edit-page .edit-card {
    width: 100%;
    padding: 35px;

    border: 1px solid #2d3a50;
    border-radius: 18px;

    background: #172235;

    box-shadow: 0 15px 35px rgba(0,0,0,0.16);
}

/* FORM LAYOUT */

.department-edit-page .edit-form {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

/* FORM FIELDS */

.department-edit-page .edit-field {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 0;
}

.department-edit-page .edit-field label {
    display: block;

    color: #dce5f4;
    font-size: 13px;
    font-weight: 700;
}

/* INPUT BOXES */

.department-edit-page .edit-field input {
    display: block;

    width: 100%;
    min-width: 0;
    height: 51px;

    padding: 12px 15px;

    border: 1px solid #3a4962;
    border-radius: 10px;

    outline: none;

    background: #10192a;
    color: #f4f6ff;

    font-family: inherit;
    font-size: 14px;

    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.department-edit-page .edit-field input:focus {
    border-color: #af9bff;
    box-shadow: 0 0 0 3px rgba(175,155,255,0.14);
}

/* BUTTONS */

.department-edit-page .edit-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 13px;

    margin-top: 7px;
}

.department-edit-page .edit-submit,
.department-edit-page .edit-cancel {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-height: 46px;
    padding: 12px 23px;

    border-radius: 10px;

    font-family: inherit;
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;

    cursor: pointer;
}

/* UPDATE BUTTON */

.department-edit-page .edit-submit {
    border: none;

    background: linear-gradient(
        115deg,
        #b8a6ff,
        #947cfa
    );

    color: #191330;
}

.department-edit-page .edit-submit:hover {
    filter: brightness(1.1);
}

/* CANCEL BUTTON */

.department-edit-page .edit-cancel {
    border: 1px solid #4b5a74;

    background: #28364e;
    color: #e1e9f7;
}

.department-edit-page .edit-cancel:hover {
    background: #34445f;
}

/* ERROR MESSAGE */

.department-edit-page .edit-error {
    margin-bottom: 22px;
    padding: 15px 18px;

    border: 1px solid #915067;
    border-radius: 11px;

    background: #482a38;
    color: #ffd1da;

    font-size: 13px;
    line-height: 1.7;
}

/* MOBILE RESPONSIVE */

@media (max-width: 760px) {

    .department-edit-page .edit-header {
        flex-direction: column;
        align-items: flex-start;
        padding: 24px 20px;
    }

    .department-edit-page .edit-header h1 {
        font-size: 25px;
    }

    .department-edit-page .edit-card {
        padding: 23px 19px;
    }

    .department-edit-page .edit-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .department-edit-page .edit-submit,
    .department-edit-page .edit-cancel {
        width: 100%;
    }
}
</style>

<main class="department-edit-page">

    <div class="edit-wrapper">

        <!-- PAGE HEADER -->

        <div class="edit-header">

            <div>
                <h1>Edit Department</h1>

                <p>
                    Update department information.
                </p>
            </div>

            <a
                href="departments.php"
                class="edit-back-btn"
            >
                ← Back to Departments
            </a>

        </div>


        <!-- VALIDATION ERROR -->

        <?php if ($error !== ""): ?>

            <div
                class="edit-error"
                role="alert"
            >
                <?php
                echo htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>
            </div>

        <?php endif; ?>


        <!-- EDIT FORM CARD -->

        <div class="edit-card">

            <form
                method="POST"
                action=""
                class="edit-form"
            >

                <!-- DEPARTMENT CODE -->

                <div class="edit-field">

                    <label for="department_code">
                        Department Code
                    </label>

                    <input
                        type="text"
                        id="department_code"
                        name="department_code"
                        maxlength="20"
                        value="<?php
                            echo htmlspecialchars(
                                $department["department_code"],
                                ENT_QUOTES,
                                "UTF-8"
                            );
                        ?>"
                        required
                    >

                </div>


                <!-- DEPARTMENT NAME -->

                <div class="edit-field">

                    <label for="department_name">
                        Department Name
                    </label>

                    <input
                        type="text"
                        id="department_name"
                        name="department_name"
                        maxlength="100"
                        value="<?php
                            echo htmlspecialchars(
                                $department["department_name"],
                                ENT_QUOTES,
                                "UTF-8"
                            );
                        ?>"
                        required
                    >

                </div>


                <!-- FORM BUTTONS -->

                <div class="edit-actions">

                    <button
                        type="submit"
                        class="edit-submit"
                    >
                        Update Department
                    </button>

                    <a
                        href="departments.php"
                        class="edit-cancel"
                    >
                        Cancel
                    </a>

                </div>

            </form>

        </div>

    </div>

</main>

<?php require_once "../includes/footer.php"; ?>
