<?php

$conn = new mysqli("localhost", "root", "", "smart_faculty");

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

?>