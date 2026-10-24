<?php
require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "My Timetable";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = (int) $_SESSION["user_id"];

/*
 * Fetch only the timetable entries assigned
 * to the logged-in HOD's faculty account.
 */
$sql = "
    SELECT
        t.day,
        t.status,
        p.period_number,
        p.start_time,
        p.end_time,
        sec.section_name,
        s.subject_code,
        s.subject_name,
        f.faculty_name,
        c.room_number,
        c.room_type
    FROM faculty f
    INNER JOIN timetable t
        ON t.faculty_id = f.faculty_id
    INNER JOIN periods p
        ON p.period_id = t.period_id
    INNER JOIN sections sec
        ON sec.section_id = t.section_id
    INNER JOIN subjects s
        ON s.subject_id = t.subject_id
    INNER JOIN classrooms c
        ON c.classroom_id = t.classroom_id
    WHERE f.user_id = ?
    ORDER BY
        FIELD(
            UPPER(t.day),
            'MONDAY',
            'TUESDAY',
            'WEDNESDAY',
            'THURSDAY',
            'FRIDAY',
            'SATURDAY'
        ),
        p.period_number
";

$stmt = $conn->prepare($sql);
$timetableRows = [];
$errorMessage = "";

if ($stmt) {
    $stmt->bind_param("i", $userId);

    if ($stmt->execute()) {
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $timetableRows[] = $row;
        }
    } else {
        $errorMessage = "Unable to load your timetable.";
    }

    $stmt->close();
} else {
    $errorMessage = "Unable to prepare the timetable query.";
}

function timetableEscape($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}
?>

<?php require_once "../includes/header.php"; ?>
<?php require_once "../includes/navbar.php"; ?>
<?php require_once "../includes/sidebar.php"; ?>

<main>
    <div class="page-header">
        <div>
            <h1>My Timetable</h1>
            <p>View your assigned teaching periods and classes.</p>
        </div>
    </div>

    <?php if ($errorMessage !== ""): ?>

        <div class="alert-error">
            <?= timetableEscape($errorMessage) ?>
        </div>

    <?php else: ?>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Period</th>
                        <th>Time</th>
                        <th>Section</th>
                        <th>Subject</th>
                        <th>Room / Lab</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($timetableRows) > 0): ?>

                        <?php foreach ($timetableRows as $row): ?>
                            <tr>
                                <td>
                                    <?= timetableEscape($row["day"]) ?>
                                </td>

                                <td>
                                    P<?= (int) $row["period_number"] ?>
                                </td>

                                <td>
                                    <?= timetableEscape(
                                        substr($row["start_time"], 0, 5)
                                    ) ?>
                                    -
                                    <?= timetableEscape(
                                        substr($row["end_time"], 0, 5)
                                    ) ?>
                                </td>

                                <td>
                                    <?= timetableEscape($row["section_name"]) ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= timetableEscape($row["subject_code"]) ?>
                                    </strong>
                                    <br>
                                    <small>
                                        <?= timetableEscape($row["subject_name"]) ?>
                                    </small>
                                </td>

                                <td>
                                    <?= timetableEscape($row["room_number"]) ?>
                                    <br>
                                    <small>
                                        <?= timetableEscape($row["room_type"]) ?>
                                    </small>
                                </td>

                                <td>
                                    <?= timetableEscape($row["status"]) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="7">
                                No teaching periods are currently assigned
                                to your HOD account.
                            </td>
                        </tr>

                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</main>

<?php require_once "../includes/footer.php"; ?>