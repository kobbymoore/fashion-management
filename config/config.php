<?php
/**
 * Fashion Management System – Application Configuration
 */

// ─── Site Identity ───────────────────────────────────────────
define('SITE_NAME',     'Fashion Studio GH');
define('SITE_TAGLINE',  'Curated Looks, Styled With Intention');
define('BASE_URL',      (isset($_SERVER['HTTP_HOST']) ? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : ''));

// ─── Database (Support for Environment Variables on Vercel) ──
define('DB_HOST',    getenv('DB_HOST')    ?: 'db.ggravbyayzmaiksobylv.supabase.co');
define('DB_PORT',    getenv('DB_PORT')    ?: '6543');
define('DB_NAME',    getenv('DB_NAME')    ?: 'postgres');
define('DB_USER',    getenv('DB_USER')    ?: 'postgres');
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

// ─── Start Session ───────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}
