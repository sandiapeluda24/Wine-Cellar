<?php
// Script para generar y actualizar hashes de contraseñas (XAMPP o Docker)

require_once __DIR__ . '/includes/db.php'; // <-- usa tu conexión central
require_once __DIR__ . '/config.php';

echo "<h2>Actualizando contraseñas...</h2>";

try {
    $db = getDB(); // <-- conexión usando DB_HOST + DB_PORT + DB_NAME

    echo "✅ Conexión exitosa a la base de datos<br><br>";

    $updates = [
        ['admin@vinos.test', 'admin123'],
        ['coleccionista@vinos.test', 'coleccionista123'],
        ['sommelier@vinos.test', 'sommelier123']
    ];

    foreach ($updates as [$email, $plainPassword]) {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);

        $stmt = $db->prepare("UPDATE usuarios SET password = ? WHERE email = ?");
        $stmt->execute([$hash, $email]);

        echo "✅ Actualizado: <strong>$email</strong> → contraseña: <strong>$plainPassword</strong><br>";
    }

    echo "<br><p style='color: green; font-weight: bold;'>✅ Todas las contraseñas actualizadas correctamente.</p>";
    echo "<p><a href='" . BASE_URL . "/pages/login.php'>Ir al login</a></p>";

} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error de conexión: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
