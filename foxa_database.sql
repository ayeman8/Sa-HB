-- ============================================================
-- FOXA FAMILY SA-MP Website — Database Schema
-- Version : 2.0 Professional
-- Engine  : MySQL 5.7+ / MariaDB 10.3+
-- Charset : utf8mb4 (full Unicode + emoji support)
--
-- HOW TO IMPORT ON LEMEHOST.COM:
--  1. Login to cPanel → phpMyAdmin
--  2. Create a new database (e.g. "foxa_db")
--  3. Click on the database → Import tab
--  4. Choose this file → Execute
--  5. Upload api.php, config.php, index.html to public_html
--  6. Edit config.php with your DB credentials
--  7. Visit yoursite.com/setup.php to create the first admin
--  8. DELETE setup.php immediately after!
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

-- ============================================================
-- TABLE: users
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(50)       NOT NULL                   COMMENT 'Unique player name',
  `password_hash` VARCHAR(255)      NOT NULL                   COMMENT 'bcrypt hash',
  `role`          ENUM('player','moderator','admin','superadmin') NOT NULL DEFAULT 'player',
  `avatar_emoji`  VARCHAR(12)       NOT NULL DEFAULT '🦊',
  `email`         VARCHAR(150)          NULL DEFAULT NULL,
  `level`         SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `score`         INT UNSIGNED      NOT NULL DEFAULT 0,
  `money`         INT               NOT NULL DEFAULT 5000,
  `warnings`      TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  `is_banned`     TINYINT(1)        NOT NULL DEFAULT 0,
  `ban_reason`    VARCHAR(255)          NULL DEFAULT NULL,
  `faction`       VARCHAR(100)          NULL DEFAULT NULL,
  `gang`          VARCHAR(100)          NULL DEFAULT NULL,
  `rank_title`    VARCHAR(100)      NOT NULL DEFAULT 'مبتدئ',
  `bio`           TEXT                  NULL DEFAULT NULL,
  `last_login`    DATETIME              NULL DEFAULT NULL,
  `last_ip`       VARCHAR(50)           NULL DEFAULT NULL,
  `created_at`    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE  KEY `uq_username`   (`username`),
  KEY             `idx_role`       (`role`),
  KEY             `idx_banned`     (`is_banned`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: player_skills
-- ============================================================
CREATE TABLE IF NOT EXISTS `player_skills` (
  `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED     NOT NULL,
  `skill_name`  VARCHAR(60)      NOT NULL,
  `skill_value` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 to 100',
  PRIMARY KEY (`id`),
  UNIQUE KEY  `uq_user_skill` (`user_id`, `skill_name`),
  CONSTRAINT `fk_skills_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: sessions
-- ============================================================
CREATE TABLE IF NOT EXISTS `sessions` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `token`       CHAR(64)     NOT NULL COMMENT 'hex(random_bytes(32))',
  `ip_address`  VARCHAR(50)      NULL DEFAULT NULL,
  `user_agent`  VARCHAR(300)     NULL DEFAULT NULL,
  `expires_at`  DATETIME     NOT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY  `uq_token`     (`token`),
  KEY         `idx_user_id`  (`user_id`),
  KEY         `idx_expires`  (`expires_at`),
  CONSTRAINT `fk_sessions_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: commands  (dynamic — managed by superadmin panel)
-- ============================================================
CREATE TABLE IF NOT EXISTS `commands` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category`      VARCHAR(30)  NOT NULL DEFAULT 'general'
                    COMMENT 'rp | general | chat | vehicle | faction | gang | illegal | admin',
  `sub_category`  VARCHAR(100)     NULL DEFAULT NULL,
  `command_code`  VARCHAR(150) NOT NULL,
  `label`         VARCHAR(100) NOT NULL,
  `description`   TEXT             NULL DEFAULT NULL,
  `requires_role` ENUM('player','moderator','admin','superadmin') NOT NULL DEFAULT 'player',
  `sort_order`    SMALLINT     NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `added_by`      INT UNSIGNED     NULL DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category`  (`category`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_sort`      (`category`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: page_sections  (editable content blocks per page)
-- ============================================================
CREATE TABLE IF NOT EXISTS `page_sections` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `section_key`    VARCHAR(100) NOT NULL COMMENT 'Unique identifier e.g. home_welcome',
  `section_title`  VARCHAR(200)     NULL DEFAULT NULL,
  `content`        TEXT             NULL DEFAULT NULL,
  `content_type`   ENUM('text','html','notice','announcement','json') NOT NULL DEFAULT 'text',
  `page`           VARCHAR(50)  NOT NULL DEFAULT 'home',
  `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `updated_by`     INT UNSIGNED     NULL DEFAULT NULL,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_section_key` (`section_key`),
  KEY        `idx_page`       (`page`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: announcements
-- ============================================================
CREATE TABLE IF NOT EXISTS `announcements` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(200) NOT NULL,
  `body`        TEXT         NOT NULL,
  `type`        ENUM('info','warning','update','event','maintenance') NOT NULL DEFAULT 'info',
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `is_pinned`   TINYINT(1)   NOT NULL DEFAULT 0,
  `created_by`  INT UNSIGNED     NULL DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_active_pinned` (`is_active`, `is_pinned`),
  KEY `idx_created`       (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: activity_log
-- ============================================================
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED     NULL DEFAULT NULL,
  `username`    VARCHAR(50)      NULL DEFAULT NULL,
  `action`      VARCHAR(100) NOT NULL,
  `details`     TEXT             NULL DEFAULT NULL,
  `ip_address`  VARCHAR(50)      NULL DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id`  (`user_id`),
  KEY `idx_action`   (`action`),
  KEY `idx_created`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: site_settings
-- ============================================================
CREATE TABLE IF NOT EXISTS `site_settings` (
  `setting_key`   VARCHAR(100) NOT NULL,
  `setting_value` TEXT             NULL DEFAULT NULL,
  `description`   VARCHAR(255)     NULL DEFAULT NULL,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DEFAULT DATA — Site Settings
-- ============================================================
INSERT INTO `site_settings` (`setting_key`, `setting_value`, `description`) VALUES
('server_name',        'FOXA FAMILY',                'اسم السيرفر'),
('server_ip',          'FOXAhands.ct.ws',             'عنوان IP السيرفر'),
('server_version',     'Baltimore v1.0',              'إصدار الخريطة'),
('max_players',        '180',                         'أقصى عدد لاعبين'),
('discord_url',        'https://discord.gg/',         'رابط سيرفر الديسكورد'),
('whatsapp_url',       'https://wa.me/',              'رابط مجموعة واتساب'),
('maintenance_mode',   '0',                           '1 = تفعيل وضع الصيانة'),
('registration_open',  '1',                           '1 = قبول تسجيلات جديدة'),
('site_announcement',  '',                            'إعلان يظهر لجميع الزوار'),
('session_days',       '30',                          'مدة بقاء الجلسة بالأيام'),
('min_password_len',   '6',                           'أقل عدد أحرف لكلمة السر')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- ============================================================
-- DEFAULT DATA — Page Sections
-- ============================================================
INSERT INTO `page_sections` (`section_key`, `section_title`, `content`, `page`) VALUES
('home_welcome',   'رسالة الترحيب',     'سيرفر SA-MP روليبلاي عربي احترافي — انضم الآن', 'home'),
('home_badge',     'شعار الصفحة',       '🎮 SA-MP RolePlay Server — Baltimore v1.0',      'home'),
('server_notice',  'تنبيه السيرفر',     '',                                                'server'),
('rules_intro',    'مقدمة القوانين',    'اقرأ القوانين قبل اللعب — الجهل ليس عذراً',      'rules')
ON DUPLICATE KEY UPDATE `content` = VALUES(`content`);

-- ============================================================
-- DEFAULT DATA — Sample Commands (bonus)
-- ============================================================
INSERT INTO `commands`
  (`category`, `sub_category`, `command_code`, `label`, `description`, `requires_role`, `sort_order`)
VALUES
('rp',      '🎭 التفاعل',   '/me [فعل]',            'فعل شخصي',         'وصف فعل شخصيتك للمحيطين',                     'player',    1),
('rp',      '🎭 التفاعل',   '/do [وصف]',            'وصف المشهد',        'وصف ما يراه الجميع في المشهد',                 'player',    2),
('rp',      '🎭 التفاعل',   '/ame [فعل]',           'فعل فوق الرأس',     'يظهر الفعل كنص فوق رأس الشخصية',              'player',    3),
('general', '⚙️ الأساسية',  '/stats',               'الإحصائيات',        'عرض إحصائيات شخصيتك الكاملة',                 'player',    1),
('general', '⚙️ الأساسية',  '/cash',                'الرصيد النقدي',     'عرض المال الذي بحوزتك',                        'player',    2),
('admin',   '⚖️ العقوبات',  '/kick [id] [سبب]',     'طرد لاعب',          'طرد لاعب من السيرفر مؤقتاً',                   'admin',     1),
('admin',   '⚖️ العقوبات',  '/ban [id] [سبب]',      'حظر دائم',          'حظر لاعب بشكل دائم',                           'admin',     2),
('admin',   '⚖️ العقوبات',  '/tempban [id] [وقت]',  'حظر مؤقت',          'حظر لاعب لمدة محددة (بالدقائق)',               'admin',     3),
('admin',   '⚖️ العقوبات',  '/warn [id] [سبب]',     'تحذير رسمي',        'إعطاء لاعب تحذيراً مسجلاً',                   'admin',     4),
('admin',   '🔧 إدارة',     '/aduty',               'دوام الأدمن',       'الدخول/الخروج من وضع دوام الإدارة',            'admin',     5),
('admin',   '🔧 إدارة',     '/god',                 'وضع الله',          'الحصانة الكاملة من الموت',                     'admin',     6),
('admin',   '🔧 إدارة',     '/tp [id]',             'نقل سريع',          'الانتقال إلى موقع لاعب',                       'admin',     7),
('admin',   '🔧 إدارة',     '/spec [id]',           'مراقبة',            'مشاهدة اللعب من منظور اللاعب',                 'admin',     8),
('admin',   '📢 التواصل',   '/ann [رسالة]',         'إعلان عام',         'إرسال إعلان يظهر لجميع اللاعبين',              'admin',     9);

-- ============================================================
-- DEFAULT DATA — Welcome Announcement
-- ============================================================
INSERT INTO `announcements` (`title`, `body`, `type`, `is_pinned`) VALUES
('🦊 مرحباً بكم في FOXA FAMILY!', 'سيرفر SA-MP روليبلاي عربي احترافي — تأكدوا من قراءة القوانين قبل البدء. نتمنى لكم وقتاً ممتعاً!', 'info', 1);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- NOTES:
--  * Superadmin account is created via setup.php (NOT here)
--  * Default admin password: run setup.php → change immediately
--  * To clean sessions: DELETE FROM sessions WHERE expires_at < NOW();
--  * Backup command: mysqldump -u USER -p foxa_db > backup.sql
-- ============================================================
