# Changelog

All notable changes to ChainOfDots will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2025-01-11

### Added - GDPR/PIPEDA Compliance
- **Cookie Consent Banner** - GDPR-compliant opt-in/opt-out for analytics cookies
  - Non-intrusive dark theme design matching site aesthetics
  - Renamed to `app-init.js` to bypass ad blockers
  - Granular control: Accept all, essential only, or customize
  - localStorage tracking for user preferences
- **Privacy Policy Page** (`privacy.html`)
  - Comprehensive disclosure of data collection and encryption practices
  - First-person language ("I" vs "we") for authentic sole proprietorship voice
  - Details on AES-256-CBC encryption and bcrypt password hashing
  - Third-party service disclosure (Brevo, Umami)
  - User rights: access, portability, erasure (GDPR Articles 15-20)
  - Data retention policy: immediate deletion, 14-day backup retention
  - Contact information and supervisory authorities (Canada & EU)
- **Terms of Service Page** (`terms.html`)
  - Legal terms for service usage
  - MIT License for code, copyright for branding
  - Acceptable use policy and account termination conditions
- **Privacy Policy Links in Emails**
  - Added to HTML and plain text versions of verification emails
  - Added to password reset emails
  - Footer format: "Privacy Policy: https://chainofdots.com/privacy.html"
- **Data Breach Response Plan**
  - 72-hour GDPR notification procedures documented
  - Contact information for Canadian and EU authorities
  - User notification templates
  - Incident log templates
  - Stored privately (not in public repository)

### Added - Security Enhancements
- **Comprehensive Security Headers** (root `.htaccess`)
  - X-Frame-Options: DENY (clickjacking protection)
  - X-XSS-Protection: 1; mode=block
  - X-Content-Type-Options: nosniff (MIME sniffing protection)
  - Referrer-Policy: strict-origin-when-cross-origin
  - Content-Security-Policy: Restricts resource loading to trusted sources
  - Permissions-Policy: Disables geolocation, camera, microphone, payment APIs
  - Strict-Transport-Security (HSTS) - commented out until SSL enabled
  - Directory browsing disabled
  - Sensitive file blocking (.env, .git, .log, .sql, .md files)
- **Input Validation & Sanitization**
  - Email validation: format + length checks (max 255 characters)
  - Password validation: 8-72 characters (prevents bcrypt DoS)
  - Habit name validation: max 500 characters before encryption
  - Color validation: max 20 characters, prevents injection
  - Payload size limits: 1MB maximum request size
  - Habit count limits: max 10 habits per user
  - Entry count limits: max 5,000 habit entries per save operation
- **Verification Code Security**
  - Code reuse prevention: marked as used after validation
  - Already had: 15-minute expiration, 5 max attempts, 60-second resend cooldown

### Added - Documentation & Organization
- **docs/ Folder** - Centralized documentation directory
  - `docs/README.md` - Documentation index with quick links
  - `docs/SECURITY.md` - Comprehensive security documentation (moved from root)
  - `docs/COOKIE-CONSENT.md` - Cookie consent implementation guide (moved & renamed)
  - `docs/QUICK_REFERENCE.md` - Quick setup guide (moved from app/)
  - `docs/.env.example` - Environment variables template (moved from app/)
  - `docs/.htaccess.example` - Apache config example (moved & renamed from app/)
- **CHANGELOG.md** - This file
- **Version Constant** - `APP_VERSION` in `config.php` for centralized version management

### Changed
- **File Organization** - Moved all documentation to `docs/` folder for cleaner root directory
- **Updated README.md** - All documentation links now point to `docs/` folder
- **Updated File Structure Diagram** - Reflects new `docs/` folder organization

### Security
- All new features follow security best practices
- No breaking changes to existing security implementations
- Enhanced defense-in-depth with multiple layers of protection

---

## [1.0.0] - 2024-10-24

### Added
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
- **Security Features**
  - AES-256-CBC encryption for habit names (server-side)
  - User-specific encryption keys
  - MySQL database with proper security
  - Protected data directory
  - Session security (httponly, samesite)

### Security
- bcrypt password hashing
- CSRF protection
- Rate limiting
- Email verification required
- Encrypted habit names
- Secure session management

---

## Version Numbering

ChainOfDots follows [Semantic Versioning](https://semver.org/):

- **MAJOR** version (x.0.0) - Incompatible API changes
- **MINOR** version (1.x.0) - New features, backward compatible
- **PATCH** version (1.1.x) - Bug fixes, backward compatible

[1.1.0]: https://github.com/tomspetter/ChainOfDots/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/tomspetter/ChainOfDots/releases/tag/v1.0.0
