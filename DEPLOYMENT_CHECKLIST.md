# MySQL Migration - Deployment Checklist

Use this checklist when migrating your HabitTracker from JSON to MySQL with encryption.

---

## 📋 Pre-Migration Checklist

### Environment Preparation

- [ ] MySQL 5.7+ or MariaDB 10.2+ installed and running
- [ ] PHP 7.4+ installed
- [ ] PHP OpenSSL extension enabled
  ```bash
  php -m | grep openssl
  ```
- [ ] PHP PDO MySQL extension enabled
  ```bash
  php -m | grep pdo_mysql
  ```
- [ ] Command-line access to server
- [ ] Backup of current application and data directory

### Security Preparation

- [ ] Encryption key generated
  ```bash
  openssl rand -base64 32
  ```
- [ ] Encryption key stored in password manager (backup!)
- [ ] Database password chosen (strong, random)
- [ ] `.gitignore` updated to exclude `.env` and database backups

---

## 🗄️ Database Setup

- [ ] Database created
  ```bash
  mysql -u root -p -e "CREATE DATABASE habittracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  ```

- [ ] Database user created (production only)
  ```sql
  CREATE USER 'habittracker'@'localhost' IDENTIFIED BY 'strong_password_here';
  GRANT ALL PRIVILEGES ON habittracker.* TO 'habittracker'@'localhost';
  FLUSH PRIVILEGES;
  ```

- [ ] Schema imported
  ```bash
  mysql -u root -p habittracker < app/schema.sql
  ```

- [ ] Tables verified
  ```bash
  mysql -u root -p habittracker -e "SHOW TABLES;"
  ```
  Should show: `users`, `habits`, `habit_entries`, `login_attempts`, `pending_registrations`, `verification_codes`

---

## 🔧 Configuration

### Environment Variables

- [ ] Environment variables configured for your web server:

**Apache (.htaccess):**
```apache
SetEnv DB_HOST "localhost"
SetEnv DB_NAME "habittracker"
SetEnv DB_USER "habittracker"
SetEnv DB_PASS "your_db_password"
SetEnv HABIT_ENCRYPTION_KEY "your_generated_key"
```

**nginx (PHP-FPM pool config):**
```ini
env[DB_HOST] = localhost
env[DB_NAME] = habittracker
env[DB_USER] = habittracker
env[DB_PASS] = your_db_password
env[HABIT_ENCRYPTION_KEY] = your_generated_key
```

- [ ] Web server restarted
  ```bash
  # Apache
  sudo systemctl restart apache2

  # nginx + PHP-FPM
  sudo systemctl restart php7.4-fpm
  ```

- [ ] Environment variables accessible to PHP
  ```bash
  php -r "echo getenv('HABIT_ENCRYPTION_KEY') ? 'OK' : 'FAILED'; echo PHP_EOL;"
  ```

---

## 🚀 Migration

### Before Migration

- [ ] Application is in maintenance mode (or off-hours)
- [ ] No users are currently logged in
- [ ] Recent database backup exists (if re-migrating)

### Run Migration

- [ ] Environment variables loaded (if using .env file)
  ```bash
  source .env
  ```

- [ ] Migration script executed
  ```bash
  php app/migrate_to_mysql.php
  ```

- [ ] Migration completed successfully
  - Check for "MIGRATION COMPLETED SUCCESSFULLY!" message
  - Review migration output for any errors
  - Note backup location (e.g., `data/backup_20250120_143022`)

### Verify Migration

- [ ] User count matches
  ```sql
  SELECT COUNT(*) FROM users;
  ```
  Compare with original `users.json`

- [ ] Habit count looks reasonable
  ```sql
  SELECT COUNT(*) FROM habits;
  ```

- [ ] Entries were migrated
  ```sql
  SELECT COUNT(*) FROM habit_entries;
  ```

- [ ] Encryption test passed
  ```sql
  SELECT id, LEFT(name_encrypted, 30) as encrypted, color FROM habits LIMIT 3;
  ```
  `name_encrypted` should be gibberish (base64 encoded)

---

