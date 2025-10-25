<?php
/**
 * Email Service
 *
 * Handles sending emails via Brevo API
 */

// Load email configuration if it exists
if (file_exists(__DIR__ . '/email_config.php')) {
    require_once __DIR__ . '/email_config.php';
} else {
    // Email features disabled if config doesn't exist
    if (!defined('EMAIL_ENABLED')) {
        define('EMAIL_ENABLED', false);
    }
}

/**
 * Send email via Brevo API
 *
 * @param string $to Recipient email address
 * @param string $toName Recipient name
 * @param string $subject Email subject
 * @param string $htmlContent HTML email content
 * @param string|null $textContent Plain text email content (optional)
 * @return array Response with 'success' boolean and 'message' or 'error'
 */
function sendEmail($to, $toName, $subject, $htmlContent, $textContent = null) {
    // Check if email is enabled
    if (!EMAIL_ENABLED) {
        return [
            'success' => false,
            'error' => 'Email functionality is disabled'
        ];
    }

    // Validate email configuration
    if (!defined('BREVO_API_KEY') || BREVO_API_KEY === 'your-brevo-api-key-here') {
        return [
            'success' => false,
            'error' => 'Email not configured. Please set up email_config.php'
        ];
    }

    // Prepare email data
    $emailData = [
        'sender' => [
            'name' => EMAIL_FROM_NAME,
            'email' => EMAIL_FROM_ADDRESS
        ],
        'to' => [
            [
                'email' => $to
            ]
        ],
        'subject' => $subject,
        'htmlContent' => $htmlContent
    ];

    // Only add name if it's not empty
    if (!empty($toName)) {
        $emailData['to'][0]['name'] = $toName;
    }

    // Add plain text content if provided
    if ($textContent) {
        $emailData['textContent'] = $textContent;
    }

    // Send via Brevo API
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => BREVO_API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'api-key: ' . BREVO_API_KEY,
            'content-type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($emailData)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Handle response
    if ($error) {
        return [
            'success' => false,
            'error' => 'cURL error: ' . $error
        ];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success' => true,
            'message' => 'Email sent successfully',
            'response' => json_decode($response, true)
        ];
    } else {
        $responseData = json_decode($response, true);
        return [
            'success' => false,
            'error' => 'Brevo API error (HTTP ' . $httpCode . '): ' . ($responseData['message'] ?? 'Unknown error'),
            'response' => $responseData
        ];
    }
}

/**
 * Send verification code email (6-digit code)
 *
 * @param string $email User's email address
 * @param string $verificationCode 6-digit verification code
 * @return array Response from sendEmail()
 */
function sendVerificationCodeEmail($email, $verificationCode) {
    $htmlContent = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #ff6b35; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background-color: #f6f8fa; padding: 30px; border-radius: 0 0 5px 5px; text-align: center; }
        .code-box { background-color: #white; border: 2px dashed #ff6b35; padding: 20px; margin: 30px 0; border-radius: 8px; }
        .code { font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #ff6b35; font-family: "Courier New", monospace; }
        .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
        .warning { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 20px 0; text-align: left; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>' . APP_NAME . '</h1>
        </div>
        <div class="content">
            <h2>Verify Your Email Address</h2>
            <p>Thanks for signing up for ' . APP_NAME . '!</p>
            <p>Enter this verification code to complete your registration:</p>
            <div class="code-box">
                <div class="code">' . htmlspecialchars($verificationCode) . '</div>
            </div>
            <p style="color: #666; font-size: 14px;">This code will expire in 15 minutes.</p>
            <div class="warning">
                <strong>Security Notice:</strong> If you didn\'t create this account, you can safely ignore this email.
            </div>
        </div>
        <div class="footer">
            <p>&copy; ' . date('Y') . ' ' . APP_NAME . ' • <a href="' . APP_URL . '">Visit Website</a></p>
        </div>
    </div>
</body>
</html>';

    $textContent = "Welcome to " . APP_NAME . "!\n\n"
        . "Your verification code is: " . $verificationCode . "\n\n"
        . "Enter this code on the verification page to complete your registration.\n\n"
        . "This code will expire in 15 minutes.\n\n"
        . "If you didn't create this account, you can safely ignore this email.\n\n"
        . "Best regards,\n"
        . APP_NAME . " Team";

    return sendEmail($email, '', 'Verify your ' . APP_NAME . ' account', $htmlContent, $textContent);
}

/**
 * Send password reset code email (6-digit code)
 *
 * @param string $email User's email address
 * @param string $resetCode 6-digit reset code
 * @return array Response from sendEmail()
 */
function sendPasswordResetEmail($email, $resetCode) {

    $htmlContent = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #ff6b35; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background-color: #f6f8fa; padding: 30px; border-radius: 0 0 5px 5px; text-align: center; }
        .code-box { background-color: white; border: 2px dashed #ff6b35; padding: 20px; margin: 30px 0; border-radius: 8px; }
        .code { font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #ff6b35; font-family: "Courier New", monospace; }
        .warning { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 20px 0; text-align: left; }
        .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>' . APP_NAME . '</h1>
        </div>
        <div class="content">
            <h2>Reset Your Password</h2>
            <p>We received a request to reset your password.</p>
            <p>Enter this code to create a new password:</p>
            <div class="code-box">
                <div class="code">' . htmlspecialchars($resetCode) . '</div>
            </div>
            <p style="color: #666; font-size: 14px;">This code will expire in 15 minutes.</p>
            <div class="warning">
                <strong>Security Notice:</strong> If you didn\'t request this password reset, please ignore this email and ensure your account is secure.
            </div>
        </div>
        <div class="footer">
            <p>&copy; ' . date('Y') . ' ' . APP_NAME . ' • <a href="' . APP_URL . '">Visit Website</a></p>
        </div>
    </div>
</body>
</html>';

    $textContent = "Password Reset Request\n\n"
        . "We received a request to reset your password.\n\n"
        . "Your reset code is: " . $resetCode . "\n\n"
        . "Enter this code on the password reset page to create a new password.\n\n"
        . "This code will expire in 15 minutes.\n\n"
        . "If you didn't request this password reset, please ignore this email and ensure your account is secure.\n\n"
        . "Best regards,\n"
        . APP_NAME . " Team";

    return sendEmail($email, '', 'Reset your ' . APP_NAME . ' password', $htmlContent, $textContent);
}
