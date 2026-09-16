-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Versión del servidor:         8.0.30 - MySQL Community Server - GPL
-- SO del servidor:              Win64
-- HeidiSQL Versión:             12.10.0.7051
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Volcando estructura para tabla sae8206.cache
DROP TABLE IF EXISTS `cache`;
CREATE TABLE IF NOT EXISTS `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.cache: ~0 rows (aproximadamente)
DELETE FROM `cache`;

-- Volcando estructura para tabla sae8206.cache_locks
DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.cache_locks: ~0 rows (aproximadamente)
DELETE FROM `cache_locks`;

-- Volcando estructura para tabla sae8206.careers
DROP TABLE IF EXISTS `careers`;
CREATE TABLE IF NOT EXISTS `careers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resolution` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allow_enrollments` tinyint(1) NOT NULL DEFAULT '1',
  `allow_evaluations` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.careers: ~0 rows (aproximadamente)
DELETE FROM `careers`;

-- Volcando estructura para tabla sae8206.career_user
DROP TABLE IF EXISTS `career_user`;
CREATE TABLE IF NOT EXISTS `career_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `career_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `career_user_user_id_foreign` (`user_id`),
  KEY `career_user_career_id_foreign` (`career_id`),
  CONSTRAINT `career_user_career_id_foreign` FOREIGN KEY (`career_id`) REFERENCES `careers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `career_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.career_user: ~0 rows (aproximadamente)
DELETE FROM `career_user`;

-- Volcando estructura para tabla sae8206.class_sessions
DROP TABLE IF EXISTS `class_sessions`;
CREATE TABLE IF NOT EXISTS `class_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `subject_id` bigint unsigned NOT NULL,
  `date` date NOT NULL,
  `teacher_id` bigint unsigned DEFAULT NULL,
  `class_number` smallint unsigned NOT NULL,
  `unit` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `activities` text COLLATE utf8mb4_unicode_ci,
  `observations` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `class_sessions_subject_id_foreign` (`subject_id`),
  KEY `class_sessions_teacher_id_foreign` (`teacher_id`),
  CONSTRAINT `class_sessions_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `class_sessions_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.class_sessions: ~0 rows (aproximadamente)
DELETE FROM `class_sessions`;

-- Volcando estructura para tabla sae8206.configs
DROP TABLE IF EXISTS `configs`;
CREATE TABLE IF NOT EXISTS `configs` (
  `id` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `group` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `configs_group_index` (`group`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.configs: ~15 rows (aproximadamente)
DELETE FROM `configs`;
INSERT INTO `configs` (`id`, `group`, `description`, `type`, `value`, `created_at`, `updated_at`) VALUES
	('admin@admin.com-paybox', 'payboxes', 'Contador de recibos del Usuarios habilitado', 'text', '1', '2021-08-31 01:16:53', '2021-08-31 05:13:51'),
	('cycle', 'main', 'Año Ciclo Lectivo', 'text', '2026', '2024-01-19 22:48:30', '2026-02-20 17:35:54'),
	('exams', 'institute', 'Inscripciones a Mesas de Julio', 'bool', 'false', NULL, '2023-07-30 19:45:06'),
	('inscriptions', 'iconfigsnstitute', 'Inscripciones al Ciclo Lectivo', 'bool', 'false', NULL, '2021-10-15 04:25:46'),
	('label_career', 'labels', 'Etiqueta para Carrera/Año', 'text', 'Carrera', '2026-02-20 17:18:25', '2026-02-20 17:18:25'),
	('label_careers', 'labels', 'Etiqueta para Carreras/Años (Plural)', 'text', 'Carreras', '2026-02-20 17:18:25', '2026-02-20 17:18:25'),
	('label_subject', 'labels', 'Etiqueta para Materia', 'text', 'Materia', '2026-02-20 17:18:25', '2026-02-20 17:18:25'),
	('label_subjects', 'labels', 'Etiqueta para Materias (Plural)', 'text', 'Materias', '2026-02-20 17:18:25', '2026-02-20 17:18:25'),
	('longname', 'main', 'Nombre Largo', 'text', '"Inno Design"', NULL, '2021-01-12 20:24:56'),
	('mesasExamen', 'inscriptions', 'Inscripciones a Mesas de Examen', 'bool', 'false', NULL, '2026-02-20 15:59:29'),
	('modalities', 'institute', 'Inscripción a Modalidad de Cursado', 'bool', 'false', NULL, NULL),
	('modality', 'inscriptions', 'Inscripción a Modalidades', 'bool', 'false', NULL, '2025-08-18 18:41:43'),
	('prefix', 'main', 'Prefijo para archivos generados por el sistema', 'text', '2026', NULL, '2021-01-25 20:51:47'),
	('promoDirecta', 'inscriptions', 'Inscripciones a Promoción Directa', 'bool', 'false', NULL, '2023-02-03 04:09:42'),
	('shortname', 'main', 'Nombre corto', 'text', 'ID 2026', NULL, '2021-01-25 20:51:53');

-- Volcando estructura para tabla sae8206.enrollments
DROP TABLE IF EXISTS `enrollments`;
CREATE TABLE IF NOT EXISTS `enrollments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `subject_id` bigint unsigned NOT NULL,
  `status` enum('active','completed','withdrawn') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `modalities_id` bigint unsigned DEFAULT NULL,
  `observations` text COLLATE utf8mb4_unicode_ci,
  `attendance_percentage` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `enrollments_user_id_foreign` (`user_id`),
  KEY `enrollments_subject_id_foreign` (`subject_id`),
  KEY `enrollments_modalities_id_foreign` (`modalities_id`),
  CONSTRAINT `enrollments_modalities_id_foreign` FOREIGN KEY (`modalities_id`) REFERENCES `modalities` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `enrollments_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `enrollments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.enrollments: ~0 rows (aproximadamente)