## 🔄 Switch to MySQL Endpoints

- [ ] Backup original API files
  ```bash
  cd app/api
  cp auth.php auth_json_backup.php
  cp data.php data_json_backup.php
  cp account.php account_json_backup.php
  ```

- [ ] Replace with MySQL versions
  ```bash
  mv auth_mysql.php auth.php
  mv data_mysql.php data.php
  mv account_mysql.php account.php
  ```

- [ ] Update verification helpers
  ```bash
  cd app
  cp verification_helpers.php verification_helpers_json_backup.php
  mv verification_helpers_mysql.php verification_helpers.php
  ```

- [ ] File permissions set correctly
  ```bash
  chmod 644 app/api/*.php
  chmod 644 app/*.php
  chmod 755 app/data
  ```

---

## ✅ Testing

### Basic Functionality

- [ ] App loads without "FATAL ERROR" message
- [ ] Existing user can log in
- [ ] User sees their habits correctly (decrypted)
- [ ] Habit names match what they were before migration
- [ ] Can create a new habit
- [ ] Can mark dates as complete
- [ ] Can view statistics/streaks
- [ ] Can export data (JSON/CSV)
- [ ] Export contains readable habit names (decrypted)
- [ ] Can import data
- [ ] Can change password
- [ ] Can log out and log back in

### Security Verification

- [ ] Habit names are encrypted in database
  ```bash
  mysql -u root -p habittracker -e "SELECT id, name_encrypted FROM habits LIMIT 1;"
  ```
  Should see base64-encoded gibberish, NOT plain text

- [ ] Passwords are hashed in database
  ```bash
  mysql -u root -p habittracker -e "SELECT email, password_hash FROM users LIMIT 1;"
  ```
  Should see bcrypt hash starting with `$2y$`

- [ ] Cannot access app without encryption key
  - Temporarily unset encryption key
  - Should see "FATAL ERROR" message
  - Re-set encryption key

### Edge Cases

- [ ] User with no habits works correctly
- [ ] Creating maximum habits (6) works
- [ ] Deleting habits works
- [ ] Account deletion works (and cascades)
- [ ] Password reset flow works
- [ ] Email verification works (if using)
- [ ] Rate limiting works (try 5+ failed logins)

---

## 🔐 Security Hardening

### Production Security

- [ ] HTTPS enabled and enforced
- [ ] SSL certificate valid
- [ ] HSTS header configured
  ```apache
  Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
  ```

- [ ] Database user has minimum required privileges
  ```sql
  -- Should have access only to habittracker database, not all databases
  SHOW GRANTS FOR 'habittracker'@'localhost';
  ```

- [ ] Encryption key NOT committed to version control
  ```bash
  git status
  # Should NOT show .env or .htaccess with encryption key
  ```

- [ ] Database backups configured
  ```bash
  # Add to crontab
  0 3 * * * mysqldump -u root -p'password' habittracker > /backup/habittracker_$(date +\%Y\%m\%d).sql
  ```

- [ ] Error reporting disabled in production
  ```php
  // In php.ini or .htaccess
  display_errors = Off
  log_errors = On
  ```

### File Permissions

- [ ] API files not writable by web server
  ```bash
  ls -la app/api/*.php
  # Should be 644 or 444, NOT 755 or 777
  ```

- [ ] Config file protected
  ```bash
  ls -la app/config.php
  # Should be 644 or 440
  ```

- [ ] Data directory not web-accessible
  ```apache
  # In .htaccess or Apache config
  <Directory "/path/to/app/data">
      Require all denied
  </Directory>
  ```

---

## 📊 Monitoring

### Set Up Monitoring

- [ ] Error logging enabled
  ```bash
  tail -f /var/log/php_errors.log
  tail -f /var/log/apache2/error.log  # or nginx
  ```

- [ ] Database size monitoring
  ```sql
  -- Run weekly
  SELECT table_name, ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb
  FROM information_schema.TABLES
  WHERE table_schema = 'habittracker';
  ```

