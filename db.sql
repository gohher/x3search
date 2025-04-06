-- pjindex table
CREATE TABLE IF NOT EXISTS `pjindex` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `directory_path` varchar(255) NOT NULL,
  `file_creation_time` datetime NOT NULL,
  `update_count` int(11) NOT NULL DEFAULT 1,
  `base` varchar(50) NOT NULL DEFAULT 'Gallery',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_base_path` (`base`,`directory_path`),
  KEY `idx_pjindex_path` (`directory_path`)
) ENGINE=InnoDB AUTO_INCREMENT=163 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- pages table
CREATE TABLE IF NOT EXISTS `pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pjindex_id` int(11) NOT NULL,
  `base` varchar(50) NOT NULL DEFAULT 'Gallery',
  `file_path` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `label` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `date` date DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `seo_title` varchar(255) DEFAULT NULL,
  `seo_description` varchar(255) DEFAULT NULL,
  `seo_keywords` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `imgsnumber` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pjindex_id` (`pjindex_id`),
  KEY `idx_pages_path` (`file_path`),
  KEY `idx_id_date` (`id`,`date`),
  KEY `idx_base` (`base`),
  CONSTRAINT `pages_ibfk_1` FOREIGN KEY (`pjindex_id`) REFERENCES `pjindex` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=325 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- images table
CREATE TABLE IF NOT EXISTS `images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_id` int(11) NOT NULL,
  `base` varchar(50) NOT NULL DEFAULT 'Gallery',
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `image_index` int(11) DEFAULT NULL,
  `filenames` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_images_path` (`file_path`),
  KEY `idx_page_id` (`page_id`),
  KEY `idx_filenames` (`filenames`),
  KEY `idx_page_date` (`page_id`,`date`),
  KEY `idx_main` (`page_id`,`filenames`,`date`),
  CONSTRAINT `images_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5597 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- index_word table
CREATE TABLE IF NOT EXISTS `index_word` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `word` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_index_word_word` (`word`)
) ENGINE=InnoDB AUTO_INCREMENT=227 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- input_temp table
CREATE TABLE IF NOT EXISTS `input_temp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `search_word` varchar(255) NOT NULL,
  `search_time` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2289 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
