<?php

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";

requireRole("HOD");

require_once "../config/database.php";

$pageTitle = "Student Management";

$sql = "
    SELECT
        s.student_id,
        s.register_number,
        s.student_name,
        s.email,
        s.phone,
        s.section_id,
        sec.section_name,
        u.username,
        u.status
    FROM students s
    INNER JOIN users u ON s.user_id = u.user_id
    INNER JOIN sections sec ON s.section_id = sec.section_id
    ORDER BY sec.section_id ASC, s.register_number ASC
";

$result = $conn->query($sql);

require_once "../includes/header.php";
require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";

?>

<style>
.students-page {
    margin-left: 245px;
    padding: 105px 34px 40px;
    min-height: 100vh;
    background: transparent;
    color: #f1f5ff;
    box-sizing: border-box;
}

.students-container {
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
}

.students-header {
    min-height: 105px;
    padding: 24px 28px;
    background: linear-gradient(115deg, #252343, #383068 65%, #4b3c83);
    border: 1px solid #51478c;
    border-radius: 17px;
    margin-bottom: 23px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    box-sizing: border-box;
}

.students-header h2 {
    color: #fff;
    font-size: 28px;
    font-weight: 800;
    margin: 0 0 6px;
}

.students-header p {
    color: #d0c8e8;
    font-size: 13px;
    margin: 0;
    line-height: 1.7;
}

.students-page a {
    text-decoration: none;
}

.add-student-btn,
.import-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 43px;
    padding: 10px 18px;
    background: #b09cff;
    color: #1a1530 !important;
    border: none;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    transition: background .2s ease;
}

.add-student-btn:hover,
.import-btn:hover {
    background: #c8bcff;
}

/* UPLOAD CARD */

.student-import-card {
    background: #172235;
    border: 1px solid #35445b;
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 20px;
    box-sizing: border-box;
}

.student-import-card h3 {
    margin: 0 0 8px;
    color: #eee8ff;
    font-size: 20px;
}

.student-import-card p {
    color: #aebad0;
    font-size: 13px;
    line-height: 1.8;
    margin: 0 0 18px;
}

.student-file-field {
    display: flex;
    flex-direction: column;
    gap: 9px;
    flex: 1;
    min-width: 230px;
}

.student-file-field label {
    color: #e1e8f4;
    font-size: 13px;
    font-weight: 700;
}

.student-file-field input[type="file"] {
    width: 100%;
    box-sizing: border-box;
    padding: 11px;
    border: 1px solid #394860;
    border-radius: 9px;
    background: #0c192a;
    color: #dce4f1;
    font-size: 13px;
}

.student-import-form {
    display: flex;
    align-items: end;
    flex-wrap: wrap;
    gap: 15px;
}

.template-link {
    display: inline-block;
    margin-bottom: 18px;
    color: #c4b5fd !important;
    font-size: 13px;
    font-weight: 700;
}

.template-link:hover {
    color: #e2d9ff !important;
    text-decoration: underline;
}

.student-import-card .import-note {
    margin: 15px 0 0;
    font-size: 12px;
}

/* SECTION CAPACITY */

.section-capacity-card {
    background: #172235;
    border: 1px solid #2a364b;
    border-radius: 14px;
    padding: 18px 22px;
    margin-bottom: 20px;
    box-sizing: border-box;
}

.section-capacity-card p {
    color: #aebad0;
    font-size: 13px;
    line-height: 1.8;
    margin: 0;
}

.section-capacity-card strong:first-child {
    color: #f0ebff;
}

.section-capacity-card strong:last-child {
    color: #b7a5ff;
}

/* STUDENT TABLE */

.students-table-container {
    overflow-x: auto;
    border: 1px solid #2a364b;
    border-radius: 17px;
    background: #172235;
}

.students-page table {
    width: 100%;
    min-width: 1050px;
    border-collapse: collapse;
    background: #172235;
    color: #e1e8f4;
    font-size: 13px;
}

.students-page table thead tr {
    background: #202d43;
    border-bottom: 1px solid #394860;
}

.students-page table thead th {
    padding: 17px 14px;
    background: #202d43;
    color: #d8cbff;
    font-size: 12px;
    font-weight: 750;
    text-align: left;
    white-space: nowrap;
}

.students-page table tbody tr {
    border-bottom: 1px solid #2a364b;
    background: #172235;
}

.students-page table tbody tr:nth-child(even) {
    background: #1b2940;
}

.students-page table tbody tr:hover {
    background: #25334d;
}

.students-page table tbody td {
    padding: 15px 14px;
    color: #dce4f1;
    border-bottom: 1px solid #2a364b;
    font-size: 12px;
    vertical-align: middle;
}

.students-page table tbody td strong {
    color: #f4f1ff;
}

.students-page table tbody td a.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    margin: 3px 4px 3px 0;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 750;
    white-space: nowrap;
    color: #fff !important;
}

.students-page table tbody td a.action-btn:hover {
    filter: brightness(1.15);
}

.students-page table tbody td[colspan] {
    padding: 50px 20px;
    text-align: center;
    color: #a4b3c9;
    font-size: 14px;
}

/* TABLET */

@media (max-width: 1100px) {
    .students-page {
        margin-left: 220px;
        padding: 100px 24px 32px;
    }
}

