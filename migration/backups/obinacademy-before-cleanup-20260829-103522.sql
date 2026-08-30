-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: obinacademy
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
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(120) NOT NULL,
  `target_type` varchar(60) NOT NULL,
  `target_label` varchar(255) NOT NULL,
  `detail` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `actor_id` int(11) DEFAULT NULL,
  `actor_name` varchar(191) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `actor_id` (`actor_id`),
  CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_log`
--

LOCK TABLES `audit_log` WRITE;
/*!40000 ALTER TABLE `audit_log` DISABLE KEYS */;
INSERT INTO `audit_log` VALUES (1,'CREATOR_APPLICATION_APPROVED','user','Sarah Namuli','Approved creator application','2026-05-29 09:32:03',1,'Obin Admin'),(2,'CREATOR_APPLICATION_APPROVED','user','David Okello','Approved creator application','2026-05-30 09:32:03',1,'Obin Admin'),(3,'CREATOR_APPLICATION_APPROVED','user','Grace Atim','Approved creator application','2026-05-31 09:32:03',1,'Obin Admin'),(4,'COURSE_PUBLISHED','course','Personal Finance Mastery for Beginners','Reviewed and published','2026-06-17 09:32:03',1,'Obin Admin'),(5,'COURSE_PUBLISHED','course','Practical Web Development with PHP & MySQL','Reviewed and published','2026-06-22 09:32:03',1,'Obin Admin'),(6,'COURSE_REJECTED','course','Crypto Trading Basics','Rejected pending compliance review','2026-08-16 09:32:03',1,'Obin Admin'),(7,'category.created','Category','Tech',NULL,'2026-08-25 14:08:00',1,'Obin Admin'),(8,'creator_application.approved','User','Opio Chris',NULL,'2026-08-26 13:52:23',1,'Obin Admin'),(9,'course.approved','Course','Advanced Excel for Financial Analysts',NULL,'2026-08-26 13:52:48',1,'Obin Admin'),(10,'withdrawal.approved','Withdrawal','David Okello','UGX 150,000','2026-08-26 17:16:35',1,'Obin Admin'),(11,'creator_application.approved','User','John Mukasa',NULL,'2026-08-28 15:22:46',1,'Obin Admin'),(12,'course.approved','Course','The ChatGPT Money Machine',NULL,'2026-08-28 15:39:05',1,'Obin Admin'),(13,'creator_application.approved','User','Warom Hurry',NULL,'2026-08-28 21:59:41',1,'Obin Admin'),(14,'course.deleted','Course','Compound Interest','via creator dashboard','2026-08-29 05:54:58',1,'Obin Admin'),(15,'course.deleted','Course','Compound Interest Blurprint','via creator dashboard','2026-08-29 05:55:09',1,'Obin Admin'),(16,'course.deleted','Course','Intro to Graphic Design','via creator dashboard','2026-08-29 05:55:17',1,'Obin Admin'),(17,'course.deleted','Course','Advanced Excel for Financial Analysts','via creator dashboard','2026-08-29 05:55:23',1,'Obin Admin'),(18,'course.removed','Course','Personal Finance Mastery for Beginners','repeated on the platform','2026-08-29 05:55:48',1,'Obin Admin'),(19,'course.deleted','Course','Crypto Trading Basics','via creator dashboard','2026-08-29 05:56:08',1,'Obin Admin'),(20,'course.removed','Course','Practical Web Development with PHP & MySQL','repeated copy right compliant','2026-08-29 05:56:53',1,'Obin Admin'),(21,'course.removed','Course','Small Business Accounting Fundamentals','repeated copy right compliant','2026-08-29 05:57:14',1,'Obin Admin'),(22,'course.removed','Course','Modern JavaScript & React Crash Course','repeated copy right compliant','2026-08-29 05:57:25',1,'Obin Admin'),(23,'course.removed','Course','Social Media Marketing That Sells','repeated copy right compliant','2026-08-29 05:57:50',1,'Obin Admin'),(24,'course.removed','Course','Building a Profitable Ecommerce Store in Uganda','repeated copy right compliant','2026-08-29 05:58:02',1,'Obin Admin'),(25,'creator_application.approved','User','Coach Obin',NULL,'2026-08-29 06:07:27',1,'Obin Admin'),(26,'course.approved','Course','Money Mastery For Young People',NULL,'2026-08-29 08:35:23',1,'Obin Admin'),(27,'course.removed','Course','Money Mastery For Young People','Repeated learner complaints about misleading course content and refusal to respond to support.','2026-08-29 09:47:30',1,'Obin Admin');
/*!40000 ALTER TABLE `audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `icon` varchar(60) NOT NULL DEFAULT 'sparkles',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Business','business','briefcase'),(2,'Finance','finance','wallet'),(3,'Technology & Software Development','technology-software-development','code'),(4,'Marketing & Digital Marketing','marketing-digital-marketing','megaphone'),(5,'Health & Wellness','health-wellness','heart'),(6,'Agriculture','agriculture','sprout'),(7,'Education & Teaching','education-teaching','graduation-cap'),(8,'Design & Creative','design-creative','palette'),(9,'Ecommerce','ecommerce','shopping-cart'),(10,'Artificial Intelligence','artificial-intelligence','cpu'),(12,'Tech','tech','sparkles');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `summary` varchar(500) NOT NULL,
  `description` text NOT NULL,
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `access_duration_days` int(11) DEFAULT NULL,
  `premium_price` decimal(12,2) DEFAULT NULL,
  `status` enum('DRAFT','PENDING_REVIEW','PUBLISHED','REJECTED','REMOVED') NOT NULL DEFAULT 'DRAFT',
  `rejection_reason` text DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `creator_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `category_id` (`category_id`),
  KEY `idx_courses_status` (`status`),
  KEY `idx_courses_creator` (`creator_id`),
  CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `courses_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courses`
--

LOCK TABLES `courses` WRITE;
/*!40000 ALTER TABLE `courses` DISABLE KEYS */;
INSERT INTO `courses` VALUES (1,'Personal Finance Mastery for Beginners','personal-finance-mastery-for-beginners','Take control of your money: budgeting, saving, and debt payoff using tools that work in Uganda.','A practical, judgment-free course on managing money as a working adult in Uganda — building a budget you\'ll actually stick to, saving with mobile money, and getting out of debt without giving up everything you enjoy.','/uploads/thumbnails/1e0f39f8-16e2-44f0-9d82-7f8d1154bb66.png',50000.00,365,NULL,'REMOVED','repeated on the platform','2026-06-16 09:32:03','2026-08-29 05:55:48','2026-06-16 09:32:03','2026-08-29 05:55:48',2,2),(2,'Practical Web Development with PHP & MySQL','practical-web-development-with-php-mysql','Build and deploy real, database-backed websites from scratch using PHP and MySQL.','Learn web development the practical way: PHP fundamentals, MySQL databases, forms, authentication, and deploying a real project to shared hosting — the same stack powering thousands of small business sites.','/uploads/thumbnails/2763a404-9aa8-4cca-85bd-842187d5b86a.webp',120000.00,NULL,180000.00,'REMOVED','repeated copy right compliant','2026-06-21 09:32:03','2026-08-29 05:56:53','2026-06-21 09:32:03','2026-08-29 05:56:53',3,3),(3,'Social Media Marketing That Sells','social-media-marketing-that-sells','Turn Instagram, Facebook and TikTok followers into paying customers — without a big budget.','A hands-on guide to marketing on the platforms your customers actually use: content that converts, running your first ad, and building a simple sales funnel for a small budget.','/uploads/thumbnails/5706bdc4-8794-4f67-9e74-790956858ec3.jpg',80000.00,180,NULL,'REMOVED','repeated copy right compliant','2026-06-26 09:32:03','2026-08-29 05:57:50','2026-06-26 09:32:03','2026-08-29 05:57:50',4,4),(4,'Small Business Accounting Fundamentals','small-business-accounting-fundamentals','Keep clean books, track profit, and prepare for tax season without hiring an accountant.','For shop owners, freelancers and small business operators: bookkeeping basics, tracking income and expenses, and understanding your numbers well enough to make better decisions.','/uploads/thumbnails/af6a781b-a4ba-4326-8028-618ad20899bb.jpeg',60000.00,365,NULL,'REMOVED','repeated copy right compliant','2026-07-01 09:32:03','2026-08-29 05:57:14','2026-07-01 09:32:03','2026-08-29 05:57:14',2,1),(5,'Modern JavaScript & React Crash Course','modern-javascript-react-crash-course','Go from JavaScript basics to building interactive React interfaces.','A fast-paced, project-driven crash course covering modern JavaScript (ES6+), the DOM, and building your first interactive UIs with React.','/uploads/thumbnails/b6baa699-7009-4c49-aa35-a63d6571ca37.jpg',2000.00,NULL,1000.00,'REMOVED','repeated copy right compliant','2026-07-06 09:32:03','2026-08-29 05:57:25','2026-07-06 09:32:03','2026-08-29 05:57:25',3,3),(6,'Building a Profitable Ecommerce Store in Uganda','building-a-profitable-ecommerce-store-in-uganda','Launch and grow an online store using local delivery and mobile money payments.','Everything you need to launch an online store selling to Ugandan customers: picking products, setting up payments and delivery, and your first month of marketing.','/uploads/thumbnails/e9998068-ad07-4d16-aa6f-f2f6192c19c9.png',2000.00,365,NULL,'REMOVED','repeated copy right compliant','2026-07-11 09:32:03','2026-08-29 05:58:02','2026-07-11 09:32:03','2026-08-29 05:58:02',4,9),(15,'The ChatGPT Money Machine','the-chatgpt-money-machine','How To Use ChatGPT to start multiple streams of income','1. ChatGPT Prompt Engineering\r\n2. Digital Product Creation\r\n3. Ai Content Creation','/uploads/thumbnails/af894c27e0783c962217761e1e4e5290.jpeg',2000.00,30,25000.00,'PUBLISHED',NULL,'2026-08-28 15:38:24','2026-08-28 15:39:05','2026-08-28 15:34:37','2026-08-29 03:58:21',9,10),(16,'Money Mastery For Young People','money-mastery-for-young-people','Money Mastery For Young People is a practical guide that helps young people build smart money habits, grow their income, and take control of their financial future early.','Money Mastery For Young People\r\n\r\nThe earlier you understand money, the more freedom you create for your future.\r\n\r\n\"Money Mastery For Young People\" is a practical, easy-to-follow guide for ambitious young people who want to stop struggling with money and start making smarter financial decisions. You will learn how to budget, save with purpose, avoid costly money mistakes, grow your income, and build habits that put you ahead of the crowd.\r\n\r\nThis is more than a course about saving—it is a roadmap to confidence, discipline, and financial independence. If you are ready to take control of your money before life gets more expensive, this course is your first powerful step.\r\n\r\nStart today and give your future self a reason to thank you.','/uploads/thumbnails/721556223485e8f6f689f620115f016e.png',5000.00,30,5000.00,'REMOVED','Repeated learner complaints about misleading course content and refusal to respond to support.','2026-08-29 08:34:37','2026-08-29 09:47:30','2026-08-29 08:28:59','2026-08-29 09:47:30',11,2);
/*!40000 ALTER TABLE `courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `creator_applications`
--

