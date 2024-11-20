-- MySQL dump 10.13  Distrib 8.0.30, for Win64 (x86_64)
--
-- Host: localhost    Database: db
-- ------------------------------------------------------
-- Server version	8.0.28

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `Config`
--

DROP TABLE IF EXISTS `Config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Config` (
  `config_id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `value` text,
  `readonly` tinyint(1) NOT NULL DEFAULT '0',
  `can_delete` tinyint(1) NOT NULL DEFAULT '1',
  `remark` text,
  `type` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`config_id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=97 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Config`
--

LOCK TABLES `Config` WRITE;
/*!40000 ALTER TABLE `Config` DISABLE KEYS */;
INSERT INTO `Config` VALUES (42,'authentication_lock_time','180',0,1,NULL,NULL),(43,'company','HostLink',0,1,NULL,NULL),(44,'company_logo','https://raw.githubusercontent.com/HostLink/.github/master/profile/logo.webp',0,1,NULL,NULL),(45,'two_step_verification','0',0,1,NULL,NULL),(46,'biometric_authentication','0',0,1,NULL,NULL),(48,'copyright_url','https://www.hostlink.com.hk',0,1,NULL,NULL),(49,'copyright_name','HostLink',0,1,NULL,NULL),(50,'login_version','v1',0,1,NULL,NULL),(61,'file_manager_show','0',0,1,NULL,NULL),(62,'allow_remember_me','0',0,1,NULL,NULL),(63,'vx_url',NULL,0,1,NULL,NULL),(64,'company_url','https://www.hostlink.com.hk',0,1,NULL,NULL),(65,'mail_from',NULL,0,1,NULL,NULL),(66,'jwt_blacklist','1',0,1,NULL,NULL),(67,'two_step_verification_whitelist',NULL,0,1,NULL,NULL),(68,'file_manager_preview','0',0,1,NULL,NULL),(69,'css',NULL,0,1,NULL,NULL),(70,'js',NULL,0,1,NULL,NULL),(72,'access_token_expire','3600',0,1,NULL,NULL),(73,'copyright_year','2023',0,1,NULL,NULL),(74,'search-form','0',0,1,NULL,NULL),(75,'domain',NULL,0,1,NULL,NULL),(76,'smtp',NULL,0,1,NULL,NULL),(77,'smtp-username',NULL,0,1,NULL,NULL),(78,'smtp-password',NULL,0,1,NULL,NULL),(79,'smtp-auto-tls','1',0,1,NULL,NULL),(80,'return-path',NULL,0,1,NULL,NULL),(81,'password-length','6',0,1,NULL,NULL),(82,'log-save','1',0,1,NULL,NULL),(83,'menu_width','280',0,1,NULL,NULL),(84,'theme_customizer','1',0,1,NULL,NULL),(88,'authentication_lock','1',0,1,NULL,NULL),(89,'password_length',NULL,0,1,NULL,NULL),(90,'password_upper_case','1',0,1,NULL,NULL),(91,'password_lower_case','1',0,1,NULL,NULL),(92,'password_number','1',0,1,NULL,NULL),(93,'password_special_character','1',0,1,NULL,NULL),(94,'menus','[]',0,1,NULL,NULL),(95,'two_factor_authentication','0',0,1,NULL,NULL),(96,'file_manager','1',0,1,NULL,NULL);
/*!40000 ALTER TABLE `Config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `EventLog`
--

DROP TABLE IF EXISTS `EventLog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `EventLog` (
  `eventlog_id` int unsigned NOT NULL AUTO_INCREMENT,
  `class` varchar(64) DEFAULT NULL,
  `id` int unsigned NOT NULL,
  `action` varchar(64) DEFAULT NULL,
  `source` json DEFAULT NULL,
  `target` json DEFAULT NULL,
  `remark` text,
  `user_id` int unsigned DEFAULT NULL,
  `created_time` datetime NOT NULL,
  `status` int unsigned DEFAULT '0',
  `different` json DEFAULT NULL,
  PRIMARY KEY (`eventlog_id`),
  KEY `class` (`class`),
  KEY `user_id` (`user_id`),
  KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `EventLog`
--

LOCK TABLES `EventLog` WRITE;
/*!40000 ALTER TABLE `EventLog` DISABLE KEYS */;
/*!40000 ALTER TABLE `EventLog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `MailLog`
--

DROP TABLE IF EXISTS `MailLog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MailLog` (
  `maillog_id` int unsigned NOT NULL AUTO_INCREMENT,
  `created_time` datetime DEFAULT NULL,
  `from` varchar(255) DEFAULT NULL,
  `to` varchar(255) DEFAULT NULL,
  `body` text,
  `subject` varchar(255) DEFAULT NULL,
  `from_name` varchar(255) DEFAULT NULL,
  `to_name` varchar(255) DEFAULT NULL,
  `altbody` text,
  `host` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`maillog_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `MailLog`
--

LOCK TABLES `MailLog` WRITE;
/*!40000 ALTER TABLE `MailLog` DISABLE KEYS */;
/*!40000 ALTER TABLE `MailLog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Permission`
--

DROP TABLE IF EXISTS `Permission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Permission` (
  `permission_id` int NOT NULL AUTO_INCREMENT,
  `role` varchar(255) DEFAULT NULL,
  `value` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Permission`
--

LOCK TABLES `Permission` WRITE;
/*!40000 ALTER TABLE `Permission` DISABLE KEYS */;
/*!40000 ALTER TABLE `Permission` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Role`
--

DROP TABLE IF EXISTS `Role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Role` (
  `role_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `child` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`role_id`),
  KEY `name` (`name`),
  KEY `child` (`child`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Role`
--

LOCK TABLES `Role` WRITE;
/*!40000 ALTER TABLE `Role` DISABLE KEYS */;
/*!40000 ALTER TABLE `Role` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `SystemValue`
--

DROP TABLE IF EXISTS `SystemValue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SystemValue` (
  `systemvalue_id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) DEFAULT NULL,
  `value` text,
  `status` int unsigned NOT NULL DEFAULT '0',
  `can_delete` tinyint(1) NOT NULL DEFAULT '1',
  `language` varchar(5) DEFAULT NULL,
  PRIMARY KEY (`systemvalue_id`),
  KEY `language` (`language`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `SystemValue`
--

LOCK TABLES `SystemValue` WRITE;
/*!40000 ALTER TABLE `SystemValue` DISABLE KEYS */;
/*!40000 ALTER TABLE `SystemValue` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Translate`
--

DROP TABLE IF EXISTS `Translate`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Translate` (
  `translate_id` int unsigned NOT NULL AUTO_INCREMENT,
  `module` varchar(255) DEFAULT NULL,
  `language` varchar(5) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `value` varchar(255) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`translate_id`),
  KEY `language` (`language`),
  KEY `name` (`name`),
  KEY `action` (`action`),
  KEY `module` (`module`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Translate`
--

LOCK TABLES `Translate` WRITE;
/*!40000 ALTER TABLE `Translate` DISABLE KEYS */;
/*!40000 ALTER TABLE `Translate` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `User`
--

DROP TABLE IF EXISTS `User`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `User` (
  `user_id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `theme` varchar(255) DEFAULT NULL,
  `addr1` varchar(255) DEFAULT NULL,
  `addr2` varchar(255) DEFAULT NULL,
  `addr3` varchar(255) DEFAULT NULL,
  `status` tinyint unsigned NOT NULL DEFAULT '0',
  `type` int unsigned NOT NULL DEFAULT '0',
  `can_delete` tinyint unsigned NOT NULL DEFAULT '1',
  `language` varchar(5) DEFAULT NULL,
  `default_page` varchar(255) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `join_date` date NOT NULL,
  `inactive` tinyint(1) NOT NULL DEFAULT '0',
  `openid` tinyint(1) NOT NULL DEFAULT '0',
  `secret` varchar(16) DEFAULT NULL,
  `style` json DEFAULT NULL,
  `setting` json DEFAULT NULL,
  `bs_theme` varchar(255) DEFAULT NULL,
  `skin` varchar(255) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `credential` json DEFAULT NULL,
  `last_online` datetime DEFAULT NULL,
  `access_token` varchar(255) DEFAULT NULL,
  `photo` blob,
  `credential_creation_options` json DEFAULT NULL,
  `credential_request_options` json DEFAULT NULL,
  `two_step` json DEFAULT NULL,
  `gmail` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  KEY `inactive` (`inactive`),
  KEY `openid` (`openid`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;


--
-- Table structure for table `UserLog`
--

DROP TABLE IF EXISTS `UserLog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `UserLog` (
  `userlog_id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `login_dt` datetime NOT NULL,
  `logout_dt` datetime DEFAULT NULL,
  `ip` varchar(15) DEFAULT NULL,
  `result` enum('SUCCESS','FAIL') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `user_agent` text,
  PRIMARY KEY (`userlog_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `FK_UserLog_1` FOREIGN KEY (`user_id`) REFERENCES `User` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `UserLog`
--

LOCK TABLES `UserLog` WRITE;
/*!40000 ALTER TABLE `UserLog` DISABLE KEYS */;
/*!40000 ALTER TABLE `UserLog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `UserRole`
--

DROP TABLE IF EXISTS `UserRole`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `UserRole` (
  `user_role_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `role` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`user_role_id`),
  UNIQUE KEY `user_id_role` (`user_id`,`role`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2023-11-13 15:57:15
