# MySQL Migration Setup Guide

This guide will help you migrate your HabitTracker application from JSON file storage to MySQL database with server-side encryption for habit names.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Security Overview](#security-overview)
3. [Step 1: Generate Encryption Key](#step-1-generate-encryption-key)
4. [Step 2: Configure Environment Variables](#step-2-configure-environment-variables)
5. [Step 3: Create MySQL Database](#step-3-create-mysql-database)
6. [Step 4: Run Migration Script](#step-4-run-migration-script)
7. [Step 5: Update API Endpoints](#step-5-update-api-endpoints)
8. [Step 6: Test the Application](#step-6-test-the-application)
9. [Troubleshooting](#troubleshooting)
10. [Security Best Practices](#security-best-practices)

---

## Prerequisites

Before you begin, ensure you have:

- ✅ MySQL 5.7+ or MariaDB 10.2+
- ✅ PHP 7.4+ with OpenSSL extension enabled
- ✅ Command-line access to your server
- ✅ Backup of existing JSON data (migration script creates this automatically)
- ✅ Apache or nginx with ability to set environment variables

---

## Security Overview

### What Gets Encrypted?

| Data Type | Encrypted? | Why? |
|-----------|-----------|------|
| **Habit Names** | ✅ YES | Most sensitive data (e.g., "Stop drinking", "Therapy") |
| **Passwords** | ✅ YES (bcrypt) | Already hashed, not reversible |
| **Dates** | ❌ NO | Meaningless without habit names, needed for queries |
| **Colors** | ❌ NO | Just UI preferences, not sensitive |
| **Emails** | ❌ NO | Needed for login, not considered secret |

### How Encryption Works

```
User creates habit: "Stop smoking"
                ↓
Server encrypts with user-specific key (derived from master key + user ID)
                ↓
Stored in database: "a8f3k9s0d2j4h6..." (encrypted + IV)
                ↓
User loads data → Server decrypts → Returns "Stop smoking"
```

**In a database breach:**
- ❌ Attacker CANNOT read habit names (encrypted)
- ❌ Attacker CANNOT crack passwords (bcrypt hashed)
- ✅ Attacker can see dates, but doesn't know what habits they belong to
- ✅ Each user has unique encryption key (limits blast radius)

---

## Step 1: Generate Encryption Key

The encryption key is the **most critical security component**. Generate a strong, random key:

### Option A: Using OpenSSL (Recommended)

```bash
openssl rand -base64 32
```

Example output:
```
Xk8mP3vN2qR9sT4wY7eA1bC5dF6gH8jK0lM9nO2pQ4r=
```

### Option B: Using PHP

```bash
php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"
```

### Option C: Using Python

```bash
python3 -c "import os, base64; print(base64.b64encode(os.urandom(32)).decode())"
```

**⚠️ CRITICAL SECURITY NOTES:**

1. **NEVER commit this key to version control** (add to `.gitignore`)
2. **Store it securely** - if you lose it, encrypted data cannot be recovered
3. **Use a different key for production and development**
4. **Keep backups of the key** in a secure location (password manager, encrypted file, etc.)

---

## Step 2: Configure Environment Variables

You need to configure environment variables for database connection and encryption.

### For Apache (.htaccess)

Create or edit `/app/.htaccess`:

```apache
# Database Configuration
SetEnv DB_HOST "localhost"
SetEnv DB_NAME "habittracker"
SetEnv DB_USER "your_db_username"
SetEnv DB_PASS "your_db_password"

# Encryption Key (REQUIRED)
SetEnv HABIT_ENCRYPTION_KEY "Xk8mP3vN2qR9sT4wY7eA1bC5dF6gH8jK0lM9nO2pQ4r="
```

### For nginx (PHP-FPM)

Edit your PHP-FPM pool configuration (usually `/etc/php/7.4/fpm/pool.d/www.conf`):

```ini
env[DB_HOST] = localhost
env[DB_NAME] = habittracker
env[DB_USER] = your_db_username
env[DB_PASS] = your_db_password
env[HABIT_ENCRYPTION_KEY] = Xk8mP3vN2qR9sT4wY7eA1bC5dF6gH8jK0lM9nO2pQ4r=
```

Then restart PHP-FPM:

```bash
sudo systemctl restart php7.4-fpm
```

### For Development (Local Testing)

Create a `.env` file (add to `.gitignore`!):

```bash
export DB_HOST="localhost"
export DB_NAME="habittracker"
export DB_USER="root"
export DB_PASS=""
export HABIT_ENCRYPTION_KEY="Xk8mP3vN2qR9sT4wY7eA1bC5dF6gH8jK0lM9nO2pQ4r="
```

Load it before running scripts:

```bash
source .env
php app/migrate_to_mysql.php
```

### Verify Environment Variables

Test that PHP can read the environment variables:

```bash
php -r "echo getenv('HABIT_ENCRYPTION_KEY') ? 'OK' : 'NOT SET';" && echo
```

Should output: `OK`

---

## Step 3: Create MySQL Database

### Create the Database

```bash
mysql -u root -p
```

```sql
CREATE DATABASE habittracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Create a Database User (Recommended for Production)

```sql
CREATE USER 'habittracker_user'@'localhost' IDENTIFIED BY 'your_secure_password_here';
GRANT ALL PRIVILEGES ON habittracker.* TO 'habittracker_user'@'localhost';
FLUSH PRIVILEGES;
```

### Import the Schema

```bash
mysql -u root -p habittracker < app/schema.sql
```

### Verify Tables Were Created

```bash
mysql -u root -p habittracker -e "SHOW TABLES;"
```

Expected output:
```
+-------------------------+
| Tables_in_habittracker  |
+-------------------------+
| habit_entries           |
| habits                  |
| login_attempts          |
| pending_registrations   |
| users                   |
| verification_codes      |
+-------------------------+
```

---

## Step 4: Run Migration Script

The migration script will:
1. ✅ Backup all JSON files
2. ✅ Encrypt habit names
3. ✅ Import users, habits, and entries into MySQL
4. ✅ Validate the migration

### Run the Migration

```bash
cd app
php migrate_to_mysql.php
```

### Expected Output

```
╔═══════════════════════════════════════════════════════════════════╗
║  HabitTracker: JSON to MySQL Migration Script                    ║
║  with Server-Side Encryption                                      ║
╚═══════════════════════════════════════════════════════════════════╝

[STEP 0] Running pre-flight checks...
   ✓ Database connection successful
   ✓ All pre-flight checks passed

[STEP 1] Backing up existing JSON files...
   ✓ Backed up: users.json
   ✓ Backed up: user_abc123.json
   ✓ Backup created at: /path/to/data/backup_20250120_143022

[STEP 2] Loading data from JSON files...
   ✓ Found 2 users
   ✓ Loaded data for user@example.com: 3 habits

[STEP 3] Migrating users to MySQL...
   ✓ Migrated user: user@example.com (ID: 1)
   ✓ All users migrated successfully

[STEP 4] Migrating habits with encryption...
   Processing user ID 1 (abc123)...
      ✓ Habit: "Exercise" → encrypted (ID: 1)
         → 45 entries
      ✓ Habit: "Meditation" → encrypted (ID: 2)
         → 30 entries
   ✓ Migrated 3 habits with 75 entries

[STEP 5] Migrating verification data...
   ✓ No pending registrations to migrate

[STEP 6] Validating migration...
   Database contains:
   - 2 users
   - 3 habits (encrypted)
   - 75 habit entries

   Testing encryption/decryption...
   ✓ Encryption test passed: Successfully decrypted habit name
      User: user@example.com
      Encrypted: YThmM2s5czBkMmo0aDY...
      Decrypted: "Exercise"

╔═══════════════════════════════════════════════════════════════════╗
║  MIGRATION COMPLETED SUCCESSFULLY! ✓                              ║
╚═══════════════════════════════════════════════════════════════════╝
```

### If Migration Fails

- Check database credentials in environment variables
- Ensure `HABIT_ENCRYPTION_KEY` is set
- Verify MySQL is running
- Check PHP error logs: `tail -f /var/log/php_errors.log`
- Re-run the script (it's safe to run multiple times)

---

## Step 5: Update API Endpoints

You need to switch from the JSON-based API files to the MySQL versions.

### Option A: Rename Files (Recommended)

```bash
cd app/api

# Backup originals
mv auth.php auth_json_backup.php
mv data.php data_json_backup.php
mv account.php account_json_backup.php

# Use MySQL versions
mv auth_mysql.php auth.php
mv data_mysql.php data.php
mv account_mysql.php account.php
```

### Option B: Symlinks (Alternative)

```bash
cd app/api

rm auth.php data.php account.php
ln -s auth_mysql.php auth.php
ln -s data_mysql.php data.php
ln -s account_mysql.php account.php
```

### Update Verification Helpers

```bash
cd app

# Backup original
mv verification_helpers.php verification_helpers_json_backup.php

# Use MySQL version
mv verification_helpers_mysql.php verification_helpers.php
```

---

## Step 6: Test the Application

### 1. Test Encryption Key Check

Visit your app in a browser. If the encryption key is **not** set, you should see:

```
FATAL ERROR: HABIT_ENCRYPTION_KEY environment variable is not set.
The application cannot run without encryption enabled.
Please see SETUP.md for configuration instructions.
```

If you see this, go back to [Step 2](#step-2-configure-environment-variables).

### 2. Test Login

1. Open your app in a browser
2. Log in with an existing account
3. Verify you can see your habits (decrypted correctly)

### 3. Test Creating a New Habit

1. Add a new habit (e.g., "Read daily")
2. Mark a few days as complete
3. Refresh the page - habit should still be there

### 4. Verify Encryption in Database

```bash
mysql -u root -p habittracker -e "SELECT id, name_encrypted, color FROM habits LIMIT 1;"
```

Output should show encrypted data:
```
+----+----------------------------------------------------------+-------+
| id | name_encrypted                                           | color |
+----+----------------------------------------------------------+-------+
|  1 | YThmM2s5czBkMmo0aDZsOXAxcjN0NXZ3OHg5ejBhMWM0ZTVnN2o... | blue  |
+----+----------------------------------------------------------+-------+
```

**The `name_encrypted` field should be gibberish** - this confirms encryption is working!

### 5. Test Export/Import

1. Go to Settings → Export Data
2. Download the JSON file
3. Verify habit names are **decrypted** in the export (readable)
4. Test importing the data back

### 6. Test Password Reset

1. Log out
2. Use "Forgot Password" flow
3. Verify email is sent and reset works

---

## Troubleshooting

### "FATAL ERROR: HABIT_ENCRYPTION_KEY environment variable is not set"

**Problem:** The encryption key is not accessible to PHP.

**Solutions:**

1. Verify environment variable is set:
   ```bash
   php -r "var_dump(getenv('HABIT_ENCRYPTION_KEY'));"
   ```

2. For Apache: Check `.htaccess` has `SetEnv` directives and mod_env is enabled
   ```bash
   sudo a2enmod env
   sudo systemctl restart apache2
   ```

3. For nginx: Restart PHP-FPM after updating pool config
   ```bash
   sudo systemctl restart php7.4-fpm
   ```

### "Database connection failed"

**Problem:** Cannot connect to MySQL.

**Solutions:**

1. Verify MySQL is running:
   ```bash
   sudo systemctl status mysql
   ```

2. Test database credentials:
   ```bash
   mysql -u your_username -p your_database
   ```

3. Check database exists:
   ```bash
   mysql -u root -p -e "SHOW DATABASES;"
   ```

### "Failed to decrypt habit name"

**Problem:** Encryption key has changed or data was encrypted with a different key.

**Solutions:**

1. **DO NOT CHANGE THE ENCRYPTION KEY** after migration - encrypted data cannot be decrypted with a different key

2. If you accidentally changed the key:
   - Restore the original encryption key
   - Re-run migration from backup if necessary

3. Check error logs:
   ```bash
   tail -f /var/log/apache2/error.log
   # or
   tail -f /var/log/nginx/error.log
   ```

### Habits Not Showing Up

**Problem:** User can log in but no habits appear.

**Solutions:**

1. Check if data was migrated for that user:
   ```sql
   SELECT u.email, COUNT(h.id) as habit_count
   FROM users u
   LEFT JOIN habits h ON u.id = h.user_id
   WHERE u.email = 'user@example.com'
   GROUP BY u.email;
   ```

2. Check for decryption errors in PHP error log

3. Verify session has correct `user_id`:
   ```php
   // Add temporarily to auth.php check endpoint
   error_log('Session user_id: ' . ($_SESSION['user_id'] ?? 'NOT SET'));
   ```

---

## Security Best Practices

### 🔐 Production Deployment

1. **Use Strong Database Passwords**
   ```bash
   # Generate strong password
   openssl rand -base64 24
   ```

2. **Restrict Database Access**
   ```sql
   -- Only allow localhost connections
   CREATE USER 'habittracker'@'localhost' IDENTIFIED BY 'strong_password';
   -- Don't use '%' (allows any host)
   ```

3. **Enable SSL/TLS**
   - Always use HTTPS in production
   - Enforce with HSTS headers

4. **Set Correct File Permissions**
   ```bash
   # Web server should NOT be able to write to API files
   chmod 644 app/api/*.php
   chmod 755 app/data  # Data directory (for backups during migration)
   ```

5. **Regular Backups**
   ```bash
   # Backup database daily
   mysqldump -u root -p habittracker > backup_$(date +%Y%m%d).sql
   ```

6. **Rotate Encryption Key (Advanced)**
   - If you need to rotate the key, you must re-encrypt all habit names
   - This requires a custom migration script
   - Keep old key accessible until re-encryption is complete

### 🔒 Key Storage

**NEVER:**
- ❌ Commit encryption key to Git
- ❌ Email the key
- ❌ Store in database
- ❌ Hardcode in PHP files
- ❌ Share via Slack/Discord/etc.

**ALWAYS:**
- ✅ Use environment variables
- ✅ Store backup in password manager (1Password, LastPass, etc.)
- ✅ Keep separate keys for dev/staging/production
- ✅ Restrict access to key (need-to-know basis)

### 📊 Monitoring

Monitor these metrics:

```sql
-- Failed decryptions (check PHP error logs for pattern)
-- Unusual login attempts
SELECT email, attempt_count, locked_until
FROM login_attempts
WHERE attempt_count >= 3;

-- Database size
SELECT
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
FROM information_schema.TABLES
WHERE table_schema = 'habittracker'
ORDER BY size_mb DESC;
```

---

## Maintenance

### Clean Up Expired Data

Run this periodically (e.g., via cron):

```sql
-- Clean up expired verification codes
DELETE FROM verification_codes WHERE expires_at < NOW();

-- Clean up expired pending registrations
DELETE FROM pending_registrations WHERE expires_at < NOW();

-- Clean up old login attempts
DELETE FROM login_attempts
WHERE locked_until IS NOT NULL AND locked_until < DATE_SUB(NOW(), INTERVAL 1 DAY);
```

### Cron Job Example

```bash
# Add to crontab: crontab -e
# Run cleanup daily at 3 AM
0 3 * * * mysql -u root -p'your_password' habittracker < /path/to/cleanup.sql
```

---

## Rolling Back (Emergency)

If you need to roll back to JSON files:

1. Stop using MySQL endpoints:
   ```bash
   cd app/api
   mv auth.php auth_mysql.php
   mv auth_json_backup.php auth.php
   # Repeat for data.php and account.php
   ```

2. Restore JSON files from backup:
   ```bash
   cp data/backup_YYYYMMDD_HHMMSS/*.json data/
   ```

3. Comment out encryption key check in `config.php` (lines 70-74)

---

## Support

If you encounter issues:

1. Check PHP error logs
2. Check MySQL error logs
3. Review this documentation
4. Check that all environment variables are set correctly
5. Verify file permissions

---

## Summary Checklist

Before going live with MySQL + encryption:

- [ ] Encryption key generated and stored securely
- [ ] Environment variables configured (DB credentials + encryption key)
- [ ] MySQL database created and schema imported
- [ ] Migration script run successfully
- [ ] API endpoints updated to MySQL versions
- [ ] Login tested with existing user
- [ ] New habit creation tested
- [ ] Habit names verified as encrypted in database
- [ ] Export/import tested
- [ ] Backups configured (database + encryption key)
- [ ] HTTPS enabled in production
- [ ] File permissions set correctly
- [ ] Monitoring/logging configured

---

**🎉 Congratulations!** Your habit tracker now uses MySQL with server-side encryption for habit names!
