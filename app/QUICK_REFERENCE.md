# HabitTracker MySQL + Encryption - Quick Reference

## 🔑 Generate Encryption Key

```bash
openssl rand -base64 32
```

## 🔧 Set Up Environment Variables

### Apache (.htaccess)
```apache
SetEnv DB_HOST "localhost"
SetEnv DB_NAME "habittracker"
SetEnv DB_USER "db_username"
SetEnv DB_PASS "db_password"
SetEnv HABIT_ENCRYPTION_KEY "your-generated-key-here"
```

### nginx (PHP-FPM pool config)
```ini
env[DB_HOST] = localhost
env[DB_NAME] = habittracker
env[DB_USER] = db_username
env[DB_PASS] = db_password
env[HABIT_ENCRYPTION_KEY] = your-generated-key-here
```

### Development (.env file)
```bash
export DB_HOST="localhost"
export DB_NAME="habittracker"
export DB_USER="root"
export DB_PASS=""
export HABIT_ENCRYPTION_KEY="your-generated-key-here"
```

## 📊 Database Setup

```bash
# Create database
mysql -u root -p -e "CREATE DATABASE habittracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import schema
mysql -u root -p habittracker < app/schema.sql

# Verify tables
mysql -u root -p habittracker -e "SHOW TABLES;"
```

## 🚀 Migration

```bash
# Set environment variables (if using .env)
source .env

# Run migration
php app/migrate_to_mysql.php
```

## 🔄 Switch to MySQL Endpoints

```bash
cd app/api

# Backup JSON versions
mv auth.php auth_json_backup.php
mv data.php data_json_backup.php
mv account.php account_json_backup.php

# Activate MySQL versions
mv auth_mysql.php auth.php
mv data_mysql.php data.php
mv account_mysql.php account.php

# Update helpers
cd ..
mv verification_helpers.php verification_helpers_json_backup.php
mv verification_helpers_mysql.php verification_helpers.php
```

## 🧪 Testing

```bash
# Verify encryption key is accessible
php -r "echo getenv('HABIT_ENCRYPTION_KEY') ? 'OK' : 'NOT SET'; echo PHP_EOL;"

# Test database connection
php -r "require 'app/config.php'; try { getDBConnection(); echo 'Connected\n'; } catch (Exception \$e) { echo 'Failed: ' . \$e->getMessage() . \n'; }"

# Check encrypted habits in database
mysql -u root -p habittracker -e "SELECT id, LEFT(name_encrypted, 30) as encrypted_name, color FROM habits LIMIT 3;"
```

## 🔍 Common SQL Queries

```sql
-- Count users
SELECT COUNT(*) as user_count FROM users;

-- Count habits per user
SELECT u.email, COUNT(h.id) as habit_count
FROM users u
LEFT JOIN habits h ON u.id = h.user_id
GROUP BY u.email;

-- Count total habit entries
SELECT COUNT(*) as total_entries FROM habit_entries;

-- View most recent entries
SELECT u.email, h.id as habit_id, he.date, he.completed
FROM habit_entries he
JOIN habits h ON he.habit_id = h.id
JOIN users u ON h.user_id = u.id
ORDER BY he.date DESC
LIMIT 10;

-- Find locked accounts
SELECT email, attempt_count, locked_until
FROM login_attempts
WHERE locked_until > NOW();
```

## 🧹 Maintenance

```sql
-- Clean up expired data (run daily via cron)
DELETE FROM verification_codes WHERE expires_at < NOW();
DELETE FROM pending_registrations WHERE expires_at < NOW();
DELETE FROM login_attempts WHERE locked_until IS NOT NULL AND locked_until < DATE_SUB(NOW(), INTERVAL 1 DAY);
```

## 💾 Backup

```bash
# Backup database
mysqldump -u root -p habittracker > backup_$(date +%Y%m%d_%H%M%S).sql

# Backup with compression
mysqldump -u root -p habittracker | gzip > backup_$(date +%Y%m%d_%H%M%S).sql.gz

# Restore from backup
mysql -u root -p habittracker < backup_20250120_143022.sql
```

