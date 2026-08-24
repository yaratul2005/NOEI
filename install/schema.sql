-- NOEI CMS Base Database Schema
-- Charset: utf8mb4, Collation: utf8mb4_unicode_ci

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Roles Table
DROP TABLE IF EXISTS `cms_roles`;
CREATE TABLE `cms_roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `slug` VARCHAR(50) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Permissions Table
DROP TABLE IF EXISTS `cms_permissions`;
CREATE TABLE `cms_permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Role Permissions Pivot Table
DROP TABLE IF EXISTS `cms_role_permission`;
CREATE TABLE `cms_role_permission` (
    `role_id` INT NOT NULL,
    `permission_id` INT NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    KEY `idx_role_id` (`role_id`),
    KEY `idx_permission_id` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Users Table
DROP TABLE IF EXISTS `cms_users`;
CREATE TABLE `cms_users` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(60) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role_id` INT NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_role_id` (`role_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Options Table
DROP TABLE IF EXISTS `cms_options`;
CREATE TABLE `cms_options` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `option_name` VARCHAR(191) NOT NULL UNIQUE,
    `option_value` LONGTEXT NULL,
    `autoload` TINYINT(1) NOT NULL DEFAULT 1,
    KEY `idx_autoload` (`autoload`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Posts Table
DROP TABLE IF EXISTS `cms_posts`;
CREATE TABLE `cms_posts` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `author_id` BIGINT NOT NULL,
    `title` TEXT NOT NULL,
    `slug` VARCHAR(191) NOT NULL,
    `content` LONGTEXT NULL,
    `excerpt` TEXT NULL,
    `type` VARCHAR(20) NOT NULL DEFAULT 'post',
    `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
    `parent_id` BIGINT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_slug` (`slug`),
    KEY `idx_type_status` (`type`, `status`),
    KEY `idx_author_id` (`author_id`),
    KEY `idx_parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Post Meta Table
DROP TABLE IF EXISTS `cms_post_meta`;
CREATE TABLE `cms_post_meta` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `post_id` BIGINT NOT NULL,
    `meta_key` VARCHAR(191) NOT NULL,
    `meta_value` LONGTEXT NULL,
    KEY `idx_post_id` (`post_id`),
    KEY `idx_meta_key` (`meta_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Terms Table
DROP TABLE IF EXISTS `cms_terms`;
CREATE TABLE `cms_terms` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(200) NOT NULL,
    `slug` VARCHAR(191) NOT NULL,
    KEY `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Taxonomies Table
DROP TABLE IF EXISTS `cms_taxonomies`;
CREATE TABLE `cms_taxonomies` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `term_id` BIGINT NOT NULL,
    `taxonomy` VARCHAR(32) NOT NULL,
    `description` LONGTEXT NULL,
    `parent_id` BIGINT NOT NULL DEFAULT 0,
    `count` BIGINT NOT NULL DEFAULT 0,
    KEY `idx_taxonomy` (`taxonomy`),
    KEY `idx_term_id` (`term_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Term Relationships Table
DROP TABLE IF EXISTS `cms_term_relationships`;
CREATE TABLE `cms_term_relationships` (
    `object_id` BIGINT NOT NULL,
    `taxonomy_id` BIGINT NOT NULL,
    PRIMARY KEY (`object_id`, `taxonomy_id`),
    KEY `idx_taxonomy_id` (`taxonomy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Media Table
DROP TABLE IF EXISTS `cms_media`;
CREATE TABLE `cms_media` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `file_size` BIGINT NOT NULL,
    `meta_data` LONGTEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_user_id` (`user_id`),
    KEY `idx_mime_type` (`mime_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pre-seed Roles
INSERT INTO `cms_roles` (`id`, `name`, `slug`, `description`) VALUES
(1, 'Administrator', 'administrator', 'Full access to all system features and settings'),
(2, 'Editor', 'editor', 'Can publish and manage all posts, pages, and media'),
(3, 'Author', 'author', 'Can publish and manage their own posts'),
(4, 'Contributor', 'contributor', 'Can write and edit their own posts but cannot publish'),
(5, 'Subscriber', 'subscriber', 'Can manage their profile and leave comments');

-- Pre-seed Permissions
INSERT INTO `cms_permissions` (`id`, `name`, `slug`, `description`) VALUES
(1, 'Manage System Options', 'manage_options', 'Modify global CMS settings and configurations'),
(2, 'Manage Users', 'manage_users', 'Create, update, or delete user accounts'),
(3, 'Manage Modules', 'manage_modules', 'Install, enable, or disable extension modules'),
(4, 'Publish Posts', 'publish_posts', 'Publish posts to public view'),
(5, 'Edit Others Posts', 'edit_others_posts', 'Edit posts authored by other users'),
(6, 'Delete Posts', 'delete_posts', 'Delete published or draft posts'),
(7, 'Upload Media', 'upload_media', 'Upload files to media library');

-- Seed Role Permissions for Administrator
INSERT INTO `cms_role_permission` (`role_id`, `permission_id`) VALUES
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6), (1, 7),
(2, 4), (2, 5), (2, 6), (2, 7),
(3, 4), (3, 7),
(4, 7);

-- Pre-seed Options
INSERT INTO `cms_options` (`option_name`, `option_value`, `autoload`) VALUES
('site_title', 'NOEI CMS Site', 1),
('site_tagline', 'Fast, secure, and affordable shared-hosting CMS', 1),
('site_url', 'http://localhost:8000', 1),
('admin_email', 'admin@example.com', 1),
('theme', 'default', 1),
('date_format', 'Y-m-d H:i:s', 1),
('active_modules', '[]', 1);

SET FOREIGN_KEY_CHECKS = 1;
