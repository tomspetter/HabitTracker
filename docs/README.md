# ChainOfDots Documentation

Welcome to the ChainOfDots documentation directory. This folder contains all technical documentation, configuration examples, and implementation guides.

## Documentation Files

### Core Documentation

- **[SECURITY.md](SECURITY.md)** - Comprehensive security documentation
  - Security features implemented
  - GDPR/PIPEDA compliance details
  - Deployment considerations
  - Input validation and attack prevention
  - Email security features
  - Data breach response procedures

- **[QUICK_REFERENCE.md](QUICK_REFERENCE.md)** - Quick setup guide
  - Database setup
  - Environment configuration
  - Email service setup
  - Common troubleshooting

### Implementation Guides

- **[COOKIE-CONSENT.md](COOKIE-CONSENT.md)** - Cookie consent implementation
  - GDPR/PIPEDA cookie consent requirements
  - Implementation details
  - Maintenance during redesigns
  - Ad blocker bypass strategy (app-init.js naming)

### Configuration Examples

- **[.env.example](.env.example)** - Environment variables template
  - Database credentials format
  - Encryption key setup
  - For use with PHP dotenv packages

- **[.htaccess.example](.htaccess.example)** - Apache environment configuration
  - SetEnv directives for Apache servers
  - Encryption key configuration
  - Database credential overrides

## Quick Links

### For Developers

- [Main README](../README.md) - Project overview and features
- [SECURITY.md](SECURITY.md) - Security implementation details
- [QUICK_REFERENCE.md](QUICK_REFERENCE.md) - Fast setup guide

### For Self-Hosters

1. Start with [QUICK_REFERENCE.md](QUICK_REFERENCE.md)
2. Use [.htaccess.example](.htaccess.example) or [.env.example](.env.example) for configuration
3. Review [SECURITY.md](SECURITY.md) for production deployment

### For Privacy Compliance

- [SECURITY.md - GDPR & PIPEDA Compliance](SECURITY.md#gdpr--pipeda-compliance)
- [COOKIE-CONSENT.md](COOKIE-CONSENT.md)
- [Privacy Policy](../privacy.html) (live page)
- [Terms of Service](../terms.html) (live page)

## File Organization

```
docs/
├── README.md              # This file - documentation index
├── SECURITY.md            # Security & privacy documentation
├── COOKIE-CONSENT.md      # Cookie consent implementation guide
├── QUICK_REFERENCE.md     # Quick setup reference
├── .env.example           # Environment variables template
└── .htaccess.example      # Apache configuration example
```

## Need Help?

- **Security Issues**: See [SECURITY.md](SECURITY.md) for vulnerability reporting
- **Setup Issues**: Check [QUICK_REFERENCE.md](QUICK_REFERENCE.md)
- **Privacy Questions**: Review [SECURITY.md - GDPR & PIPEDA](SECURITY.md#gdpr--pipeda-compliance)
- **General Questions**: Open an issue on GitHub
