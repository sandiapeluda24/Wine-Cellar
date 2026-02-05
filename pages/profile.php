<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['usuario'])) {
  header('Location: login.php');
  exit;
}

$u = $_SESSION['usuario'];
$nombre = $u['nombre'] ?? 'User';
$email = $u['email'] ?? '';
$rol = $u['rol'] ?? '';
$created_at = $u['created_at'] ?? ($u['fechaIngreso'] ?? '');
$certificado = isset($u['certificado']) ? (int)$u['certificado'] : null;
$sommelier_desc = $u['sommelier_description'] ?? '';

function initials($name) {
  $name = trim((string)$name);
  if ($name === '') return 'U';
  $parts = preg_split('/\s+/', $name);
  $first = strtoupper(substr($parts[0], 0, 1));
  $second = '';
  if (count($parts) > 1) $second = strtoupper(substr($parts[count($parts)-1], 0, 1));
  return $first . $second;
}
$ini = initials($nombre);
?>

<div class="profile-hero">
  <section class="profile-shell">
    <header class="profile-head">
      <div class="profile-kicker">My account</div>
      <h1 class="profile-title">My profile</h1>
      <p class="profile-subtitle">Your personal details and account status.</p>
    </header>

    <div class="profile-card">
      <div class="profile-top">
        <div class="profile-avatar" aria-hidden="true"><?= htmlspecialchars($ini) ?></div>
        <div class="profile-who">
          <div class="profile-name"><?= htmlspecialchars($nombre) ?></div>
          <div class="profile-email"><?= htmlspecialchars($email) ?></div>
        </div>

        <div class="profile-badges">
          <span class="badge badge-role"><?= htmlspecialchars($rol ?: 'user') ?></span>
          <?php if ($rol === 'sommelier' && $certificado !== null): ?>
            <span class="badge <?= $certificado ? 'badge-ok' : 'badge-muted' ?>">
              <?= $certificado ? 'Certified' : 'Not certified' ?>
            </span>
          <?php endif; ?>
        </div>
      </div>

      <div class="profile-grid">
        <div class="profile-kv">
          <div class="profile-k">Name</div>
          <div class="profile-v"><?= htmlspecialchars($nombre) ?></div>
        </div>
        <div class="profile-kv">
          <div class="profile-k">Email</div>
          <div class="profile-v"><?= htmlspecialchars($email) ?></div>
        </div>
        <div class="profile-kv">
          <div class="profile-k">Role</div>
          <div class="profile-v"><?= htmlspecialchars($rol) ?></div>
        </div>
        <div class="profile-kv">
          <div class="profile-k">Member since</div>
          <div class="profile-v"><?= htmlspecialchars($created_at ?: '-') ?></div>
        </div>

        <?php if ($rol === 'sommelier' && $sommelier_desc): ?>
          <div class="profile-kv profile-kv-wide">
            <div class="profile-k">Sommelier bio</div>
            <div class="profile-v"><?= nl2br(htmlspecialchars($sommelier_desc)) ?></div>
          </div>
        <?php endif; ?>
      </div>

      <div class="profile-actions">
        <a class="btn btn-ghost" href="/index.php">Back to home</a>
        <a class="btn" href="/pages/logout.php">Log out</a>
      </div>
    </div>
  </section>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
