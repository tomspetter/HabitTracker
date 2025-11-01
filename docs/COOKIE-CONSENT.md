# Cookie Consent Implementation

## Overview

This site now has GDPR/PIPEDA-compliant cookie consent implemented. This document explains what was added and how to maintain it during redesigns.

## What Was Added

### 1. Cookie Consent Script
**File:** `cookie-consent.js` (in the root directory)

This JavaScript file handles:
- Displaying the cookie consent banner to first-time visitors
- Storing user preferences in localStorage
- Blocking Umami analytics if user opts out
- Allowing session cookies (required for login)

### 2. HTML Integration

The cookie consent script was added to **all HTML pages**:
- ✅ `index.html` (home page)
- ✅ `about.html` (about page)
- ✅ `contact.html` (contact page)
- ✅ `app/index.html` (habit tracker app)

**Look for this in the `<head>` section:**

```html
<!-- ═══════════════════════════════════════════════════════════════════════
     🍪 COOKIE CONSENT - REQUIRED FOR GDPR/PIPEDA COMPLIANCE
     ═══════════════════════════════════════════════════════════════════════
     DO NOT DELETE THIS SECTION DURING REDESIGNS!
     ...
     ═══════════════════════════════════════════════════════════════════════ -->
<script src="/cookie-consent.js"></script>
<!-- ═══════════════════════════════════════════════════════════════════════
     END OF COOKIE CONSENT SECTION
     ═══════════════════════════════════════════════════════════════════════ -->
```

## How It Works

1. **First Visit:**
   - User sees a purple banner at the bottom of the page
   - Two options: "Accept All" or "Essential Only"

2. **Accept All:**
   - Allows session cookies (required for login)
   - Allows Umami analytics (privacy-focused tracking)
   - Preference saved in localStorage

3. **Essential Only:**
   - Allows session cookies only (required for login)
   - Blocks Umami analytics
   - Preference saved in localStorage

4. **Return Visits:**
   - Banner doesn't show again (preference remembered)
   - Analytics loads or doesn't load based on previous choice

## Important: DO NOT DELETE During Redesigns!

When you redesign the marketing site:

### ✅ Keep These Files:
- `cookie-consent.js` - The consent logic
- The HTML comment blocks marked with cookie emoji 🍪

### ✅ Keep This HTML Section:
```html
<script src="/cookie-consent.js"></script>
```

### ⚠️ You Can Modify:
- The banner styling (edit `cookie-consent.js` lines 65-145)
- The banner text (edit `cookie-consent.js` lines 75-90)
- The button colors (edit `cookie-consent.js` lines 95-135)

### ❌ Do NOT:
- Delete the `<script src="/cookie-consent.js"></script>` line
- Remove the cookie consent comment blocks
- Delete the `cookie-consent.js` file

## Customization

If you want to change the banner appearance, edit `cookie-consent.js`:

**Change colors:**
```javascript
// Line 70 - Background gradient
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);

// Line 95 - "Accept All" button
background: white;
color: #667eea;

// Line 107 - "Essential Only" button
background: rgba(255,255,255,0.2);
color: white;
```

**Change text:**
```javascript
// Lines 75-85 - Banner text
<h3>🍪 Cookie Notice</h3>
<p>This site uses cookies for login sessions and privacy-focused analytics...</p>
```

## Testing

To test the cookie consent:

1. Open your site in a browser
2. Check browser console (F12) - should see no errors
3. You should see the purple banner at the bottom
4. Click "Accept All" - banner disappears
5. Refresh page - banner should NOT reappear
6. Clear localStorage to reset: `localStorage.clear()` in console
7. Refresh - banner should reappear

## What Cookies Are Used

### Essential Cookies (Always Allowed):
- **PHP Session Cookie** (`PHPSESSID`)
  - Purpose: Login authentication
  - Duration: Session (cleared when browser closes)
  - Cannot be disabled (app won't work)

### Optional Cookies (User Can Block):
- **Umami Analytics**
  - Purpose: Privacy-focused website analytics
  - No personal data collected
  - No advertising or tracking across sites
  - User can opt-out via "Essential Only" button

## Compliance Status

✅ **GDPR (EU):** Compliant - users can opt-out before analytics loads
✅ **PIPEDA (Canada):** Compliant - transparent cookie notice with choice
✅ **Best Practices:** Using privacy-focused analytics (Umami), not Google Analytics

## Need Help?

If you accidentally delete the cookie consent:

1. Restore from Git: `git checkout cookie-consent.js`
2. Re-add the `<script>` tag to all HTML files
3. Look for the backup in `.git/` history

## Future: Privacy Policy

**TODO:** Create a privacy policy page that links from the footer. This should explain:
- What data you collect (email, encrypted habits)
- Why you collect it (app functionality)
- Third parties (Umami, Brevo)
- User rights (export, delete, opt-out)
- Contact information

The privacy policy page should be created separately.
