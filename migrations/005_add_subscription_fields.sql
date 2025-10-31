-- Add basic subscription fields to users
ALTER TABLE users 
  ADD COLUMN IF NOT EXISTS subscription_status ENUM('trial','active','expired','disabled') DEFAULT 'trial' AFTER is_active,
  ADD COLUMN IF NOT EXISTS trial_end_date DATE NULL AFTER subscription_status,
  ADD COLUMN IF NOT EXISTS renewal_date DATE NULL AFTER trial_end_date,
  ADD COLUMN IF NOT EXISTS disabled_at DATETIME NULL AFTER renewal_date;

-- Initialize values for existing rows
UPDATE users
SET trial_end_date = DATE_ADD(DATE(created_at), INTERVAL 7 DAY),
    subscription_status = CASE WHEN CURRENT_DATE() < DATE_ADD(DATE(created_at), INTERVAL 7 DAY) THEN 'trial' ELSE 'expired' END
WHERE trial_end_date IS NULL;


