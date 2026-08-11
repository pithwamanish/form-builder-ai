-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: form_builder
-- ------------------------------------------------------
-- Server version	8.0.46

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
-- Table structure for table `ai_generation_logs`
--

DROP TABLE IF EXISTS `ai_generation_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_generation_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `form_id` bigint unsigned DEFAULT NULL,
  `prompt` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prompt_tokens` int unsigned NOT NULL DEFAULT '0',
  `completion_tokens` int unsigned NOT NULL DEFAULT '0',
  `total_tokens` int unsigned NOT NULL DEFAULT '0',
  `latency_seconds` float NOT NULL DEFAULT '0',
  `status` enum('pending','processing','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_generation_logs_form_id_foreign` (`form_id`),
  KEY `ai_generation_logs_tenant_id_index` (`tenant_id`),
  CONSTRAINT `ai_generation_logs_form_id_foreign` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_generation_logs`
--

LOCK TABLES `ai_generation_logs` WRITE;
/*!40000 ALTER TABLE `ai_generation_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_generation_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_import_logs`
--

DROP TABLE IF EXISTS `document_import_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_import_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint unsigned NOT NULL DEFAULT '0',
  `status` enum('pending','processing','completed','review_required','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `parsed_schema` json DEFAULT NULL,
  `unparseable_blocks` json DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `document_import_logs_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_import_logs`
--

LOCK TABLES `document_import_logs` WRITE;
/*!40000 ALTER TABLE `document_import_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `document_import_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `form_submissions`
--

DROP TABLE IF EXISTS `form_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `form_submissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `form_id` bigint unsigned NOT NULL,
  `submission_data` json NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_form_created` (`form_id`,`created_at`),
  KEY `form_submissions_tenant_id_index` (`tenant_id`),
  CONSTRAINT `form_submissions_form_id_foreign` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `form_submissions`
--

LOCK TABLES `form_submissions` WRITE;
/*!40000 ALTER TABLE `form_submissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `form_submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `form_templates`
--

DROP TABLE IF EXISTS `form_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `form_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `schema` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `form_templates`
--

LOCK TABLES `form_templates` WRITE;
/*!40000 ALTER TABLE `form_templates` DISABLE KEYS */;
INSERT INTO `form_templates` VALUES (1,'Job Application Form','HR & Recruitment','Comprehensive candidate application form with personal details, experience, skills checklist, and resume upload.','{\"title\": \"Job Application Form\", \"sections\": [{\"id\": \"sec_personal\", \"title\": \"Personal Information\", \"fields\": [{\"id\": \"fld_1\", \"key\": \"full_name\", \"type\": \"text\", \"label\": \"Full Name\", \"required\": true, \"validation\": {\"max\": 100, \"min\": 2}, \"placeholder\": \"Jane Doe\"}, {\"id\": \"fld_2\", \"key\": \"email\", \"type\": \"email\", \"label\": \"Email Address\", \"required\": true, \"validation\": {\"email\": true}, \"placeholder\": \"jane.doe@example.com\"}, {\"id\": \"fld_3\", \"key\": \"phone\", \"type\": \"phone\", \"label\": \"Phone Number\", \"required\": true, \"placeholder\": \"+1 (555) 123-4567\"}]}, {\"id\": \"sec_experience\", \"title\": \"Professional Background\", \"fields\": [{\"id\": \"fld_4\", \"key\": \"years_exp\", \"type\": \"number\", \"label\": \"Years of Experience\", \"required\": true, \"validation\": {\"max\": 40, \"min\": 0}, \"placeholder\": \"3\"}, {\"id\": \"fld_5\", \"key\": \"skills\", \"type\": \"checkbox\", \"label\": \"Technical Skills\", \"options\": [\"PHP / Laravel\", \"JavaScript / React\", \"Python / AI\", \"MySQL / Redis\"], \"required\": true}, {\"id\": \"fld_6\", \"key\": \"resume\", \"type\": \"file\", \"label\": \"Upload Resume (PDF/DOCX)\", \"required\": true}]}], \"description\": \"Please fill out all required fields to submit your job application.\"}','2026-08-09 16:35:34','2026-08-09 16:35:34'),(2,'Customer Feedback Survey','Customer Success','Collect actionable customer feedback, star ratings, and improvement suggestions.','{\"title\": \"Customer Feedback Survey\", \"sections\": [{\"id\": \"sec_feedback\", \"title\": \"Product Experience\", \"fields\": [{\"id\": \"fld_rating\", \"key\": \"satisfaction_rating\", \"type\": \"rating\", \"label\": \"Overall Satisfaction Rating (1 to 5 Stars)\", \"required\": true}, {\"id\": \"fld_recommend\", \"key\": \"would_recommend\", \"type\": \"radio\", \"label\": \"Would you recommend us to a colleague?\", \"options\": [\"Yes, absolutely\", \"Maybe\", \"No\"], \"required\": true}, {\"id\": \"fld_comments\", \"key\": \"feedback_comments\", \"type\": \"textarea\", \"label\": \"Additional Comments or Suggestions\", \"required\": false, \"placeholder\": \"What did you like or what can we improve?\"}]}], \"description\": \"We value your feedback! Help us improve our product.\"}','2026-08-09 16:35:34','2026-08-09 16:35:34'),(3,'Event Registration Form','Events','Registrations form for conferences, webinars, or workshops with dietary and session preferences.','{\"title\": \"Event Registration\", \"sections\": [{\"id\": \"sec_attendee\", \"title\": \"Attendee Details\", \"fields\": [{\"id\": \"fld_attendee_name\", \"key\": \"attendee_name\", \"type\": \"text\", \"label\": \"Attendee Full Name\", \"required\": true, \"placeholder\": \"Alex Johnson\"}, {\"id\": \"fld_ticket_type\", \"key\": \"ticket_type\", \"type\": \"dropdown\", \"label\": \"Ticket Type\", \"options\": [\"General Admission (Free)\", \"VIP Access ($99)\", \"Student Pass ($25)\"], \"required\": true}, {\"id\": \"fld_session\", \"key\": \"track_preference\", \"type\": \"radio\", \"label\": \"Preferred Breakout Session\", \"options\": [\"Track A: Backend Scalability\", \"Track B: AI Integrations\", \"Track C: Modern Frontend\"], \"required\": true}]}], \"description\": \"Register your spot for the upcoming Tech Summit.\"}','2026-08-09 16:35:34','2026-08-09 16:35:34'),(4,'Contact & Inquiry Form','General','Standard website contact form with inquiry category dropdown and contact information.','{\"title\": \"Contact Us\", \"sections\": [{\"id\": \"sec_contact\", \"title\": \"Your Message\", \"fields\": [{\"id\": \"fld_c_name\", \"key\": \"contact_name\", \"type\": \"text\", \"label\": \"Your Name\", \"required\": true}, {\"id\": \"fld_c_email\", \"key\": \"contact_email\", \"type\": \"email\", \"label\": \"Email Address\", \"required\": true}, {\"id\": \"fld_c_subject\", \"key\": \"inquiry_subject\", \"type\": \"dropdown\", \"label\": \"Subject / Category\", \"options\": [\"General Support\", \"Sales & Pricing\", \"Partnership\", \"Media Inquiry\"], \"required\": true}, {\"id\": \"fld_c_message\", \"key\": \"message_body\", \"type\": \"textarea\", \"label\": \"Message\", \"required\": true, \"placeholder\": \"How can we help you?\"}]}], \"description\": \"Get in touch with our team. We reply within 24 hours.\"}','2026-08-09 16:35:34','2026-08-09 16:35:34');
/*!40000 ALTER TABLE `form_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `forms`
--

DROP TABLE IF EXISTS `forms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `forms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `schema` json NOT NULL,
  `status` enum('draft','published','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'published',
  `views_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `forms_uuid_unique` (`uuid`),
  UNIQUE KEY `forms_slug_unique` (`slug`),
  KEY `idx_status_created` (`status`,`created_at`),
  KEY `idx_slug` (`slug`),
  KEY `forms_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `forms`
--

LOCK TABLES `forms` WRITE;
/*!40000 ALTER TABLE `forms` DISABLE KEYS */;
/*!40000 ALTER TABLE `forms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2026_08_05_000001_create_forms_table',1),(2,'2026_08_05_000002_create_form_submissions_table',1),(3,'2026_08_05_000003_create_form_templates_table',1),(4,'2026_08_05_000004_create_ai_generation_logs_table',1),(5,'2026_08_05_000005_create_jobs_table',1),(6,'2026_08_05_000006_create_document_import_logs_table',1),(7,'2026_08_09_161206_add_tenant_id_to_tables',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-09 11:05:40
