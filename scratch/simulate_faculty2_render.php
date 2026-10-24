<?php
chdir(__DIR__ . '/../faculty');
session_start();
$_SESSION['user_id'] = 3;
$_SESSION['username'] = 'faculty02';
$_SESSION['role'] = 'FACULTY';

ob_start();
require "timetable.php";
$out = ob_get_clean();

echo $out;
