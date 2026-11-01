<?php
/**
 * Smart Fire and Rescue Response System
 * SMTP Configuration Template (Example File)
 *
 * Copy this file to `smtp_config.php` and configure your credentials.
 * DO NOT commit `smtp_config.php` with real credentials to version control.
 */

// Hostname of your SMTP provider:
// - Local Testing (MailHog): 'localhost' or '127.0.0.1' (Port 1025)
// - Papercut SMTP:          'localhost' (Port 25)
// - Google Gmail SMTP:      'smtp.gmail.com' (Port 587, STARTTLS)
// - Microsoft 365 / Outlook:'smtp.office365.com' (Port 587, STARTTLS)
// - Brevo (Sendinblue):     'smtp-relay.brevo.com' (Port 587)
define('SMTP_HOST', 'localhost');

// Port (25, 465, 587, or 1025)
define('SMTP_PORT', 1025);

// Set to true if SMTP server requires authentication
define('SMTP_AUTH', false);

// SMTP Username (e.g., your email address or API username)
define('SMTP_USER', 'your-email@example.com');

// SMTP Password (or Gmail App Password, never regular Google password)
define('SMTP_PASS', 'your-secret-app-password');

// Encryption type: '' (none/plain), 'tls' (STARTTLS on port 587), or 'ssl' (port 465)
define('SMTP_SECURE', '');

// Sender email and display name
define('SMTP_FROM_EMAIL', 'cad@sudar.gov.in');
define('SMTP_FROM_NAME', 'Sudar Emergency CAD Dispatch');

// Emergency Duty Officer Recipient Email
define('OFFICER_NOTIFY_EMAIL', 'duty-officer@sudar.gov.in');

// Development fallback:
// If true, if SMTP is unreachable or in test mode, the email is logged into
// storage/emails/ as an HTML file and marked Sent (Local Test Log) so testing succeeds.
define('SMTP_LOG_FALLBACK', true);
