<?php
require_once "config/database.php";

echo "--- DEPARTMENTS ---\n";
$res = $conn->query("SELECT * FROM departments");
while ($r = $res->fetch_assoc()) print_r($r);

echo "--- ACADEMIC CLASSES ---\n";
$res = $conn->query("SELECT * FROM academic_classes");
while ($r = $res->fetch_assoc()) print_r($r);

echo "--- SECTIONS ---\n";
$res = $conn->query("SELECT * FROM sections");
while ($r = $res->fetch_assoc()) print_r($r);
