<?php
// config.php (SOLO configuración)

// 1) Output buffering: evita "headers already sent"
if (!ob_get_level()) {
    ob_start();
}

// 2) Sesión global: siempre antes de cualquier HTML
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database (Docker o XAMPP)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'vinos');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Base URL (en Docker debe ser '')
define('BASE_URL', getenv('BASE_URL') ?: '');

// Stripe (deja tus keys)
define('STRIPE_SECRET_KEY', 'sk_test_51xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_51xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
