-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: smart_faculty
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `academic_classes`
--

DROP TABLE IF EXISTS `academic_classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `academic_classes` (
  `class_id` int NOT NULL AUTO_INCREMENT,
  `department_id` int NOT NULL,
  `year_number` int NOT NULL DEFAULT '2',
  `semester_number` int NOT NULL DEFAULT '3',
  `academic_year` varchar(20) NOT NULL,
  PRIMARY KEY (`class_id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `academic_classes_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_classes`
--

LOCK TABLES `academic_classes` WRITE;
/*!40000 ALTER TABLE `academic_classes` DISABLE KEYS */;
INSERT INTO `academic_classes` VALUES (1,1,2,3,'2026-2027');
/*!40000 ALTER TABLE `academic_classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `announcements` (
  `announcement_id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`announcement_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcements`
--

LOCK TABLES `announcements` WRITE;
/*!40000 ALTER TABLE `announcements` DISABLE KEYS */;
/*!40000 ALTER TABLE `announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance` (
  `attendance_id` int NOT NULL AUTO_INCREMENT,
  `timetable_id` int NOT NULL,
  `student_id` int NOT NULL,
  `faculty_id` int NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('PRESENT','ABSENT') NOT NULL DEFAULT 'ABSENT',
  `marked_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`attendance_id`),
  UNIQUE KEY `timetable_id` (`timetable_id`,`student_id`,`attendance_date`),
  KEY `student_id` (`student_id`),
  KEY `faculty_id` (`faculty_id`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`timetable_id`) REFERENCES `timetable` (`timetable_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance`
--

LOCK TABLES `attendance` WRITE;
/*!40000 ALTER TABLE `attendance` DISABLE KEYS */;
/*!40000 ALTER TABLE `attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `class_handover`
--

DROP TABLE IF EXISTS `class_handover`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `class_handover` (
  `handover_id` int NOT NULL AUTO_INCREMENT,
  `timetable_id` int NOT NULL,
  `original_faculty_id` int NOT NULL,
  `reason` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('PENDING','ASSIGNED','COMPLETED','CANCELLED','HOD_REQUIRED') DEFAULT 'PENDING',
  PRIMARY KEY (`handover_id`),
  KEY `timetable_id` (`timetable_id`),
  KEY `original_faculty_id` (`original_faculty_id`),
  CONSTRAINT `class_handover_ibfk_1` FOREIGN KEY (`timetable_id`) REFERENCES `timetable` (`timetable_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `class_handover_ibfk_2` FOREIGN KEY (`original_faculty_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `class_handover`
--

LOCK TABLES `class_handover` WRITE;
/*!40000 ALTER TABLE `class_handover` DISABLE KEYS */;
/*!40000 ALTER TABLE `class_handover` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classrooms`
--

DROP TABLE IF EXISTS `classrooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classrooms` (
  `classroom_id` int NOT NULL AUTO_INCREMENT,
  `room_number` varchar(30) NOT NULL,
  `floor` varchar(50) DEFAULT NULL,
  `room_type` enum('CLASSROOM','LAB','PROJECT') NOT NULL DEFAULT 'CLASSROOM',
  `capacity` int DEFAULT '60',
  `status` enum('AVAILABLE','UNAVAILABLE') DEFAULT 'AVAILABLE',
  PRIMARY KEY (`classroom_id`),
  UNIQUE KEY `room_number` (`room_number`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classrooms`
--

LOCK TABLES `classrooms` WRITE;
/*!40000 ALTER TABLE `classrooms` DISABLE KEYS */;
INSERT INTO `classrooms` VALUES (1,'36','1st Floor','CLASSROOM',60,'AVAILABLE'),(2,'37','1st Floor','CLASSROOM',60,'AVAILABLE'),(3,'38','2nd Floor','CLASSROOM',60,'AVAILABLE'),(4,'39','2nd Floor','CLASSROOM',60,'AVAILABLE'),(5,'40','3rd Floor','CLASSROOM',60,'AVAILABLE'),(6,'LAB-1','Near CSE Department','LAB',36,'AVAILABLE'),(7,'LAB-2','Above CSE Department - 2nd Floor','LAB',36,'AVAILABLE'),(8,'LAB-3','Above CSE Department - 2nd Floor','LAB',36,'AVAILABLE');
/*!40000 ALTER TABLE `classrooms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `departments` (
  `department_id` int NOT NULL AUTO_INCREMENT,
  `department_code` varchar(20) NOT NULL,
  `department_name` varchar(100) NOT NULL,
  PRIMARY KEY (`department_id`),
  UNIQUE KEY `department_code` (`department_code`),
  UNIQUE KEY `department_name` (`department_name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES (1,'CSE','Computer Science and Engineering');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ee_projects`
--

DROP TABLE IF EXISTS `ee_projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ee_projects` (
  `project_id` int NOT NULL AUTO_INCREMENT,
  `project_title` varchar(200) NOT NULL,
  `section_id` int NOT NULL,
  `faculty_id` int DEFAULT NULL,
  `project_date` date DEFAULT NULL,
  `description` text,
  `status` enum('PLANNED','ONGOING','COMPLETED') DEFAULT 'PLANNED',
  PRIMARY KEY (`project_id`),
  KEY `section_id` (`section_id`),
  KEY `faculty_id` (`faculty_id`),
  CONSTRAINT `ee_projects_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`) ON DELETE RESTRICT,
  CONSTRAINT `ee_projects_ibfk_2` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ee_projects`
--

LOCK TABLES `ee_projects` WRITE;
/*!40000 ALTER TABLE `ee_projects` DISABLE KEYS */;
INSERT INTO `ee_projects` VALUES (1,'Smart Campus Project - CSE A',1,6,'2026-09-12','Saturday EE Project','PLANNED'),(2,'Smart Campus Project - CSE B',2,11,'2026-09-12','Saturday EE Project','PLANNED'),(3,'Smart Campus Project - CSE C',3,1,'2026-09-12','Saturday EE Project','PLANNED'),(4,'Smart Campus Project - CSE D',4,6,'2026-09-12','Saturday EE Project','PLANNED'),(5,'Smart Campus Project - CSE E',5,11,'2026-09-12','Saturday EE Project','PLANNED');
/*!40000 ALTER TABLE `ee_projects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `faculty`
--

DROP TABLE IF EXISTS `faculty`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `faculty` (
  `faculty_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `faculty_code` varchar(20) NOT NULL,
  `faculty_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department_id` int NOT NULL,
  `designation` varchar(50) DEFAULT 'Assistant Professor',
  `is_hod` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`faculty_id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `faculty_code` (`faculty_code`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `faculty_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `faculty_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faculty`
--

LOCK TABLES `faculty` WRITE;
/*!40000 ALTER TABLE `faculty` DISABLE KEYS */;
INSERT INTO `faculty` VALUES (1,1,'CSE001','Dr. Arun Kumar','arun@college.edu','9000000001',1,'Professor',1),(2,2,'CSE002','Prof. Priya Sharma','priya@college.edu','9000000002',1,'Assistant Professor',0),(3,3,'CSE003','Prof. Karthik Raj','karthik@college.edu','9000000003',1,'Assistant Professor',0),(4,4,'CSE004','Prof. Divya S','divya@college.edu','9000000004',1,'Assistant Professor',0),(5,5,'CSE005','Prof. Naveen Kumar','naveen@college.edu','9000000005',1,'Assistant Professor',0),(6,6,'CSE006','Prof. Meena R','meena@college.edu','9000000006',1,'Assistant Professor',0),(7,7,'CSE007','Prof. Rahul P','rahul@college.edu','9000000007',1,'Assistant Professor',0),(8,8,'CSE008','Prof. Sneha K','sneha@college.edu','9000000008',1,'Assistant Professor',0),(9,9,'CSE009','Prof. Vignesh M','vignesh@college.edu','9000000009',1,'Assistant Professor',0),(10,10,'CSE010','Prof. Anitha S','anitha@college.edu','9000000010',1,'Assistant Professor',0),(11,11,'CSE011','Prof. Sanjay R','sanjay@college.edu','9000000011',1,'Assistant Professor',0),(12,12,'CSE012','Prof. Harini V','harini@college.edu','9000000012',1,'Assistant Professor',0),(13,13,'CSE013','Prof. Ajay Kumar','ajay@college.edu','9000000013',1,'Assistant Professor',0),(14,14,'CSE014','Prof. Keerthana P','keerthana@college.edu','9000000014',1,'Assistant Professor',0),(15,15,'CSE015','Prof. Manoj S','manoj@college.edu','9000000015',1,'Assistant Professor',0);
/*!40000 ALTER TABLE `faculty` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `faculty_availability`
--

DROP TABLE IF EXISTS `faculty_availability`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `faculty_availability` (
  `availability_id` int NOT NULL AUTO_INCREMENT,
  `faculty_id` int NOT NULL,
  `status` varchar(30) NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`availability_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faculty_availability`
--

LOCK TABLES `faculty_availability` WRITE;
/*!40000 ALTER TABLE `faculty_availability` DISABLE KEYS */;
/*!40000 ALTER TABLE `faculty_availability` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `faculty_subjects`
--

DROP TABLE IF EXISTS `faculty_subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `faculty_subjects` (
  `faculty_subject_id` int NOT NULL AUTO_INCREMENT,
  `faculty_id` int NOT NULL,
  `subject_id` int NOT NULL,
  PRIMARY KEY (`faculty_subject_id`),
  UNIQUE KEY `faculty_id` (`faculty_id`,`subject_id`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `faculty_subjects_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `faculty_subjects_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faculty_subjects`
--

LOCK TABLES `faculty_subjects` WRITE;
/*!40000 ALTER TABLE `faculty_subjects` DISABLE KEYS */;
INSERT INTO `faculty_subjects` VALUES (1,1,1),(30,1,12),(2,2,1),(27,2,11),(5,3,4),(19,3,8),(8,4,3),(22,4,9),(11,5,2),(25,5,10),(13,6,5),(31,6,12),(3,7,1),(15,7,6),(28,7,11),(6,8,4),(17,8,7),(20,8,8),(9,9,3),(23,9,9),(12,10,2),(26,10,10),(14,11,5),(32,11,12),(4,12,1),(16,12,6),(29,12,11),(7,13,4),(18,13,7),(21,13,8),(10,14,3),(24,14,9);
/*!40000 ALTER TABLE `faculty_subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `handover_requests`
--

DROP TABLE IF EXISTS `handover_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `handover_requests` (
  `request_id` int NOT NULL AUTO_INCREMENT,
  `handover_id` int NOT NULL,
  `alternate_faculty_id` int NOT NULL,
  `status` enum('PENDING','ACCEPTED','REJECTED','EXPIRED') DEFAULT 'PENDING',
  `requested_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `responded_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `handover_id` (`handover_id`),
  KEY `alternate_faculty_id` (`alternate_faculty_id`),
  CONSTRAINT `handover_requests_ibfk_1` FOREIGN KEY (`handover_id`) REFERENCES `class_handover` (`handover_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `handover_requests_ibfk_2` FOREIGN KEY (`alternate_faculty_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `handover_requests`
--

LOCK TABLES `handover_requests` WRITE;
/*!40000 ALTER TABLE `handover_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `handover_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `notification_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `notification_type` varchar(50) DEFAULT NULL,
  `response` varchar(30) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `periods`
--

DROP TABLE IF EXISTS `periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `periods` (
  `period_id` int NOT NULL AUTO_INCREMENT,
  `period_number` int NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  PRIMARY KEY (`period_id`),
  UNIQUE KEY `period_number` (`period_number`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `periods`
--

LOCK TABLES `periods` WRITE;
/*!40000 ALTER TABLE `periods` DISABLE KEYS */;
INSERT INTO `periods` VALUES (1,1,'08:30:00','09:15:00'),(2,2,'09:15:00','10:00:00'),(3,3,'10:00:00','10:45:00'),(4,4,'11:00:00','11:45:00'),(5,5,'11:45:00','12:30:00'),(6,6,'12:30:00','13:15:00'),(7,7,'14:00:00','14:45:00'),(8,8,'14:45:00','15:30:00'),(9,9,'15:45:00','16:30:00');
/*!40000 ALTER TABLE `periods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_groups`
--

DROP TABLE IF EXISTS `project_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_groups` (
  `project_group_id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `group_name` varchar(100) NOT NULL,
  PRIMARY KEY (`project_group_id`),
  KEY `project_id` (`project_id`),
  CONSTRAINT `project_groups_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `ee_projects` (`project_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_groups`
--

LOCK TABLES `project_groups` WRITE;
/*!40000 ALTER TABLE `project_groups` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sections`
--

DROP TABLE IF EXISTS `sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sections` (
  `section_id` int NOT NULL AUTO_INCREMENT,
  `class_id` int NOT NULL,
  `section_name` varchar(20) NOT NULL,
  PRIMARY KEY (`section_id`),
  UNIQUE KEY `class_id` (`class_id`,`section_name`),
  CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `academic_classes` (`class_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sections`
--

LOCK TABLES `sections` WRITE;
/*!40000 ALTER TABLE `sections` DISABLE KEYS */;
INSERT INTO `sections` VALUES (1,1,'CSE-A'),(2,1,'CSE-B'),(3,1,'CSE-C'),(4,1,'CSE-D'),(5,1,'CSE-E');
/*!40000 ALTER TABLE `sections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `students` (
  `student_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `register_number` varchar(30) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `section_id` int NOT NULL,
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `register_number` (`register_number`),
  KEY `section_id` (`section_id`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `students_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (1,16,'714025104022','Student 01','student01@example.com',NULL,1),(4,17,'STU2026002','Student 02','student02@example.com',NULL,2),(5,18,'STU2026003','Student 03','student03@example.com',NULL,3),(6,19,'STU2026004','Student 04','student04@example.com',NULL,4),(7,20,'STU2026005','Student 05','student05@example.com',NULL,5),(8,21,'7376241CS001','Arun Kumar','arun.kumar@example.com','9876501001',1),(9,22,'7376241CS002','Priya S','priya.s@example.com','9876501002',1),(10,23,'7376241CS003','Karthik R','karthik.r@example.com','9876501003',1),(11,24,'7376241CS004','Divya M','divya.m@example.com','9876501004',1),(12,25,'7376241CS005','Vignesh P','vignesh.p@example.com','9876501005',1),(13,26,'7376241CS006','Harini K','harini.k@example.com','9876501006',1),(14,27,'7376241CS007','Santhosh V','santhosh.v@example.com','9876501007',1),(15,28,'7376241CS008','Nithya R','nithya.r@example.com','9876501008',1),(16,29,'7376241CS009','Praveen S','praveen.s@example.com','9876501009',1),(17,30,'7376241CS010','Monisha T','monisha.t@example.com','9876501010',1),(18,31,'7376241CS051','Aadhavan R','aadhavan.r@example.com','9876501051',5),(19,32,'7376241CS052','Abinaya S','abinaya.s@example.com','9876501052',5),(20,33,'7376241CS053','Ajay Kumar','ajay.kumar@example.com','9876501053',5),(21,34,'7376241CS054','Akshaya M','akshaya.m@example.com','9876501054',5),(22,35,'7376241CS055','Bharath V','bharath.v@example.com','9876501055',5),(23,36,'7376241CS056','Deepika R','deepika.r@example.com','9876501056',5),(24,37,'7376241CS057','Gokul P','gokul.p@example.com','9876501057',5),(25,38,'7376241CS058','Janani K','janani.k@example.com','9876501058',5),(26,39,'7376241CS059','Manoj S','manoj.s@example.com','9876501059',5),(27,40,'7376241CS060','Sanjana T','sanjana.t@example.com','9876501060',5);
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subjects`
--

DROP TABLE IF EXISTS `subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subjects` (
  `subject_id` int NOT NULL AUTO_INCREMENT,
  `subject_code` varchar(30) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `subject_type` enum('THEORY','LAB','PROJECT') NOT NULL,
  `weekly_periods` int NOT NULL DEFAULT '0',
  `continuous_periods` int NOT NULL DEFAULT '1',
  `department_id` int DEFAULT NULL,
  PRIMARY KEY (`subject_id`),
  UNIQUE KEY `subject_code` (`subject_code`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subjects`
--

LOCK TABLES `subjects` WRITE;
/*!40000 ALTER TABLE `subjects` DISABLE KEYS */;
INSERT INTO `subjects` VALUES (1,'DBMS','Database Management Systems','THEORY',6,1,1),(2,'ADSA','Advanced Data Structures and Algorithms','THEORY',5,1,1),(3,'KVD','Knowledge AI Vector Database','THEORY',5,1,1),(4,'OS','Operating Systems','THEORY',5,1,1),(5,'MATHS','Mathematics','THEORY',6,1,1),(6,'ENG','English','THEORY',2,2,1),(7,'QUANTS','Quants','THEORY',1,1,1),(8,'ADSA-LAB','Advanced DSA Lab','LAB',3,3,1),(9,'KVD-LAB','KVD Lab','LAB',3,3,1),(10,'OS-LAB','OS Lab','LAB',3,3,1),(11,'DBMS-LAB','DBMS Lab','LAB',3,3,1),(12,'EE-PROJECT','EE Project','PROJECT',0,1,1);
/*!40000 ALTER TABLE `subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `timetable`
--

DROP TABLE IF EXISTS `timetable`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `timetable` (
  `timetable_id` int NOT NULL AUTO_INCREMENT,
  `day` enum('MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY') NOT NULL,
  `period_id` int NOT NULL,
  `section_id` int NOT NULL,
  `subject_id` int NOT NULL,
  `faculty_id` int NOT NULL,
  `classroom_id` int NOT NULL,
  `status` enum('DRAFT','PUBLISHED') DEFAULT 'DRAFT',
  PRIMARY KEY (`timetable_id`),
  UNIQUE KEY `section_id` (`section_id`,`day`,`period_id`),
  UNIQUE KEY `faculty_id` (`faculty_id`,`day`,`period_id`),
  UNIQUE KEY `classroom_id` (`classroom_id`,`day`,`period_id`),
  KEY `period_id` (`period_id`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `timetable_ibfk_1` FOREIGN KEY (`period_id`) REFERENCES `periods` (`period_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `timetable_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `timetable_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `timetable_ibfk_4` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `timetable_ibfk_5` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`classroom_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=353 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `timetable`
--

LOCK TABLES `timetable` WRITE;
/*!40000 ALTER TABLE `timetable` DISABLE KEYS */;
INSERT INTO `timetable` VALUES (122,'MONDAY',1,1,1,1,1,'DRAFT'),(123,'MONDAY',2,1,2,10,1,'DRAFT'),(124,'MONDAY',3,1,3,14,1,'DRAFT'),(125,'MONDAY',4,1,4,3,1,'DRAFT'),(126,'MONDAY',5,1,5,6,1,'DRAFT'),(127,'MONDAY',6,1,6,12,1,'DRAFT'),(128,'MONDAY',7,1,7,8,1,'DRAFT'),(129,'MONDAY',8,1,11,7,6,'DRAFT'),(130,'MONDAY',9,1,8,13,7,'DRAFT'),(131,'TUESDAY',1,1,2,10,1,'DRAFT'),(132,'TUESDAY',2,1,3,14,1,'DRAFT'),(133,'TUESDAY',3,1,4,3,1,'DRAFT'),(134,'TUESDAY',4,1,5,6,1,'DRAFT'),(135,'TUESDAY',5,1,6,12,1,'DRAFT'),(136,'TUESDAY',6,1,7,8,1,'DRAFT'),(137,'TUESDAY',7,1,11,7,6,'DRAFT'),(138,'TUESDAY',8,1,8,13,7,'DRAFT'),(139,'TUESDAY',9,1,1,2,1,'DRAFT'),(140,'WEDNESDAY',1,1,3,14,1,'DRAFT'),(141,'WEDNESDAY',2,1,4,3,1,'DRAFT'),(142,'WEDNESDAY',3,1,5,6,1,'DRAFT'),(143,'WEDNESDAY',4,1,6,12,1,'DRAFT'),(144,'WEDNESDAY',5,1,7,8,1,'DRAFT'),(145,'WEDNESDAY',6,1,11,7,6,'DRAFT'),(146,'WEDNESDAY',7,1,8,13,7,'DRAFT'),(147,'WEDNESDAY',8,1,1,2,1,'DRAFT'),(148,'WEDNESDAY',9,1,2,5,1,'DRAFT'),(149,'THURSDAY',1,1,4,3,1,'DRAFT'),(150,'THURSDAY',2,1,5,6,1,'DRAFT'),(151,'THURSDAY',3,1,6,12,1,'DRAFT'),(152,'THURSDAY',4,1,7,8,1,'DRAFT'),(153,'THURSDAY',5,1,11,7,6,'DRAFT'),(154,'THURSDAY',6,1,8,13,7,'DRAFT'),(155,'THURSDAY',7,1,1,2,1,'DRAFT'),(156,'THURSDAY',8,1,2,5,1,'DRAFT'),(157,'THURSDAY',9,1,3,14,1,'DRAFT'),(158,'FRIDAY',1,1,5,6,1,'DRAFT'),(159,'FRIDAY',2,1,6,12,1,'DRAFT'),(160,'FRIDAY',3,1,7,8,1,'DRAFT'),(161,'FRIDAY',4,1,11,7,6,'DRAFT'),(162,'FRIDAY',5,1,8,13,7,'DRAFT'),(163,'FRIDAY',6,1,1,2,1,'DRAFT'),(164,'FRIDAY',7,1,2,5,1,'DRAFT'),(165,'FRIDAY',8,1,3,14,1,'DRAFT'),(166,'FRIDAY',9,1,4,3,1,'DRAFT'),(167,'SATURDAY',1,1,12,11,1,'DRAFT'),(168,'SATURDAY',2,1,12,1,1,'DRAFT'),(169,'SATURDAY',3,1,12,6,1,'DRAFT'),(170,'SATURDAY',4,1,12,11,1,'DRAFT'),(171,'SATURDAY',5,1,12,1,1,'DRAFT'),(172,'SATURDAY',6,1,12,6,1,'DRAFT'),(173,'SATURDAY',7,1,12,11,1,'DRAFT'),(174,'SATURDAY',8,1,12,1,1,'DRAFT'),(175,'SATURDAY',9,1,12,6,1,'DRAFT'),(176,'MONDAY',1,2,2,10,2,'DRAFT'),(177,'MONDAY',2,2,3,14,2,'DRAFT'),(178,'MONDAY',3,2,4,3,2,'DRAFT'),(179,'MONDAY',4,2,5,6,2,'DRAFT'),(180,'MONDAY',5,2,1,2,2,'DRAFT'),(181,'MONDAY',6,2,7,8,2,'DRAFT'),(182,'MONDAY',7,2,6,12,2,'DRAFT'),(183,'MONDAY',8,2,9,4,8,'DRAFT'),(184,'MONDAY',9,2,10,10,6,'DRAFT'),(185,'TUESDAY',1,2,3,14,2,'DRAFT'),(186,'TUESDAY',2,2,4,3,2,'DRAFT'),(187,'TUESDAY',3,2,5,6,2,'DRAFT'),(188,'TUESDAY',4,2,1,2,2,'DRAFT'),(189,'TUESDAY',5,2,7,8,2,'DRAFT'),(190,'TUESDAY',6,2,6,12,2,'DRAFT'),(191,'TUESDAY',7,2,9,4,8,'DRAFT'),(192,'TUESDAY',8,2,10,10,6,'DRAFT'),(193,'TUESDAY',9,2,2,5,2,'DRAFT'),(194,'WEDNESDAY',1,2,4,3,2,'DRAFT'),(195,'WEDNESDAY',2,2,5,6,2,'DRAFT'),(196,'WEDNESDAY',3,2,1,2,2,'DRAFT'),(197,'WEDNESDAY',4,2,7,8,2,'DRAFT'),(198,'WEDNESDAY',5,2,6,12,2,'DRAFT'),(199,'WEDNESDAY',6,2,9,4,8,'DRAFT'),(200,'WEDNESDAY',7,2,10,10,6,'DRAFT'),(201,'WEDNESDAY',8,2,2,5,2,'DRAFT'),(202,'WEDNESDAY',9,2,3,14,2,'DRAFT'),(203,'THURSDAY',1,2,5,6,2,'DRAFT'),(204,'THURSDAY',2,2,1,2,2,'DRAFT'),(205,'THURSDAY',3,2,7,8,2,'DRAFT'),(206,'THURSDAY',4,2,6,12,2,'DRAFT'),(207,'THURSDAY',5,2,9,4,8,'DRAFT'),(208,'THURSDAY',6,2,10,10,6,'DRAFT'),(209,'THURSDAY',7,2,2,5,2,'DRAFT'),(210,'THURSDAY',8,2,3,14,2,'DRAFT'),(211,'THURSDAY',9,2,4,3,2,'DRAFT'),(212,'FRIDAY',1,2,1,2,2,'DRAFT'),(213,'FRIDAY',2,2,7,8,2,'DRAFT'),(214,'FRIDAY',3,2,6,12,2,'DRAFT'),(215,'FRIDAY',4,2,9,4,8,'DRAFT'),(216,'FRIDAY',5,2,10,10,6,'DRAFT'),(217,'FRIDAY',6,2,2,5,2,'DRAFT'),(218,'FRIDAY',7,2,3,14,2,'DRAFT'),(219,'FRIDAY',8,2,4,3,2,'DRAFT'),(220,'FRIDAY',9,2,5,11,2,'DRAFT'),(221,'SATURDAY',1,2,12,1,2,'DRAFT'),(222,'SATURDAY',2,2,12,11,2,'DRAFT'),(223,'SATURDAY',3,2,12,1,2,'DRAFT'),(224,'SATURDAY',4,2,12,6,2,'DRAFT'),(225,'SATURDAY',5,2,12,6,2,'DRAFT'),(226,'SATURDAY',6,2,12,11,2,'DRAFT'),(227,'SATURDAY',7,2,12,1,2,'DRAFT'),(228,'SATURDAY',8,2,12,11,2,'DRAFT'),(229,'SATURDAY',9,2,12,1,2,'DRAFT'),(230,'MONDAY',1,3,3,14,3,'DRAFT'),(231,'MONDAY',2,3,4,3,3,'DRAFT'),(232,'MONDAY',3,3,5,6,3,'DRAFT'),(233,'MONDAY',4,3,1,2,3,'DRAFT'),(234,'MONDAY',5,3,2,5,3,'DRAFT'),(235,'MONDAY',6,3,6,7,3,'DRAFT'),(236,'MONDAY',7,3,7,13,3,'DRAFT'),(237,'MONDAY',8,3,11,12,7,'DRAFT'),(238,'MONDAY',9,3,8,3,8,'DRAFT'),(239,'TUESDAY',1,3,4,3,3,'DRAFT'),(240,'TUESDAY',2,3,5,6,3,'DRAFT'),(241,'TUESDAY',3,3,1,2,3,'DRAFT'),(242,'TUESDAY',4,3,2,5,3,'DRAFT'),(243,'TUESDAY',5,3,6,7,3,'DRAFT'),(244,'TUESDAY',6,3,7,13,3,'DRAFT'),(245,'TUESDAY',7,3,11,12,7,'DRAFT'),(246,'TUESDAY',8,3,8,3,8,'DRAFT'),(247,'TUESDAY',9,3,3,14,3,'DRAFT'),(248,'WEDNESDAY',1,3,5,6,3,'DRAFT'),(249,'WEDNESDAY',2,3,1,2,3,'DRAFT'),(250,'WEDNESDAY',3,3,2,5,3,'DRAFT'),(251,'WEDNESDAY',4,3,6,7,3,'DRAFT'),(252,'WEDNESDAY',5,3,7,13,3,'DRAFT'),(253,'WEDNESDAY',6,3,11,12,7,'DRAFT'),(254,'WEDNESDAY',7,3,8,3,8,'DRAFT'),(255,'WEDNESDAY',8,3,3,14,3,'DRAFT'),(256,'WEDNESDAY',9,3,4,3,3,'DRAFT'),(257,'THURSDAY',1,3,1,2,3,'DRAFT'),(258,'THURSDAY',2,3,2,5,3,'DRAFT'),(259,'THURSDAY',3,3,6,7,3,'DRAFT'),(260,'THURSDAY',4,3,7,13,3,'DRAFT'),(261,'THURSDAY',5,3,11,12,7,'DRAFT'),(262,'THURSDAY',6,3,8,3,8,'DRAFT'),(263,'THURSDAY',7,3,3,14,3,'DRAFT'),(264,'THURSDAY',8,3,4,3,3,'DRAFT'),(265,'THURSDAY',9,3,5,11,3,'DRAFT'),(266,'FRIDAY',1,3,2,5,3,'DRAFT'),(267,'FRIDAY',2,3,6,7,3,'DRAFT'),(268,'FRIDAY',3,3,7,13,3,'DRAFT'),(269,'FRIDAY',4,3,11,12,7,'DRAFT'),(270,'FRIDAY',5,3,8,3,8,'DRAFT'),(271,'FRIDAY',6,3,3,14,3,'DRAFT'),(272,'FRIDAY',7,3,4,3,3,'DRAFT'),(273,'FRIDAY',8,3,5,11,3,'DRAFT'),(274,'FRIDAY',9,3,1,7,3,'DRAFT'),(275,'SATURDAY',1,3,12,6,3,'DRAFT'),(276,'SATURDAY',2,3,12,6,3,'DRAFT'),(277,'SATURDAY',3,3,12,11,3,'DRAFT'),(278,'SATURDAY',4,3,12,1,3,'DRAFT'),(279,'SATURDAY',5,3,12,11,3,'DRAFT'),(280,'SATURDAY',6,3,12,1,3,'DRAFT'),(281,'SATURDAY',7,3,12,6,3,'DRAFT'),(282,'SATURDAY',8,3,12,6,3,'DRAFT'),(283,'SATURDAY',9,3,12,11,3,'DRAFT'),(284,'MONDAY',1,4,4,3,4,'DRAFT'),(285,'MONDAY',2,4,5,6,4,'DRAFT'),(286,'MONDAY',3,4,1,2,4,'DRAFT'),(287,'MONDAY',4,4,2,5,4,'DRAFT'),(288,'MONDAY',5,4,3,9,4,'DRAFT'),(289,'MONDAY',6,4,7,13,4,'DRAFT'),(290,'MONDAY',7,4,6,7,4,'DRAFT'),(291,'MONDAY',8,4,9,9,4,'DRAFT'),(292,'MONDAY',9,4,10,5,4,'DRAFT'),(293,'TUESDAY',1,4,5,6,4,'DRAFT'),(294,'TUESDAY',2,4,1,2,4,'DRAFT'),(295,'TUESDAY',3,4,2,5,4,'DRAFT'),(296,'TUESDAY',4,4,3,9,4,'DRAFT'),(297,'TUESDAY',5,4,7,13,4,'DRAFT'),(298,'TUESDAY',6,4,6,7,4,'DRAFT'),(299,'TUESDAY',7,4,9,9,4,'DRAFT'),(300,'TUESDAY',8,4,10,5,4,'DRAFT'),(301,'TUESDAY',9,4,4,3,4,'DRAFT'),(302,'WEDNESDAY',1,4,1,2,4,'DRAFT'),(303,'WEDNESDAY',2,4,2,5,4,'DRAFT'),(304,'WEDNESDAY',3,4,3,9,4,'DRAFT'),(305,'WEDNESDAY',4,4,7,13,4,'DRAFT'),(306,'WEDNESDAY',5,4,6,7,4,'DRAFT'),(307,'WEDNESDAY',6,4,9,9,4,'DRAFT'),(308,'WEDNESDAY',7,4,10,5,4,'DRAFT'),(309,'WEDNESDAY',8,4,4,3,4,'DRAFT'),(310,'WEDNESDAY',9,4,5,11,4,'DRAFT'),(311,'THURSDAY',1,4,2,5,4,'DRAFT'),(312,'THURSDAY',2,4,3,9,4,'DRAFT'),(313,'THURSDAY',3,4,7,13,4,'DRAFT'),(314,'THURSDAY',4,4,6,7,4,'DRAFT'),(315,'THURSDAY',5,4,9,9,4,'DRAFT'),(316,'THURSDAY',6,4,10,5,4,'DRAFT'),(317,'THURSDAY',7,4,4,3,4,'DRAFT'),(318,'THURSDAY',8,4,5,11,4,'DRAFT'),(319,'THURSDAY',9,4,1,7,4,'DRAFT'),(320,'FRIDAY',1,4,3,9,4,'DRAFT'),(321,'FRIDAY',2,4,7,13,4,'DRAFT'),(322,'FRIDAY',3,4,6,7,4,'DRAFT'),(323,'FRIDAY',4,4,9,9,4,'DRAFT'),(324,'FRIDAY',5,4,10,5,4,'DRAFT'),(325,'FRIDAY',6,4,4,3,4,'DRAFT'),(326,'FRIDAY',7,4,5,11,4,'DRAFT'),(327,'FRIDAY',8,4,1,7,4,'DRAFT'),(328,'FRIDAY',9,4,2,10,4,'DRAFT'),(329,'MONDAY',1,5,5,6,5,'DRAFT'),(330,'MONDAY',2,5,1,2,5,'DRAFT'),(331,'MONDAY',3,5,2,5,5,'DRAFT'),(332,'MONDAY',4,5,3,9,5,'DRAFT'),(333,'MONDAY',5,5,4,13,5,'DRAFT'),(334,'TUESDAY',1,5,1,2,5,'DRAFT'),(335,'TUESDAY',2,5,2,5,5,'DRAFT'),(336,'TUESDAY',3,5,3,9,5,'DRAFT'),(337,'TUESDAY',4,5,4,13,5,'DRAFT'),(338,'TUESDAY',9,5,5,11,5,'DRAFT'),(339,'WEDNESDAY',1,5,2,5,5,'DRAFT'),(340,'WEDNESDAY',2,5,3,9,5,'DRAFT'),(341,'WEDNESDAY',3,5,4,13,5,'DRAFT'),(342,'WEDNESDAY',8,5,5,11,5,'DRAFT'),(343,'WEDNESDAY',9,5,1,7,5,'DRAFT'),(344,'THURSDAY',1,5,3,9,5,'DRAFT'),(345,'THURSDAY',2,5,4,13,5,'DRAFT'),(346,'THURSDAY',8,5,1,7,5,'DRAFT'),(347,'THURSDAY',9,5,2,10,5,'DRAFT'),(348,'FRIDAY',1,5,4,13,5,'DRAFT'),(349,'FRIDAY',6,5,5,11,5,'DRAFT'),(350,'FRIDAY',7,5,1,7,5,'DRAFT'),(351,'FRIDAY',8,5,2,10,5,'DRAFT'),(352,'FRIDAY',9,5,3,9,5,'DRAFT');
/*!40000 ALTER TABLE `timetable` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('HOD','FACULTY','STUDENT') NOT NULL,
  `status` enum('ACTIVE','INACTIVE') DEFAULT 'ACTIVE',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'hod.cse','$2y$10$Sgl4zhxNJaHeHx3YNbYGPuOfrUcfjbIUkaKYaFVaQlHI3QAebKdoO','HOD','ACTIVE','2026-10-22 06:54:12'),(2,'faculty01','$2y$10$P72mcpTAOp1Hm5uNhVZvduS4m7DpOtXUyWQ/iIwLB3x2HcD442Jvy','FACULTY','ACTIVE','2026-10-22 06:54:12'),(3,'faculty02','$2y$10$t9DaZr6eBk7Kr5AAV84aLOwrIHYLRuAaD/l5/X8rLMOAcNCvhIY.i','FACULTY','ACTIVE','2026-10-22 06:54:12'),(4,'faculty03','$2y$10$demo','FACULTY','ACTIVE','2026-10-22 06:54:12'),(5,'faculty04','$2y$10$demo','FACULTY','ACTIVE','2026-10-22 06:54:12'),(6,'faculty05','$2y$10$demo','FACULTY','ACTIVE','2026-10-22 06:54:12'),(7,'faculty06','$2y$10$demo','FACULTY','ACTIVE','2026-10-22 06:54:12'),(8,'faculty07','$2y$10$demo','FACULTY','ACTIVE','2026-10-22 06:54:12'),(9,'faculty08','$2y$10$demo','FACULTY','ACTIVE','2026-10-22 06:54:12'),(10,'faculty09','$2y$10$demo','FACULTY','ACTIVE','2026-10-22 06:54:12'),(11,'faculty10','$2y$10$demo','FACULTY','ACTIVE','2026-10-22 06:54:12'),(12,'faculty11','$2y$10$demo','FACULTY','ACTIVE','2026-10-22 06:54:12'),(13,'faculty12','$2y$10$demo','FACULTY','ACTIVE','2026-10-22 06:54:12'),(14,'faculty13','$2y$10$demo','FACULTY','ACTIVE','2026-10-22 06:54:12'),(15,'faculty14','$2y$10$demo','FACULTY','ACTIVE','2026-10-22 06:54:12'),(16,'student01','$2y$10$ABkLV1k4dnUvrfu6gSi8mO/FVIWi4r/qmEF9kQjrZRPeyWcFMemsa','STUDENT','ACTIVE','2026-10-22 06:58:14'),(17,'student02','$2y$10$Th5dknf5fk97xKKMMhTEuOFwrhYYAlXRP9TRkLtkuhrbOpex2JyGe','STUDENT','ACTIVE','2026-10-22 07:09:11'),(18,'student03','$2y$10$2HO5TcCZoJj39DRZxH0LzOCiMNbBTbXfzIwL50Yo.LeAPxWOyq7RG','STUDENT','ACTIVE','2026-10-22 07:09:11'),(19,'student04','$2y$10$0mc3ePzN5KicEs6/5myRZes9swuJgi.ajr4zj/HbcmQX3.CBGGx/K','STUDENT','ACTIVE','2026-10-22 07:09:11'),(20,'student05','$2y$10$G0doU0VrpEx6Fqvklgl5ZOWjPSRoaRTaOnHhhef0BNmzoDRT9Z0V6','STUDENT','ACTIVE','2026-10-22 07:09:11'),(21,'7376241CS001','$2y$10$6WBj83wHYaPbNIk/9Ct5pOWFdpGBBqtrKGQdJ2M.TaCu456qaTEOW','STUDENT','ACTIVE','2026-10-25 13:17:56'),(22,'7376241CS002','$2y$10$gnExQfl39fccHl7tE0Tlb.Re98nF5eZK0FdDhhBEDYSZLfUPm57QO','STUDENT','ACTIVE','2026-10-25 13:17:56'),(23,'7376241CS003','$2y$10$YR7sqnbwBhwZCvJ8S0LDC.UsWvfNLtdly3jcaGGGQCupuCf.dhkle','STUDENT','ACTIVE','2026-10-25 13:17:56'),(24,'7376241CS004','$2y$10$b.5mYxa04GCZa5euEztIg.EMfuLUhhduiSkyinDTE2Uuo0Cl2tbRK','STUDENT','ACTIVE','2026-10-25 13:17:56'),(25,'7376241CS005','$2y$10$NPJTyI9hdABXaHHQ0Wu6zeIta1959vOLlrhcAvyA1q3gK.RUFxKXO','STUDENT','ACTIVE','2026-10-25 13:17:57'),(26,'7376241CS006','$2y$10$i64tx0ezRcCY1jq2.CoDD.kc9GIdOHwDlOXyo8ZXI1yeHps9nrByi','STUDENT','ACTIVE','2026-10-25 13:17:57'),(27,'7376241CS007','$2y$10$r1ELjh2ljzR05H8Kei8FueqyYakY5E7GbUb./Pgqag8jfBOXoduvq','STUDENT','ACTIVE','2026-10-25 13:17:57'),(28,'7376241CS008','$2y$10$c0G2AJmf3czZXcqpr32YjuNTJm5bycz7macuNHqcipW7BSV29hSPq','STUDENT','ACTIVE','2026-10-25 13:17:57'),(29,'7376241CS009','$2y$10$nNNqLjfkTHN.DfdIEVnU/.afMqHnG19sjd5QaP5VU8yDCTeugtcn.','STUDENT','ACTIVE','2026-10-25 13:17:57'),(30,'7376241CS010','$2y$10$T8TUvy/j5neyU.obUpHSA.qygI/6Zw/Dvi8QKer2WQjnY0GCJ4tgy','STUDENT','ACTIVE','2026-10-25 13:17:57'),(31,'7376241CS051','$2y$10$F2eYSNMgEaF7DYEN0vfYieuYDBGu/YO/h93ho.t1e3r27/47az4o2','STUDENT','ACTIVE','2026-10-25 13:22:08'),(32,'7376241CS052','$2y$10$fNtp9gLuoL8qmqIBqukEbeLe8uD9ursUU5BESk/s3GBkFygWiRuJu','STUDENT','ACTIVE','2026-10-25 13:22:08'),(33,'7376241CS053','$2y$10$xkzn3n9pV/hB0fVsaxT4GOw24qf1Z/B.N95EotuMFFWDu55ciG6PW','STUDENT','ACTIVE','2026-10-25 13:22:08'),(34,'7376241CS054','$2y$10$X3ecpVvdK.1UsefdHPvfu.0n1gH6hrH9uvNy8KQDfBeTIlq1Fyr4y','STUDENT','ACTIVE','2026-10-25 13:22:08'),(35,'7376241CS055','$2y$10$Je6EwckgWpBGp.0pvYygv.rxSv12Z65emlecCSI5oOGQvTK2jisJe','STUDENT','ACTIVE','2026-10-25 13:22:08'),(36,'7376241CS056','$2y$10$mtXICoT3G.lFmJfr3w5ev.yeAIRi9hbx/yS/gex7ZAZ.2YSJ0UVAe','STUDENT','ACTIVE','2026-10-25 13:22:09'),(37,'7376241CS057','$2y$10$sZNyqTc0OUKWG11tYkZv5eY6oYEK/RHWw/AkSOlWihLEBWNNuaGv2','STUDENT','ACTIVE','2026-10-25 13:22:09'),(38,'7376241CS058','$2y$10$hpy58g4bW0jnTJOuTxnUlOnXqJCwPP1RUq0NVTycW3CRV650TVAla','STUDENT','ACTIVE','2026-10-25 13:22:09'),(39,'7376241CS059','$2y$10$Z0C1ZRON8GMxCmbvCpsphOXqf4Y4L0kO2BE5xc8PhLprBW837Vgp.','STUDENT','ACTIVE','2026-10-25 13:22:09'),(40,'7376241CS060','$2y$10$HyDiT4yw.s0btXWnIUfeI.I9OChrtl09HOGpj8dHiwIO3Ps940mqO','STUDENT','ACTIVE','2026-10-25 13:22:09');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-27 16:25:45
