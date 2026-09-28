<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole("HOD");

require_once "../config/database.php";


/* ==========================================================
   CHECK STUDENT ID
   ========================================================== */

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {

    header("Location: students.php?error=invalid_id");
    exit;
}


$student_id = (int) $_GET["id"];


/* ==========================================================
   GET STUDENT
   ========================================================== */

$sql = "
    SELECT
        student_id,
        user_id,
        register_number,
        student_name
    FROM students
    WHERE student_id = ?
";


$stmt = $conn->prepare($sql);

if (!$stmt) {

    header("Location: students.php?error=delete_failed");
    exit;
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


$user_id = (int) $student["user_id"];


/* ==========================================================
   START TRANSACTION
   ========================================================== */

$conn->begin_transaction();


try {

    /*
     * Delete student record first.
     */

    $deleteStudent = "
        DELETE FROM students
        WHERE student_id = ?
    ";

    $stmt = $conn->prepare($deleteStudent);

    if (!$stmt) {
        throw new Exception("Student delete preparation failed.");
    }

    $stmt->bind_param("i", $student_id);

    if (!$stmt->execute()) {
        throw new Exception("Student delete failed.");
    }

    $stmt->close();


    /*
     * Delete corresponding user account.
     */

    $deleteUser = "
        DELETE FROM users
        WHERE user_id = ?
    ";

    $stmt = $conn->prepare($deleteUser);

    if (!$stmt) {
        throw new Exception("User delete preparation failed.");
    }

    $stmt->bind_param("i", $user_id);

    if (!$stmt->execute()) {
        throw new Exception("User delete failed.");
    }

    $stmt->close();


    /*
     * Commit changes.
     */

    $conn->commit();


    header("Location: students.php?success=deleted");
    exit;


} catch (Exception $e) {

    /*
     * Roll back if anything fails.
     */

    $conn->rollback();

    header("Location: students.php?error=delete_failed");
    exit;
}

?>