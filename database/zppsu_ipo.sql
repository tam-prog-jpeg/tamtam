-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: zppsu_ipo
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `zppsu_ipo`
--

/*!40000 DROP DATABASE IF EXISTS `zppsu_ipo`*/;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `zppsu_ipo` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `zppsu_ipo`;

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcements` (
  `announcement_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `posted_by` int(10) unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `posted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`announcement_id`),
  KEY `fk_announce_user` (`posted_by`),
  CONSTRAINT `fk_announce_user` FOREIGN KEY (`posted_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcements`
--

LOCK TABLES `announcements` WRITE;
/*!40000 ALTER TABLE `announcements` DISABLE KEYS */;
INSERT INTO `announcements` VALUES (1,'Team Building','participate in team building \r\n\r\nSep-29-2026',1,1,'2026-09-25 06:45:00');
/*!40000 ALTER TABLE `announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `applicants`
--

DROP TABLE IF EXISTS `applicants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `applicants` (
  `applicant_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `applicant_type` enum('Student','Faculty','Staff','External') NOT NULL DEFAULT 'External',
  `college_id` int(10) unsigned DEFAULT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`applicant_id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `fk_applicants_college` (`college_id`),
  KEY `fk_applicants_department` (`department_id`),
  CONSTRAINT `fk_applicants_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`college_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_applicants_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_applicants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `applicants`
--

LOCK TABLES `applicants` WRITE;
/*!40000 ALTER TABLE `applicants` DISABLE KEYS */;
INSERT INTO `applicants` VALUES (4,8,'Student',1,1,'Don Toribio St','2026-07-15 06:20:20'),(5,10,'Faculty',1,1,'awdwad123','2026-11-02 15:17:56'),(6,11,'Student',1,1,'ZPPSU Main Campus, R.T. Lim Boulevard, Zamboanga City','2026-11-02 16:54:05');
/*!40000 ALTER TABLE `applicants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `application_status_history`
--

DROP TABLE IF EXISTS `application_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `application_status_history` (
  `history_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` int(10) unsigned NOT NULL,
  `status` varchar(30) NOT NULL,
  `remarks` text DEFAULT NULL,
  `changed_by` int(10) unsigned DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`history_id`),
  KEY `fk_hist_application` (`application_id`),
  KEY `fk_hist_user` (`changed_by`),
  CONSTRAINT `fk_hist_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hist_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `application_status_history`
--

