<?php
session_start();

require_once __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

/**
 * Admin Reports page
 * - Shows multiple SQL reports of different complexity (aggregations, joins, subqueries)
 * - Adapted to your schema: tastings.id_cata, tasting_signups.id_cata/id_usuario/role/status
 */

if (!isset($_SESSION['usuario']) || ($_SESSION['usuario']['rol'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDB();

/* ---------- helpers ---------- */
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function contains(string $haystack, string $needle): bool {
    return $needle === '' ? true : (strpos($haystack, $needle) !== false);
}

function tableExists(PDO $db, string $table): bool {
    $stmt = $db->prepare("
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = ?
        LIMIT 1
    ");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function describe(PDO $db, string $table): array {
    $rows = $db->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) $out[$r['Field']] = $r;
    return $out;
}

function pickCol(array $desc, array $candidates, ?string $fallback = null): ?string {
    foreach ($candidates as $c) {
        if (isset($desc[$c])) return $c;
    }
    return $fallback;
}

function isDateLikeType(string $type): bool {
    $t = strtolower($type);
    return contains($t, 'date') || contains($t, 'time') || contains($t, 'timestamp');
}

function dateExpr(string $alias, string $col, string $type): string {
    $t = strtolower($type);
    if (contains($t, 'datetime') || contains($t, 'timestamp')) return "DATE($alias.`$col`)";
    return "$alias.`$col`";
}

function run(PDO $db, string $sql, array $params = []): array {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function renderTable(array $rows): void {
    if (empty($rows)) {
        echo "<div class='notice'>No data.</div>";
        return;
    }
    $cols = array_keys($rows[0]);

    echo "<div class='table-wrap'>";
    echo "<table class='report-table'>";
    echo "<thead><tr>";
    foreach ($cols as $c) echo "<th>" . h($c) . "</th>";
    echo "</tr></thead><tbody>";

    foreach ($rows as $r) {
        echo "<tr>";
        foreach ($cols as $c) echo "<td>" . h($r[$c]) . "</td>";
        echo "</tr>";
    }

    echo "</tbody></table></div>";
}

/* ---------- detect schema ---------- */
if (!tableExists($db, 'tastings')) {
    ?>
    <section class="admin-hero">
      <div class="admin-shell">
        <div class="admin-head">
          <div class="admin-kicker">Administration</div>
          <h1 class="admin-title">Admin reports</h1>
          <p class="admin-subtitle">Analytics & summaries for tastings, attendance, sommeliers, and wines.</p>
          <div class="notice notice-error" style="margin-top:14px;">
            Table <b>tastings</b> not found.
          </div>
        </div>
      </div>
    </section>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$tDesc   = describe($db, 'tastings');
$tsExists= tableExists($db, 'tasting_signups');
$tsDesc  = $tsExists ? describe($db, 'tasting_signups') : [];

$tId     = pickCol($tDesc, ['id_cata', 'id_tasting', 'id']);
$tName   = pickCol($tDesc, ['titulo', 'title', 'nombre', 'name']);
$tStatus = pickCol($tDesc, ['estado', 'status'], null);

// best date column for filtering & time series
$dateCol  = pickCol($tDesc, ['tasting_date', 'fecha_cata', 'created_at', 'fecha'], null);
$dateType = $dateCol ? ($tDesc[$dateCol]['Type'] ?? '') : '';

$capCol = pickCol($tDesc, ['max_participantes', 'aforo', 'capacidad'], null);

// signups schema
$tsCata   = $tsExists ? pickCol($tsDesc, ['id_cata', 'tasting_id', 'id_tasting']) : null;
$tsUser   = $tsExists ? pickCol($tsDesc, ['id_usuario', 'user_id']) : null;
$tsRole   = $tsExists ? pickCol($tsDesc, ['role', 'rol']) : null;
$tsStatus = $tsExists ? pickCol($tsDesc, ['status', 'estado']) : null;

/* ---------- filters ---------- */
$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to'] ?? '');
$hasFilter = ($from !== '' && $to !== '' && $dateCol);

$dateWhere = '';
$params = [];
if ($hasFilter) {
    $expr = dateExpr('t', $dateCol, $dateType);
    $dateWhere = "WHERE $expr BETWEEN :from AND :to";
    $params = [':from' => $from, ':to' => $to];
}
?>

<section class="admin-hero">
  <div class="admin-shell">

    <div class="admin-head">
      <div class="admin-kicker">Administration</div>
      <h1 class="admin-title">Admin reports</h1>
      <p class="admin-subtitle">Filter by date and review key performance summaries.</p>

      <form method="get" class="reports-filter">
        <div class="rf-field">
          <label for="from">From</label>
          <input id="from" type="date" name="from" value="<?= h($from) ?>">
        </div>

        <div class="rf-field">
          <label for="to">To</label>
          <input id="to" type="date" name="to" value="<?= h($to) ?>">
        </div>

        <div class="rf-actions">
          <button class="btn btn-sm" type="submit">Apply</button>
          <a class="btn btn-sm btn-ghost" href="admin_reports.php">Reset</a>
        </div>

        <?php if (!$dateCol): ?>
          <div class="rf-note notice" style="margin:0;">
            <b>Info:</b> No date column detected for filtering.
          </div>
        <?php endif; ?>
      </form>

      <div class="admin-actions" style="margin-top: 14px;">
        <a class="btn btn-sm btn-ghost" href="admin_panel.php">← Back to admin panel</a>
      </div>
    </div>

    <div class="reports-grid">

      <div class="form-card report-card">
        <div class="report-head">
          <div>
            <h2 class="report-title">1) Attendance per Tasting (Collectors)</h2>
            <p class="report-subtitle">Confirmed / waitlist / pending collectors per tasting.</p>
          </div>
        </div>
        <?php
        if (!$tsExists || !$tsCata || !$tsRole || !$tsStatus) {
            echo "<div class='notice notice-error'>Missing tasting_signups or required columns.</div>";
        } else {
            $sql = "
                SELECT
                  t.`$tId` AS tasting_id,
                  t.`$tName` AS title,
                  SUM(CASE WHEN ts.`$tsStatus`='confirmed' AND ts.`$tsRole`='coleccionista' THEN 1 ELSE 0 END) AS confirmed,
                  SUM(CASE WHEN ts.`$tsStatus`='waitlist'  AND ts.`$tsRole`='coleccionista' THEN 1 ELSE 0 END) AS waitlist,
                  SUM(CASE WHEN ts.`$tsStatus`='pending'   AND ts.`$tsRole`='coleccionista' THEN 1 ELSE 0 END) AS pending
                FROM tastings t
                LEFT JOIN tasting_signups ts ON ts.`$tsCata` = t.`$tId`
                " . ($hasFilter ? $dateWhere : "") . "
                GROUP BY t.`$tId`, t.`$tName`
                ORDER BY t.`$tId` DESC
                LIMIT 50
            ";
            renderTable(run($db, $sql, $params));
        }
        ?>
      </div>

      <div class="form-card report-card">
        <div class="report-head">
          <div>
            <h2 class="report-title">2) Occupancy / Fill Rate per Tasting</h2>
            <p class="report-subtitle">Capacity vs confirmed collectors (percentage).</p>
          </div>
        </div>
        <?php
        if (!$tsExists || !$tsCata || !$tsRole || !$tsStatus || !$capCol) {
            echo "<div class='notice notice-error'>Missing tasting_signups or capacity column (max_participantes/aforo/capacidad).</div>";
        } else {
            $capExpr = "COALESCE(t.`max_participantes`, t.`aforo`, t.`capacidad`)";
            $sql = "
                SELECT
                  t.`$tId` AS tasting_id,
                  t.`$tName` AS title,
                  $capExpr AS capacity_total,
                  SUM(CASE WHEN ts.`$tsStatus`='confirmed' AND ts.`$tsRole`='coleccionista' THEN 1 ELSE 0 END) AS confirmed,
                  ROUND(
                    100 * SUM(CASE WHEN ts.`$tsStatus`='confirmed' AND ts.`$tsRole`='coleccionista' THEN 1 ELSE 0 END)
                    / NULLIF($capExpr, 0),
                  1) AS occupancy_pct
                FROM tastings t
                LEFT JOIN tasting_signups ts ON ts.`$tsCata` = t.`$tId`
                " . ($hasFilter ? $dateWhere : "") . "
                GROUP BY t.`$tId`, t.`$tName`, $capExpr
                ORDER BY occupancy_pct DESC
                LIMIT 50
            ";
            renderTable(run($db, $sql, $params));
        }
        ?>
      </div>

      <div class="form-card report-card">
        <div class="report-head">
          <div>
            <h2 class="report-title">3) Tastings by Status</h2>
            <p class="report-subtitle">How many tastings are in each status.</p>
          </div>
        </div>
        <?php
        if (!$tStatus) {
            echo "<div class='notice notice-error'>No status column detected (estado/status).</div>";
        } else {
            $sql = "SELECT `$tStatus` AS status, COUNT(*) AS total FROM tastings GROUP BY `$tStatus` ORDER BY total DESC";
            renderTable(run($db, $sql));
        }
        ?>
      </div>

      <div class="form-card report-card">
        <div class="report-head">
          <div>
            <h2 class="report-title">4) Upcoming Tastings (Next 15)</h2>
            <p class="report-subtitle">Next scheduled tastings in chronological order.</p>
          </div>
        </div>
        <?php
        $hasTastingDate = isset($tDesc['tasting_date']);
        $hasFechaCata   = isset($tDesc['fecha_cata']);
        $hasHoraCata    = isset($tDesc['hora_cata']);

        $whenExpr = $hasTastingDate ? "t.`tasting_date`"
            : ($hasFechaCata && $hasHoraCata
                ? "STR_TO_DATE(CONCAT(t.`fecha_cata`, ' ', t.`hora_cata`), '%Y-%m-%d %H:%i:%s')"
                : null);

        if (!$whenExpr) {
            echo "<div class='notice notice-error'>No suitable datetime columns found (tasting_date or fecha_cata + hora_cata).</div>";
        } else {
            $sql = "
              SELECT
                t.`$tId` AS id_cata,
                t.`$tName` AS title,
                $whenExpr AS scheduled_at,
                " . ($tStatus ? "t.`$tStatus` AS status," : "'-' AS status,") . "
                " . (isset($tDesc['precio']) ? "t.`precio`" : "NULL") . " AS price
              FROM tastings t
              WHERE $whenExpr IS NOT NULL
              ORDER BY scheduled_at ASC
              LIMIT 15
            ";
            renderTable(run($db, $sql));
        }
        ?>
      </div>

      <div class="form-card report-card">
        <div class="report-head">
          <div>
            <h2 class="report-title">5) Sommelier Activity (Accepted/Declined/Pending)</h2>
            <p class="report-subtitle">Per sommelier signups status + acceptance rate.</p>
          </div>
        </div>
        <?php
        if (!tableExists($db, 'usuarios') || !$tsExists || !$tsUser || !$tsRole || !$tsStatus) {
            echo "<div class='notice notice-error'>Missing usuarios or tasting_signups columns.</div>";
        } else {
            $uDesc = describe($db, 'usuarios');
            $uId   = pickCol($uDesc, ['id_usuario', 'id', 'user_id']);
            $uName = pickCol($uDesc, ['nombre', 'name']);
            $uRole = pickCol($uDesc, ['rol', 'role']);
            $uCert = pickCol($uDesc, ['certificado'], null);

            if (!$uId || !$uName || !$uRole) {
                echo "<div class='notice notice-error'>usuarios schema not compatible.</div>";
            } else {
                $certSelect = $uCert ? "u.`$uCert` AS certificado," : "";
                $sql = "
                    SELECT
                      u.`$uId` AS sommelier_id,
                      u.`$uName` AS name,
                      $certSelect
                      SUM(CASE WHEN ts.`$tsStatus`='confirmed' THEN 1 ELSE 0 END) AS accepted,
                      SUM(CASE WHEN ts.`$tsStatus`='declined'  THEN 1 ELSE 0 END) AS declined,
                      SUM(CASE WHEN ts.`$tsStatus`='pending'   THEN 1 ELSE 0 END) AS pending,
                      ROUND(
                        100 * SUM(CASE WHEN ts.`$tsStatus`='confirmed' THEN 1 ELSE 0 END) /
                        NULLIF(SUM(CASE WHEN ts.`$tsStatus` IN ('confirmed','declined') THEN 1 ELSE 0 END), 0),
                      1) AS acceptance_rate_pct
                    FROM usuarios u
                    LEFT JOIN tasting_signups ts
                      ON ts.`$tsUser` = u.`$uId` AND ts.`$tsRole`='sommelier'
                    WHERE u.`$uRole`='sommelier'
                    GROUP BY u.`$uId`, u.`$uName`" . ($uCert ? ", u.`$uCert`" : "") . "
                    ORDER BY accepted DESC, acceptance_rate_pct DESC
                    LIMIT 50
                ";
                renderTable(run($db, $sql));
            }
        }
        ?>
      </div>

      <div class="form-card report-card">
        <div class="report-head">
          <div>
            <h2 class="report-title">6) Monthly Trend: Tastings Created / Scheduled</h2>
            <p class="report-subtitle">Monthly buckets from the detected date column.</p>
          </div>
        </div>
        <?php
        if (!$dateCol || !isDateLikeType($dateType)) {
            echo "<div class='notice notice-error'>No suitable date column detected for time series.</div>";
        } else {
            $expr = dateExpr('t', $dateCol, $dateType);
            $sql = "
              SELECT
                DATE_FORMAT($expr, '%Y-%m') AS month,
                COUNT(*) AS num_catas
              FROM tastings t
              " . ($hasFilter ? $dateWhere : "") . "
              GROUP BY DATE_FORMAT($expr, '%Y-%m')
              ORDER BY month ASC
            ";
            renderTable(run($db, $sql, $params));
        }
        ?>
      </div>

      <div class="form-card report-card">
        <div class="report-head">
          <div>
            <h2 class="report-title">7) Low-Stock Wines</h2>
            <p class="report-subtitle">Wines with stock ≤ 3 (plus inventory value if price exists).</p>
          </div>
        </div>
        <?php
        if (!tableExists($db, 'vinos')) {
            echo "<div class='notice notice-error'>Table vinos not found.</div>";
        } else {
            $vDesc  = describe($db, 'vinos');
            $vName  = pickCol($vDesc, ['nombre', 'name', 'titulo']);
            $vStock = pickCol($vDesc, ['stock', 'cantidad']);
            $vPrice = pickCol($vDesc, ['precio', 'price'], null);
            $vId    = pickCol($vDesc, ['id_vino', 'id'], null);

            if (!$vName || !$vStock) {
                echo "<div class='notice notice-error'>No compatible columns found (need nombre + stock).</div>";
            } else {
                $valueExpr = $vPrice ? "(v.`$vStock` * v.`$vPrice`)" : "NULL";
                $sql = "
                  SELECT
                    " . ($vId ? "v.`$vId` AS id_vino," : "") . "
                    v.`$vName` AS wine,
                    v.`$vStock` AS stock,
                    " . ($vPrice ? "v.`$vPrice` AS price," : "") . "
                    $valueExpr AS inventory_value
                  FROM vinos v
                  WHERE v.`$vStock` <= 3
                  ORDER BY v.`$vStock` ASC
                  LIMIT 50
                ";
                renderTable(run($db, $sql));
            }
        }
        ?>
      </div>

      <div class="form-card report-card">
        <div class="report-head">
          <div>
            <h2 class="report-title">8) Top Wines Used in Tastings</h2>
            <p class="report-subtitle">Most frequently used wines in tasting_wines.</p>
          </div>
        </div>
        <?php
        if (!tableExists($db, 'tasting_wines') || !tableExists($db, 'vinos')) {
            echo "<div class='notice notice-error'>Need tables tasting_wines and vinos.</div>";
        } else {
            $twDesc = describe($db, 'tasting_wines');
            $vDesc  = describe($db, 'vinos');

            $twWine = pickCol($twDesc, ['id_vino', 'wine_id']);
            $twCata = pickCol($twDesc, ['id_cata', 'tasting_id']);
            $vId    = pickCol($vDesc, ['id_vino', 'id']);
            $vName  = pickCol($vDesc, ['nombre', 'name', 'titulo']);

            if (!$twWine || !$twCata || !$vId || !$vName) {
                echo "<div class='notice notice-error'>Schema not compatible for this report.</div>";
            } else {
                $sql = "
                  SELECT
                    v.`$vName` AS wine,
                    COUNT(*) AS times_in_tastings
                  FROM tasting_wines tw
                  JOIN vinos v ON v.`$vId` = tw.`$twWine`
                  GROUP BY v.`$vId`, v.`$vName`
                  ORDER BY times_in_tastings DESC
                  LIMIT 10
                ";
                renderTable(run($db, $sql));
            }
        }
        ?>
      </div>

    </div>
  </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
