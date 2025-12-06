# 🚨 CRITICAL PRE-LAUNCH CHECKLIST
## Must-Do Before Going Live Tomorrow

**Priority: URGENT - Do these first!**

---

## 1. Database Indexes (15 minutes) ⚠️ CRITICAL

**Without these, your site will be SLOW with just 10-20 users.**

```sql
-- Run these immediately on your database:

-- Users table
ALTER TABLE users ADD INDEX idx_restaurant_id (restaurant_id);
ALTER TABLE users ADD INDEX idx_username (username);
ALTER TABLE users ADD INDEX idx_is_active (is_active);

-- Menu Items (MOST IMPORTANT - used on every page)
ALTER TABLE menu_items ADD INDEX idx_restaurant_menu (restaurant_id, menu_id);
ALTER TABLE menu_items ADD INDEX idx_available_category (is_available, item_category);
ALTER TABLE menu_items ADD INDEX idx_restaurant_available (restaurant_id, is_available);

-- Orders
ALTER TABLE orders ADD INDEX idx_restaurant_date (restaurant_id, created_at);
ALTER TABLE orders ADD INDEX idx_status_date (order_status, created_at);

-- Menu
ALTER TABLE menu ADD INDEX idx_restaurant_active (restaurant_id, is_active);

-- Payments
ALTER TABLE payments ADD INDEX idx_order_id (order_id);
ALTER TABLE payments ADD INDEX idx_restaurant_date (restaurant_id, created_at);
```

**Time:** 15 minutes  
**Impact:** 10x faster queries

---

## 2. Database Connection Limits (5 minutes) ⚠️ CRITICAL

**Prevent "Too many connections" errors.**

```sql
-- Check current limit
SHOW VARIABLES LIKE 'max_connections';

-- Increase if needed (contact Hostinger if you can't change)
SET GLOBAL max_connections = 200;  -- Minimum for launch
```

**Time:** 5 minutes  
**Impact:** Prevents crashes under load

---

## 3. Enable Error Logging (10 minutes) ⚠️ CRITICAL

**You need to see errors in production.**

```php
// Add to top of db_connection.php (if not already there)
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Create logs directory
mkdir(__DIR__ . '/../logs', 0755, true);
```

**Time:** 10 minutes  
**Impact:** Can debug issues in production

---

## 4. Test Image Loading (5 minutes) ⚠️ CRITICAL

**Make sure images work on live server.**

1. Visit: `https://restrogrow.com/test_image_loading.php`
2. Verify all images load correctly
3. Check uploads directory exists and is writable

**Time:** 5 minutes  
**Impact:** Images won't show if broken

---

## 5. Test Database Connection (2 minutes) ⚠️ CRITICAL

**Verify connection works on live server.**

1. Visit: `https://restrogrow.com/sujay/index.php`
2. Check "Database Status" shows "Online"
3. Verify connection stats are working

**Time:** 2 minutes  
**Impact:** Site won't work if DB connection fails

---

## 6. Set Up Basic Backup (20 minutes) ⚠️ CRITICAL

**Don't lose data on day 1.**

### Option A: Hostinger Backup (Easiest)
- Enable daily backups in Hostinger control panel
- Set retention to 7 days minimum

### Option B: Manual Backup Script
```bash
# Create backup script: /home/u509616587/backup.sh
#!/bin/bash
mysqldump -u u509616587_restrogrow -p'your_password' u509616587_restrogrow | gzip > /home/u509616587/backups/backup_$(date +%Y%m%d).sql.gz

# Make executable
chmod +x /home/u509616587/backup.sh

# Run daily via cron
# Add to crontab: 0 2 * * * /home/u509616587/backup.sh
```

**Time:** 20 minutes  
**Impact:** Can recover if something goes wrong

---

## 7. Disable Test Files (5 minutes) ⚠️ CRITICAL

**Don't expose test files to public.**

```bash
# Move or delete test files
mv test_image_loading.php test_image_loading.php.bak
# Or add to .htaccess to block access
```

**Or add to .htaccess:**
```apache
# Block test files
<FilesMatch "test_.*\.php$">
    Order allow,deny
    Deny from all
</FilesMatch>
```

**Time:** 5 minutes  
**Impact:** Prevents exposing sensitive info

---

## 8. Verify Uploads Directory (5 minutes) ⚠️ CRITICAL

**Images won't upload if this is wrong.**

```bash
# Check uploads directory exists
ls -la /home/u509616587/domains/restrogrow.com/public_html/uploads/

# Set correct permissions
chmod 755 /home/u509616587/domains/restrogrow.com/public_html/uploads/
chmod 755 /home/u509616587/domains/restrogrow.com/public_html/uploads/logos/
chmod 755 /home/u509616587/domains/restrogrow.com/public_html/uploads/menu_items/
chmod 755 /home/u509616587/domains/restrogrow.com/public_html/uploads/banners/
```

**Time:** 5 minutes  
**Impact:** Image uploads will fail

---

## 9. Test Critical User Flows (30 minutes) ⚠️ CRITICAL

**Make sure basic functionality works.**

- [ ] Login works (admin, superadmin)
- [ ] Menu items display correctly
- [ ] Images load on menu pages
- [ ] Orders can be created
- [ ] Payments can be processed
- [ ] Dashboard loads without errors

**Time:** 30 minutes  
**Impact:** Core functionality must work

---

## 10. Set Production Error Display (2 minutes) ⚠️ CRITICAL

**Hide errors from users, log them instead.**

```php
// In db_connection.php and all entry points
ini_set('display_errors', 0);  // Hide from users
ini_set('log_errors', 1);      // Log to file
```

**Time:** 2 minutes  
**Impact:** Professional appearance, security

---

## Quick Execution Order

1. **Database Indexes** (15 min) - Do this FIRST
2. **Test Database Connection** (2 min)
3. **Test Image Loading** (5 min)
4. **Verify Uploads Directory** (5 min)
5. **Set Error Display** (2 min)
6. **Enable Error Logging** (10 min)
7. **Set Connection Limits** (5 min)
8. **Test Critical Flows** (30 min)
9. **Set Up Backup** (20 min)
10. **Disable Test Files** (5 min)

**Total Time: ~1.5 hours**

---

## What You Can Skip for Now

- ❌ Caching (can add later)
- ❌ Advanced monitoring (basic logging is enough)
- ❌ Read replicas (not needed for launch)
- ❌ CDN setup (can add after launch)
- ❌ Advanced security hardening (basic is fine)

---

## Emergency Contacts

- **Hostinger Support:** For database connection issues
- **Check Logs:** `/home/u509616587/domains/restrogrow.com/public_html/logs/php_errors.log`

---

## Post-Launch (Do These After Launch)

- [ ] Set up automated daily backups
- [ ] Implement caching (Redis/Memcached)
- [ ] Set up monitoring alerts
- [ ] Optimize slow queries
- [ ] Add CDN for images

---

**🎯 Focus: Get it working first, optimize later!**

