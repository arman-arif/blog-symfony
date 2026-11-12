-- Local infrastructure bootstrap, not an application schema migration.
CREATE DATABASE IF NOT EXISTS fieldnotes_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON fieldnotes_test.* TO 'fieldnotes'@'%';
