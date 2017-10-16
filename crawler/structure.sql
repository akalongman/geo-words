CREATE TABLE IF NOT EXISTS `projects` (
    `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(1000)    NOT NULL,
    `created_at` DATETIME         NOT NULL,
    PRIMARY KEY (`id`)
)
    ENGINE = InnoDB
    AUTO_INCREMENT = 1
    DEFAULT CHARSET = utf8;


CREATE TABLE IF NOT EXISTS `crawls` (
    `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` INT(11) UNSIGNED NOT NULL,
    `url`        VARCHAR(1000)    NOT NULL,
    `msg`        VARCHAR(100)     NOT NULL DEFAULT '',
    `words`      INT(10)          NOT NULL DEFAULT '0',
    `status`     TINYINT(1)       NOT NULL DEFAULT '0',
    `created_at` DATETIME         NOT NULL,
    `updated_at` DATETIME         NOT NULL,
    PRIMARY KEY (`id`)
)
    ENGINE = InnoDB
    AUTO_INCREMENT = 1
    DEFAULT CHARSET = utf8;

CREATE TABLE IF NOT EXISTS `words` (
    `word`        CHAR(100)        NOT NULL,
    `project_id`  INT(11) UNSIGNED NOT NULL,
    `crawl_id`    INT(11) UNSIGNED NOT NULL,
    `occurrences` INT(11) UNSIGNED NOT NULL DEFAULT '1',
    `created_at`  DATETIME         NOT NULL,
    `updated_at`  DATETIME         NOT NULL,
    PRIMARY KEY (`word`, `project_id`)
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8;
