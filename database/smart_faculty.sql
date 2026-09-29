-- ============================================================
-- SMART FACULTY ASSISTANT & PERIOD-WISE ATTENDANCE PORTAL
-- CSE 2nd Year - 3rd Semester
-- ============================================================

DROP DATABASE IF EXISTS smart_faculty;

CREATE DATABASE smart_faculty
CHARACTER SET utf8mb4
COLLATE utf8mb4_general_ci;

USE smart_faculty;


-- ============================================================
-- 1. USERS
-- ============================================================

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,

    username VARCHAR(50) NOT NULL UNIQUE,

    password VARCHAR(255) NOT NULL,

    role ENUM(
        'HOD',
        'FACULTY',
        'STUDENT'
    ) NOT NULL,

    status ENUM(
        'ACTIVE',
        'INACTIVE'
    ) DEFAULT 'ACTIVE',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- ============================================================
-- 2. DEPARTMENT
-- ============================================================

CREATE TABLE departments (
    department_id INT AUTO_INCREMENT PRIMARY KEY,

    department_code VARCHAR(20) NOT NULL UNIQUE,

    department_name VARCHAR(100) NOT NULL UNIQUE
);


-- ============================================================
-- 3. ACADEMIC CLASS
-- Only CSE 2nd Year 3rd Semester
-- ============================================================