/* MOBILE */

@media (max-width: 760px) {
    .students-page {
        margin-left: 0;
        padding: 24px 16px 32px;
    }

    .students-header {
        flex-direction: column;
        align-items: flex-start;
        padding: 23px 20px;
    }

    .students-header h2 {
        font-size: 24px;
    }

    .add-student-btn,
    .import-btn {
        width: 100%;
    }

    .student-import-form {
        flex-direction: column;
        align-items: stretch;
    }

    .student-file-field {
        min-width: 0;
    }

    .student-import-card,
    .section-capacity-card {
        padding: 16px;
    }

    .students-page table {
        min-width: 920px;
    }
}

</style>

<div class="main-content students-page">
    <div class="students-container">

        <!-- PAGE HEADER -->

        <div class="students-header">
            <div>
                <h2>Student Management</h2>
                <p>Manage CSE students and their sections.</p>
            </div>

            <a href="student-add.php" class="add-student-btn">
                + Add Student
            </a>
        </div>

        <!-- MULTI-FORMAT UPLOAD -->

        <div class="student-import-card" id="upload-students">
            <h3>Upload Students</h3>

            <p>
                Upload student records using CSV, Excel, PDF, or image files.
                You can also download your Google Sheets data and upload it.
            </p>

            <a
                href="student-template.csv"
                class="template-link"
                download
            >
                Download Student CSV Template
            </a>

            <form
                class="student-import-form"
                action="student-import.php"
                method="POST"
                enctype="multipart/form-data"
            >
            
                <div class="student-file-field">
                    <label for="student_file">
                        Choose Student File
                    </label>

                    <input
                        type="file"
                        id="student_file"
                        name="student_file"
                        accept=".csv,.xlsx,.xls,.pdf,.png,.jpg,.jpeg,.webp"
                        required
                    >

                    <small style="color:#aebad0;line-height:1.8;">
                        Supported: CSV, XLSX, XLS, PDF, PNG, JPG, JPEG, WEBP
                    </small>
                </div>

                <button type="submit" class="import-btn">
                    Upload Students
                </button>
            </form>

            <p class="import-note">
                Files are processed by the import handler. PDF and image
                files require text extraction or OCR. Student records must
                be validated before they are saved.
            </p>
        </div>

        <!-- SECTION CAPACITY -->

        <div class="section-capacity-card">
            <p>
                <strong>Section Capacity:</strong>
                Each section can have a maximum of
                <strong>64 students</strong>.
            </p>
        </div>

        <!-- STUDENT TABLE -->

        <div class="students-table-container">
            <table>
                <thead>
                    <tr>
                        <th>Register Number</th>
                        <th>Student Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th style="text-align:center;">Section</th>
                        <th>Username</th>
                        <th style="text-align:center;">Status</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($result && $result->num_rows > 0): ?>

                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= htmlspecialchars(
                                        $row["register_number"] ?? "",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </strong>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row["student_name"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row["email"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row["phone"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td style="text-align:center;">
                                <span style="
                                    display:inline-block;
                                    background:#302951;
                                    color:#d8cbff;
                                    padding:5px 11px;
                                    border-radius:7px;
                                    font-size:12px;
                                    font-weight:700;
                                ">
                                    <?= htmlspecialchars(
                                        $row["section_name"] ?? "",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row["username"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td style="text-align:center;">
                                <?php if ($row["status"] === "ACTIVE"): ?>
                                    <span style="
                                        display:inline-block;
                                        background:#164e3a;
                                        color:#bbf7d0;
                                        padding:5px 10px;
                                        border-radius:7px;
                                        font-size:11px;
                                        font-weight:700;
                                    ">ACTIVE</span>
                                <?php else: ?>
                                    <span style="
                                        display:inline-block;
                                        background:#572b36;
                                        color:#fecaca;
                                        padding:5px 10px;
                                        border-radius:7px;
                                        font-size:11px;
                                        font-weight:700;
                                    ">INACTIVE</span>
                                <?php endif; ?>
                            </td>

                            <td style="text-align:center;white-space:nowrap;">
                                <a
                                    class="action-btn"
                                    href="student-edit.php?id=<?= (int)$row["student_id"] ?>"
                                    style="background:#2563eb;"
                                >Edit</a>

                                <?php if ($row["status"] === "ACTIVE"): ?>
                                    <a
                                        class="action-btn"
                                        href="student-status.php?id=<?= (int)$row["student_id"] ?>&status=INACTIVE"
                                        onclick="return confirm('Deactivate this student?');"
                                        style="background:#dc2626;"
                                    >Deactivate</a>
                                <?php else: ?>
                                    <a
                                        class="action-btn"
                                        href="student-status.php?id=<?= (int)$row["student_id"] ?>&status=ACTIVE"
                                        onclick="return confirm('Activate this student?');"
                                        style="background:#16a34a;"
                                    >Activate</a>
                                <?php endif; ?>

                                <a
                                    class="action-btn"
                                    href="student-delete.php?id=<?= (int)$row["student_id"] ?>"
                                    onclick="return confirm('WARNING: This will permanently delete this student and their login account. Continue?');"
                                    style="background:#991b1b;"
                                >Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="8">No students found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
