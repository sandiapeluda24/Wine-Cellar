<?php
// Script para generar y actualizar hashes de contraseñas

echo "<h2>Actualizando contraseñas...</h2>";

try {
    // Conectar directamente a la base de datos
    $host = 'localhost';
    $dbname = 'vinos';
    $username = 'root';
    $password = ''; // Deja vacío si no tienes contraseña en XAMPP
    
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Conexión exitosa a la base de datos<br><br>";
    
    $updates = [
        ['admin@vinos.test', 'admin123'],
        ['coleccionista@vinos.test', 'coleccionista123'],
        ['sommelier@vinos.test', 'sommelier123']
    ];
    
    foreach ($updates as [$email, $password]) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE usuarios SET password = ? WHERE email = ?");
        $stmt->execute([$hash, $email]);
        echo "✅ Actualizado: <strong>$email</strong> → contraseña: <strong>$password</strong><br>";
    }
    
    echo "<br><p style='color: green; font-weight: bold;'>✅ Todas las contraseñas actualizadas correctamente.</p>";
    echo "<p><a href='views/login.php'>Ir al login</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error de conexión: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>