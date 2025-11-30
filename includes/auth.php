//Session management and user authentication functions

<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return !empty($_SESSION['usuario']);
}

function currentUser() {
    return $_SESSION['usuario'] ?? null;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/pages/login.php');
        exit;
    }
}

function requireRole($rol) {
    requireLogin();
    if ($_SESSION['usuario']['rol'] !== $rol) {
        // aquí puedes mandar a una página de error o al inicio
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

