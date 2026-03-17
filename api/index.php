<?php
/**
 * Vercel Universal Gateway for PHP
 * This file satisfies Vercel's need for an 'api' directory while
 * allowing the codebase to remain in the root directory.
 */

// Set the working directory to the project root
chdir(dirname(__DIR__));

// Get the requested URI path (stripping query strings)
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Simple routing gateway
if ($uri === '/' || $uri === '' || $uri === '/index.php') {
    include 'index.php';
} else {
    // Check if the file exists in the root (and is a PHP file)
    $path = ltrim($uri, '/');
    if ($path !== 'api/index.php' && file_exists($path) && is_file($path)) {
        if (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            include $path;
        } else {
            // Fallback for static assets handled by Vercel routes isn't needed here,
            // but just in case, we'll default to index.php.
            include 'index.php';
        }
    } else {
        // Fallback for unknown routes
        include 'index.php';
    }
}
