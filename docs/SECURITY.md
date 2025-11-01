# Security Policy

## Current Security Status

✅ **This project now implements secure authentication and data storage for local/personal server use.**

This habit tracker uses PHP-based server-side authentication with proper password hashing and session management. It is designed for **personal, local server use** or deployment on a trusted server.

## Implemented Security Features

### Data Encryption (MySQL Version)
- ✅ **AES-256-CBC encryption**: Habit names are encrypted server-side before storage in database
- ✅ **User-specific encryption keys**: Each user gets a unique encryption key derived from master key + user ID
- ✅ **Unique IVs**: Each encrypted value uses its own Initialization Vector for maximum security
- ✅ **Environment variable storage**: Master encryption key stored in `HABIT_ENCRYPTION_KEY` environment variable (never in code)
- ✅ **Breach protection**: Even if database is compromised, habit names remain encrypted and unreadable without the master key
- ✅ **OpenSSL implementation**: Uses PHP's OpenSSL extension with industry-standard AES-256-CBC

**What's Protected in a Database Breach:**
- ✅ Habit names (encrypted) - e.g., "Stop drinking", "Therapy sessions"
- ✅ Passwords (bcrypt hashed) - cannot be reversed
- ❌ Dates (not encrypted) - needed for efficient queries, meaningless without habit names
- ❌ Colors (not encrypted) - just UI preferences, not sensitive
- ❌ Emails (not encrypted) - needed for login, not considered secret

### Authentication & Email Verification
- ✅ **Email verification**: Required for new account registration using 6-digit codes
- ✅ **Password reset flow**: Secure 3-step password recovery with verification codes
- ✅ **Password hashing**: Passwords are hashed using PHP's `password_hash()` with bcrypt (PASSWORD_DEFAULT)
- ✅ **Session management**: Secure server-side sessions with httponly cookies
- ✅ **CSRF protection**: CSRF tokens required for all data-modifying operations
- ✅ **Login rate limiting**: 5 failed attempts trigger 15-minute lockout
- ✅ **Code rate limiting**: 60-second cooldown between verification code resends
- ✅ **Code expiration**: Verification codes expire after 15 minutes, reset tokens after 5 minutes
- ✅ **Attempt limiting**: Maximum 5 attempts per verification code before requiring new code
- ✅ **Email enumeration prevention**: Password reset doesn't reveal whether user exists
- ✅ **Session timeout**: Sessions expire after 1 hour of inactivity
- ✅ **No client-side password storage**: All authentication happens server-side

### Data Storage
- ✅ **MySQL database**: All habit data is stored securely in MySQL database
- ✅ **Per-user data isolation**: Each user has their own data with unique encryption keys
- ✅ **Protected database**: Encrypted habit names ensure privacy even in a database breach
- ✅ **No localStorage usage**: All sensitive data stays on the server
- ✅ **Database access control**: MySQL user privileges limit access to application database only

### Input Validation & Rate Limiting
- ✅ **Email validation**: Format and length checks (max 255 characters)
- ✅ **Password validation**: Length limits (8-72 characters, prevents bcrypt DoS)
- ✅ **Habit name validation**: Maximum 500 characters before encryption
- ✅ **Color validation**: Maximum 20 characters, prevents injection
- ✅ **Payload size limits**: 1MB maximum request size
- ✅ **Habit count limits**: Maximum 10 habits per user
- ✅ **Entry count limits**: Maximum 5,000 habit entries per save operation
- ✅ **Code reuse prevention**: Verification codes marked as used after validation

### Security Headers
- ✅ **X-Frame-Options (DENY)**: Prevents clickjacking attacks
- ✅ **X-XSS-Protection**: Enables browser XSS filter
- ✅ **X-Content-Type-Options (nosniff)**: Prevents MIME sniffing
- ✅ **Referrer-Policy (strict-origin-when-cross-origin)**: Controls referrer information
- ✅ **Content-Security-Policy (CSP)**: Restricts resource loading to trusted sources only
- ✅ **Permissions-Policy**: Disables geolocation, camera, microphone, payment APIs
- ✅ **Strict-Transport-Security (HSTS)**: Forces HTTPS (when SSL enabled)
- ✅ **Directory browsing disabled**: Prevents file listing
- ✅ **Sensitive file blocking**: Blocks access to .env, .git, .log, .sql, .md files

## Deployment Considerations

### ✅ Local/Personal Use (Current Status: SECURE)

For localhost or trusted personal server use:
- ✅ All security features are implemented
- ✅ Safe for personal habit tracking
- ✅ No additional steps needed
- ✅ HTTP is acceptable on localhost

**You're ready to use the app as-is for personal use!**

### ⚠️ Public Server Deployment (Additional Steps Required)

If deploying to a public web server, **you MUST complete this checklist:**

