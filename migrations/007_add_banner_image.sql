-- Add banner_image field to website_settings table
ALTER TABLE website_settings 
ADD COLUMN IF NOT EXISTS banner_image VARCHAR(255) NULL AFTER primary_yellow;

