<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
include __DIR__ . '/../includes/header.php';

$errores = [];

// If user is already logged in, no need to register again
if (isLoggedIn()) {
    $u = currentUser();
    echo "<p>You are already logged in as <strong>" . htmlspecialchars($u['nombre']) . "</strong>.</p>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim($_POST['nombre'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rol      = $_POST['rol'] ?? 'coleccionista';
    $somDesc  = trim($_POST['sommelier_description'] ?? '');

    // Never allow admin from form
    if ($rol !== 'coleccionista' && $rol !== 'sommelier') {
        $rol = 'coleccionista';
    }

    // Basic validation
    if ($nombre === '' || $email === '' || $password === '') {
        $errores[] = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "Email is not valid.";
    } else {
        try {
            $db = getDB();

            // Check if email already exists
            $stmt = $db->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errores[] = "There is already a user with this email.";
            } else {
                // For now plain password to match your current login
                $certificado = ($rol === 'sommelier') ? 0 : 0;
                $somDescToSave = ($rol === 'sommelier') ? $somDesc : null;

                $stmt = $db->prepare(
                    "INSERT INTO usuarios (nombre, email, password, rol, certificado, sommelier_description)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$nombre, $email, $password, $rol, $certificado, $somDescToSave]);

                // Auto login after register
                $_SESSION['usuario'] = [
                    'id'     => $db->lastInsertId(),
                    'nombre' => $nombre,
                    'rol'    => $rol
                ];

                header("Location: " . BASE_URL . "/index.php");
                exit;
            }

        } catch (Exception $e) {
            $errores[] = "Error while registering: " . $e->getMessage();
        }
    }
}
?>


<h2>Register</h2>

<?php if (!empty($errores)): ?>
    <div class="errores">
        <?php foreach ($errores as $e): ?>
            <p class="error"><?= htmlspecialchars($e) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" id="formRegistro">
    <label>Name<br>
        <input type="text" name="nombre" required>
    </label>
    <br><br>

    <label>Email<br>
        <input type="email" name="email" required>
    </label>
    <br><br>

    <label>Password<br>
        <input type="password" name="password" required>
    </label>
    <br><br>

    <p>User type:</p>
    <label>
        <input type="radio" name="rol" value="coleccionista" checked>
        Collector
    </label>
    <label>
        <input type="radio" name="rol" value="sommelier">
        Sommelier
    </label>
    <br><br>

    <div id="sommelier-description-wrapper" style="display: none;">
    <label>Sommelier description (optional)<br>
        <textarea name="sommelier_description" rows="4" cols="40"
                  placeholder="Describe your skills and background as a sommelier..."></textarea>
    </label>
    <br><br>
</div>


    <button type="submit">Register</button>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const roleInputs = document.querySelectorAll('input[name="rol"], select[name="rol"]');
    const descWrapper = document.getElementById('sommelier-description-wrapper');

    if (!roleInputs.length || !descWrapper) return;

    function updateDescriptionVisibility() {
        let selectedRole = null;

        roleInputs.forEach(function (el) {
            if (el.tagName === 'SELECT') {
                selectedRole = el.value;
            } else if ((el.type === 'radio' || el.type === 'checkbox') && el.checked) {
                selectedRole = el.value;
            }
        });

        if (selectedRole === 'sommelier') {
            descWrapper.style.display = '';
        } else {
            descWrapper.style.display = 'none';
            const textarea = descWrapper.querySelector('textarea');
            if (textarea) textarea.value = '';
        }
    }

    roleInputs.forEach(function (el) {
        el.addEventListener('change', updateDescriptionVisibility);
    });

    // Estado inicial al cargar la página
    updateDescriptionVisibility();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
