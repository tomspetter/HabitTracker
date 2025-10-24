# ChainOfDots - Simple Habit Tracking

**Version: 1.0.0**

A beautiful, minimalist habit tracker with a year-at-a-glance view. Track up to 6 daily habits with color-coded circular dots inspired by GitHub's contribution graph.

## Live Demo

**Try it now at [chainofdots.com](https://chainofdots.com)** - Free to sign up and use!

You can either:
- **Sign up and use it** - Create a free account and start tracking your habits
- **View as a demo** - See the app in action before deciding to fork and self-host

No installation needed to try it out!

---

> **Note:** Now with secure authentication, statistics, and account management!

![Habit Tracker Preview](images/preview.png)

## Features

### Core Tracking
- **Year-at-a-Glance View**: 365-day calendar grid showing your entire year
- **6 Color-Coded Habits**: Track up to 6 different habits with rainbow colors (red, orange, yellow, green, blue, purple)
- **Circular Dots**: Clean, minimal circular indicators for each day (matching the "ChainOfDots" brand)
- **Streak Tracking**: Current streak and best streak for each habit
- **Quick Daily Tracking**: Check off today's habits with one click
- **Auto-Save**: Changes are automatically saved to the server

### Statistics & Analytics
- **Time Period Filters**: View stats for last 7 days, 30 days, or full year
- **Completion Rates**: Percentage completion for each habit
- **Overall Dashboard**: Total check-ins, average completion rate, best performing habit
- **Visual Progress Bars**: Color-coded progress indicators per habit

### Account Management
- **Email-Based Authentication**: Secure user accounts with email verification
- **Email Verification**: 6-digit verification codes sent via Brevo API
- **Password Reset**: Secure password recovery with 6-digit verification codes
- **Password Management**: Change your password anytime from settings
- **Data Export**: Download your data as CSV (user-friendly) or JSON (complete backup)
- **Data Import**: Restore from CSV or JSON files
- **Account Deletion**: Permanently delete your account and all data

### Security
- **AES-256 Encryption**: Habit names encrypted server-side with user-specific keys
- **Email Verification**: Required email verification for new account registration
- **Password Reset Flow**: Secure 3-step password recovery with verification codes
- **Bcrypt Password Hashing**: Industry-standard password encryption (never stored in plain text)
- **CSRF Protection**: Protected against cross-site request forgery attacks
- **Rate Limiting**: Login attempt limiting to prevent brute force attacks (5 attempts, 15-min lockout)
- **Code Expiration**: Verification codes expire after 15 minutes
- **Attempt Limiting**: Maximum 5 attempts per verification code
- **Session Management**: 1-hour session timeout with automatic renewal
- **Server-Side Storage**: All data stored securely on the server (no localStorage)
- **Protected Data Directory**: `.htaccess` blocks direct file access
- **Email Enumeration Prevention**: Password reset doesn't reveal if email exists

### Design
- **Clean Dark Theme**: GitHub-inspired dark interface for comfortable viewing
- **Mobile Responsive**: Works seamlessly on desktop and mobile devices
- **Responsive Navigation**: Hamburger menu on mobile, full nav on desktop

## Getting Started

### Requirements

- PHP 7.4 or higher with OpenSSL and PDO MySQL extensions enabled
- MySQL 5.7+ or MariaDB 10.2+
- Web server (Apache recommended for `.htaccess` support)
- Email service account (Brevo/Sendinblue recommended - free tier available)
- cURL extension enabled in PHP

### Installation

1. Download or clone this repository
2. Place files in your web server directory
3. **Set up MySQL database**:
   ```bash
   # Create database
   mysql -u root -p -e "CREATE DATABASE habittracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

   # Import schema
   mysql -u root -p habittracker < app/schema.sql

   # Verify tables were created
   mysql -u root -p habittracker -e "SHOW TABLES;"
   ```

4. **Generate encryption key** (for habit name encryption):
   ```bash
   openssl rand -base64 32
   # Save this key securely - you'll need it for the environment variables
   ```

5. **Configure environment variables** (database and encryption):

   **For Apache (.htaccess in project root):**
   ```apache
   SetEnv DB_HOST "localhost"
   SetEnv DB_NAME "habittracker"
   SetEnv DB_USER "root"
   SetEnv DB_PASS "your_mysql_password"
   SetEnv HABIT_ENCRYPTION_KEY "your_generated_key_from_step_4"
   ```

   **For nginx (PHP-FPM pool config):**
   ```ini
   env[DB_HOST] = localhost
   env[DB_NAME] = habittracker
   env[DB_USER] = root
   env[DB_PASS] = your_mysql_password
   env[HABIT_ENCRYPTION_KEY] = your_generated_key_from_step_4
   ```

6. **Configure Email Service** (required for registration and password reset):
   ```bash
   cd app/
   cp email_config.sample.php email_config.php
   nano email_config.php  # Edit with your Brevo API key and settings
   ```

   To get a Brevo API key:
   - Sign up at [Brevo](https://www.brevo.com) (free tier: 300 emails/day)
   - Go to **Settings** → **API Keys** → Create a new API key
   - Add your sender email and verify your domain (see DNS setup section below)

7. **Verify configuration**:
   ```bash
   # Test database connection
   php -r "require 'app/config.php'; try { getDBConnection(); echo 'Database: Connected\n'; } catch (Exception \$e) { echo 'Database: Failed\n'; }"

   # Test encryption key
   php -r "echo getenv('HABIT_ENCRYPTION_KEY') ? 'Encryption Key: OK\n' : 'Encryption Key: NOT SET\n';"
   ```

8. Start your web server (or use PHP's built-in server for testing):
   ```bash
   php -S localhost:8000
   ```
9. Open `http://localhost:8000` in your web browser (marketing site)
10. Click "Launch App" to access the habit tracker at `http://localhost:8000/app/`
11. Click "Register" to create your account
12. Check your email for the 6-digit verification code
13. Start tracking your habits!

### Email DNS Setup (for production)

For emails to be trusted and not marked as spam, add these DNS records to your domain:

1. **SPF Record** (authorize Brevo to send on your behalf)
2. **DKIM Record** (cryptographically sign your emails)
3. **DMARC Record** (policy for handling unauthenticated emails)

Brevo provides these records in **Senders & IP** → **Domains** after you add your domain. Add them as TXT records in your DNS settings.

### First Time Setup

1. Navigate to the app in your browser
2. Click "Don't have an account? Register"
3. Enter your email address
4. Choose a strong password (min 8 characters)
5. Click "Send Verification Code"
6. Check your email for the 6-digit code
7. Enter the code to complete registration
8. Start tracking your habits!

## Deploying to a Public Server

⚠️ **Important**: Additional security steps are required for public deployment.

### Prerequisites

- Web server with PHP 7.4+ (Apache/Nginx)
- SSL/TLS certificate (HTTPS is **required**)
- Domain name
- SSH access to server

### Deployment Steps

1. **Enable HTTPS First**

   ```bash
   # Use Let's Encrypt for free SSL
   sudo certbot --apache -d yourdomain.com
   ```

2. **Upload Files**

   ```bash
   # Via Git
   git clone your-repo.git /var/www/yourdomain.com

   # Or via SFTP/SCP
   scp -r HabitTracker/ user@server:/var/www/yourdomain.com/
   ```

3. **Configure MySQL Database**

   ```bash
   # Create production database
   mysql -u root -p -e "CREATE DATABASE habittracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

   # Import schema
   mysql -u root -p habittracker < app/schema.sql

   # Create database user (recommended)
   mysql -u root -p -e "CREATE USER 'habittracker'@'localhost' IDENTIFIED BY 'strong_password_here';"
   mysql -u root -p -e "GRANT ALL PRIVILEGES ON habittracker.* TO 'habittracker'@'localhost';"
   mysql -u root -p -e "FLUSH PRIVILEGES;"
   ```

4. **Set Environment Variables**

   Generate encryption key and configure environment:
   ```bash
   # Generate encryption key
   openssl rand -base64 32

   # Add to Apache .htaccess or virtual host config:
   SetEnv DB_HOST "localhost"
   SetEnv DB_NAME "habittracker"
   SetEnv DB_USER "habittracker"
   SetEnv DB_PASS "your_db_password"
   SetEnv HABIT_ENCRYPTION_KEY "your_generated_key"
   ```

5. **Set Proper Permissions**

   ```bash
   # Protect configuration files
   sudo chmod 600 app/email_config.php
   sudo chmod 644 app/config.php
   sudo chmod 644 app/schema.sql
   ```

6. **Configure Apache Virtual Host**

   ```apache
   <VirtualHost *:443>
       ServerName yourdomain.com
       DocumentRoot /var/www/yourdomain.com

       SSLEngine on
       SSLCertificateFile /path/to/cert.pem
       SSLCertificateKeyFile /path/to/key.pem

       <Directory /var/www/yourdomain.com>
           AllowOverride All
           Require all granted
       </Directory>

       # Security headers
       Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
       Header always set X-Content-Type-Options "nosniff"
       Header always set X-Frame-Options "DENY"
   </VirtualHost>

   # Redirect HTTP to HTTPS
   <VirtualHost *:80>
       ServerName yourdomain.com
       Redirect permanent / https://yourdomain.com/
   </VirtualHost>
   ```

7. **Update PHP Configuration**

   ```bash
   # Edit php.ini for production
   sudo nano /etc/php/8.x/apache2/php.ini
   ```

   Set these values:

   ```ini
   expose_php = Off
   display_errors = Off
   log_errors = On
   session.cookie_secure = On
   session.cookie_httponly = On
   ```

8. **Test the Deployment**

   - Visit `https://yourdomain.com`
   - Verify HTTPS is working (green padlock)
   - Test registration and login
   - Check that data saves properly

9. **Set Up Backups**
   ```bash
   # Cron job to backup MySQL database daily
   0 2 * * * mysqldump -u habittracker -p'your_password' habittracker | gzip > /backups/habittracker-$(date +\%Y\%m\%d).sql.gz
   ```

### Security Checklist for Production

- [ ] HTTPS enabled and enforced
- [ ] MySQL database created with proper user privileges
- [ ] Encryption key set in environment variables (never in code)
- [ ] Database credentials set in environment variables
- [ ] PHP `display_errors` is Off
- [ ] PHP `expose_php` is Off
- [ ] `.htaccess` files are in place
- [ ] Regular database backups configured
- [ ] Firewall configured (only 80/443 open)
- [ ] Keep PHP, MySQL, and server software updated
- [ ] Monitor server logs regularly

### Recommended: Additional Hardening

For extra security on public servers:

1. **Rate Limiting at Server Level**

   ```apache
   # Add to .htaccess
   <IfModule mod_ratelimit.c>
       SetOutputFilter RATE_LIMIT
       SetEnv rate-limit 400
   </IfModule>
   ```

2. **Fail2Ban Configuration**

   ```bash
   sudo apt install fail2ban
   # Configure to ban IPs after failed login attempts
   ```

3. **Encryption Key Rotation** (Advanced)
   - Implement encryption key rotation for long-term deployments
   - Requires re-encrypting all habit names with new key

See [SECURITY.md](SECURITY.md) for complete security documentation.

## Usage

### Habit Tracking
- **Add a Habit**: Click "Add Habit" button (max 6 habits)
- **Rename a Habit**: Click on the habit name to edit it
- **Mark Today**: Use the checkbox next to each habit name
- **Mark Any Day**: Click on any circular dot in the calendar grid
- **Remove a Habit**: Click "Remove Habit" button, then click the trash icon next to a habit
- **Auto-Save**: Changes are automatically saved to the server

### Navigation
- **Tracker**: Main habit tracking page (default view)
- **Statistics**: View analytics and insights for your habits
- **Settings**: Manage your account, export/import data, change password
- **Logout**: Click the logout button in the navigation bar

### Data Management
- **Export as CSV**: Download readable spreadsheet format (open in Excel/Google Sheets)
- **Export as JSON**: Download complete backup in JSON format
- **Import Data**: Restore from previously exported CSV or JSON files (auto-detected)
- **Delete Account**: Permanently remove your account and all associated data

## Security Features

✅ **Implemented Security Measures:**

### Data Encryption
- **AES-256-CBC encryption** for habit names (server-side)
- **User-specific encryption keys** derived from master key + user ID
- **Unique IVs** (Initialization Vectors) for each encrypted value
- **Environment variable** storage for master encryption key (never in code)
- Even if database is breached, habit names remain encrypted and unreadable

### Authentication & Passwords
- Email verification required for new accounts (6-digit codes)
- Secure password reset flow with verification codes
- **Bcrypt password hashing** - passwords never stored in plain text
- Server-side session management
- Session timeout (1 hour with automatic renewal)

### Attack Prevention
- CSRF token protection on all state-changing requests
- Login rate limiting (5 attempts, 15-minute lockout)
- Verification code rate limiting (60-second resend cooldown)
- Code expiration (15 minutes for verification, 5 minutes for reset tokens)
- Maximum attempt limiting (5 attempts per verification code)
- Email enumeration prevention (password reset doesn't reveal if user exists)

### Data Protection
- Protected data directory (`.htaccess` blocks direct access)
- Security headers (X-Frame-Options, XSS Protection, etc.)
- Server-side storage only (no localStorage for sensitive data)

See [SECURITY.md](SECURITY.md) for full security details.

## Roadmap

### ✅ Completed Features (v1.0.0)

- **Email-Based Authentication System**
  - Email verification with 6-digit codes (via Brevo API)
  - Secure password reset flow with verification codes
  - Email enumeration prevention
  - Rate limiting and code expiration
- **User Account Management**
  - Server-side authentication with bcrypt password hashing
  - CSRF protection on all state-changing requests
  - Login rate limiting (5 attempts, 15-min lockout)
  - Session management with 1-hour timeout
- **Habit Tracking**
  - Year-at-a-glance calendar (365 days with circular dots)
  - 6 color-coded habits (rainbow palette)
  - Streak tracking (current streak + best streak)
  - Auto-save functionality
- **Statistics & Analytics Page**
  - Time period filters (7 days, 30 days, year)
  - Completion percentages and rates
  - Overall dashboard with best performing habit
  - Color-coded progress bars
- **Account Settings Page**
  - Change password
  - Export data (CSV and JSON formats)
  - Import data (auto-detect CSV/JSON)
  - Delete account with confirmation
- **Marketing Site**
  - Professional landing page with feature highlights
  - About, Contact, and documentation pages
  - ChainOfDots branding and logo
- **Design & UX**
  - Responsive navigation (desktop + mobile hamburger menu)
  - Mobile responsive design
  - GitHub-inspired dark theme
  - Accessible UI with ARIA labels

### 💡 Future Enhancements (Post v1.0)

- **Better Mobile UX**: Larger tap targets, swipe gestures, improved scrolling
- **Dark/Light Theme Toggle**: User-selectable color schemes
- **Email Reminders**: Daily notifications and streak alerts
- **Achievement Badges**: Milestone celebrations (7-day, 30-day streaks, etc.)
- **Two-Factor Authentication**: Optional 2FA for enhanced security
- **Multi-year Support**: View and track habits across multiple years
- **Encryption Key Rotation**: Automated key rotation with re-encryption

## Technology Stack

### Frontend

- React 18 (via CDN)
- Tailwind CSS (via CDN)
- Pure JavaScript (ES6+)

### Backend

- PHP 8.3+ with OpenSSL and PDO MySQL extensions
- MySQL 5.7+ database with AES-256-CBC encryption
- Session-based authentication
- Server-side habit name encryption

## File Structure

```
HabitTracker/
├── index.html          # Marketing landing page
├── about.html          # About page
├── contact.html        # Contact page
├── app/                # Main application
│   ├── index.html      # Habit tracker (React frontend)
│   ├── config.php      # Configuration, encryption, and database functions
│   ├── schema.sql      # MySQL database schema
│   ├── verification_helpers.php  # Email verification helpers
│   ├── email_service.php         # Email sending service
│   ├── email_config.php          # Email API configuration (not in git)
│   ├── api/
│   │   ├── auth.php    # Authentication endpoints (MySQL)
│   │   ├── account.php # Account management endpoints (MySQL)
│   │   ├── data.php    # Data storage/export/import endpoints (MySQL)
│   │   └── .htaccess   # Security headers
│   └── QUICK_REFERENCE.md  # Quick setup guide
├── images/
│   ├── logo.svg        # ChainOfDots logo
│   └── preview.png     # Screenshot for marketing site
├── README.md           # This file
└── SECURITY.md         # Security documentation
```

## License

**Code:** MIT License - Free to use, modify, and distribute.

**Logo/Branding:** © Tom Spetter - Please use your own branding when forking or deploying your own instance.

## Contributing

This is a personal project. Feel free to fork and modify for your own needs!

## Security Note

✅ **This version implements proper security practices** including password hashing, CSRF protection, and rate limiting. It is suitable for personal use on a local server or trusted hosting environment.

⚠️ **For production deployment**: Always use HTTPS and ensure proper server configuration. See [SECURITY.md](SECURITY.md) for deployment recommendations.
