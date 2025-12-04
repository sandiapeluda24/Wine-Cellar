<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/register.php');
    exit;
}

// Obtener y limpiar datos
$nombre = trim($_POST['nombre'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';
$rol = $_POST['rol'] ?? 'coleccionista';
$certificado = ($rol === 'sommelier' && isset($_POST['certificado'])) ? 1 : 0;

// Validaciones
$errors = [];

if (empty($nombre)) {
    $errors[] = "El nombre es obligatorio";
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Email inválido";
}

if (strlen($password) < 6) {
    $errors[] = "La contraseña debe tener al menos 6 caracteres";
}

if ($password !== $password_confirm) {
    $errors[] = "Las contraseñas no coinciden";
}

if (!in_array($rol, ['coleccionista', 'sommelier'])) {
    $errors[] = "Rol inválido";
}

// Si hay errores, volver al formulario
if (!empty($errors)) {
    $_SESSION['error'] = implode('<br>', $errors);
    header('Location: ../pages/register.php');
    exit;
}

try {
    $db = new PDO("mysql:host=localhost;dbname=vinos;charset=utf8mb4", "root", "");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar si el email ya existe
    $stmt = $db->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        $_SESSION['error'] = "El email ya está registrado";
        header('Location: ../pages/register.php');
        exit;
    }
    
    // Hash de la contraseña
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insertar usuario
    $stmt = $db->prepare("
        INSERT INTO usuarios (nombre, email, password, rol, certificado) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([$nombre, $email, $password_hash, $rol, $certificado]);
    
    $_SESSION['success'] = "Cuenta creada exitosamente. Ya puedes iniciar sesión.";
    header('Location: ../pages/login.php');
    exit;
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al crear la cuenta: " . $e->getMessage();
    header('Location: ../pages/register.php');
    exit;
}
?>