/**
 * ============================================================================
 * COOKIE CONSENT BANNER - DO NOT DELETE DURING REDESIGNS
 * ============================================================================
 *
 * Purpose: GDPR/PIPEDA cookie consent implementation
 * Created: 2025-10-31
 *
 * This file handles:
 * - Cookie consent banner display
 * - User consent storage (localStorage)
 * - Umami analytics opt-in/opt-out
 * - Session cookie notice
 *
 * Required by: GDPR (EU), PIPEDA (Canada), privacy best practices
 *
 * ============================================================================
 */

(function() {
    'use strict';

    // Configuration
    const CONSENT_KEY = 'chainofdots_cookie_consent';
    const ANALYTICS_KEY = 'chainofdots_analytics_consent';

    /**
     * Check if user has already given consent
     */
    function hasConsent() {
        return localStorage.getItem(CONSENT_KEY) !== null;
    }

    /**
     * Check if analytics is enabled
     */
    function hasAnalyticsConsent() {
        return localStorage.getItem(ANALYTICS_KEY) === 'true';
    }

    /**
     * Save consent preferences
     */
    function saveConsent(acceptAnalytics) {
        localStorage.setItem(CONSENT_KEY, 'true');
        localStorage.setItem(ANALYTICS_KEY, acceptAnalytics ? 'true' : 'false');

        // Reload page to apply analytics preference
        if (acceptAnalytics && typeof umami !== 'undefined') {
            window.location.reload();
        }
    }

    /**
     * Create and show cookie consent banner
     */
    function showConsentBanner() {
        // Don't show if already consented
        if (hasConsent()) {
            return;
        }

        // Create banner HTML
        const banner = document.createElement('div');
        banner.id = 'cookie-consent-banner';
        banner.innerHTML = `
            <div style="
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: #161b22;
                border-top: 1px solid #30363d;
                color: #c9d1d9;
                padding: 16px 20px;
                box-shadow: 0 -4px 12px rgba(0,0,0,0.3);
                z-index: 999999;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            ">
                <div style="max-width: 1200px; margin: 0 auto;">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 300px;">
                            <h3 style="margin: 0 0 8px 0; font-size: 16px; font-weight: 600; color: #ffffff;">
                                🍪 Cookie Notice
                            </h3>
                            <p style="margin: 0; font-size: 13px; line-height: 1.5; color: #8b949e;">
                                This site uses cookies for login sessions and privacy-focused analytics (Umami).
                                <strong style="color: #c9d1d9;">Session cookies are required</strong> for the app to work.
                                Analytics help us improve but are optional.
                            </p>
                        </div>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <button id="cookie-accept-all" style="
                                background: #238636;
                                color: #ffffff;
                                border: none;
                                padding: 10px 20px;
                                border-radius: 6px;
                                font-weight: 600;
                                cursor: pointer;
                                font-size: 14px;
                                transition: background 0.2s;
                            ">
                                Accept All
                            </button>
                            <button id="cookie-essential-only" style="
                                background: #21262d;
                                color: #c9d1d9;
                                border: 1px solid #30363d;
                                padding: 10px 20px;
                                border-radius: 6px;
                                font-weight: 600;
                                cursor: pointer;
                                font-size: 14px;
                                transition: background 0.2s;
                            ">
                                Essential Only
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Add to page
        document.body.appendChild(banner);

        // Add hover effects
        const acceptBtn = document.getElementById('cookie-accept-all');
        const essentialBtn = document.getElementById('cookie-essential-only');

        acceptBtn.addEventListener('mouseenter', () => {
            acceptBtn.style.background = '#2ea043';
        });
        acceptBtn.addEventListener('mouseleave', () => {
            acceptBtn.style.background = '#238636';
        });

        essentialBtn.addEventListener('mouseenter', () => {
            essentialBtn.style.background = '#30363d';
        });
        essentialBtn.addEventListener('mouseleave', () => {
            essentialBtn.style.background = '#21262d';
        });

        // Handle button clicks
        acceptBtn.addEventListener('click', () => {
            saveConsent(true);
            banner.remove();
        });

        essentialBtn.addEventListener('click', () => {
            saveConsent(false);
            banner.remove();
        });
    }

    /**
     * Block Umami analytics if consent not given
     */
    function handleAnalytics() {
        if (!hasAnalyticsConsent()) {
            // Remove Umami script if it exists
            const umamiScript = document.querySelector('script[data-website-id]');
            if (umamiScript) {
                umamiScript.remove();
            }
        }
    }

    /**
     * Initialize cookie consent on page load
     */
    function init() {
        // Handle analytics consent
        handleAnalytics();

        // Show banner if needed
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', showConsentBanner);
        } else {
            showConsentBanner();
        }
    }

    // Start the consent system
    init();

})();

/**
 * ============================================================================
 * END OF COOKIE CONSENT - DO NOT DELETE DURING REDESIGNS
 * ============================================================================
 */