DELETE FROM `enrollments`;

-- Volcando estructura para tabla sae8206.events
DROP TABLE IF EXISTS `events`;
CREATE TABLE IF NOT EXISTS `events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start` datetime NOT NULL,
  `end` datetime NOT NULL,
  `color` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#000000',
  `user_id` bigint unsigned NOT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `presidente_id` bigint unsigned DEFAULT NULL,
  `vocal1_id` bigint unsigned DEFAULT NULL,
  `vocal2_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `events_user_id_foreign` (`user_id`),
  KEY `events_subject_id_foreign` (`subject_id`),
  KEY `events_presidente_id_foreign` (`presidente_id`),
  KEY `events_vocal1_id_foreign` (`vocal1_id`),
  KEY `events_vocal2_id_foreign` (`vocal2_id`),
  CONSTRAINT `events_presidente_id_foreign` FOREIGN KEY (`presidente_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `events_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `events_vocal1_id_foreign` FOREIGN KEY (`vocal1_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `events_vocal2_id_foreign` FOREIGN KEY (`vocal2_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.events: ~0 rows (aproximadamente)
DELETE FROM `events`;

-- Volcando estructura para tabla sae8206.failed_jobs
DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE IF NOT EXISTS `failed_jobs` (
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

-- Volcando datos para la tabla sae8206.failed_jobs: ~0 rows (aproximadamente)
DELETE FROM `failed_jobs`;

-- Volcando estructura para tabla sae8206.grades
DROP TABLE IF EXISTS `grades`;
CREATE TABLE IF NOT EXISTS `grades` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `class_session_id` bigint unsigned DEFAULT NULL,
  `grade` tinyint unsigned NOT NULL DEFAULT '0',
  `approved` tinyint(1) NOT NULL DEFAULT '0',
  `attendance` tinyint unsigned NOT NULL DEFAULT '0',
  `comments` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_class_session_user` (`class_session_id`,`user_id`),
  KEY `grades_user_id_foreign` (`user_id`),
  CONSTRAINT `grades_class_session_id_foreign` FOREIGN KEY (`class_session_id`) REFERENCES `class_sessions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `grades_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.grades: ~0 rows (aproximadamente)
DELETE FROM `grades`;

-- Volcando estructura para tabla sae8206.inscriptions
DROP TABLE IF EXISTS `inscriptions`;
CREATE TABLE IF NOT EXISTS `inscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `subject_id` bigint unsigned NOT NULL,
  `configs_id` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('csv-1','csv-n','text','bool','int') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'csv-1',
  `value` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_subject_user_config` (`subject_id`,`user_id`,`configs_id`),
  KEY `inscriptions_user_id_foreign` (`user_id`),
  CONSTRAINT `inscriptions_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `inscriptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.inscriptions: ~0 rows (aproximadamente)
DELETE FROM `inscriptions`;

-- Volcando estructura para tabla sae8206.installments
DROP TABLE IF EXISTS `installments`;
CREATE TABLE IF NOT EXISTS `installments` (
  `user_id` bigint unsigned NOT NULL,
  `type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `point_id` smallint unsigned NOT NULL,
  `number_id` int unsigned NOT NULL,
  `text` tinytext COLLATE utf8mb4_unicode_ci NOT NULL,
  `debit` decimal(10,2) NOT NULL DEFAULT '0.00',
  `credit` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`,`type_id`,`point_id`,`number_id`),
  KEY `installments_type_id_index` (`type_id`),
  KEY `installments_point_id_index` (`point_id`),
  KEY `installments_number_id_index` (`number_id`),
  CONSTRAINT `installments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.installments: ~0 rows (aproximadamente)
DELETE FROM `installments`;

-- Volcando estructura para tabla sae8206.jobs
DROP TABLE IF EXISTS `jobs`;
CREATE TABLE IF NOT EXISTS `jobs` (
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

-- Volcando datos para la tabla sae8206.jobs: ~0 rows (aproximadamente)
DELETE FROM `jobs`;

-- Volcando estructura para tabla sae8206.job_batches
DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE IF NOT EXISTS `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.job_batches: ~0 rows (aproximadamente)
DELETE FROM `job_batches`;

-- Volcando estructura para tabla sae8206.messages
DROP TABLE IF EXISTS `messages`;
CREATE TABLE IF NOT EXISTS `messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sender_id` bigint unsigned NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `messages_sender_id_foreign` (`sender_id`),
  KEY `messages_subject_id_foreign` (`subject_id`),
  CONSTRAINT `messages_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.messages: ~0 rows (aproximadamente)
DELETE FROM `messages`;

-- Volcando estructura para tabla sae8206.message_user
DROP TABLE IF EXISTS `message_user`;
CREATE TABLE IF NOT EXISTS `message_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `message_id` bigint unsigned NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `message_user_user_id_foreign` (`user_id`),
  KEY `message_user_message_id_foreign` (`message_id`),
  CONSTRAINT `message_user_message_id_foreign` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `message_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.message_user: ~0 rows (aproximadamente)
DELETE FROM `message_user`;

-- Volcando estructura para tabla sae8206.migrations
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.migrations: ~24 rows (aproximadamente)
DELETE FROM `migrations`;
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(1, '0001_01_01_000000_create_users_table', 1),
	(2, '0001_01_01_000001_create_cache_table', 1),
	(3, '0001_01_01_000002_create_jobs_table', 1),
	(4, '2024_11_19_230252_create_careers_table', 1),
	(5, '2024_11_19_230308_create_subjects_table', 1),
	(6, '2024_11_19_230450_create_modalities_table', 1),
	(7, '2024_11_19_230451_create_enrollments_table', 1),
	(8, '2024_11_19_230708_create_class_sessions_table', 1),
	(9, '2024_11_19_230959_create_grades_table', 1),
	(10, '2024_11_19_231235_create_career_user_table', 1),
	(11, '2025_02_22_212928_create_configs_table', 1),
	(12, '2025_03_06_224649_create_inscriptions_table', 1),
	(13, '2025_04_08_141106_create_messages_table', 1),
	(14, '2025_07_18_105003_create_message_user_table', 1),
	(15, '2025_09_17_155756_create_units_table', 1),
	(16, '2025_09_17_155803_create_topics_table', 1),
	(17, '2025_09_17_155815_create_resources_table', 1),
	(18, '2025_09_23_121908_create_events_table', 1),
	(19, '2025_09_24_140000_create_installments_table', 1),
	(20, '2025_09_24_140001_create_plans_masters_table', 1),
	(21, '2025_09_24_140002_create_plans_details_table', 1),
	(22, '2025_09_24_140003_create_userpayments_table', 1),
	(23, '2025_09_24_140004_create_paymentrecords_table', 1),
	(24, '2026_02_13_123025_create_personal_access_tokens_table', 1);

-- Volcando estructura para tabla sae8206.modalities
DROP TABLE IF EXISTS `modalities`;
CREATE TABLE IF NOT EXISTS `modalities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `percentage` int NOT NULL DEFAULT '75',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `modalities_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.modalities: ~0 rows (aproximadamente)
DELETE FROM `modalities`;

-- Volcando estructura para tabla sae8206.password_reset_tokens
DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.password_reset_tokens: ~0 rows (aproximadamente)
DELETE FROM `password_reset_tokens`;

-- Volcando estructura para tabla sae8206.paymentrecords
DROP TABLE IF EXISTS `paymentrecords`;
CREATE TABLE IF NOT EXISTS `paymentrecords` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `userpayments_id` bigint unsigned NOT NULL,
  `paymentBox` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `paymentAmount` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `paymentrecords_user_id_foreign` (`user_id`),
  KEY `paymentrecords_userpayments_id_foreign` (`userpayments_id`),
  CONSTRAINT `paymentrecords_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `paymentrecords_userpayments_id_foreign` FOREIGN KEY (`userpayments_id`) REFERENCES `userpayments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.paymentrecords: ~0 rows (aproximadamente)
DELETE FROM `paymentrecords`;

-- Volcando estructura para tabla sae8206.personal_access_tokens
DROP TABLE IF EXISTS `personal_access_tokens`;
CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.personal_access_tokens: ~0 rows (aproximadamente)
DELETE FROM `personal_access_tokens`;

-- Volcando estructura para tabla sae8206.plans_details
DROP TABLE IF EXISTS `plans_details`;
CREATE TABLE IF NOT EXISTS `plans_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `plans_master_id` bigint unsigned NOT NULL,
  `date` date NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `plans_details_plans_master_id_foreign` (`plans_master_id`),
  KEY `plans_details_date_index` (`date`),
  CONSTRAINT `plans_details_plans_master_id_foreign` FOREIGN KEY (`plans_master_id`) REFERENCES `plans_masters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.plans_details: ~0 rows (aproximadamente)
DELETE FROM `plans_details`;

-- Volcando estructura para tabla sae8206.plans_masters
DROP TABLE IF EXISTS `plans_masters`;
CREATE TABLE IF NOT EXISTS `plans_masters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.plans_masters: ~0 rows (aproximadamente)
DELETE FROM `plans_masters`;

-- Volcando estructura para tabla sae8206.resources
DROP TABLE IF EXISTS `resources`;
CREATE TABLE IF NOT EXISTS `resources` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `topic_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `resources_topic_id_foreign` (`topic_id`),
  CONSTRAINT `resources_topic_id_foreign` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.resources: ~0 rows (aproximadamente)
DELETE FROM `resources`;

-- Volcando estructura para tabla sae8206.sessions
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.sessions: ~0 rows (aproximadamente)
DELETE FROM `sessions`;

-- Volcando estructura para tabla sae8206.subjects
DROP TABLE IF EXISTS `subjects`;
CREATE TABLE IF NOT EXISTS `subjects` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `career_id` bigint unsigned NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prerequisite` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subjects_career_id_foreign` (`career_id`),
  CONSTRAINT `subjects_career_id_foreign` FOREIGN KEY (`career_id`) REFERENCES `careers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.subjects: ~0 rows (aproximadamente)
DELETE FROM `subjects`;

-- Volcando estructura para tabla sae8206.topics
DROP TABLE IF EXISTS `topics`;
CREATE TABLE IF NOT EXISTS `topics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `unit_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci,
  `order` int NOT NULL DEFAULT '0',
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `topics_unit_id_foreign` (`unit_id`),
  CONSTRAINT `topics_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.topics: ~0 rows (aproximadamente)
DELETE FROM `topics`;

-- Volcando estructura para tabla sae8206.units
DROP TABLE IF EXISTS `units`;
CREATE TABLE IF NOT EXISTS `units` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `subject_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `order` int NOT NULL DEFAULT '0',
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `units_subject_id_foreign` (`subject_id`),
  CONSTRAINT `units_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.units: ~0 rows (aproximadamente)
DELETE FROM `units`;

-- Volcando estructura para tabla sae8206.userpayments
DROP TABLE IF EXISTS `userpayments`;
CREATE TABLE IF NOT EXISTS `userpayments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `date` date NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `paid` decimal(10,2) NOT NULL DEFAULT '0.00',
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `userpayments_user_id_foreign` (`user_id`),
  KEY `userpayments_date_index` (`date`),
  CONSTRAINT `userpayments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.userpayments: ~0 rows (aproximadamente)
DELETE FROM `userpayments`;

-- Volcando estructura para tabla sae8206.users
DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `firstname` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lastname` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','student','teacher','director','administrative','treasurer','user') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla sae8206.users: ~3 rows (aproximadamente)
DELETE FROM `users`;
INSERT INTO `users` (`id`, `name`, `email`, `password`, `firstname`, `lastname`, `role`, `phone`, `enabled`, `email_verified_at`, `remember_token`, `created_at`, `updated_at`) VALUES
	(1, 'admin', 'admin@admin.com', '$2y$12$kOk3KJ63kti9Hk0QwRL12eRD9gkP/GjsqdZqKj35kVJM.tWjn8x3m', 'admin', 'admin', 'admin', '1234567890', 1, '2024-11-20 03:32:13', 'w5rxegswVbzodoOJnRG57LlvPPY5XfcP3la4rfK4Lskcw3ktlEu96J36L8I7', '2024-11-20 03:32:14', '2024-11-20 03:32:14'),
	(9, 'user', 'user@user.com', '$2y$10$JCCgt7YBjMZRyE5HYkEGNeMC6.kfbbut/m9GClAIpeloRWakrvDvm', 'user', 'user', 'student', '', 1, NULL, NULL, NULL, NULL),
	(10, 'student', 'student@student.com', '$2y$12$oNnt32DTJ2QVkDBgK0At2OS/TDvW9t0sim5rL/goo8a4tHj/OPcoy', 'student', 'student', 'student', '3482555555', 1, NULL, NULL, NULL, '2025-03-24 19:04:41');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
