-- ============================================================
-- Schema : influenceurs_tracker
-- Projet  : Influenceurs_Tracker_sos_expat
-- Port    : 3309 (dédié)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `influenceurs_tracker`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `influenceurs_tracker`;

-- Exécuter les migrations Laravel via :
--   php artisan migrate --seed
-- Ce fichier est une référence documentaire du schéma.

CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','member') NOT NULL DEFAULT 'member',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `influenceurs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `handle` varchar(255) DEFAULT NULL,
  `avatar_url` varchar(500) DEFAULT NULL,
  `platforms` json NOT NULL,
  `primary_platform` varchar(50) NOT NULL,
  `followers` bigint unsigned DEFAULT NULL,
  `followers_secondary` json DEFAULT NULL,
  `niche` varchar(255) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `language` varchar(10) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `profile_url` varchar(500) DEFAULT NULL,
  `status` enum('prospect','contacted','negotiating','active','refused','inactive') NOT NULL DEFAULT 'prospect',
  `assigned_to` bigint unsigned DEFAULT NULL,
  `reminder_days` int unsigned NOT NULL DEFAULT 7,
  `reminder_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_contact_at` timestamp NULL DEFAULT NULL,
  `partnership_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `tags` json DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `assigned_to` (`assigned_to`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `influenceurs_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `influenceurs_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `contacts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `influenceur_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `date` date NOT NULL,
  `channel` enum('email','instagram','linkedin','whatsapp','phone','other') NOT NULL,
  `result` enum('sent','replied','refused','registered','no_answer') NOT NULL,
  `sender` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `reply` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `influenceur_id` (`influenceur_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `contacts_influenceur_id_foreign` FOREIGN KEY (`influenceur_id`) REFERENCES `influenceurs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contacts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `reminders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `influenceur_id` bigint unsigned NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('pending','dismissed','done') NOT NULL DEFAULT 'pending',
  `dismissed_by` bigint unsigned DEFAULT NULL,
  `dismissed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `influenceur_id` (`influenceur_id`),
  CONSTRAINT `reminders_influenceur_id_foreign` FOREIGN KEY (`influenceur_id`) REFERENCES `influenceurs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reminders_dismissed_by_foreign` FOREIGN KEY (`dismissed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `activity_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `influenceur_id` bigint unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` json DEFAULT NULL,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `influenceur_id` (`influenceur_id`),
  CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `activity_logs_influenceur_id_foreign` FOREIGN KEY (`influenceur_id`) REFERENCES `influenceurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