LOCK TABLES `application_status_history` WRITE;
/*!40000 ALTER TABLE `application_status_history` DISABLE KEYS */;
INSERT INTO `application_status_history` VALUES (1,1,'pending','Application submitted by applicant.',8,'2026-07-15 06:21:04'),(2,1,'under_review','Verified by staff and forwarded to an evaluator.',5,'2026-07-15 06:29:10'),(3,1,'under_review','Verified by staff and forwarded to an evaluator.',5,'2026-07-15 06:29:21'),(4,1,'evaluated','good',6,'2026-07-15 06:34:11'),(5,1,'approved','Final approval granted by the Manager.',7,'2026-07-15 06:36:27'),(6,2,'pending','Application submitted by applicant.',8,'2026-09-22 07:41:23'),(7,2,'returned','wow',5,'2026-09-22 07:42:37'),(8,2,'pending','Resubmitted by applicant after revision.',8,'2026-09-22 07:42:56'),(9,2,'returned','wawa',5,'2026-09-22 07:43:10'),(10,3,'pending','Application submitted by applicant.',8,'2026-09-22 07:48:27'),(11,3,'returned','idk',5,'2026-09-22 07:50:50'),(12,3,'pending','Resubmitted by applicant after revision.',8,'2026-09-22 07:51:07'),(13,3,'rejected','wew',5,'2026-09-22 07:53:34'),(14,4,'pending','Application submitted by applicant.',10,'2026-11-02 15:19:03');
/*!40000 ALTER TABLE `application_status_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `application_types`
--

DROP TABLE IF EXISTS `application_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `application_types` (
  `type_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`type_id`),
  UNIQUE KEY `type_name` (`type_name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `application_types`
--

LOCK TABLES `application_types` WRITE;
/*!40000 ALTER TABLE `application_types` DISABLE KEYS */;
INSERT INTO `application_types` VALUES (1,'Patent','Protection for new inventions','Technical description, claims, drawings',1),(2,'Utility Model','Protection for minor inventions','Technical description, claims, drawings',1),(3,'Industrial Design','Protection for ornamental designs','Drawings/photos of the design',1),(4,'Trademark','Protection for brand names/logos','Specimen of mark, list of goods/services',1),(5,'Copyright','Protection for original works','Copy of the work, proof of authorship',1);
/*!40000 ALTER TABLE `application_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `applications`
--

DROP TABLE IF EXISTS `applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `applications` (
  `application_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tracking_code` varchar(30) NOT NULL,
  `applicant_id` int(10) unsigned NOT NULL,
  `type_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `college_id` int(10) unsigned DEFAULT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `status` enum('pending','under_review','evaluated','returned','approved','rejected') NOT NULL DEFAULT 'pending',
  `assigned_staff_id` int(10) unsigned DEFAULT NULL,
  `assigned_evaluator_id` int(10) unsigned DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`application_id`),
  UNIQUE KEY `tracking_code` (`tracking_code`),
  KEY `fk_app_applicant` (`applicant_id`),
  KEY `fk_app_type` (`type_id`),
  KEY `fk_app_college` (`college_id`),
  KEY `fk_app_department` (`department_id`),
  KEY `fk_app_staff` (`assigned_staff_id`),
  KEY `fk_app_evaluator` (`assigned_evaluator_id`),
  CONSTRAINT `fk_app_applicant` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`applicant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_app_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`college_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_app_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_app_evaluator` FOREIGN KEY (`assigned_evaluator_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_app_staff` FOREIGN KEY (`assigned_staff_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_app_type` FOREIGN KEY (`type_id`) REFERENCES `application_types` (`type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `applications`
--

LOCK TABLES `applications` WRITE;
/*!40000 ALTER TABLE `applications` DISABLE KEYS */;
INSERT INTO `applications` VALUES (1,'IPO-2026-774786',4,1,'IPO portal','ipo portal web based',1,1,'approved',5,6,'2026-07-15 06:21:04','2026-07-15 06:36:27'),(2,'IPO-2026-355711',4,1,'IPO 2','',1,1,'returned',5,NULL,'2026-09-22 07:41:23','2026-09-22 07:48:04'),(3,'IPO-2026-299090',4,1,'IPO 3','im sending this for sample',1,1,'rejected',5,NULL,'2026-09-22 07:48:27','2026-09-22 07:53:34'),(4,'IPO-2026-061077',5,5,'awdaf','adawdawd',1,1,'pending',NULL,NULL,'2026-11-02 15:19:03','2026-11-02 15:19:03');
/*!40000 ALTER TABLE `applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `log_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(150) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `fk_audit_user` (`user_id`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=256 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'login','User logged in successfully','::1','2026-07-03 08:15:14'),(2,1,'login','User logged in successfully','::1','2026-07-06 05:32:52'),(3,1,'logout','User logged out','::1','2026-07-06 06:16:27'),(4,NULL,'register','New applicant account created','::1','2026-07-06 06:17:51'),(5,NULL,'login','User logged in successfully','::1','2026-07-06 06:17:54'),(6,NULL,'logout','User logged out','::1','2026-07-06 06:18:10'),(7,NULL,'register','New applicant account created','::1','2026-07-06 06:19:04'),(8,NULL,'login','User logged in successfully','::1','2026-07-06 06:19:07'),(9,NULL,'logout','User logged out','::1','2026-07-06 06:19:54'),(10,1,'login','User logged in successfully','::1','2026-07-06 06:19:57'),(11,1,'logout','User logged out','::1','2026-07-06 06:21:18'),(12,NULL,'login','User logged in successfully','::1','2026-07-06 06:22:43'),(13,NULL,'logout','User logged out','::1','2026-07-06 06:22:51'),(14,NULL,'register','New applicant account created','::1','2026-07-06 06:23:43'),(15,NULL,'login','User logged in successfully','::1','2026-07-06 06:23:45'),(16,NULL,'logout','User logged out','::1','2026-07-06 06:23:52'),(17,1,'login','User logged in successfully','::1','2026-07-13 05:38:24'),(18,1,'logout','User logged out','::1','2026-07-13 05:39:31'),(19,NULL,'login','User logged in successfully','::1','2026-07-13 05:39:35'),(20,NULL,'logout','User logged out','::1','2026-07-13 05:40:40'),(21,1,'login','User logged in successfully','::1','2026-07-15 06:16:14'),(22,1,'create_user','Created user #5 (tamtim)','::1','2026-07-15 06:16:57'),(23,1,'create_user','Created user #6 (tinytan)','::1','2026-07-15 06:17:24'),(24,1,'create_user','Created user #7 (kylie12)','::1','2026-07-15 06:17:51'),(25,1,'logout','User logged out','::1','2026-07-15 06:19:05'),(26,8,'register','New applicant account created','::1','2026-07-15 06:20:20'),(27,8,'login','User logged in successfully','::1','2026-07-15 06:20:23'),(28,8,'submit_application','Submitted application IPO-2026-774786','::1','2026-07-15 06:21:04'),(29,8,'logout','User logged out','::1','2026-07-15 06:21:48'),(30,8,'login','User logged in successfully','::1','2026-07-15 06:21:59'),(31,8,'logout','User logged out','::1','2026-07-15 06:22:03'),(32,1,'login','User logged in successfully','::1','2026-07-15 06:22:39'),(33,1,'reset_password','Reset password for user #5','::1','2026-07-15 06:22:51'),(34,1,'reset_password','Reset password for user #5','::1','2026-07-15 06:23:01'),(35,1,'logout','User logged out','::1','2026-07-15 06:23:08'),(36,1,'login','User logged in successfully','::1','2026-07-15 06:24:16'),(37,1,'reset_password','Reset password for user #5','::1','2026-07-15 06:24:29'),(38,1,'logout','User logged out','::1','2026-07-15 06:24:36'),(39,1,'login','User logged in successfully','::1','2026-07-15 06:26:43'),(40,1,'reset_password','Reset password for user #5','::1','2026-07-15 06:26:57'),(41,1,'logout','User logged out','::1','2026-07-15 06:27:23'),(42,5,'login','User logged in successfully','::1','2026-07-15 06:27:35'),(43,5,'forward_application','Forwarded application #1 to evaluator #6','::1','2026-07-15 06:29:10'),(44,5,'forward_application','Forwarded application #1 to evaluator #6','::1','2026-07-15 06:29:21'),(45,5,'logout','User logged out','::1','2026-07-15 06:32:41'),(46,1,'login','User logged in successfully','::1','2026-07-15 06:32:54'),(47,1,'reset_password','Reset password for user #6','::1','2026-07-15 06:33:01'),(48,1,'logout','User logged out','::1','2026-07-15 06:33:06'),(49,6,'login','User logged in successfully','::1','2026-07-15 06:33:29'),(50,6,'evaluate_application','Application #1 evaluated: approved','::1','2026-07-15 06:34:11'),(51,6,'logout','User logged out','::1','2026-07-15 06:35:10'),(52,1,'login','User logged in successfully','::1','2026-07-15 06:35:14'),(53,1,'reset_password','Reset password for user #7','::1','2026-07-15 06:35:19'),(54,1,'logout','User logged out','::1','2026-07-15 06:35:27'),(55,7,'login','User logged in successfully','::1','2026-07-15 06:35:40'),(56,7,'final_approve','Application #1 final decision: approved','::1','2026-07-15 06:36:27'),(57,7,'logout','User logged out','::1','2026-07-15 06:37:11'),(58,8,'login','User logged in successfully','::1','2026-07-15 06:37:14'),(59,1,'login','User logged in successfully','::1','2026-07-27 06:14:14'),(60,1,'logout','User logged out','::1','2026-07-27 06:14:32'),(61,1,'login','User logged in successfully','::1','2026-07-27 06:27:24'),(62,1,'login','User logged in successfully','::1','2026-09-07 14:23:15'),(63,1,'logout','User logged out','::1','2026-09-07 14:27:37'),(64,1,'login','User logged in successfully','::1','2026-09-07 14:30:06'),(65,1,'logout','User logged out','::1','2026-09-07 14:30:20'),(66,7,'login','User logged in successfully','::1','2026-09-07 14:30:25'),(67,7,'logout','User logged out','::1','2026-09-07 14:30:44'),(68,5,'login','User logged in successfully','::1','2026-09-07 14:30:47'),(69,5,'logout','User logged out','::1','2026-09-07 14:32:04'),(70,1,'login','User logged in successfully','::1','2026-09-07 14:32:13'),(71,1,'logout','User logged out','::1','2026-09-07 14:34:50'),(72,1,'login','User logged in successfully','::1','2026-09-07 14:35:20'),(73,1,'login','User logged in successfully','::1','2026-09-22 07:04:56'),(74,1,'logout','User logged out','::1','2026-09-22 07:05:13'),(75,7,'login','User logged in successfully','::1','2026-09-22 07:05:16'),(76,7,'export_report','Manager exported applications report as CSV','::1','2026-09-22 07:05:20'),(77,7,'logout','User logged out','::1','2026-09-22 07:05:48'),(78,5,'login','User logged in successfully','::1','2026-09-22 07:05:51'),(79,5,'logout','User logged out','::1','2026-09-22 07:06:02'),(80,8,'login','User logged in successfully','::1','2026-09-22 07:06:06'),(81,8,'logout','User logged out','::1','2026-09-22 07:06:20'),(82,1,'login','User logged in successfully','::1','2026-09-22 07:06:27'),(83,1,'login','User logged in successfully','::1','2026-09-22 07:13:21'),(84,1,'logout','User logged out','::1','2026-09-22 07:17:42'),(85,1,'login','User logged in successfully','::1','2026-09-22 07:18:07'),(86,1,'login','User logged in successfully','192.168.1.6','2026-09-22 07:28:54'),(87,1,'logout','User logged out','192.168.1.6','2026-09-22 07:29:15'),(88,1,'login','User logged in successfully','192.168.1.6','2026-09-22 07:29:19'),(89,1,'logout','User logged out','192.168.1.6','2026-09-22 07:29:39'),(90,1,'logout','User logged out','::1','2026-09-22 07:30:01'),(91,1,'login','User logged in successfully','::1','2026-09-22 07:30:13'),(92,1,'logout','User logged out','::1','2026-09-22 07:30:24'),(93,1,'login','User logged in successfully','::1','2026-09-22 07:40:22'),(94,1,'logout','User logged out','::1','2026-09-22 07:40:28'),(95,5,'login','User logged in successfully','::1','2026-09-22 07:40:31'),(96,5,'logout','User logged out','::1','2026-09-22 07:40:55'),(97,1,'login','User logged in successfully','::1','2026-09-22 07:40:59'),(98,1,'logout','User logged out','::1','2026-09-22 07:41:03'),(99,8,'login','User logged in successfully','::1','2026-09-22 07:41:06'),(100,8,'submit_application','Submitted application IPO-2026-355711','::1','2026-09-22 07:41:23'),(101,8,'logout','User logged out','::1','2026-09-22 07:41:37'),(102,1,'login','User logged in successfully','::1','2026-09-22 07:41:41'),(103,1,'logout','User logged out','::1','2026-09-22 07:42:01'),(104,5,'login','User logged in successfully','::1','2026-09-22 07:42:05'),(105,5,'verify_return','Application #2 marked returned','::1','2026-09-22 07:42:37'),(106,5,'logout','User logged out','::1','2026-09-22 07:42:41'),(107,8,'login','User logged in successfully','::1','2026-09-22 07:42:44'),(108,8,'resubmit_application','Application #2 resubmitted after revision','::1','2026-09-22 07:42:56'),(109,8,'logout','User logged out','::1','2026-09-22 07:43:03'),(110,5,'login','User logged in successfully','::1','2026-09-22 07:43:06'),(111,5,'verify_return','Application #2 marked returned','::1','2026-09-22 07:43:10'),(112,5,'logout','User logged out','::1','2026-09-22 07:43:11'),(113,8,'login','User logged in successfully','::1','2026-09-22 07:43:14'),(114,8,'logout','User logged out','::1','2026-09-22 07:43:57'),(115,8,'login','User logged in successfully','::1','2026-09-22 07:44:00'),(116,8,'delete_document','Applicant removed a document from application #2 while revising','::1','2026-09-22 07:48:00'),(117,8,'edit_application','Applicant edited application #2 while revising','::1','2026-09-22 07:48:04'),(118,8,'submit_application','Submitted application IPO-2026-299090','::1','2026-09-22 07:48:27'),(119,8,'logout','User logged out','::1','2026-09-22 07:48:30'),(120,5,'login','User logged in successfully','::1','2026-09-22 07:48:34'),(121,5,'verify_return','Application #3 marked returned','::1','2026-09-22 07:50:50'),(122,5,'logout','User logged out','::1','2026-09-22 07:50:52'),(123,8,'login','User logged in successfully','::1','2026-09-22 07:50:55'),(124,8,'resubmit_application','Application #3 resubmitted after revision','::1','2026-09-22 07:51:07'),(125,8,'logout','User logged out','::1','2026-09-22 07:51:08'),(126,5,'login','User logged in successfully','::1','2026-09-22 07:51:10'),(127,5,'verify_reject','Application #3 marked rejected','::1','2026-09-22 07:53:34'),(128,5,'logout','User logged out','::1','2026-09-22 07:53:35'),(129,8,'login','User logged in successfully','::1','2026-09-22 07:53:38'),(130,1,'login','User logged in successfully','::1','2026-09-25 06:34:02'),(131,1,'create_announcement','Team Building','::1','2026-09-25 06:45:00'),(132,1,'logout','User logged out','::1','2026-09-25 06:45:13'),(133,1,'login','User logged in successfully','::1','2026-09-25 06:45:40'),(134,1,'logout','User logged out','::1','2026-09-25 06:47:52'),(135,5,'login','User logged in successfully','::1','2026-09-25 06:47:55'),(136,5,'logout','User logged out','::1','2026-09-25 06:48:05'),(137,8,'login','User logged in successfully','::1','2026-09-25 06:48:07'),(138,8,'logout','User logged out','::1','2026-09-25 06:48:16'),(139,8,'login','User logged in successfully','::1','2026-10-06 07:18:08'),(140,8,'logout','User logged out','::1','2026-10-06 07:18:14'),(141,8,'login','User logged in successfully','::1','2026-10-06 07:18:19'),(142,8,'logout','User logged out','::1','2026-10-06 07:18:26'),(143,1,'login','User logged in successfully','::1','2026-10-06 07:18:28'),(144,1,'logout','User logged out','::1','2026-10-06 07:18:57'),(145,7,'login','User logged in successfully','::1','2026-10-06 07:19:03'),(146,7,'logout','User logged out','::1','2026-10-06 07:19:14'),(147,1,'login','User logged in successfully','::1','2026-10-07 07:12:33'),(148,1,'logout','User logged out','::1','2026-10-07 07:17:56'),(149,1,'login','User logged in successfully','::1','2026-10-07 07:17:59'),(150,1,'logout','User logged out','::1','2026-10-07 07:18:10'),(151,7,'login','User logged in successfully','::1','2026-10-07 07:18:13'),(152,7,'logout','User logged out','::1','2026-10-07 07:19:27'),(153,5,'login','User logged in successfully','::1','2026-10-07 07:19:29'),(154,5,'logout','User logged out','::1','2026-10-07 07:19:33'),(155,8,'login','User logged in successfully','::1','2026-10-07 07:19:36'),(156,8,'logout','User logged out','::1','2026-10-07 07:19:43'),(157,6,'login','User logged in successfully','::1','2026-10-07 07:19:50'),(158,6,'logout','User logged out','::1','2026-10-07 07:20:00'),(159,1,'login','User logged in successfully','::1','2026-10-07 07:20:06'),(160,1,'logout','User logged out','::1','2026-10-07 07:21:04'),(161,7,'login','User logged in successfully','::1','2026-10-07 07:21:06'),(162,1,'login','User logged in successfully','::1','2026-10-18 10:14:02'),(163,1,'logout','User logged out','::1','2026-10-18 10:14:16'),(164,1,'login','User logged in successfully','::1','2026-10-18 10:14:18'),(165,1,'logout','User logged out','::1','2026-10-18 10:14:23'),(166,7,'login','User logged in successfully','::1','2026-10-18 10:14:26'),(167,7,'login','User logged in successfully','::1','2026-10-18 10:29:08'),(168,7,'logout','User logged out','::1','2026-10-18 10:29:22'),(169,1,'login','User logged in successfully','::1','2026-10-18 10:29:24'),(170,1,'login','User logged in successfully','::1','2026-11-02 14:26:25'),(171,1,'toggle_status','Set user #8 status to inactive','::1','2026-11-02 14:26:45'),(172,1,'toggle_status','Set user #7 status to inactive','::1','2026-11-02 14:26:48'),(173,1,'toggle_status','Set user #6 status to inactive','::1','2026-11-02 14:26:50'),(174,1,'toggle_status','Set user #5 status to inactive','::1','2026-11-02 14:26:52'),(175,1,'logout','User logged out','::1','2026-11-02 14:29:13'),(176,1,'login','User logged in successfully','::1','2026-11-02 14:29:19'),(177,1,'logout','User logged out','::1','2026-11-02 15:12:20'),(178,1,'login','User logged in successfully','::1','2026-11-02 15:12:33'),(179,1,'create_user','Created user #9 (aljonreyes)','::1','2026-11-02 15:13:09'),(180,1,'logout','User logged out','::1','2026-11-02 15:14:46'),(181,1,'login','User logged in successfully','::1','2026-11-02 15:16:01'),(182,1,'logout','User logged out','::1','2026-11-02 15:16:44'),(183,10,'register','New applicant account created','::1','2026-11-02 15:17:56'),(184,1,'login','User logged in successfully','::1','2026-11-02 15:18:25'),(185,1,'logout','User logged out','::1','2026-11-02 15:18:29'),(186,10,'login','User logged in successfully','::1','2026-11-02 15:18:36'),(187,10,'submit_application','Submitted application IPO-2026-061077','::1','2026-11-02 15:19:03'),(188,10,'logout','User logged out','::1','2026-11-02 15:19:34'),(189,1,'login','User logged in successfully','::1','2026-11-02 15:19:42'),(190,1,'toggle_status','Set user #8 status to active','::1','2026-11-02 15:19:50'),(191,1,'toggle_status','Set user #7 status to active','::1','2026-11-02 15:19:51'),(192,1,'toggle_status','Set user #6 status to active','::1','2026-11-02 15:19:54'),(193,1,'toggle_status','Set user #5 status to active','::1','2026-11-02 15:19:59'),(194,1,'reset_password','Reset password for user #7','::1','2026-11-02 15:20:27'),(195,1,'logout','User logged out','::1','2026-11-02 15:20:29'),(196,7,'login','User logged in successfully','::1','2026-11-02 15:20:35'),(197,7,'logout','User logged out','::1','2026-11-02 15:20:46'),(198,1,'login','User logged in successfully','::1','2026-11-02 15:20:52'),(199,1,'reset_password','Reset password for user #6','::1','2026-11-02 15:21:13'),(200,1,'logout','User logged out','::1','2026-11-02 15:21:15'),(201,6,'login','User logged in successfully','::1','2026-11-02 15:21:23'),(202,6,'logout','User logged out','::1','2026-11-02 15:21:29'),(203,1,'login','User logged in successfully','::1','2026-11-02 15:21:35'),(204,1,'reset_password','Reset password for user #5','::1','2026-11-02 15:21:49'),(205,1,'logout','User logged out','::1','2026-11-02 15:21:51'),(206,5,'login','User logged in successfully','::1','2026-11-02 15:21:57'),(207,5,'logout','User logged out','::1','2026-11-02 15:29:05'),(208,5,'login','User logged in successfully','::1','2026-11-02 15:29:16'),(209,5,'logout','User logged out','::1','2026-11-02 15:29:24'),(210,6,'login','User logged in successfully','::1','2026-11-02 15:29:31'),(211,6,'logout','User logged out','::1','2026-11-02 15:29:34'),(212,7,'login','User logged in successfully','::1','2026-11-02 15:29:40'),(213,7,'logout','User logged out','::1','2026-11-02 15:42:04'),(214,1,'login','User logged in successfully','::1','2026-11-02 15:42:11'),(215,1,'logout','User logged out','::1','2026-11-02 16:00:20'),(216,1,'login','User logged in successfully','::1','2026-11-02 16:00:29'),(217,1,'export_report','Exported applications report as CSV','::1','2026-11-02 16:40:39'),(218,1,'logout','User logged out','::1','2026-11-02 16:41:08'),(219,1,'login','User logged in successfully','::1','2026-11-02 16:41:14'),(220,1,'login','User logged in successfully','::1','2026-11-02 16:47:13'),(221,1,'logout','User logged out','::1','2026-11-02 16:51:32'),(222,1,'logout','User logged out','::1','2026-11-02 16:55:59'),(223,1,'login','User logged in successfully','::1','2026-11-02 16:56:49'),(224,1,'logout','User logged out','::1','2026-11-02 16:57:37'),(225,10,'login','User logged in successfully','::1','2026-11-02 16:57:42'),(226,10,'logout','User logged out','::1','2026-11-02 17:00:55'),(227,1,'login','User logged in successfully','::1','2026-11-02 17:01:00'),(228,1,'login','User logged in successfully','::1','2026-11-02 17:20:43'),(229,1,'logout','User logged out','::1','2026-11-02 17:27:53'),(230,1,'login','User logged in successfully','::1','2026-11-02 17:40:20'),(231,1,'login','User logged in successfully','::1','2026-11-02 17:42:49'),(232,1,'logout','User logged out','::1','2026-11-02 17:43:00'),(233,1,'login','User logged in successfully','::1','2026-11-02 17:47:42'),(234,1,'login','User logged in successfully','::1','2026-11-02 17:48:56'),(235,1,'login','User logged in successfully','::1','2026-11-02 17:51:41'),(236,1,'logout','User logged out','::1','2026-11-02 17:52:21'),(237,10,'login','User logged in successfully','::1','2026-11-02 17:54:31'),(238,1,'login','User logged in successfully','::1','2026-11-02 18:14:14'),(239,5,'login','User logged in successfully','::1','2026-11-02 18:14:14'),(240,6,'login','User logged in successfully','::1','2026-11-02 18:14:14'),(241,7,'login','User logged in successfully','::1','2026-11-02 18:14:14'),(242,8,'login','User logged in successfully','::1','2026-11-02 18:14:14'),(243,10,'logout','User logged out','::1','2026-11-02 18:15:35'),(244,10,'login','User logged in successfully','::1','2026-11-02 18:16:27'),(245,10,'logout','User logged out','::1','2026-11-02 18:16:33'),(246,1,'login','User logged in successfully','::1','2026-11-02 18:19:57'),(247,1,'logout','User logged out','::1','2026-11-02 18:20:48'),(248,5,'login','User logged in successfully','::1','2026-11-02 18:20:54'),(249,5,'logout','User logged out','::1','2026-11-02 18:21:10'),(250,6,'login','User logged in successfully','::1','2026-11-02 18:21:27'),(251,6,'logout','User logged out','::1','2026-11-02 18:21:43'),(252,7,'login','User logged in successfully','::1','2026-11-02 18:21:52'),(253,7,'logout','User logged out','::1','2026-11-02 18:22:08'),(254,8,'login','User logged in successfully','::1','2026-11-02 18:22:16'),(255,8,'logout','User logged out','::1','2026-11-02 18:22:41');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `colleges`
--

DROP TABLE IF EXISTS `colleges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `colleges` (
  `college_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `college_name` varchar(150) NOT NULL,
  `college_code` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`college_id`),
  UNIQUE KEY `college_code` (`college_code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `colleges`
--

LOCK TABLES `colleges` WRITE;
/*!40000 ALTER TABLE `colleges` DISABLE KEYS */;
INSERT INTO `colleges` VALUES (1,'College of Information and Computer Studies','CICS','2026-07-03 08:14:20');
/*!40000 ALTER TABLE `colleges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `department_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `college_id` int(10) unsigned NOT NULL,
  `department_name` varchar(150) NOT NULL,
  `department_code` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`department_id`),
  KEY `fk_dept_college` (`college_id`),
  CONSTRAINT `fk_dept_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`college_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES (1,1,'Bachelor of Science in Information Technology','BSIT','2026-07-03 08:14:20');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `document_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` int(10) unsigned NOT NULL,
  `uploaded_by` int(10) unsigned NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(20) NOT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  `version` int(10) unsigned NOT NULL DEFAULT 1,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`document_id`),
  KEY `fk_doc_application` (`application_id`),
  KEY `fk_doc_user` (`uploaded_by`),
  CONSTRAINT `fk_doc_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_doc_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
INSERT INTO `documents` VALUES (1,1,8,'juan pansit.jpg','ae271ab7ab0c57b077fd0761e9fce99d.jpg','uploads/documents/ae271ab7ab0c57b077fd0761e9fce99d.jpg','jpg',196604,1,'2026-07-15 06:21:04'),(3,3,8,'RobloxScreenShot20250910_163821568.png','9ebb50ba0c1448ae8abf2d0e14bb1d81.png','uploads/documents/9ebb50ba0c1448ae8abf2d0e14bb1d81.png','png',850890,1,'2026-09-22 07:48:27'),(4,4,10,'jaja-babalu-andherMATE.docx','c03a861291a6a4b2c451c09f2b70113c.docx','uploads/documents/c03a861291a6a4b2c451c09f2b70113c.docx','docx',32050,1,'2026-11-02 15:19:03');
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evaluations`
--

DROP TABLE IF EXISTS `evaluations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluations` (
  `evaluation_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` int(10) unsigned NOT NULL,
  `evaluator_id` int(10) unsigned NOT NULL,
  `decision` enum('approved','rejected','returned') NOT NULL,
  `comments` text DEFAULT NULL,
  `evaluated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`evaluation_id`),
  KEY `fk_eval_application` (`application_id`),
  KEY `fk_eval_evaluator` (`evaluator_id`),
  CONSTRAINT `fk_eval_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_eval_evaluator` FOREIGN KEY (`evaluator_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evaluations`
--

LOCK TABLES `evaluations` WRITE;
/*!40000 ALTER TABLE `evaluations` DISABLE KEYS */;
INSERT INTO `evaluations` VALUES (1,1,6,'approved','good','2026-07-15 06:34:11');
/*!40000 ALTER TABLE `evaluations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `notification_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `fk_notif_user` (`user_id`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,1,'New Application Submitted','IPO-2026-774786 ΓÇö IPO portal','admin/application_view.php?id=1',0,'2026-07-15 06:21:04'),(2,6,'Application Assigned for Evaluation','An application has been forwarded to you for evaluation.','evaluator/evaluations.php',0,'2026-07-15 06:29:10'),(3,8,'Application Verified','Your application passed initial verification and is now under review.','applicant/my_applications.php',1,'2026-07-15 06:29:10'),(4,6,'Application Assigned for Evaluation','An application has been forwarded to you for evaluation.','evaluator/evaluations.php',0,'2026-07-15 06:29:21'),(5,8,'Application Verified','Your application passed initial verification and is now under review.','applicant/my_applications.php',1,'2026-07-15 06:29:21'),(6,8,'Application Recommended for Approval','good','applicant/my_applications.php',1,'2026-07-15 06:34:11'),(7,7,'Application Ready for Final Approval','An evaluator has recommended approval for an application.','manager/reports.php',0,'2026-07-15 06:34:11'),(8,8,'Application Approved','Final approval granted by the Manager.','applicant/my_applications.php',1,'2026-07-15 06:36:27'),(9,1,'New Application Submitted','IPO-2026-355711 ΓÇö IPO 2','admin/application_view.php?id=2',1,'2026-09-22 07:41:23'),(10,8,'Application Returned','wow','applicant/my_applications.php',1,'2026-09-22 07:42:37'),(11,5,'Application Resubmitted','An applicant resubmitted a previously returned application for re-verification.','staff/application_view.php?id=2',0,'2026-09-22 07:42:56'),(12,8,'Application Returned','wawa','applicant/my_applications.php',1,'2026-09-22 07:43:10'),(13,1,'New Application Submitted','IPO-2026-299090 ΓÇö IPO 3','admin/application_view.php?id=3',0,'2026-09-22 07:48:27'),(14,8,'Application Returned','idk','applicant/my_applications.php',0,'2026-09-22 07:50:50'),(15,5,'Application Resubmitted','An applicant resubmitted a previously returned application for re-verification.','staff/application_view.php?id=3',0,'2026-09-22 07:51:07'),(16,8,'Application Rejected','wew','applicant/my_applications.php',0,'2026-09-22 07:53:34'),(17,1,'New Application Submitted','IPO-2026-061077 ΓÇö awdaf','admin/application_view.php?id=4',0,'2026-11-02 15:19:03');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `role_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Administrator','Full system access and configuration'),(2,'Staff','Verifies applications and manages documents'),(3,'Evaluator','Evaluates and decides on IP applications'),(4,'Manager','Reviews reports and gives final approval'),(5,'Applicant','Submits and tracks IP applications');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `setting_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES (1,'site_name','ZPPSU Intellectual Property Office','2026-07-03 08:14:20'),(2,'site_email','ipo@zppsu.edu.ph','2026-07-03 08:14:20'),(3,'max_upload_mb','10','2026-07-03 08:14:20'),(4,'allowed_file_types','pdf,docx,jpg,jpeg,png','2026-07-03 08:14:20'),(5,'maintenance_mode','0','2026-07-03 08:14:20');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(10) unsigned NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `recovery_email` varchar(150) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `avatar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_users_role` (`role_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,1,'admin','admin@zppsu.edu.ph',NULL,'$2y$10$ar/xL.Nt/UKEYvAsKIyK7OffPrY6WnHwEvAHiFRc36TxHH4.tdsDe','System','Administrator',NULL,'active',NULL,'2026-07-03 08:14:40','2026-11-02 18:14:13'),(5,2,'tamtim','tamtim@gmail.com',NULL,'$2y$10$ar/xL.Nt/UKEYvAsKIyK7OffPrY6WnHwEvAHiFRc36TxHH4.tdsDe','tam','tim',NULL,'active',NULL,'2026-07-15 06:16:57','2026-11-02 18:14:14'),(6,3,'tinytan','tinytan@gmail.com',NULL,'$2y$10$ar/xL.Nt/UKEYvAsKIyK7OffPrY6WnHwEvAHiFRc36TxHH4.tdsDe','tiny','tan',NULL,'active',NULL,'2026-07-15 06:17:24','2026-11-02 18:14:14'),(7,4,'kylie12','kylie@gmail.com',NULL,'$2y$10$ar/xL.Nt/UKEYvAsKIyK7OffPrY6WnHwEvAHiFRc36TxHH4.tdsDe','kylie','enri',NULL,'active',NULL,'2026-07-15 06:17:51','2026-11-02 18:14:14'),(8,5,'timothy','timothy@gmail.com',NULL,'$2y$10$ar/xL.Nt/UKEYvAsKIyK7OffPrY6WnHwEvAHiFRc36TxHH4.tdsDe','timo','thy','0998765432','active',NULL,'2026-07-15 06:20:20','2026-11-02 18:14:14'),(9,3,'aljonreyes','ae202403715@wmsu.edu.ph',NULL,'$2y$10$qk19GLI9Zv2sx/tRXk55eezsRGMNRQAmisYrSQAQUrDtoKgu9.dBC','Aljon','Reyes',NULL,'active',NULL,'2026-11-02 15:13:09','2026-11-02 15:13:09'),(10,5,'akatsukkzzooci@gmail.com','hnjwoh@gmail.com',NULL,'$2y$10$KT930OKOnSeEG9.JgyKcRObetlS07H7Yf3Clh2O3Drbzz231OkYeS','ALJON','REYES','9362540613','active',NULL,'2026-11-02 15:17:56','2026-11-02 15:17:56'),(11,5,'dump_applicant','dump.applicant@zppsu.edu.ph',NULL,'$2y$10$QcVe9XN/rHUf.uObEcfhYeEPLj4cTAeeCbHV.n2iBOouxEumT00Ly','Dump','Applicant','09123456789','active',NULL,'2026-11-02 16:54:05','2026-11-02 16:54:05');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'zppsu_ipo'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-03  2:23:21
