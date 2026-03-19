<?php
ob_start(); // Prevent "Headers already sent" errors
/**
 * Fashion Management System – Application Configuration
 */

// ─── Site Identity ───────────────────────────────────────────
define('SITE_NAME',     'Fashion Studio GH');
define('SITE_TAGLINE',  'Curated Looks, Styled With Intention');
// Portable BASE_URL detection (supports subdirectories on XAMPP)
if (!defined('BASE_URL')) {
    $envBase = getenv('BASE_URL');
    if ($envBase !== false) {
        define('BASE_URL', $envBase);
    } else {
        $physicalRoot = str_replace(['\\', '/config'], ['', ''], __DIR__);
        $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
        $urlPath = str_replace($docRoot, '', str_replace('\\', '/', $physicalRoot));
        define('BASE_URL', rtrim($urlPath, '/'));
    }
}

// ─── Database (Support for Environment Variables on Vercel) ──
define('DB_HOST',    getenv('DB_HOST')    ?: 'aws-1-eu-west-1.pooler.supabase.com');
define('DB_PORT',    getenv('DB_PORT')    ?: '6543');
define('DB_NAME',    getenv('DB_NAME')    ?: 'postgres');
define('DB_USER',    getenv('DB_USER')    ?: 'postgres.ggravbyayzmaiksobylv');
define('DB_PASS',    getenv('DB_PASS')    ?: 'KobbyMoore02@');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8');

// ─── Session ─────────────────────────────────────────────────
define('SESSION_NAME',    'fashion_sess');
define('SESSION_TIMEOUT', 3600); // 1 hour

// ─── Paths ───────────────────────────────────────────────────
define('ROOT_PATH',    dirname(__DIR__));
define('UPLOADS_PATH', ROOT_PATH . '/assets/uploads/');
define('LOGS_PATH',    ROOT_PATH . '/logs/');

// ─── Inventory ───────────────────────────────────────────────
define('LOW_STOCK_THRESHOLD', 5); // yards

// ─── Error Display (set false in production) ─────────────────
define('APP_DEBUG', true);
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ─── Paystack Integration (Ghana) ───────────────────────────
define('PAYSTACK_PUBLIC_KEY', getenv('PAYSTACK_PUBLIC_KEY') ?: 'pk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('PAYSTACK_SECRET_KEY', getenv('PAYSTACK_SECRET_KEY') ?: 'sk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');

// ─── Start Session ───────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    // Secure cookie path to prevent conflicts and leakage
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path'     => BASE_URL ?: '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
