-- AutoBlog Scraper Database Migration
-- Run this SQL to create required tables for the enhanced scraper

-- =============================================
-- SCRAPE LOGS TABLE
-- =============================================

CREATE TABLE IF NOT EXISTS autoblog_scrape_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_id INT DEFAULT NULL,
    url VARCHAR(2048) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    http_status INT DEFAULT NULL,
    response_time DECIMAL(10, 3) DEFAULT 0,
    error_message TEXT,
    content_length INT DEFAULT 0,
    retry_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- SCRAPE QUEUE TABLE
-- =============================================

CREATE TABLE IF NOT EXISTS autoblog_scrape_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_id INT NOT NULL,
    url VARCHAR(2048) NOT NULL,
    priority INT DEFAULT 5,
    status VARCHAR(20) DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    last_attempt DATETIME DEFAULT NULL,
    next_attempt DATETIME DEFAULT NULL,
    error_message TEXT,
    result_data JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- ADD COLUMNS TO EXISTING TABLES (Optional - Run manually if needed)
-- =============================================

-- For autoblog_sources table:
-- ALTER TABLE autoblog_sources ADD COLUMN pagination_type VARCHAR(50) DEFAULT NULL;
-- ALTER TABLE autoblog_sources ADD COLUMN pagination_selector VARCHAR(255) DEFAULT NULL;

-- For autoblog_articles table:
-- ALTER TABLE autoblog_articles ADD COLUMN simhash VARCHAR(64) DEFAULT NULL;
-- ALTER TABLE autoblog_articles ADD COLUMN content_hash VARCHAR(32) DEFAULT NULL;

-- =============================================
-- COMPLETE
-- =============================================
