# Security Policy

## Current Security Status

⚠️ **WARNING: This project is currently a proof of concept and NOT SECURE for production use.**

This habit tracker is designed for **personal, local use only**. It has known security vulnerabilities that will be addressed in future releases.

## Known Security Issues

### Authentication
- **Plain-text password storage**: The password is stored directly in the JavaScript code (line 70 of index.html) without any encryption or hashing
- **No session management**: Login state is managed client-side only
- **No secure authentication**: This implementation is NOT suitable for protecting sensitive data

### Data Storage
- **Client-side only**: All data is stored in browser localStorage with no encryption
- **No backup protection**: Data can be lost if browser data is cleared
- **CSV exports**: Exported data is unencrypted

## Recommendations

### For Users
- ✅ Use this app locally on your personal computer only
- ✅ Do NOT deploy this to a public web server
- ✅ Do NOT use it for sensitive or confidential habit tracking
- ✅ Do NOT share your exported CSV files if they contain private information
- ❌ Do NOT use this in a shared computer environment
- ❌ Do NOT rely on the password protection for security

### For Developers
If you fork this project and want to improve security before I get to it:
- Implement proper password hashing (bcrypt, scrypt, or similar)
- Add server-side authentication
- Encrypt localStorage data
- Implement HTTPS if deploying to a server
- Add input validation and sanitization
- Implement rate limiting on login attempts

## Planned Security Improvements

The following security enhancements are planned for future releases:
- [ ] Proper password hashing implementation
- [ ] Optional encryption for localStorage data
- [ ] Security best practices documentation
- [ ] Optional cloud backup with encryption

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
| Current | ⚠️ Proof of Concept | Known security issues - local use only |

## Disclaimer

This software is provided "as is" under the MIT License. Use at your own risk. The author takes no responsibility for any security breaches, data loss, or other issues arising from the use of this software.
