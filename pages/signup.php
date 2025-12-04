<?php
session_start();
// Si ya está logueado, redirigir
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Wine Cellar</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2>Crear Cuenta</h2>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <form action="../controllers/register_controller.php" method="POST">
                <div class="form-group">
                    <label for="nombre">Nombre completo:</label>
                    <input type="text" id="nombre" name="nombre" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña:</label>
                    <input type="password" id="password" name="password" required minlength="6">
                    <small>Mínimo 6 caracteres</small>
                </div>
                
                <div class="form-group">
                    <label for="password_confirm">Confirmar contraseña:</label>
                    <input type="password" id="password_confirm" name="password_confirm" required>
                </div>
                
                <div class="form-group">
                    <label for="rol">Tipo de cuenta:</label>
                    <select id="rol" name="rol" required>
                        <option value="coleccionista">Coleccionista</option>
                        <option value="sommelier">Sommelier</option>
                    </select>
                </div>
                
                <div id="certificado-group" class="form-group" style="display: none;">
                    <label>
                        <input type="checkbox" name="certificado" value="1">
                        Tengo certificación profesional de sommelier
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary">Registrarse</button>
            </form>
            
            <p class="text-center">
                ¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a>
            </p>
        </div>
    </div>
    
    <script>
        // Mostrar checkbox de certificado solo si es sommelier
        document.getElementById('rol').addEventListener('change', function() {
            const certificadoGroup = document.getElementById('certificado-group');
            if (this.value === 'sommelier') {
                certificadoGroup.style.display = 'block';
            } else {
                certificadoGroup.style.display = 'none';
            }
        });
    </script>
</body>
</html>