#### **Critical Requirements (Non-Negotiable)**

1. **Email Service Configuration**
   - ⚠️ **Required for registration and password reset**
   - Copy `app/email_config.sample.php` to `app/email_config.php`
   - Add your Brevo API key and sender email
   - Verify your domain with SPF, DKIM, and DMARC records
   - Set `EMAIL_ENABLED = false` to disable email features (registration will fail)
   - Reason: Users cannot register or reset passwords without email verification

2. **HTTPS/SSL Certificate**
   - ❌ **NEVER deploy without HTTPS**
   - Use Let's Encrypt (free) or purchase certificate
   - Our code automatically enables secure cookies when HTTPS is detected
   - Reason: Prevents session hijacking and man-in-the-middle attacks

3. **Strict File Permissions**
   ```bash
   sudo chmod 700 app/data/                    # Owner read/write/execute only
   sudo chmod 600 app/data/*.json             # Owner read/write only
   sudo chmod 600 app/email_config.php        # Protect API keys
   sudo chown www-data:www-data app/data/     # Web server owns files
   sudo chown www-data:www-data app/email_config.php
   ```
   - Reason: Prevents other users on shared hosting from reading password hashes and API keys

4. **PHP Production Configuration**
   ```ini
   display_errors = Off          # Don't show errors to users
   log_errors = On              # Log errors to file instead
   expose_php = Off             # Don't advertise PHP version
   session.cookie_secure = On   # HTTPS-only cookies
   ```
   - Reason: Prevents information leakage to attackers

4. **HTTP to HTTPS Redirect**
   ```apache
   # Force HTTPS
   RewriteEngine On
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```
   - Reason: Ensures users always connect securely

5. **Regular Backups**
   - Schedule automated backups of `data/` directory
   - Store backups off-server
   - Test restore process
   - Reason: Protect against data loss

#### **Recommended Security Enhancements**

6. **Server-Level Rate Limiting**
   - Install and configure Fail2Ban
   - Monitor failed login attempts in PHP logs
   - Ban IPs after repeated failures
   - Reason: Adds defense-in-depth beyond app-level rate limiting

7. **HTTP Security Headers**
   - HSTS (Strict-Transport-Security)
   - Content-Security-Policy
   - Already configured in `api/.htaccess`
   - Verify they're working: https://securityheaders.com

8. **Firewall Configuration**
   ```bash
   sudo ufw allow 80/tcp     # HTTP (for redirect)
   sudo ufw allow 443/tcp    # HTTPS
   sudo ufw allow 22/tcp     # SSH
   sudo ufw enable
   ```

9. **Keep Software Updated**
   ```bash
   sudo apt update && sudo apt upgrade    # Ubuntu/Debian
   ```
   - Update PHP, Apache, OS packages regularly
   - Subscribe to security mailing lists

#### **Optional: For High-Security Needs**

10. ~~**Database Migration**~~ ✅ **COMPLETED - MySQL support with encryption**
    - ✅ MySQL/PostgreSQL support implemented
    - ✅ AES-256-CBC encryption for habit names
    - ✅ User-specific encryption keys
    - ✅ Environment variable for master encryption key

11. ~~**Encryption at Rest**~~ ✅ **COMPLETED - AES-256 encryption**
    - ✅ Habit names encrypted with AES-256-CBC
    - ✅ Uses PHP's OpenSSL extension
    - ✅ User-specific keys with unique IVs per value
    - ✅ Master key stored in environment variable

12. **Two-Factor Authentication (2FA)**
    - Add TOTP support (Google Authenticator, Authy)
    - Requires additional development

13. ~~**Email Verification & Password Reset**~~ ✅ **COMPLETED in v1.0.0**
    - ✅ Email verification for new accounts (6-digit codes)
    - ✅ Password reset with verification codes
    - ✅ Brevo API integration
    - ✅ Rate limiting and code expiration

### For Developers

If forking this project for commercial/SaaS use, consider:
- ✅ **MySQL database** - already implemented with encryption
- Redis/Memcached for session storage at scale
- Container deployment (Docker/Kubernetes)
- CI/CD pipeline with security scanning
- Automated security testing (OWASP ZAP, etc.)
- DDoS protection (Cloudflare, AWS Shield)
- Compliance certifications (SOC 2, GDPR, etc.)
- Database connection pooling for high concurrency
- Read replicas for scaling read operations

## GDPR & PIPEDA Compliance

✅ **Full Privacy Law Compliance (EU & Canada):**

ChainOfDots is fully compliant with GDPR (EU General Data Protection Regulation) and PIPEDA (Canadian Personal Information Protection and Electronic Documents Act).

