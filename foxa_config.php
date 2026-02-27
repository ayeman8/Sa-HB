<?php
/**
 * ============================================================
 * FOXA FAMILY — Database Configuration
 * File : config.php
 *
 * !! عدّل هذه القيم بمعلومات استضافتك على LEMEHOST.com !!
 * !! EDIT THESE VALUES with your LEMEHOST.com credentials !!
 * ============================================================
 *
 * HOW TO FIND THESE VALUES ON LEMEHOST.COM:
 *  1. Login to LEMEHOST.com → cPanel
 *  2. Go to "MySQL Databases"
 *  3. Create a database → note the name
 *  4. Create a database user → note the username & password
 *  5. Assign the user to the database (All Privileges)
 *  6. Paste everything below
 * ============================================================
 */

// ── Database Host ───────────────────────────────────────────
// Usually 'localhost' on shared hosting
define('DB_HOST', 'localhost');

// ── Database Name ───────────────────────────────────────────
// The name you chose in cPanel MySQL Databases
// Example: 'foxa_12345'
define('DB_NAME', 'YOUR_DATABASE_NAME');

// ── Database Username ───────────────────────────────────────
// The MySQL user you created and assigned to the DB
// Example: 'foxa_user'
define('DB_USER', 'YOUR_DATABASE_USERNAME');

// ── Database Password ───────────────────────────────────────
// The password you set for the MySQL user
define('DB_PASS', 'YOUR_DATABASE_PASSWORD');

// ── App URL ─────────────────────────────────────────────────
// Your full website URL (no trailing slash)
// Example: 'https://foxafamily.lemehost.com'
define('APP_URL', 'https://YOUR_SITE.lemehost.com');

// ── App Info ────────────────────────────────────────────────
define('APP_NAME',    'FOXA FAMILY');
define('APP_VERSION', '2.0');

// ────────────────────────────────────────────────────────────
// Don't edit anything below this line
// ────────────────────────────────────────────────────────────
error_reporting(0);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Riyadh');

if (!defined('FOXA_API')) {
    http_response_code(403);
    exit('Direct access not allowed');
}
