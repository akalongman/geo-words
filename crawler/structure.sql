CREATE TABLE `projects` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(1000) NOT NULL,
    `created_at` timestamp(3) NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `crawls` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `project_id` int unsigned NOT NULL,
    `url` varchar(1000) NOT NULL,
    `msg` varchar(1000) DEFAULT NULL,
    `words` int NOT NULL DEFAULT '0',
    `status` tinyint(1) NOT NULL DEFAULT '0',
    `created_at` timestamp(3) NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at` timestamp(3) NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (`id`),
    KEY `crawls_projects_id_fk` (`project_id`),
    CONSTRAINT `crawls_projects_id_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `words` (
    `word` char(100) NOT NULL,
    `project_id` int unsigned NOT NULL,
    `crawl_id` int unsigned NOT NULL,
    `occurrences` int unsigned NOT NULL DEFAULT '1',
    `created_at` timestamp(3) NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at` timestamp(3) NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (`word`,`project_id`),
    KEY `words_crawls_id_fk` (`crawl_id`),
    KEY `words_projects_id_fk` (`project_id`),
    CONSTRAINT `words_crawls_id_fk` FOREIGN KEY (`crawl_id`) REFERENCES `crawls` (`id`),
    CONSTRAINT `words_projects_id_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