### Legal Documentation
- ✅ **Privacy Policy** ([privacy.html](privacy.html)): Comprehensive disclosure of data collection, encryption, and third-party services
- ✅ **Terms of Service** ([terms.html](terms.html)): Legal terms including MIT License for code, copyright for branding
- ✅ **Cookie Consent Banner**: Opt-in/opt-out mechanism for analytics cookies (app-init.js)
- ✅ **Footer Links**: Privacy policy and terms accessible from all pages (public site and app)
- ✅ **Email Privacy**: Privacy policy links included in all transactional emails

### Data Subject Rights (GDPR Articles 15-20)
- ✅ **Right to Access** (Art. 15): Users can view all their data in the app
- ✅ **Right to Portability** (Art. 20): Export data in CSV (human-readable) or JSON (machine-readable) formats
- ✅ **Right to Erasure** (Art. 17): Complete account deletion from Settings page
- ✅ **Right to Be Informed** (Art. 13-14): Privacy policy discloses all data collection
- ✅ **Right to Rectification** (Art. 16): Users can edit/update their habit data anytime

### Data Collection Transparency
The Privacy Policy discloses:
- **What data is collected**: Email address, encrypted habit names, completion dates, colors, sort order
- **How data is encrypted**: AES-256-CBC for habit names, bcrypt for passwords
- **What cannot be read**: "I cannot read your encrypted habit names without the master encryption key"
- **Third-party services**: Brevo (transactional email), Umami (privacy-focused analytics with opt-in)
- **Data retention**: Immediate deletion from database, 14-day backup retention
- **User location**: Operated by Tom Spetter Design, British Columbia, Canada

### Cookie Consent (GDPR Article 5(3))
- ✅ **Essential cookies**: Session cookies (no consent needed, necessary for service)
- ✅ **Analytics cookies**: Umami analytics (opt-in required)
- ✅ **Consent banner**: Non-intrusive dark theme banner on first visit
- ✅ **Granular control**: Accept all, essential only, or customize
- ✅ **LocalStorage tracking**: `chainofdots_cookie_consent` and `chainofdots_analytics_consent`
- ✅ **Respects DNT**: Analytics only load if user explicitly opts in

### Data Breach Response (GDPR Article 33-34)
- ✅ **72-hour notification plan**: Documented data breach response procedures
- ✅ **Contact information**: Office of Privacy Commissioner of Canada, EU Data Protection Authorities
- ✅ **User notification templates**: Pre-written email templates for affected users
- ✅ **Incident log**: Documentation templates for breach investigation
- ✅ **Secure storage**: Breach response plan stored privately (not in public repository)

### Contact Information
- **Email**: info@chainofdots.com (disclosed in Privacy Policy)
- **Operator**: Tom Spetter Design, sole proprietorship, British Columbia, Canada
- **Supervisory Authorities**:
  - Canada: Office of the Privacy Commissioner of Canada (1-800-282-1376)
  - EU: User's local Data Protection Authority

### First-Person Privacy Policy
The privacy policy is written in first-person ("I collect", "I use") instead of corporate voice ("we collect"), reflecting the authentic sole proprietorship nature of the operation.

## Email Security Features (v1.0.0)

✅ **Implemented Email Security:**

### Email Verification System
- **6-digit verification codes** sent via Brevo API
- **Code expiration**: 15-minute validity window
- **Attempt limiting**: Maximum 5 attempts per code
- **Rate limiting**: 60-second cooldown between resends
- **Code reuse prevention**: Codes marked as used after successful verification
- **Privacy policy links**: Included in email footer (HTML and plain text)

### Password Reset Flow
- **3-step verification process**:
  1. Request reset code (sent to email)
  2. Verify 6-digit code
  3. Set new password with temporary token (15-minute expiry)
- **Email enumeration prevention**: Doesn't reveal if email exists
- **Token expiration**: Reset tokens expire after 15 minutes
- **Auto-cleanup**: All codes cleared after successful password reset
- **Privacy policy links**: Included in email footer (HTML and plain text)

### Email Configuration
- **Optional feature**: Can be disabled via `EMAIL_ENABLED` flag
- **API key protection**: `email_config.php` excluded from git
- **Brevo API integration**: Transactional email service (GDPR compliant)
- **DNS authentication**: Supports SPF, DKIM, DMARC records
- **Privacy disclosure**: Brevo usage disclosed in Privacy Policy

⚠️ **Note**: Email features are OPTIONAL. Self-hosters can choose to:
- Enable email features (requires Brevo API key setup)
- Disable email (set `EMAIL_ENABLED = false` - registration will fail)

## Upcoming Security Enhancements

The following security features are planned for future releases:

### Data Export Security
- ✅ **CSV Export**: User-friendly format for viewing in spreadsheets
- ✅ **JSON Export**: Complete backup format
- ✅ **Auto-detection Import**: Accepts both CSV and JSON files
- ✅ **Authentication Required**: All export/import requires valid session
- **Planned**: Export encryption option for sensitive data
- **Planned**: Export history/audit log

