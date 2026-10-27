<?php
require_once __DIR__ . '/../config/database.php';

$sql = "
    DROP TABLE IF EXISTS faculty_availability;
    CREATE TABLE faculty_availability (
        availability_id INT AUTO_INCREMENT PRIMARY KEY,
        faculty_id INT NOT NULL,
        availability_date DATE NOT NULL,
        period_id INT NOT NULL,
        status ENUM(
            'AVAILABLE',
            'UNAVAILABLE',
            'LEAVE'
        ) NOT NULL DEFAULT 'AVAILABLE',
        reason TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (faculty_id)
            REFERENCES faculty(faculty_id)
            ON DELETE CASCADE
            ON UPDATE CASCADE,
        FOREIGN KEY (period_id)
            REFERENCES periods(period_id)
            ON DELETE RESTRICT
            ON UPDATE CASCADE,
        UNIQUE (
            faculty_id,
            availability_date,
            period_id
        )
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
";

$conn->multi_query($sql);
do {
    if ($res = $conn->store_result()) {
        $res->free();
    }
} while ($conn->more_results() && $conn->next_result());

echo "faculty_availability table re-created successfully!\n";

$res = $conn->query("SHOW CREATE TABLE faculty_availability");
echo $res->fetch_row()[1] . "\n";