DROP TABLE IF EXISTS `creator_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `creator_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `status` enum('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  `expertise` text NOT NULL,
  `motivation` text NOT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `creator_applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `creator_applications`
--

LOCK TABLES `creator_applications` WRITE;
/*!40000 ALTER TABLE `creator_applications` DISABLE KEYS */;
INSERT INTO `creator_applications` VALUES (1,2,'APPROVED','Certified accountant (ACCA), 10+ years in personal finance coaching and SME bookkeeping.','I want to help everyday Ugandans build better money habits through practical, local-context courses.',NULL,'2026-05-27 09:32:03','2026-05-29 09:32:03'),(2,3,'APPROVED','Full-stack developer, 6 years building web apps for fintech and logistics companies in Kampala.','There is huge demand for practical dev skills here and not enough affordable, locally-relevant training.',NULL,'2026-05-27 09:32:03','2026-05-29 09:32:03'),(3,4,'APPROVED','Digital marketing strategist, ran marketing for two Kampala startups, certified in Meta & Google Ads.','Small businesses in Uganda are leaving money on the table by not knowing how to market online — I want to fix that.',NULL,'2026-05-27 09:32:03','2026-05-29 09:32:03'),(4,9,'APPROVED','Artificial Intelligence ( Claude Coding)','Taught 500+ Individuals Across The Globe',NULL,'2026-08-26 13:51:46','2026-08-26 13:52:23'),(6,5,'APPROVED','Personal Finance','15 Years In the Accounting And Banking Industries',NULL,'2026-08-28 15:22:01','2026-08-28 15:22:45'),(7,10,'APPROVED','Personal Finance','15 Years in banking and accounting',NULL,'2026-08-28 21:57:59','2026-08-28 21:59:41'),(8,11,'APPROVED','Personal Finance','Mentored Over 500 + Students In Accounting & Business',NULL,'2026-08-29 06:06:52','2026-08-29 06:07:27');
/*!40000 ALTER TABLE `creator_applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `earnings`
--

DROP TABLE IF EXISTS `earnings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `earnings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `amount` decimal(12,2) NOT NULL,
  `gross_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `platform_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `creator_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `creator_id` (`creator_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `earnings_ibfk_1` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `earnings_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `earnings`
--

LOCK TABLES `earnings` WRITE;
/*!40000 ALTER TABLE `earnings` DISABLE KEYS */;
INSERT INTO `earnings` VALUES (1,45000.00,50000.00,5000.00,'2026-08-05 09:32:03',2,1),(2,108000.00,120000.00,12000.00,'2026-07-21 09:32:03',3,2),(3,72000.00,80000.00,8000.00,'2026-08-22 09:32:03',4,3),(4,45000.00,50000.00,5000.00,'2026-07-16 09:32:03',2,1),(5,54000.00,60000.00,6000.00,'2026-08-10 09:32:03',2,4),(6,135000.00,150000.00,15000.00,'2026-08-24 09:32:03',3,5),(7,108000.00,120000.00,12000.00,'2026-08-17 09:32:03',3,2),(8,81000.00,90000.00,9000.00,'2026-08-03 09:32:03',4,6),(9,72000.00,80000.00,8000.00,'2026-07-31 09:32:03',4,3),(10,81000.00,90000.00,9000.00,'2026-08-20 09:32:03',4,6),(14,1800.00,2000.00,200.00,'2026-08-29 04:33:28',9,15),(15,1800.00,2000.00,200.00,'2026-08-29 04:56:27',9,15),(16,1800.00,2000.00,200.00,'2026-08-29 04:56:28',4,6),(17,1800.00,2000.00,200.00,'2026-08-29 05:03:14',3,5),(18,900.00,1000.00,100.00,'2026-08-29 05:04:04',3,5),(19,1800.00,2000.00,200.00,'2026-08-29 06:01:42',9,15),(20,4500.00,5000.00,500.00,'2026-08-29 08:40:35',11,16),(21,4500.00,5000.00,500.00,'2026-08-29 08:42:04',11,16);
/*!40000 ALTER TABLE `earnings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enrollments`
--

DROP TABLE IF EXISTS `enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `progress` decimal(5,2) NOT NULL DEFAULT 0.00,
  `enrolled_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `is_premium` tinyint(1) NOT NULL DEFAULT 0,
  `user_id` int(11) DEFAULT NULL,
  `course_id` int(11) NOT NULL,
  `guest_name` varchar(191) DEFAULT NULL,
  `guest_email` varchar(191) DEFAULT NULL,
  `access_token_hash` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_course` (`user_id`,`course_id`),
  UNIQUE KEY `uniq_access_token_hash` (`access_token_hash`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enrollments`
--

LOCK TABLES `enrollments` WRITE;
/*!40000 ALTER TABLE `enrollments` DISABLE KEYS */;
INSERT INTO `enrollments` VALUES (1,45.00,'2026-08-05 09:32:03','2027-08-25 09:32:03',0,5,1,NULL,NULL,NULL),(2,100.00,'2026-07-21 09:32:03',NULL,0,5,2,NULL,NULL,NULL),(3,10.00,'2026-08-22 09:32:03','2027-02-21 08:32:03',0,5,3,NULL,NULL,NULL),(4,100.00,'2026-07-16 09:32:03','2027-08-25 09:32:03',0,6,1,NULL,NULL,NULL),(5,60.00,'2026-08-10 09:32:03','2027-08-25 09:32:03',0,6,4,NULL,NULL,NULL),(6,0.00,'2026-08-24 09:32:03',NULL,1,6,5,NULL,NULL,NULL),(7,30.00,'2026-08-17 09:32:03',NULL,0,7,2,NULL,NULL,NULL),(8,75.00,'2026-08-03 09:32:03','2027-08-25 09:32:03',1,7,6,NULL,NULL,NULL),(9,100.00,'2026-07-31 09:32:03','2027-02-21 08:32:03',0,8,3,NULL,NULL,NULL),(10,20.00,'2026-08-20 09:32:03','2027-08-25 09:32:03',0,8,6,NULL,NULL,NULL),(14,0.00,'2026-08-29 04:33:28','2026-09-28 03:33:28',0,10,15,NULL,NULL,NULL),(15,0.00,'2026-08-29 04:56:27','2026-09-28 03:56:27',0,1,15,NULL,NULL,NULL),(16,0.00,'2026-08-29 04:56:28','2027-08-29 03:56:28',0,1,6,NULL,NULL,NULL),(17,0.00,'2026-08-29 05:03:14',NULL,1,10,5,NULL,NULL,NULL),(18,0.00,'2026-08-29 06:01:42','2026-09-28 05:01:42',0,11,15,NULL,NULL,NULL),(19,0.00,'2026-08-29 08:40:35','2026-09-28 07:40:35',1,10,16,NULL,NULL,NULL);
/*!40000 ALTER TABLE `enrollments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lessons`
--

DROP TABLE IF EXISTS `lessons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lessons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `type` enum('VIDEO','PDF') NOT NULL,
  `file_url` varchar(500) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `module_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `module_id` (`module_id`),
  CONSTRAINT `lessons_ibfk_1` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lessons`
--

LOCK TABLES `lessons` WRITE;
/*!40000 ALTER TABLE `lessons` DISABLE KEYS */;
INSERT INTO `lessons` VALUES (1,'Welcome & Course Overview','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,360,0,1),(2,'Setting Up Your First Budget','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,840,1,1),(3,'Budgeting Worksheet (Download)','PDF','https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf','budgeting-worksheet-download.pdf',NULL,2,1),(4,'Why You Should Save Before You Invest','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,660,0,2),(5,'Using Mobile Money to Save Automatically','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,540,1,2),(6,'Good Debt vs Bad Debt','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,480,0,3),(7,'Your Debt Payoff Plan','PDF','https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf','your-debt-payoff-plan.pdf',NULL,1,3),(8,'Course Introduction','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,300,0,4),(9,'Variables, Loops & Functions','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,1320,1,4),(10,'Working with Forms','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,1080,2,4),(11,'Designing Your First Schema','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,960,0,5),(12,'Connecting PHP to MySQL with PDO','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,1200,1,5),(13,'Schema Cheat Sheet','PDF','https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf','schema-cheat-sheet.pdf',NULL,2,5),(14,'Authentication & Sessions','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,1140,0,6),(15,'Deploying to Shared Hosting','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,780,1,6),(16,'Why Most Small Business Pages Fail','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,540,0,7),(17,'Picking the Right Platform for Your Business','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,720,1,7),(18,'Content Planning Template','PDF','https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf','content-planning-template.pdf',NULL,0,8),(19,'Shooting Product Photos on a Phone','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,900,1,8),(20,'Writing Captions That Sell','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,600,2,8),(21,'Meta Ads Manager Walkthrough','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,1260,0,9),(22,'Reading Your Ad Results','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,660,1,9),(23,'Why Bookkeeping Matters','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,420,0,10),(24,'Setting Up a Simple Ledger','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,780,1,10),(25,'Ledger Template','PDF','https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf','ledger-template.pdf',NULL,2,10),(26,'Profit vs Cash Flow','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,600,0,11),(27,'Preparing for Tax Season','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,840,1,11),(28,'ES6+ Essentials','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,1440,0,12),(29,'Working with Arrays & Objects','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,1020,1,12),(30,'Components & Props','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,1140,0,13),(31,'State & Events','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,1260,1,13),(32,'React Cheat Sheet','PDF','https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf','react-cheat-sheet.pdf',NULL,2,13),(33,'Choosing Products That Sell','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,720,0,14),(34,'Store Setup Walkthrough','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,1080,1,14),(35,'Accepting Mobile Money Payments','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,540,0,15),(36,'Working with Local Delivery Riders','VIDEO','https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',NULL,480,1,15),(37,'Delivery Cost Calculator','PDF','https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf','delivery-cost-calculator.pdf',NULL,2,15),(41,'Mastering The ChatGPT Prompt Engineering','PDF','pdfs/e140b5c59a84356fb73315465a505f97.pdf','HOW TO MAKE YOUR FIRST $1000 ONLINE USING CHATGPT AND TIKTOK.pdf',NULL,0,20),(42,'Understanding Money','PDF','pdfs/77cf444600bca4b5c894ac2eb669f518.pdf','Money_Mastery_For_Young_People_By_Coach_Obin Obin Finance University.pdf',NULL,0,21);
/*!40000 ALTER TABLE `lessons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `modules`
--

DROP TABLE IF EXISTS `modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `modules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `course_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `modules_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modules`
--

LOCK TABLES `modules` WRITE;
/*!40000 ALTER TABLE `modules` DISABLE KEYS */;
INSERT INTO `modules` VALUES (1,'Getting Started with Budgeting',0,1),(2,'Saving & Investing Basics',1,1),(3,'Getting Out of Debt',2,1),(4,'PHP Fundamentals',0,2),(5,'Databases with MySQL',1,2),(6,'Shipping to Production',2,2),(7,'Foundations',0,3),(8,'Content That Converts',1,3),(9,'Running Your First Ad',2,3),(10,'Bookkeeping Basics',0,4),(11,'Understanding Your Numbers',1,4),(12,'Modern JavaScript',0,5),(13,'React Basics',1,5),(14,'Setting Up Your Store',0,6),(15,'Payments & Delivery',1,6),(20,'Introduction to THE ChatGPT Money Machine',0,15),(21,'Introduction To Money Mastery For Young People',0,16);
/*!40000 ALTER TABLE `modules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `iotec_transaction_id` varchar(191) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `phone` varchar(32) NOT NULL,
  `type` enum('COURSE_PURCHASE','PREMIUM_UPGRADE') NOT NULL DEFAULT 'COURSE_PURCHASE',
  `status` enum('PENDING','SUCCESS','FAILED') NOT NULL DEFAULT 'PENDING',
  `status_message` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `course_id` int(11) NOT NULL,
  `guest_name` varchar(191) DEFAULT NULL,
  `guest_email` varchar(191) DEFAULT NULL,
  `access_token_hash` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `iotec_transaction_id` (`iotec_transaction_id`),
  UNIQUE KEY `uniq_access_token_hash` (`access_token_hash`),
  KEY `course_id` (`course_id`),
  KEY `idx_payments_user_course_status` (`user_id`,`course_id`,`status`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,'DEMO-988106CECF82',50000.00,'+256700000001','COURSE_PURCHASE','SUCCESS','Payment completed.','2026-08-05 09:32:03','2026-08-25 10:32:03',5,1,NULL,NULL,NULL),(2,'DEMO-68BF25380219',120000.00,'+256700000003','COURSE_PURCHASE','SUCCESS','Payment completed.','2026-07-21 09:32:03','2026-08-25 10:32:03',5,2,NULL,NULL,NULL),(3,'DEMO-A89BDC73704D',80000.00,'+256700000005','COURSE_PURCHASE','SUCCESS','Payment completed.','2026-08-22 09:32:03','2026-08-25 10:32:03',5,3,NULL,NULL,NULL),(4,'DEMO-351FA64D8771',50000.00,'+256700000007','COURSE_PURCHASE','SUCCESS','Payment completed.','2026-07-16 09:32:03','2026-08-25 10:32:03',6,1,NULL,NULL,NULL),(5,'DEMO-635683C4E2A4',60000.00,'+256700000007','COURSE_PURCHASE','SUCCESS','Payment completed.','2026-08-10 09:32:03','2026-08-25 10:32:03',6,4,NULL,NULL,NULL),(6,'DEMO-977FDF616722',150000.00,'+256700000002','COURSE_PURCHASE','SUCCESS','Payment completed.','2026-08-24 09:32:03','2026-08-25 10:32:03',6,5,NULL,NULL,NULL),(7,'DEMO-343C80F35F43',120000.00,'+256700000001','COURSE_PURCHASE','SUCCESS','Payment completed.','2026-08-17 09:32:03','2026-08-25 10:32:03',7,2,NULL,NULL,NULL),(8,'DEMO-295E040713CD',90000.00,'+256700000001','COURSE_PURCHASE','SUCCESS','Payment completed.','2026-08-03 09:32:03','2026-08-25 10:32:03',7,6,NULL,NULL,NULL),(9,'DEMO-82DB229E10C6',80000.00,'+256700000001','COURSE_PURCHASE','SUCCESS','Payment completed.','2026-07-31 09:32:03','2026-08-25 10:32:03',8,3,NULL,NULL,NULL),(10,'DEMO-AA8307DE2067',90000.00,'+256700000004','COURSE_PURCHASE','SUCCESS','Payment completed.','2026-08-20 09:32:03','2026-08-25 10:32:03',8,6,NULL,NULL,NULL),(11,NULL,150000.00,'0775361998','COURSE_PURCHASE','FAILED','iotec collection request failed (400): {\"code\":\"BadRequest\",\"message\":\"Using real number on test wallet\"}','2026-08-25 11:24:16','2026-08-25 11:24:18',5,5,NULL,NULL,NULL),(12,NULL,90000.00,'0775361998','COURSE_PURCHASE','FAILED','iotec collection request failed (400): {\"code\":\"BadRequest\",\"message\":\"Using real number on test wallet\"}','2026-08-26 07:10:55','2026-08-26 07:10:58',9,6,NULL,NULL,NULL),(14,NULL,80000.00,'0775361998','COURSE_PURCHASE','FAILED','iotec collection request failed (400): {\"code\":\"BadRequest\",\"message\":\"Using real number on test wallet\"}','2026-08-27 09:23:53','2026-08-27 09:23:55',NULL,3,'obin Ivan','ivaniinocentobin@gmail.com','dc938f204427e36ba9055c06ad04a9fbc300b561bcba74c79a183de5313b045e'),(15,NULL,50000.00,'0775361998','COURSE_PURCHASE','FAILED','iotec collection request failed (400): {\"code\":\"BadRequest\",\"message\":\"Using real number on test wallet\"}','2026-08-27 09:46:11','2026-08-27 09:46:13',NULL,1,'Obin Academy','obinacademy@gmail.com','33e34c357969fe104916b25bc99130f77c38d88dd996bb36846756580ed73c8c'),(17,NULL,80000.00,'077561998','COURSE_PURCHASE','FAILED','iotec collection request failed (400): {\"code\":\"BadRequest\",\"message\":\"Invalid Payer 25677561998\"}','2026-08-28 13:36:57','2026-08-28 13:36:59',NULL,3,'obin','ivaninnocentobin@gmail.com','98969126ffb884b89f29e79ac749aca1169d98f3a3f024aa7c117cb53c35005c'),(18,NULL,80000.00,'0775361998','COURSE_PURCHASE','FAILED','iotec collection request failed (400): {\"code\":\"BadRequest\",\"message\":\"Using real number on test wallet\"}','2026-08-28 21:56:12','2026-08-28 21:56:19',10,3,NULL,NULL,NULL),(19,'01a04b00-153a-7224-a06a-4c4fbf93f7ec',50000.00,'0775361998','COURSE_PURCHASE','FAILED','Insufficient funds','2026-08-29 03:51:38','2026-08-29 04:33:27',NULL,1,'Obin Academy','obinacademy@gmail.com','a31f2fbeb891f76fd0435b821d4f3a45d48f3df374f80f5479332b2ac4b8ed3b'),(20,'01a04b06-e72e-774a-95a7-7bb20e0ed445',2000.00,'0775361998','COURSE_PURCHASE','SUCCESS','Request successfully completed','2026-08-29 03:59:05','2026-08-29 04:33:28',10,15,NULL,NULL,NULL),(21,'01a04b2a-555b-77c2-b172-e92ade2cf334',2000.00,'0775361998','COURSE_PURCHASE','SUCCESS','Request successfully completed','2026-08-29 04:37:47','2026-08-29 04:56:27',1,15,NULL,NULL,NULL),(22,'01a04b35-bf64-770e-aab3-61815c2d9dd5',2000.00,'0775361998','COURSE_PURCHASE','SUCCESS','Request successfully completed','2026-08-29 04:50:15','2026-08-29 04:56:28',1,6,NULL,NULL,NULL),(23,'01a04b3d-ed02-7255-98dc-d7895cd2306a',25000.00,'0775361998','PREMIUM_UPGRADE','PENDING',NULL,'2026-08-29 04:59:11','2026-08-29 04:59:12',1,15,NULL,NULL,NULL),(24,'01a04b41-0327-74bc-9674-83cc48f012d8',2000.00,'0775361998','COURSE_PURCHASE','SUCCESS','Request successfully completed','2026-08-29 05:02:33','2026-08-29 05:03:14',10,5,NULL,NULL,NULL),(25,'01a04b42-35c8-709b-8372-da04bb5ddb7b',1000.00,'0775361998','PREMIUM_UPGRADE','SUCCESS','Request successfully completed','2026-08-29 05:03:52','2026-08-29 05:04:04',10,5,NULL,NULL,NULL),(26,'01a04b76-d716-7741-ba8c-a0e7a5498a5b',2000.00,'0775361998','COURSE_PURCHASE','SUCCESS','Request successfully completed','2026-08-29 06:01:21','2026-08-29 06:01:42',11,15,NULL,NULL,NULL),(27,'01a04c08-64f2-7582-b348-14e61e1492ac',5000.00,'0775361998','COURSE_PURCHASE','SUCCESS','Request successfully completed','2026-08-29 08:40:12','2026-08-29 08:40:35',10,16,NULL,NULL,NULL),(28,'01a04c09-c446-7383-8d4d-ac1ed70ef8fc',5000.00,'0775361998','PREMIUM_UPGRADE','SUCCESS','Request successfully completed','2026-08-29 08:41:42','2026-08-29 08:42:04',10,16,NULL,NULL,NULL);
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rating` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `course_id` int(11) NOT NULL,
  `author_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_course_author` (`course_id`,`author_id`),
  KEY `author_id` (`author_id`),
  CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviews`
--

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
INSERT INTO `reviews` VALUES (1,5,'Best PHP course I\'ve found for Ugandan developers — practical, no fluff, and David actually explains the \'why\'. Deployed my first real site after this.','2026-08-25 10:32:03','2026-08-25 10:32:03',2,5),(2,5,'Changed how I think about my salary. The mobile money saving trick alone paid for the course in a month.','2026-08-25 10:32:03','2026-08-25 10:32:03',1,6),(3,4,'Great practical content, especially the ad walkthrough. Would love more examples for service-based businesses next.','2026-08-25 10:32:03','2026-08-25 10:32:03',3,8);
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `testimonials`
--

DROP TABLE IF EXISTS `testimonials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `testimonials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quote` text NOT NULL,
  `rating` int(11) NOT NULL DEFAULT 5,
  `status` enum('PENDING_REVIEW','PUBLISHED','REJECTED') NOT NULL DEFAULT 'PENDING_REVIEW',
  `rejection_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  `author_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `author_id` (`author_id`),
  CONSTRAINT `testimonials_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `testimonials`
--

LOCK TABLES `testimonials` WRITE;
/*!40000 ALTER TABLE `testimonials` DISABLE KEYS */;
INSERT INTO `testimonials` VALUES (1,'Obin Academy helped me finally get my budget under control. The lessons feel like they were made for how we actually live in Uganda, not a copy-paste American course.',5,'PUBLISHED',NULL,'2026-08-19 09:32:03','2026-08-20 09:32:03',6),(2,'I went from barely knowing HTML to shipping a working PHP site for my cousin\'s shop. David\'s course is worth way more than the price.',5,'PUBLISHED',NULL,'2026-08-20 09:32:03','2026-08-21 09:32:03',5),(3,'Enrolled in the marketing course to help my sister\'s boutique — within a month our Instagram orders doubled.',5,'PUBLISHED',NULL,'2026-08-21 09:32:03','2026-08-22 09:32:03',8),(4,'I was this broke guy without any skill, when i mastered Digital Marketing, wewe i made 12,000,000 Ugx In less than 4 months',5,'PENDING_REVIEW',NULL,'2026-08-26 22:54:47',NULL,5);
/*!40000 ALTER TABLE `testimonials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `email` varchar(191) NOT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('LEARNER','CREATOR','ADMIN') NOT NULL DEFAULT 'LEARNER',
  `headline` varchar(191) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `avatar_url` varchar(500) DEFAULT NULL,
  `facebook_url` varchar(500) DEFAULT NULL,
  `instagram_url` varchar(500) DEFAULT NULL,
  `youtube_url` varchar(500) DEFAULT NULL,
  `tiktok_url` varchar(500) DEFAULT NULL,
  `linkedin_url` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Obin Admin','admin@obinacademy.com','+256700000001','$2y$10$jc8SZ9cZ6N6pFxH/y0SjKOG/iThLKEOrW7/jKJqfFrOXLdENI1e06','ADMIN','Platform Administrator',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-27 09:32:03'),(2,'Sarah Namuli','sarah.namuli@obinacademy.com','+256700000002','$2y$10$jc8SZ9cZ6N6pFxH/y0SjKOG/iThLKEOrW7/jKJqfFrOXLdENI1e06','CREATOR','Financial Literacy Coach & Certified Accountant','I\'m a certified accountant with 10+ years helping Ugandan families and small businesses take control of their money. I teach practical, no-jargon personal finance and bookkeeping.',NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-22 09:32:03'),(3,'David Okello','david.okello@obinacademy.com','+256700000003','$2y$10$jc8SZ9cZ6N6pFxH/y0SjKOG/iThLKEOrW7/jKJqfFrOXLdENI1e06','CREATOR','Full-Stack Developer & Tech Educator','Software engineer building fintech products across East Africa. I teach practical web development so more Ugandans can build careers in tech.',NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-27 09:32:03'),(4,'Grace Atim','grace.atim@obinacademy.com','+256700000004','$2y$10$jc8SZ9cZ6N6pFxH/y0SjKOG/iThLKEOrW7/jKJqfFrOXLdENI1e06','CREATOR','Digital Marketing Strategist','Helping small businesses grow through smart, affordable social media marketing.',NULL,'https://facebook.com/graceatim','https://instagram.com/graceatim','https://youtube.com/@graceatim','https://tiktok.com/@graceatim','https://linkedin.com/in/graceatim','2026-06-06 09:32:03'),(5,'John Mukasa','john.mukasa@example.com','+256700000005','$2y$10$jc8SZ9cZ6N6pFxH/y0SjKOG/iThLKEOrW7/jKJqfFrOXLdENI1e06','CREATOR',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-26 09:32:03'),(6,'Mary Nabirye','mary.nabirye@example.com','+256700000006','$2y$10$jc8SZ9cZ6N6pFxH/y0SjKOG/iThLKEOrW7/jKJqfFrOXLdENI1e06','LEARNER',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-06 09:32:03'),(7,'Peter Ssekandi','peter.ssekandi@example.com','+256700000007','$2y$10$jc8SZ9cZ6N6pFxH/y0SjKOG/iThLKEOrW7/jKJqfFrOXLdENI1e06','LEARNER',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-16 09:32:03'),(8,'Grace Achieng','grace.achieng@example.com','+256700000008','$2y$10$jc8SZ9cZ6N6pFxH/y0SjKOG/iThLKEOrW7/jKJqfFrOXLdENI1e06','LEARNER',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-26 09:32:03'),(9,'Opio Chris','obinacademyy@gmail.com','0775361998','$2y$10$naBexFzLl5eQyOYmAJD/DOenrWOz8wZVlHLdAESTCwqC9isHoL89a','CREATOR',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-26 07:09:57'),(10,'Warom Hurry','waromhurry@gmail.com','0747188116','$2y$10$ui6gVKKJ8/bGguElgslDC.HxqhAr5y0P7odPqdeC9U7LIdxsWgrK6','CREATOR',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-28 21:53:41'),(11,'Coach Obin','ivaninnocentobin@gmail.com','0775361998','$2y$10$K7yknEslNFx1jUrY6Z/aZ.uV1yZSAV//SgsklMve2wiWVm6NOHygS','CREATOR',NULL,'I have Mentored Over 500 + Business Owners, Start Ups, Leaders, In the field of Finance, Business, Technology and  Artificial Intelligence','/uploads/thumbnails/82b780e6d399a386cb758ff77bdb56f4.jpeg','https://www.facebook.com/profile.php?id=61591414895842','https://www.instagram.com/obinacademyofficial/?hl=en','https://www.youtube.com/@obinacademy','https://www.tiktok.com/@obin.academyofficial','https://www.linkedin.com/in/obin-ivan-innocent-446875291/','2026-08-29 06:00:46');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `withdrawal_requests`
--

DROP TABLE IF EXISTS `withdrawal_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `withdrawal_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `amount` decimal(12,2) NOT NULL,
  `phone` varchar(32) NOT NULL DEFAULT '',
  `status` enum('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  `note` varchar(500) DEFAULT NULL,
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL,
  `creator_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `creator_id` (`creator_id`),
  CONSTRAINT `withdrawal_requests_ibfk_1` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `withdrawal_requests`
--

LOCK TABLES `withdrawal_requests` WRITE;
/*!40000 ALTER TABLE `withdrawal_requests` DISABLE KEYS */;
INSERT INTO `withdrawal_requests` VALUES (1,150000.00,'+256700000003','APPROVED',NULL,'2026-08-24 09:32:03','2026-08-26 17:16:35',3);
/*!40000 ALTER TABLE `withdrawal_requests` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-29 10:35:22
