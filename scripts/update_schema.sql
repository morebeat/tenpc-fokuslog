-- Script to update the database schema to include weight entries in the entries table
-- This script is idempotent and can be run multiple times safely.

SET @dbname = DATABASE();
SET @tablename = 'entries';
SET @columnname = 'weight';

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE table_name = @tablename AND table_schema = @dbname AND column_name = @columnname) > 0,
  'SELECT 1',
  'ALTER TABLE entries ADD COLUMN weight DECIMAL(5,2) DEFAULT NULL AFTER medication_id'
));

PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