- [ ] Failed login attempts monitoring
  ```sql
  -- Check daily
  SELECT email, attempt_count, locked_until
  FROM login_attempts
  WHERE attempt_count >= 3;
  ```

### Maintenance Tasks

- [ ] Cleanup cron job configured
  ```bash
  # Add to crontab: crontab -e
  0 3 * * * mysql -u root -p'password' habittracker <<< "DELETE FROM verification_codes WHERE expires_at < NOW(); DELETE FROM pending_registrations WHERE expires_at < NOW();"
  ```

---

## 📦 Backup & Recovery

### Backups Configured

- [ ] Database backup script created
  ```bash
  #!/bin/bash
  mysqldump -u habittracker -p'password' habittracker | gzip > backup_$(date +%Y%m%d_%H%M%S).sql.gz
  ```

- [ ] Encryption key backed up
  - [ ] Stored in password manager
  - [ ] Secure offline backup exists
  - [ ] Team members have access (if applicable)

- [ ] JSON backup preserved
  ```bash
  ls -la app/data/backup_*/
  # Should see backup directory from migration
  ```

- [ ] Restoration tested
  ```bash
  # Test restore from backup (on dev server)
  mysql -u root -p habittracker < backup_YYYYMMDD.sql
  ```

---

## 🎉 Post-Migration

### Documentation

- [ ] Migration date recorded
- [ ] Team notified of changes (if applicable)
- [ ] Documentation updated with new setup
- [ ] Encryption key location documented (securely)

### Cleanup (After Confirming Success)

**Wait at least 1 week before cleaning up!**

- [ ] Verified migration successful for 7+ days
- [ ] No user-reported issues
- [ ] Can restore from MySQL backup if needed

Then optionally:
- [ ] Archive JSON backups to secure location
  ```bash
  tar -czf json_backups_YYYYMMDD.tar.gz app/data/backup_*/
  mv json_backups_YYYYMMDD.tar.gz /secure/archive/location/
  ```

- [ ] Remove old JSON backup files from server (keep archive!)
  ```bash
  # Only after confirming archive is safe!
  rm -rf app/data/backup_*/
  ```

**DO NOT delete:**
- Original JSON files (users.json, user_*.json) - keep as additional backup
- JSON backup API files (auth_json_backup.php, etc.) - may need for rollback

---

## 🚨 Rollback Plan

If something goes wrong:

### Immediate Rollback (Within 1 hour of migration)

- [ ] Stop using MySQL endpoints
  ```bash
  cd app/api
  mv auth.php auth_mysql.php
  mv auth_json_backup.php auth.php
  mv data.php data_mysql.php
  mv data_json_backup.php data.php
  mv account.php account_mysql.php
  mv account_json_backup.php account.php
  ```

- [ ] Restore verification helpers
  ```bash
  cd app
  mv verification_helpers.php verification_helpers_mysql.php
  mv verification_helpers_json_backup.php verification_helpers.php
  ```

- [ ] Restore JSON files from backup (if they were modified)
  ```bash
  cp app/data/backup_YYYYMMDD_HHMMSS/*.json app/data/
  ```

### Emergency Contact Info

- Database administrator: _______________
- Encryption key location: _______________ (password manager)
- Backup location: _______________

---

## ✅ Final Verification

- [ ] All tests passing
- [ ] No errors in logs
- [ ] Users can use all features
- [ ] Data integrity confirmed
- [ ] Encryption working (habit names are gibberish in DB)
- [ ] Backups configured and tested
- [ ] Monitoring in place
- [ ] Documentation updated
- [ ] Team notified

---

## 📞 Support

If you encounter issues:

1. Check [MYSQL_SETUP.md](app/MYSQL_SETUP.md) troubleshooting section
2. Review [QUICK_REFERENCE.md](app/QUICK_REFERENCE.md) for common commands
3. Check PHP error logs
4. Check MySQL error logs
5. Verify environment variables are set
6. Test database connection

---

**Migration Date:** _______________

**Migrated By:** _______________

**Notes:**
```
[Add any custom notes or environment-specific information here]
```

---

*Ready to migrate? Start with the Pre-Migration Checklist!*
