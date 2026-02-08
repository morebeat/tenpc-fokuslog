-- Script to update the database schema to include weight entries in the entries table

ALTER TABLE entries
ADD COLUMN weight DECIMAL(5,2) DEFAULT NULL AFTER medication_id;