CREATE TABLE academic_classes (
    class_id INT AUTO_INCREMENT PRIMARY KEY,

    department_id INT NOT NULL,

    year_number INT NOT NULL DEFAULT 2,

    semester_number INT NOT NULL DEFAULT 3,

    academic_year VARCHAR(20) NOT NULL,

    FOREIGN KEY (department_id)
        REFERENCES departments(department_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);


-- ============================================================
-- 4. SECTIONS
-- ============================================================

CREATE TABLE sections (
    section_id INT AUTO_INCREMENT PRIMARY KEY,

    class_id INT NOT NULL,

    section_name VARCHAR(20) NOT NULL,

    FOREIGN KEY (class_id)
        REFERENCES academic_classes(class_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    UNIQUE(class_id, section_name)
);


-- ============================================================
-- 5. FACULTY
-- ============================================================

CREATE TABLE faculty (
    faculty_id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL UNIQUE,

    faculty_code VARCHAR(20) NOT NULL UNIQUE,

    faculty_name VARCHAR(100) NOT NULL,

    email VARCHAR(100),

    phone VARCHAR(20),

    department_id INT NOT NULL,

    designation VARCHAR(50) DEFAULT 'Assistant Professor',

    is_hod BOOLEAN DEFAULT FALSE,

    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY (department_id)
        REFERENCES departments(department_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);


-- ============================================================
-- 6. STUDENTS
-- ============================================================

CREATE TABLE students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL UNIQUE,

    register_number VARCHAR(30) NOT NULL UNIQUE,

    student_name VARCHAR(100) NOT NULL,

    email VARCHAR(100),

    phone VARCHAR(20),

    section_id INT NOT NULL,

    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY (section_id)
        REFERENCES sections(section_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);


-- ============================================================
-- 7. SUBJECTS
-- ============================================================

CREATE TABLE subjects (
    subject_id INT AUTO_INCREMENT PRIMARY KEY,

    subject_code VARCHAR(30) NOT NULL UNIQUE,

    subject_name VARCHAR(100) NOT NULL,

    subject_type ENUM(
        'THEORY',
        'LAB',
        'PROJECT'
    ) NOT NULL,

    weekly_periods INT NOT NULL DEFAULT 0,

    continuous_periods INT NOT NULL DEFAULT 1,

    department_id INT,

    FOREIGN KEY (department_id)
        REFERENCES departments(department_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);


-- ============================================================
-- 8. FACULTY-SUBJECT MAPPING
-- Allows multiple faculty to teach the same subject
-- ============================================================

CREATE TABLE faculty_subjects (
    faculty_subject_id INT AUTO_INCREMENT PRIMARY KEY,

    faculty_id INT NOT NULL,

    subject_id INT NOT NULL,

    FOREIGN KEY (faculty_id)
        REFERENCES faculty(faculty_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY (subject_id)
        REFERENCES subjects(subject_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    UNIQUE(faculty_id, subject_id)
);


-- ============================================================
-- 9. CLASSROOMS
-- ============================================================

CREATE TABLE classrooms (
    classroom_id INT AUTO_INCREMENT PRIMARY KEY,

    room_number VARCHAR(30) NOT NULL UNIQUE,

    floor VARCHAR(50),

    room_type ENUM(
        'CLASSROOM',
        'LAB',
        'PROJECT'
    ) NOT NULL DEFAULT 'CLASSROOM',

    capacity INT DEFAULT 60,

    status ENUM(
        'AVAILABLE',
        'UNAVAILABLE'
    ) DEFAULT 'AVAILABLE'
);


-- ============================================================
-- 10. PERIODS
-- ============================================================

CREATE TABLE periods (
    period_id INT AUTO_INCREMENT PRIMARY KEY,

    period_number INT NOT NULL UNIQUE,

    start_time TIME NOT NULL,

    end_time TIME NOT NULL
);


-- ============================================================
-- 11. TIMETABLE
-- ============================================================

CREATE TABLE timetable (
    timetable_id INT AUTO_INCREMENT PRIMARY KEY,

    day ENUM(
        'MONDAY',
        'TUESDAY',
        'WEDNESDAY',
        'THURSDAY',
        'FRIDAY',
        'SATURDAY'
    ) NOT NULL,

    period_id INT NOT NULL,

    section_id INT NOT NULL,

    subject_id INT NOT NULL,

    faculty_id INT NOT NULL,

    classroom_id INT NOT NULL,

    status ENUM(
        'DRAFT',
        'PUBLISHED'
    ) DEFAULT 'DRAFT',

    FOREIGN KEY (period_id)
        REFERENCES periods(period_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    FOREIGN KEY (section_id)
        REFERENCES sections(section_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    FOREIGN KEY (subject_id)
        REFERENCES subjects(subject_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    FOREIGN KEY (faculty_id)
        REFERENCES faculty(faculty_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    FOREIGN KEY (classroom_id)
        REFERENCES classrooms(classroom_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    -- Same section cannot have two classes at same time
    UNIQUE(section_id, day, period_id),

    -- Same faculty cannot teach two sections at same time
    UNIQUE(faculty_id, day, period_id),

    -- Same room cannot be used by two sections at same time
    UNIQUE(classroom_id, day, period_id)
);


-- ============================================================
-- 12. ATTENDANCE
-- ============================================================

CREATE TABLE attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,

    timetable_id INT NOT NULL,

    student_id INT NOT NULL,

    faculty_id INT NOT NULL,

    attendance_date DATE NOT NULL,

    status ENUM(
        'PRESENT',
        'ABSENT'
    ) NOT NULL DEFAULT 'ABSENT',

    marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (timetable_id)
        REFERENCES timetable(timetable_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    FOREIGN KEY (student_id)
        REFERENCES students(student_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY (faculty_id)
        REFERENCES faculty(faculty_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    UNIQUE(
        timetable_id,
        student_id,
        attendance_date
    )
);


-- ============================================================
-- 13. CLASS HANDOVER
-- ============================================================

CREATE TABLE class_handover (
    handover_id INT AUTO_INCREMENT PRIMARY KEY,

    timetable_id INT NOT NULL,

    original_faculty_id INT NOT NULL,

    reason TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    status ENUM(
        'PENDING',
        'ASSIGNED',
        'COMPLETED',
        'CANCELLED',
        'HOD_REQUIRED'
    ) DEFAULT 'PENDING',

    FOREIGN KEY (timetable_id)
        REFERENCES timetable(timetable_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    FOREIGN KEY (original_faculty_id)
        REFERENCES faculty(faculty_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);


-- ============================================================
-- 14. HANDOVER REQUESTS
-- ============================================================

CREATE TABLE handover_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,

    handover_id INT NOT NULL,

    alternate_faculty_id INT NOT NULL,

    status ENUM(
        'PENDING',
        'ACCEPTED',
        'REJECTED',
        'EXPIRED'
    ) DEFAULT 'PENDING',

    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    responded_at TIMESTAMP NULL,

    FOREIGN KEY (handover_id)
        REFERENCES class_handover(handover_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY (alternate_faculty_id)
        REFERENCES faculty(faculty_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);


-- ============================================================
-- 15. NOTIFICATIONS
-- ============================================================

CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,

    title VARCHAR(150) NOT NULL,

    message TEXT NOT NULL,

    notification_type VARCHAR(50),

    is_read BOOLEAN DEFAULT FALSE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
);


-- ============================================================
-- 16. ANNOUNCEMENTS
-- ============================================================

CREATE TABLE announcements (
    announcement_id INT AUTO_INCREMENT PRIMARY KEY,

    title VARCHAR(150) NOT NULL,

    message TEXT NOT NULL,

    created_by INT NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by)
        REFERENCES users(user_id)
        ON DELETE RESTRICT
);


-- ============================================================
-- 17. EE PROJECTS
-- ============================================================

CREATE TABLE ee_projects (
    project_id INT AUTO_INCREMENT PRIMARY KEY,

    project_title VARCHAR(200) NOT NULL,

    section_id INT NOT NULL,

    faculty_id INT,

    project_date DATE,

    description TEXT,

    status ENUM(
        'PLANNED',
        'ONGOING',
        'COMPLETED'
    ) DEFAULT 'PLANNED',

    FOREIGN KEY (section_id)
        REFERENCES sections(section_id)
        ON DELETE RESTRICT,

    FOREIGN KEY (faculty_id)
        REFERENCES faculty(faculty_id)
        ON DELETE SET NULL
);


-- ============================================================
-- 18. PROJECT GROUPS
-- ============================================================

CREATE TABLE project_groups (
    project_group_id INT AUTO_INCREMENT PRIMARY KEY,

    project_id INT NOT NULL,

    group_name VARCHAR(100) NOT NULL,

    FOREIGN KEY (project_id)
        REFERENCES ee_projects(project_id)
        ON DELETE CASCADE
);


-- ============================================================
-- INITIAL DATA
-- ============================================================


-- ============================================================
-- DEPARTMENT
-- ============================================================

INSERT INTO departments
(department_code, department_name)
VALUES
('CSE', 'Computer Science and Engineering');


-- ============================================================
-- ACADEMIC CLASS
-- ============================================================

INSERT INTO academic_classes
(
    department_id,
    year_number,
    semester_number,
    academic_year
)
VALUES
(
    1,
    2,
    3,
    '2026-2027'
);


-- ============================================================
-- SECTIONS
-- ============================================================

INSERT INTO sections
(class_id, section_name)
VALUES
(1, 'CSE-A'),
(1, 'CSE-B'),
(1, 'CSE-C'),
(1, 'CSE-D'),
(1, 'CSE-E');


-- ============================================================
-- PERIODS
-- ============================================================

INSERT INTO periods
(period_number, start_time, end_time)
VALUES
(1, '08:30:00', '09:15:00'),
(2, '09:15:00', '10:00:00'),
(3, '10:00:00', '10:45:00'),
(4, '11:00:00', '11:45:00'),
(5, '11:45:00', '12:30:00'),
(6, '12:30:00', '13:15:00'),
(7, '14:00:00', '14:45:00'),
(8, '14:45:00', '15:30:00'),
(9, '15:45:00', '16:30:00');


-- ============================================================
-- CLASSROOMS
-- ============================================================

INSERT INTO classrooms
(
    room_number,
    floor,
    room_type,
    capacity
)
VALUES

('36', '1st Floor', 'CLASSROOM', 60),

('37', '1st Floor', 'CLASSROOM', 60),

('38', '2nd Floor', 'CLASSROOM', 60),

('39', '2nd Floor', 'CLASSROOM', 60),

('40', '3rd Floor', 'CLASSROOM', 60);


-- ============================================================
-- LABS
-- ============================================================

INSERT INTO classrooms
(
    room_number,
    floor,
    room_type,
    capacity
)
VALUES

('LAB-1', 'Near CSE Department', 'LAB', 36),

('LAB-2', 'Above CSE Department - 2nd Floor', 'LAB', 36),

('LAB-3', 'Above CSE Department - 2nd Floor', 'LAB', 36);


-- ============================================================
-- SUBJECTS
-- ============================================================

INSERT INTO subjects
(
    subject_code,
    subject_name,
    subject_type,
    weekly_periods,
    continuous_periods,
    department_id
)
VALUES

('DBMS',
 'Database Management Systems',
 'THEORY',
 6,
 1,
 1),

('ADSA',
 'Advanced Data Structures and Algorithms',
 'THEORY',
 5,
 1,
 1),

('KVD',
 'Knowledge AI Vector Database',
 'THEORY',
 5,
 1,
 1),

('OS',
 'Operating Systems',
 'THEORY',
 5,
 1,
 1),

('MATHS',
 'Mathematics',
 'THEORY',
 6,
 1,
 1),

('ENG',
 'English',
 'THEORY',
 2,
 2,
 1),

('QUANTS',
 'Quants',
 'THEORY',
 1,
 1,
 1),

('ADSA-LAB',
 'Advanced DSA Lab',
 'LAB',
 3,
 3,
 1),

('KVD-LAB',
 'KVD Lab',
 'LAB',
 3,
 3,
 1),

('OS-LAB',
 'OS Lab',
 'LAB',
 3,
 3,
 1),

('DBMS-LAB',
 'DBMS Lab',
 'LAB',
 3,
 3,
 1),

('EE-PROJECT',
 'EE Project',
 'PROJECT',
 0,
 1,
 1);


-- ============================================================
-- DEMO FACULTY USERS
-- 1 HOD + 14 FACULTY
-- ============================================================

INSERT INTO users
(username, password, role)
VALUES

('hod.cse',
 '$2y$10$demo',
 'HOD'),

('faculty01',
 '$2y$10$demo',
 'FACULTY'),

('faculty02',
 '$2y$10$demo',
 'FACULTY'),

('faculty03',
 '$2y$10$demo',
 'FACULTY'),

('faculty04',
 '$2y$10$demo',
 'FACULTY'),

('faculty05',
 '$2y$10$demo',
 'FACULTY'),

('faculty06',
 '$2y$10$demo',
 'FACULTY'),

('faculty07',
 '$2y$10$demo',
 'FACULTY'),

('faculty08',
 '$2y$10$demo',
 'FACULTY'),

('faculty09',
 '$2y$10$demo',
 'FACULTY'),

('faculty10',
 '$2y$10$demo',
 'FACULTY'),

('faculty11',
 '$2y$10$demo',
 'FACULTY'),

('faculty12',
 '$2y$10$demo',
 'FACULTY'),

('faculty13',
 '$2y$10$demo',
 'FACULTY'),

('faculty14',
 '$2y$10$demo',
 'FACULTY');


-- ============================================================
-- FACULTY DETAILS
-- Random demo assignment
-- ============================================================

INSERT INTO faculty
(
    user_id,
    faculty_code,
    faculty_name,
    email,
    phone,
    department_id,
    designation,
    is_hod
)
VALUES

(1, 'CSE001', 'Dr. Arun Kumar',
 'arun@college.edu',
 '9000000001',
 1,
 'Professor',
 TRUE),

(2, 'CSE002', 'Prof. Priya Sharma',
 'priya@college.edu',
 '9000000002',
 1,
 'Assistant Professor',
 FALSE),

(3, 'CSE003', 'Prof. Karthik Raj',
 'karthik@college.edu',
 '9000000003',
 1,
 'Assistant Professor',
 FALSE),

(4, 'CSE004', 'Prof. Divya S',
 'divya@college.edu',
 '9000000004',
 1,
 'Assistant Professor',
 FALSE),

(5, 'CSE005', 'Prof. Naveen Kumar',
 'naveen@college.edu',
 '9000000005',
 1,
 'Assistant Professor',
 FALSE),

(6, 'CSE006', 'Prof. Meena R',
 'meena@college.edu',
 '9000000006',
 1,
 'Assistant Professor',
 FALSE),

(7, 'CSE007', 'Prof. Rahul P',
 'rahul@college.edu',
 '9000000007',
 1,
 'Assistant Professor',
 FALSE),

(8, 'CSE008', 'Prof. Sneha K',
 'sneha@college.edu',
 '9000000008',
 1,
 'Assistant Professor',
 FALSE),

(9, 'CSE009', 'Prof. Vignesh M',
 'vignesh@college.edu',
 '9000000009',
 1,
 'Assistant Professor',
 FALSE),

(10, 'CSE010', 'Prof. Anitha S',
 'anitha@college.edu',
 '9000000010',
 1,
 'Assistant Professor',
 FALSE),

(11, 'CSE011', 'Prof. Sanjay R',
 'sanjay@college.edu',
 '9000000011',
 1,
 'Assistant Professor',
 FALSE),

(12, 'CSE012', 'Prof. Harini V',
 'harini@college.edu',
 '9000000012',
 1,
 'Assistant Professor',
 FALSE),

(13, 'CSE013', 'Prof. Ajay Kumar',
 'ajay@college.edu',
 '9000000013',
 1,
 'Assistant Professor',
 FALSE),

(14, 'CSE014', 'Prof. Keerthana P',
 'keerthana@college.edu',
 '9000000014',
 1,
 'Assistant Professor',
 FALSE),

(15, 'CSE015', 'Prof. Manoj S',
 'manoj@college.edu',
 '9000000015',
 1,
 'Assistant Professor',
 FALSE);


-- ============================================================
-- FACULTY-SUBJECT DEMO ASSIGNMENT
-- ============================================================

INSERT INTO faculty_subjects
(faculty_id, subject_id)
VALUES

-- HOD
(1, 1),

-- DBMS
(2, 1),
(7, 1),
(12, 1),

-- Advanced DSA
(3, 4),
(8, 4),
(13, 4),

-- KVD
(4, 3),
(9, 3),
(14, 3),

-- OS
(5, 2),
(10, 2),

-- Maths
(6, 5),
(11, 5),

-- English
(7, 6),
(12, 6),

-- Quants
(8, 7),
(13, 7),

-- Advanced DSA Lab
(3, 8),
(8, 8),
(13, 8),

-- KVD Lab
(4, 9),
(9, 9),
(14, 9),

-- OS Lab
(5, 10),
(10, 10),

-- DBMS Lab
(2, 11),
(7, 11),
(12, 11),

-- EE Project
(1, 12),
(6, 12),
(11, 12);


-- ============================================================
-- DEMO EE PROJECTS
-- ============================================================

INSERT INTO ee_projects
(
    project_title,
    section_id,
    faculty_id,
    project_date,
    description
)
VALUES

('Smart Campus Project - CSE A',
 1,
 6,
 '2026-09-12',
 'Saturday EE Project'),

('Smart Campus Project - CSE B',
 2,
 11,
 '2026-09-12',
 'Saturday EE Project'),

('Smart Campus Project - CSE C',
 3,
 1,
 '2026-09-12',
 'Saturday EE Project'),

('Smart Campus Project - CSE D',
 4,
 6,
 '2026-09-12',
 'Saturday EE Project'),

('Smart Campus Project - CSE E',
 5,
 11,
 '2026-09-12',
 'Saturday EE Project');



 
CREATE TABLE IF NOT EXISTS faculty_availability (
    availability_id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT NOT NULL,
    status VARCHAR(30) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);




ALTER TABLE notifications
ADD COLUMN response VARCHAR(30) NULL
AFTER notification_type;
