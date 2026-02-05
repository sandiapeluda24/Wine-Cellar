<?php
// Panel de administración sencillo usando la sesión actual
session_start();
require_once __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

// Solo admins
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Datos del usuario logueado
$usuarioNombre = $_SESSION['usuario']['nombre'] ?? 'admin';
?>

<div class="admin-hero">
  <section class="admin-shell">
    <div class="admin-head">
      <div class="admin-kicker">Admin</div>
      <h1 class="admin-title">Administration panel</h1>
      <p class="admin-subtitle">Hello, <?= htmlspecialchars($usuarioNombre) ?> (admin)</p>
    </div>

    <div class="admin-grid">
      <div class="admin-tile">
        <div class="admin-tile-top">
          <div class="admin-icon">👤</div>
          <div>
            <div class="admin-tile-title">Users</div>
            <div class="admin-tile-desc">Manage accounts, roles and access.</div>
          </div>
        </div>
        <div class="admin-actions">
          <a class="btn btn-sm" href="admin_usuarios.php">Manage</a>
          <a class="btn btn-sm btn-ghost" href="admin_usuario_new.php">New user</a>
        </div>
      </div>

      <div class="admin-tile">
        <div class="admin-tile-top">
          <div class="admin-icon">🍷</div>
          <div>
            <div class="admin-tile-title">Wines</div>
            <div class="admin-tile-desc">Catalog, pricing, stock and images.</div>
          </div>
        </div>
        <div class="admin-actions">
          <a class="btn btn-sm" href="admin_vinos.php">Manage</a>
          <a class="btn btn-sm btn-ghost" href="admin_vino_form.php">New wine</a>
        </div>
      </div>

      <div class="admin-tile">
        <div class="admin-tile-top">
          <div class="admin-icon">🧾</div>
          <div>
            <div class="admin-tile-title">Purchase history</div>
            <div class="admin-tile-desc">Orders, payment status and totals.</div>
          </div>
        </div>
        <div class="admin-actions">
          <a class="btn btn-sm" href="admin_compras.php">Open</a>
        </div>
      </div>

      <div class="admin-tile">
        <div class="admin-tile-top">
          <div class="admin-icon">📅</div>
          <div>
            <div class="admin-tile-title">Tastings</div>
            <div class="admin-tile-desc">Create events, manage signups and wines.</div>
          </div>
        </div>
        <div class="admin-actions">
          <a class="btn btn-sm" href="admin_tastings.php">Manage</a>
        </div>
      </div>

      <div class="admin-tile">
        <div class="admin-tile-top">
          <div class="admin-icon">🎓</div>
          <div>
            <div class="admin-tile-title">Sommelier certifications</div>
            <div class="admin-tile-desc">Review and validate uploaded documents.</div>
          </div>
        </div>
        <div class="admin-actions">
          <a class="btn btn-sm" href="admin_sommelier_certifications.php">Review</a>
        </div>
      </div>

      <div class="admin-tile">
        <div class="admin-tile-top">
          <div class="admin-icon">📊</div>
          <div>
            <div class="admin-tile-title">Reports</div>
            <div class="admin-tile-desc">KPIs and summaries for the platform.</div>
          </div>
        </div>
        <div class="admin-actions">
          <a class="btn btn-sm" href="admin_reports.php">View</a>
        </div>
      </div>
    </div>
  </section>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
