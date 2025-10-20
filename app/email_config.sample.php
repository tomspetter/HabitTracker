<?php
/**
 * Email Configuration Sample
 *
 * Copy this file to email_config.php and fill in your actual values.
 * email_config.php is excluded from Git for security.
 */

// Email feature toggle
define('EMAIL_ENABLED', true); // Set to false to disable all email features

// Brevo API Configuration
define('BREVO_API_KEY', 'your-brevo-api-key-here');
define('BREVO_API_URL', 'https://api.brevo.com/v3/smtp/email');

// Sender Information
define('EMAIL_FROM_ADDRESS', 'noreply@yourdomain.com'); // Change to your verified sender email
define('EMAIL_FROM_NAME', 'HabitDot');

// Application URLs (for email links)
define('APP_URL', 'https://yourdomain.com'); // Change to your production URL
define('APP_NAME', 'HabitDot');

// Email Templates
define('EMAIL_VERIFICATION_SUBJECT', 'Verify your HabitDot email address');
define('EMAIL_PASSWORD_RESET_SUBJECT', 'Reset your HabitDot password');

// Token expiration times (in seconds)
define('EMAIL_VERIFICATION_EXPIRY', 86400); // 24 hours
define('PASSWORD_RESET_EXPIRY', 3600); // 1 hour
