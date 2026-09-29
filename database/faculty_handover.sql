USE smart_faculty;

CREATE TABLE IF NOT EXISTS faculty_availability (
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
);