<?php
// Copy this file to config.php and fill in real values. config.php is
// gitignored — never commit real credentials.

// --- Database (from Hostinger hPanel > Databases > MySQL Databases) -------
define('DB_HOST', 'localhost');
define('DB_NAME', 'u123456789_obinacademy');
define('DB_USER', 'u123456789_obinuser');
define('DB_PASS', 'change-me');

// --- App -------------------------------------------------------------------
define('APP_URL', 'https://obinacademy.site');
// Random 32+ char string used to sign session cookies. Generate one with:
// php -r "echo bin2hex(random_bytes(32));"
define('APP_SECRET', 'change-me-to-a-long-random-string');

// --- Email (Resend — resend.com/api-keys) ----------------------------------
define('RESEND_API_KEY', 're_...');
// Must stay onboarding@resend.dev until you verify obinacademy.site in Resend.
define('EMAIL_FROM', 'Obin Academy <onboarding@resend.dev>');

// --- iotec Pay (mobile money — from the iotec dashboard) -------------------
define('IOTEC_CLIENT_ID', '');
define('IOTEC_CLIENT_SECRET', '');
// Use the TEST wallet ID while developing. Swap to your live wallet ID only
// once you're ready to accept real learner payments.
define('IOTEC_WALLET_ID', '');