## 🐛 Troubleshooting

### App shows "FATAL ERROR: HABIT_ENCRYPTION_KEY not set"
```bash
# Check if env var is accessible to PHP
php -r "var_dump(getenv('HABIT_ENCRYPTION_KEY'));"

# For Apache: restart web server
sudo systemctl restart apache2

# For nginx: restart PHP-FPM
sudo systemctl restart php7.4-fpm
```

### "Database connection failed"
```bash
# Check MySQL is running
sudo systemctl status mysql

# Test credentials
mysql -u your_username -p your_database

# Check database exists
mysql -u root -p -e "SHOW DATABASES LIKE 'habittracker';"
```

### Habits not decrypting
```bash
# Check PHP error logs
tail -f /var/log/php_errors.log

# For Apache
tail -f /var/log/apache2/error.log

# For nginx
tail -f /var/log/nginx/error.log
```

### Migration failed
```bash
# Check pre-flight requirements
php -r "echo extension_loaded('openssl') ? 'OpenSSL: OK' : 'OpenSSL: MISSING'; echo PHP_EOL;"
php -r "echo extension_loaded('pdo_mysql') ? 'PDO MySQL: OK' : 'PDO MySQL: MISSING'; echo PHP_EOL;"

# Re-run migration (safe to run multiple times)
php app/migrate_to_mysql.php
```

## 🔐 Security Checklist

- [ ] Encryption key generated with `openssl rand -base64 32`
- [ ] Encryption key stored securely (password manager)
- [ ] `.env` file added to `.gitignore`
- [ ] Strong database password set
- [ ] Database user has minimum required privileges
- [ ] HTTPS enabled in production
- [ ] File permissions set correctly (`chmod 644 *.php`)
- [ ] Backups configured (database + encryption key)
- [ ] Different encryption keys for dev/staging/prod

## 📁 File Structure

```
app/
├── schema.sql                          # Database schema
├── config.php                          # Config with encryption functions
├── migrate_to_mysql.php                # Migration script
├── verification_helpers_mysql.php      # MySQL verification helpers
├── .env.example                        # Environment template
├── MYSQL_SETUP.md                      # Full setup guide
├── QUICK_REFERENCE.md                  # This file
│
├── api/
│   ├── auth_mysql.php                  # MySQL auth endpoint
│   ├── data_mysql.php                  # MySQL data endpoint (with encryption)
│   ├── account_mysql.php               # MySQL account endpoint
│   ├── auth_json_backup.php            # Original JSON version (backup)
│   ├── data_json_backup.php            # Original JSON version (backup)
│   └── account_json_backup.php         # Original JSON version (backup)
│
└── data/
    └── backup_YYYYMMDD_HHMMSS/         # Automatic JSON backups
```

## 🔗 Key Concepts

### Encryption Flow
```
User Input: "Exercise daily"
     ↓
encryptHabitName($name, $userId)
     ↓
User-specific key = HMAC(master_key, user_id)
     ↓
Random IV generated (16 bytes)
     ↓
AES-256-CBC encryption
     ↓
Database: base64(IV + encrypted_data)
```

### Decryption Flow
```
Database: "a8f3k9s0d2j4h6lM9p1r3t5v..."
     ↓
decryptHabitName($encrypted, $userId)
     ↓
base64_decode()
     ↓
Extract IV (first 16 bytes)
     ↓
Extract encrypted data (remaining bytes)
     ↓
User-specific key = HMAC(master_key, user_id)
     ↓
AES-256-CBC decryption
     ↓
Return: "Exercise daily"
```

### What's Protected
✅ **Habit names** - Encrypted with AES-256-CBC
✅ **Passwords** - Hashed with bcrypt
❌ **Dates** - Not encrypted (needed for queries)
❌ **Colors** - Not encrypted (not sensitive)
❌ **Emails** - Not encrypted (needed for login)

---

**Need more help?** See [MYSQL_SETUP.md](MYSQL_SETUP.md) for detailed setup instructions.
