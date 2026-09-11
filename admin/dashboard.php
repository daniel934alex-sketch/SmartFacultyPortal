<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole('HOD');

require_once "../config/database.php";
require_once "../config/config.php";

$pageTitle = "HOD Dashboard";


// Faculty count
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM faculty
");

$facultyCount = $result->fetch_assoc()['total'];


// Student count
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM students
");

$studentCount = $result->fetch_assoc()['total'];


// Section count
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM sections
");

$sectionCount = $result->fetch_assoc()['total'];


// Subject count
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM subjects
");

$subjectCount = $result->fetch_assoc()['total'];


// Classroom/resource count
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM classrooms
");

$roomCount = $result->fetch_assoc()['total'];


// Timetable entries
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM timetable
");

$timetableCount = $result->fetch_assoc()['total'];

?>

<?php require_once "../includes/header.php"; ?>

<?php require_once "../includes/navbar.php"; ?>

<?php require_once "../includes/sidebar.php"; ?>


<main>

    <h1 class="dashboard-title">
        HOD Dashboard
    </h1>

    <p>
        Smart Faculty Assistant & Period-Wise Attendance
        Management Portal
    </p>


    <div class="dashboard-cards">

        <div class="dashboard-card">
            <h3>Total Faculty</h3>

            <div class="number">
                <?php echo $facultyCount; ?>
            </div>
        </div>


        <div class="dashboard-card">
            <h3>Total Students</h3>

            <div class="number">
                <?php echo $studentCount; ?>
            </div>
        </div>


        <div class="dashboard-card">
            <h3>Total Sections</h3>

            <div class="number">
                <?php echo $sectionCount; ?>
            </div>
        </div>


        <div class="dashboard-card">
            <h3>Total Subjects</h3>

            <div class="number">
                <?php echo $subjectCount; ?>
            </div>
        </div>


        <div class="dashboard-card">
            <h3>Rooms & Labs</h3>

            <div class="number">
                <?php echo $roomCount; ?>
            </div>
        </div>


        <div class="dashboard-card">
            <h3>Timetable Entries</h3>

            <div class="number">
                <?php echo $timetableCount; ?>
            </div>
        </div>

    </div>


    <div class="table-container">

        <h2>Department Information</h2>

        <br>

        <table>

            <tr>
                <th>Department</th>
                <td>Computer Science and Engineering</td>
            </tr>

            <tr>
                <th>Year</th>
                <td>2nd Year</td>
            </tr>

            <tr>
                <th>Semester</th>
                <td>3rd Semester</td>
            </tr>

            <tr>
                <th>Sections</th>
                <td>CSE-A, CSE-B, CSE-C, CSE-D, CSE-E</td>
            </tr>

            <tr>
                <th>Working Days</th>
                <td>Monday – Friday</td>
            </tr>

            <tr>
                <th>Saturday</th>
                <td>EE Project</td>
            </tr>

        </table>

    </div>


<?php require_once "../includes/footer.php"; ?>