### Account Management
- ✅ **Password Change**: Requires current password verification
- ✅ **Account Deletion**: Secure deletion with password confirmation and data purge
- ✅ **Data Export/Import**: Users can backup and restore their data
- **Planned**: Session management (view active sessions, logout all devices)
- **Planned**: Login history and activity log

## Migration from Previous Version

If you were using the old client-side version:
- Old data stored in localStorage will NOT be automatically migrated
- You will need to create a new account through the registration screen
- Manual migration: Export old data as CSV, then manually re-enter habits
- The new system does not read from localStorage

## Security Improvements Completed

The following security enhancements have been implemented:

### Authentication & Sessions
- ✅ Proper password hashing implementation (bcrypt)
- ✅ Server-side authentication with sessions
- ✅ CSRF token protection on all state-changing operations
- ✅ Rate limiting on login attempts (5 attempts, 15-minute lockout)
- ✅ Session timeout (1 hour of inactivity)
- ✅ Secure session cookies (httponly, samesite, secure on HTTPS)
- ✅ Password length validation (8-72 characters)
- ✅ Email verification for new accounts (6-digit codes)
- ✅ Secure password reset flow (3-step with verification)

### Data Protection
- ✅ AES-256-CBC encryption for habit names (server-side)
- ✅ User-specific encryption keys (master key + user ID derivation)
- ✅ Server-side data storage (no localStorage for sensitive data)
- ✅ Per-user data isolation with MySQL database
- ✅ Protected data directory (`.htaccess` blocks direct access)
- ✅ Directory browsing disabled
- ✅ Sensitive file access blocked (.env, .git, .log, .sql, .md)

### Input Validation & Attack Prevention
- ✅ Email validation (format + length, max 255 chars)
- ✅ Password validation (8-72 characters, prevents bcrypt DoS)
- ✅ Habit name validation (max 500 characters before encryption)
- ✅ Color validation (max 20 characters)
- ✅ Payload size limits (1MB maximum)
- ✅ Habit count limits (max 10 habits)
- ✅ Entry count limits (max 5,000 per save)
- ✅ SQL injection protection (prepared statements throughout)
- ✅ Verification code reuse prevention (marked as used)
- ✅ Email enumeration prevention (password reset flow)

### Account Management
- ✅ Password change with current password verification
- ✅ Secure account deletion (password confirmation + data purge)
- ✅ Data export (CSV and JSON) with authentication
- ✅ Data import with format auto-detection
- ✅ GDPR-compliant data portability and erasure

### HTTP Security Headers
- ✅ Root `.htaccess` with comprehensive security headers
- ✅ API `.htaccess` with endpoint-specific headers
- ✅ X-Frame-Options: DENY (clickjacking protection)
- ✅ X-XSS-Protection: 1; mode=block
- ✅ X-Content-Type-Options: nosniff (MIME sniffing protection)
- ✅ Referrer-Policy: strict-origin-when-cross-origin
- ✅ Content-Security-Policy (restricts to trusted sources only)
- ✅ Permissions-Policy (disables geolocation, camera, microphone, etc.)
- ✅ Strict-Transport-Security (HSTS, when SSL enabled)
- ✅ CSRF tokens for all API endpoints
- ✅ Proper error handling (no information leakage)

### GDPR/PIPEDA Compliance
- ✅ Cookie consent banner (opt-in/opt-out for analytics)
- ✅ Privacy Policy page (comprehensive disclosure)
- ✅ Terms of Service page
- ✅ Privacy policy links in all transactional emails
- ✅ Data export functionality (CSV/JSON)
- ✅ Account deletion functionality
- ✅ Data breach response plan (72-hour notification procedures)
- ✅ Footer links on all pages (public site and app)
- ✅ First-person privacy policy (authentic sole proprietorship)

## Reporting a Vulnerability

If you discover a security vulnerability in this project, please report it by:

1. **Opening a GitHub Issue** - Label it as "security"
2. **Email** - Contact the repository owner directly through GitHub

Please include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if you have one)

### Response Time
As this is a personal project and proof of concept:
- I will acknowledge reports within 7 days
- Fixes will be implemented on a best-effort basis
- Critical issues affecting personal data will be prioritized

## Supported Versions

| Version | Supported          | Status |
| ------- | ------------------ | ------ |
| 1.0.0+ (Current) | ✅ Production-Ready | Secure for local use; requires HTTPS for public deployment |
| Legacy (Pre-Oct 2024) | ❌ Deprecated | Plain-text passwords, localStorage only - do not use |

## Disclaimer

This software is provided "as is" under the MIT License. Use at your own risk. The author takes no responsibility for any security breaches, data loss, or other issues arising from the use of this software.
