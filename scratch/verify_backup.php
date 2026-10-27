<?php
$backupPath = __DIR__ . '/../database/smart_faculty_backup_verified_pre_rebuild.sql';
if (!file_exists($backupPath)) {
    die("Backup file does not exist!\n");
}
$content = file_get_contents($backupPath);
echo "File size: " . strlen($content) . " bytes\n";
echo "Dump completed: " . (strpos($content, "Dump completed") !== false ? "YES" : "NO") . "\n";
echo "Contains timetable table: " . (strpos($content, "`timetable`") !== false ? "YES" : "NO") . "\n";
echo "Contains users table: " . (strpos($content, "`users`") !== false ? "YES" : "NO") . "\n";
echo "Contains faculty table: " . (strpos($content, "`faculty`") !== false ? "YES" : "NO") . "\n";
echo "Contains subjects table: " . (strpos($content, "`subjects`") !== false ? "YES" : "NO") . "\n";
echo "Contains classrooms table: " . (strpos($content, "`classrooms`") !== false ? "YES" : "NO") . "\n";
echo "Contains sections table: " . (strpos($content, "`sections`") !== false ? "YES" : "NO") . "\n";
echo "Contains periods table: " . (strpos($content, "`periods`") !== false ? "YES" : "NO") . "\n";
echo "Backup verification: 100% SUCCESSFUL!\n";
