
ALTER TABLE `bookings`
  ADD COLUMN IF NOT EXISTS `payment_reviewed_by` int(11) DEFAULT NULL AFTER `confirmed_at`,
  ADD COLUMN IF NOT EXISTS `payment_reviewed_at` datetime DEFAULT NULL AFTER `payment_reviewed_by`,
  ADD COLUMN IF NOT EXISTS `confirmed_by` int(11) DEFAULT NULL AFTER `payment_reviewed_at`;

-- 2) Agreement templates (every edit creates a NEW version row; old versions are kept)
CREATE TABLE IF NOT EXISTS `agreement_templates` (
  `template_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `family_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `version` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `title` varchar(200) NOT NULL,
  `template_type` enum('editable','static_pdf') NOT NULL,
  `content_html` mediumtext DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `file_sha256` char(64) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`template_id`),
  KEY `idx_tpl_family` (`family_id`,`version`),
  KEY `idx_tpl_active` (`is_active`),
  CONSTRAINT `fk_tpl_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3) Authorized representative (single row, id = 1)
CREATE TABLE IF NOT EXISTS `agreement_settings` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `rep_name` varchar(150) DEFAULT NULL,
  `rep_position` varchar(150) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT IGNORE INTO `agreement_settings` (`id`) VALUES (1);

-- 4) Generated agreements (one persistent PDF per booking revision)
CREATE TABLE IF NOT EXISTS `agreements` (
  `agreement_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `agreement_ref` varchar(60) NOT NULL,
  `booking_id` int(10) UNSIGNED NOT NULL,
  `revision` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `is_current` tinyint(1) NOT NULL DEFAULT 1,
  `template_id` int(10) UNSIGNED NOT NULL,
  `template_version` int(10) UNSIGNED NOT NULL,
  `template_type` enum('editable','static_pdf') NOT NULL,
  `pdf_path` varchar(255) NOT NULL,
  `pdf_sha256` char(64) NOT NULL,
  `status` enum('generated','sent','failed') NOT NULL DEFAULT 'generated',
  `generated_by` int(11) DEFAULT NULL,
  `generated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL,
  `send_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `last_error` varchar(500) DEFAULT NULL,
  `send_started_at` datetime DEFAULT NULL,
  PRIMARY KEY (`agreement_id`),
  UNIQUE KEY `uq_agr_ref` (`agreement_ref`),
  UNIQUE KEY `uq_agr_booking_rev` (`booking_id`,`revision`),
  KEY `idx_agr_status` (`status`),
  CONSTRAINT `fk_agr_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_agr_template` FOREIGN KEY (`template_id`) REFERENCES `agreement_templates` (`template_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_agr_admin` FOREIGN KEY (`generated_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5) Email attempt history (every send/resend attempt is recorded)
--    result 'accepted' = accepted by the SMTP server. It does NOT prove delivery or that it was read.
CREATE TABLE IF NOT EXISTS `agreement_email_log` (
  `log_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `agreement_id` int(10) UNSIGNED NOT NULL,
  `attempt_type` enum('send','resend') NOT NULL DEFAULT 'send',
  `recipient` varchar(150) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `attempted_by` int(11) DEFAULT NULL,
  `result` enum('pending','accepted','failed') NOT NULL DEFAULT 'pending',
  `error_message` varchar(500) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`log_id`),
  KEY `idx_log_agreement` (`agreement_id`),
  CONSTRAINT `fk_log_agreement` FOREIGN KEY (`agreement_id`) REFERENCES `agreements` (`agreement_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_log_admin` FOREIGN KEY (`attempted_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
