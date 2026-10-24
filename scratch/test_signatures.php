<?php
$f = file_get_contents(__DIR__ . '/../faculty/timetable.php');
echo 'Faculty timetable has <div class="inst-sig-block">: ' . (strpos($f, '<div class="inst-sig-block">') !== false ? 'YES' : 'NO') . PHP_EOL;
echo 'Faculty timetable has Timetable Coordinator: ' . (strpos($f, 'Timetable Coordinator') !== false ? 'YES' : 'NO') . PHP_EOL;
echo 'Faculty timetable has Principal: ' . (strpos($f, 'Principal') !== false ? 'YES' : 'NO') . PHP_EOL;
echo 'Faculty timetable has Head of Department (HOD): ' . (strpos($f, 'Head of Department (HOD)') !== false ? 'YES' : 'NO') . PHP_EOL;

$a = file_get_contents(__DIR__ . '/../admin/my-timetable.php');
echo 'Admin timetable has <div class="inst-sig-block">: ' . (strpos($a, '<div class="inst-sig-block">') !== false ? 'YES' : 'NO') . PHP_EOL;
echo 'Admin timetable has Timetable Coordinator: ' . (strpos($a, 'Timetable Coordinator') !== false ? 'YES' : 'NO') . PHP_EOL;
echo 'Admin timetable has Principal: ' . (strpos($a, 'Principal') !== false ? 'YES' : 'NO') . PHP_EOL;
echo 'Admin timetable has Head of Department (HOD): ' . (strpos($a, 'Head of Department (HOD)') !== false ? 'YES' : 'NO') . PHP_EOL;
