-- Migration: Create initial database schema
-- This migration ensures all necessary tables exist

-- Create schema_migrations table (if not exists)
CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(255) NOT NULL UNIQUE,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_migration_name (migration_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure all tables from database_schema.sql exist
-- This is a compatibility migration

-- Note: Main schema is in database_schema.sql
-- This migration file is for incremental changes

