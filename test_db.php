<?php
// test_db.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

echo "<pre>";

try {
    $db = getDB();

    echo "Conexión a la base de datos OK ✅\n\n";

    // Probamos a listar las tablas
    $stmt = $db->query("SHOW TABLES");
    $tablas = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Tablas encontradas en la BD '" . DB_NAME . "':\n";
    foreach ($tablas as $t) {
        echo " - " . $t . "\n";
    }

    echo "\nPrueba de consulta a vinos:\n";
    $stmt = $db->query("SELECT id_vino, nombre FROM vinos LIMIT 5");
    $vinos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($vinos) {
        foreach ($vinos as $vino) {
            echo " * [{$vino['id_vino']}] {$vino['nombre']}\n";
        }
    } else {
        echo "No hay vinos en la tabla todavía.\n";
    }

} catch (Exception $e) {
    echo "❌ Error al conectar o consultar la base de datos:\n";
    echo $e->getMessage();
}

echo "</pre>";
