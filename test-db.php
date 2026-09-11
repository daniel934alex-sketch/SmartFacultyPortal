<?php

require_once "config/database.php";

echo "<h1>Smart Faculty Portal - Database Test</h1>";


// ========================================
// DATABASE CONNECTION
// ========================================

echo "<h2>Database Connection</h2>";

echo "<p>✅ Connected to smart_faculty</p>";


// ========================================
// FACULTY COUNT
// ========================================

$result = $conn->query("SELECT COUNT(*) AS total FROM faculty");

$row = $result->fetch_assoc();

echo "<p>Faculty: " . $row['total'] . "</p>";


// ========================================
// SECTIONS
// ========================================

echo "<h2>Sections</h2>";

$result = $conn->query("
    SELECT section_name
    FROM sections
    ORDER BY section_name
");

echo "<ul>";

while ($row = $result->fetch_assoc()) {

    echo "<li>" .
         htmlspecialchars($row['section_name']) .
         "</li>";
}

echo "</ul>";


// ========================================
// SUBJECTS
// ========================================

echo "<h2>Subjects</h2>";

$result = $conn->query("
    SELECT subject_code,
           subject_name,
           weekly_periods,
           continuous_periods
    FROM subjects
    ORDER BY subject_id
");

echo "<table border='1' cellpadding='8'>";

echo "<tr>";
echo "<th>Code</th>";
echo "<th>Subject</th>";
echo "<th>Weekly Periods</th>";
echo "<th>Continuous Periods</th>";
echo "</tr>";

while ($row = $result->fetch_assoc()) {

    echo "<tr>";

    echo "<td>" .
         htmlspecialchars($row['subject_code']) .
         "</td>";

    echo "<td>" .
         htmlspecialchars($row['subject_name']) .
         "</td>";

    echo "<td>" .
         $row['weekly_periods'] .
         "</td>";

    echo "<td>" .
         $row['continuous_periods'] .
         "</td>";

    echo "</tr>";
}

echo "</table>";


// ========================================
// CLASSROOMS & LABS
// ========================================

echo "<h2>Rooms</h2>";

$result = $conn->query("
    SELECT room_number,
           floor,
           room_type
    FROM classrooms
    ORDER BY classroom_id
");

echo "<table border='1' cellpadding='8'>";

echo "<tr>";
echo "<th>Room</th>";
echo "<th>Location</th>";
echo "<th>Type</th>";
echo "</tr>";

while ($row = $result->fetch_assoc()) {

    echo "<tr>";

    echo "<td>" .
         htmlspecialchars($row['room_number']) .
         "</td>";

    echo "<td>" .
         htmlspecialchars($row['floor']) .
         "</td>";

    echo "<td>" .
         htmlspecialchars($row['room_type']) .
         "</td>";

    echo "</tr>";
}

echo "</table>";


$conn->close();

?>