<?php
require_once __DIR__ . '/../config.php';

function redirect_to(string $url): void {
    // Con ob_start() en config.php normalmente esto no fallará,
    // pero dejo fallback por si alguna página imprime algo antes.
    if (!headers_sent()) {
        header('Location: ' . $url);
        exit;
    }
    echo '<script>window.location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
    exit;
}

function isLoggedIn(): bool {
    return !empty($_SESSION['usuario']);
}

function currentUser() {
    return $_SESSION['usuario'] ?? null;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        redirect_to(BASE_URL . '/pages/login.php');
    }
}

function requireRole(string $rol): void {
    requireLogin();
    if (($_SESSION['usuario']['rol'] ?? null) !== $rol) {
        redirect_to(BASE_URL . '/index.php');
    }
}
