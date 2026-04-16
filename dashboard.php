<?php
require_once 'config.php';
requireAdmin();

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Ensure all needed columns
$conn->query("ALTER TABLE animals ADD COLUMN IF NOT EXISTS submission_status ENUM('approved','awaiting','rejected') NOT NULL DEFAULT 'approved' AFTER status");
$conn->query("ALTER TABLE animals ADD COLUMN IF NOT EXISTS admin_note VARCHAR(255) DEFAULT NULL AFTER submission_status");
$conn->query("ALTER TABLE animals ADD COLUMN IF NOT EXISTS submitted_by INT DEFAULT NULL AFTER admin_note");
$conn->query("ALTER TABLE animals ADD COLUMN IF NOT EXISTS hourly_rate DECIMAL(6,2) NOT NULL DEFAULT 5.00");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS booking_ends_at TIMESTAMP NULL DEFAULT NULL");
$conn->query("ALTER TABLE bookings MODIFY COLUMN status ENUM('pending','confirmed','cancelled','expired') NOT NULL DEFAULT 'pending'");

// ── Auto-expire bookings whose time is up ──────────────────
$conn->query("
    UPDATE bookings b
    JOIN animals a ON b.animal_id = a.id
    SET b.status = 'expired',
        a.status = 'available'
    WHERE b.status = 'confirmed'
      AND b.booking_ends_at IS NOT NULL
      AND b.booking_ends_at < NOW()
");

// ── Recently finished bookings — notify admin ─────────────────────────────
$recentlyFinished = [];
$rfRes = $conn->query("
    SELECT b.booking_ends_at, a.name AS animal_name, a.species,
           u.full_name AS customer_name, u.email AS customer_email
    FROM bookings b
    JOIN animals a ON b.animal_id = a.id
    JOIN users u   ON b.user_id   = u.id
    WHERE b.status = 'expired'
      AND b.booking_ends_at >= NOW() - INTERVAL 30 MINUTE
    ORDER BY b.booking_ends_at DESC
    LIMIT 5
");
if ($rfRes) $recentlyFinished = $rfRes->fetch_all(MYSQLI_ASSOC);

// ── Filters ────────────────────────────────────────────────
$filterStatus  = $_GET['status']  ?? 'all';
$filterSpecies = $_GET['species'] ?? 'all';

$where  = "WHERE 1=1";
$types  = "";
$params = [];
if ($filterStatus  !== 'all') { $where .= " AND a.status=?";  $types .= "s"; $params[] = $filterStatus; }
if ($filterSpecies !== 'all') { $where .= " AND a.species=?"; $types .= "s"; $params[] = $filterSpecies; }

// Join with bookings to get timer info
$sql = "
    SELECT a.*,
           b.id           AS booking_id,
           b.hours        AS booking_hours,
           b.hourly_rate  AS booking_rate,
           b.total_cost   AS booking_total,
           b.card_last4,
           b.card_name    AS booking_card_name,
           b.status       AS booking_status,
           b.booking_ends_at,
           b.created_at   AS booked_at,
           u.full_name    AS customer_name,
           u.email        AS customer_email
    FROM animals a
    LEFT JOIN bookings b ON b.animal_id = a.id
        AND b.status IN ('confirmed','expired')
    LEFT JOIN users u ON b.user_id = u.id
    $where
    ORDER BY a.created_at DESC
";

if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
} else {
    $stmt = $conn->prepare($sql);
}
$stmt->execute();
$animals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Stats
$statsRes = $conn->query("SELECT status, COUNT(*) c FROM animals GROUP BY status");
$stats = ['available'=>0,'pending'=>0,'adopted'=>0];
while ($r = $statsRes->fetch_assoc()) $stats[$r['status']] = (int)$r['c'];
$total = array_sum($stats);

// Finished = expired bookings (care period complete)
$finRes = $conn->query("SELECT COUNT(*) c FROM bookings WHERE status='expired'");
$finishedCount = $finRes ? $finRes->fetch_assoc()['c'] : 0;

// Active bookings count (not expired)
$activeRes = $conn->query("SELECT COUNT(*) c FROM bookings WHERE status='confirmed' AND booking_ends_at > NOW()");
$activeBookings = $activeRes->fetch_assoc()['c'] ?? 0;

// Badges
$pendingApps = $conn->query("SELECT COUNT(*) c FROM adoption_applications aa JOIN animals a ON aa.animal_id=a.id WHERE aa.status='pending'")->fetch_assoc()['c'] ?? 0;
$awaitCount  = $conn->query("SELECT COUNT(*) c FROM animals WHERE submitted_by IS NOT NULL AND submission_status='awaiting'")->fetch_assoc()['c'] ?? 0;

// Auto-recalc rates
$allA = $conn->query("SELECT id, species, size, age FROM animals");
while ($row = $allA->fetch_assoc()) {
    $rate = calcHourlyRate($row['species'], $row['size'], $row['age']);
    $conn->query("UPDATE animals SET hourly_rate=$rate WHERE id={$row['id']}");
}

$msg = ['added'=>'Animal added!','updated'=>'Animal updated!','deleted'=>'Animal removed!'][$_GET['success'] ?? ''] ?? '';
$err = (($_GET['error'] ?? '') === 'delete_failed') ? 'Delete failed.' : '';
$allSpecies = ['Dog','Cat','Bird','Parrot','Eagle','Other'];

// Pass animals to JS for timer
$timerData = [];
foreach ($animals as $a) {
    if (!empty($a['booking_ends_at']) && $a['booking_status'] === 'confirmed') {
        $timerData[$a['id']] = [
            'ends_at'  => $a['booking_ends_at'],
            'hours'    => $a['booking_hours'],
            'customer' => $a['customer_name'],
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Dashboard — Happy Paws</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="dash-body">

<aside class="sidebar">
  <div class="sidebar-brand">🐾 <span>HappyPaws</span></div>
  <nav class="sidebar-nav">
    <a href="dashboard.php"          class="nav-item active">🏠 Dashboard</a>
    <a href="review_submissions.php" class="nav-item">
      📦 Submissions<?php if($awaitCount>0): ?><span class="nav-badge"><?= $awaitCount ?></span><?php endif; ?>
    </a>
    <a href="bookings_admin.php" class="nav-item">💳 Bookings</a>
    <a href="logout.php"             class="nav-item nav-logout">🚪 Logout</a>
  </nav>
  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($full_name,0,1)) ?></div>
    <div>
      <div class="user-name"><?= htmlspecialchars($full_name) ?></div>
      <div class="user-role">Admin</div>
    </div>
  </div>
</aside>

<main class="dash-main">
  <div class="dash-topbar">
    <div>
      <h1 class="dash-title">Live Dashboard</h1>
      <p class="dash-sub">Real-time animal status, active timers, and booking info</p>
    </div>
    <div class="sync-indicator" id="syncIndicator">
      <span class="sync-dot"></span> Live
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <!-- ── Admin: Recently Finished Notifications ── -->
  <div id="liveNotifArea"></div>
  <?php foreach ($recentlyFinished as $rf): ?>
    <div class="notif-banner notif-admin">
      <div class="notif-icon">🔔</div>
      <div class="notif-body">
        <strong><?= htmlspecialchars($rf['animal_name']) ?>'s care period is finished!</strong><br>
        <span>Customer: <strong><?= htmlspecialchars($rf['customer_name']) ?></strong>
        (<?= htmlspecialchars($rf['customer_email']) ?>) ·
        Ended: <?= date('M d · H:i', strtotime($rf['booking_ends_at'])) ?></span>
      </div>
      <span class="badge badge-finished" style="align-self:center">Finished</span>
    </div>
  <?php endforeach; ?>

  <!-- Stats: Total, Pending, Finished -->
  <div class="stats-row stats-row-3">
    <div class="stat-card stat-total">
      <div class="stat-icon">🐾</div>
      <div class="stat-num"><?= $total ?></div>
      <div class="stat-label">Total Animals</div>
    </div>
    <div class="stat-card stat-pending">
      <div class="stat-icon">⏳</div>
      <div class="stat-num"><?= $stats['pending'] ?></div>
      <div class="stat-label">Pending</div>
    </div>
    <div class="stat-card stat-finished">
      <div class="stat-icon">✅</div>
      <div class="stat-num"><?= $finishedCount ?></div>
      <div class="stat-label">Finished</div>
    </div>
  </div>

  <!-- Filters -->
  <div class="filter-bar">
    <div class="filter-group">
      <span class="filter-label">Status:</span>
      <?php foreach (['all','available','pending','adopted'] as $s): ?>
        <a href="?status=<?= $s ?>&species=<?= $filterSpecies ?>"
           class="filter-chip <?= $filterStatus===$s?'active':'' ?>"><?= ucfirst($s) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="filter-group">
      <span class="filter-label">Species:</span>
      <a href="?status=<?= $filterStatus ?>&species=all"
         class="filter-chip <?= $filterSpecies==='all'?'active':'' ?>">All</a>
      <?php foreach ($allSpecies as $sp): ?>
        <a href="?status=<?= $filterStatus ?>&species=<?= $sp ?>"
           class="filter-chip <?= $filterSpecies===$sp?'active':'' ?>"><?= speciesEmoji($sp) ?> <?= $sp ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Animal Table -->
  <?php if (empty($animals)): ?>
    <div class="empty-state">
      <div class="empty-icon">🐾</div>
      <h2>No animals found</h2>
    </div>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table" id="animalTable">
        <thead>
          <tr>
            <th>Animal</th>
            <th>Species</th>
            <th>Details</th>
            <th>Status</th>
            <th>⏱ Listed</th>
            <th>💰 Rate/hr</th>
            <th>👤 Customer</th>
            <th>⏰ Time Remaining</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($animals as $a):
            $rate      = (float)($a['hourly_rate'] ?? calcHourlyRate($a['species'], $a['size'], $a['age']));
            $listedAgo = timeSinceListed($a['created_at']);
            $hasTimer  = !empty($a['booking_ends_at']) && $a['booking_status'] === 'confirmed';
            $isExpired = $a['booking_status'] === 'expired';
            $fromCustomer = !empty($a['submitted_by']);
          ?>
          <tr class="animal-row <?= $a['status'] ?>-row" id="row-<?= $a['id'] ?>">
            <td>
              <div class="table-animal-name">
                <span class="table-emoji"><?= speciesEmoji($a['species']) ?></span>
                <div>
                  <strong><?= htmlspecialchars($a['name']) ?></strong>
                  <?php if ($a['breed']): ?><div class="table-breed"><?= htmlspecialchars($a['breed']) ?></div><?php endif; ?>
                </div>
              </div>
            </td>
            <td><span class="species-chip"><?= htmlspecialchars($a['species']) ?></span></td>
            <td>
              <div class="table-details">
                <span>🎂 <?= htmlspecialchars($a['age']) ?></span>
                <span><?= $a['gender']==='Male'?'♂':'♀' ?> <?= $a['gender'] ?></span>
                <span>📏 <?= $a['size'] ?></span>
              </div>
            </td>
            <td>
              <span class="badge <?= statusClass($a['status']) ?>" id="status-<?= $a['id'] ?>">
                <?= ucfirst($a['status']) ?>
              </span>
            </td>
            <td>
              <div class="time-cell">
                <span class="time-ago"><?= $listedAgo ?></span>
                <span class="time-exact"><?= date('M d, Y', strtotime($a['created_at'])) ?></span>
              </div>
            </td>
            <td>
              <div class="rate-cell">
                <span class="rate-amount">$<?= number_format($rate,2) ?></span>
                <span class="rate-label">/hr</span>
              </div>
            </td>
            <td>
              <?php if ($a['customer_name']): ?>
                <div class="customer-cell">
                  <strong><?= htmlspecialchars($a['customer_name']) ?></strong>
                  <small><?= htmlspecialchars($a['customer_email']) ?></small>
                  <?php if ($a['booking_hours']): ?>
                    <span class="booking-chip">
                      <?= $a['booking_hours'] ?>h · $<?= number_format($a['booking_total'],2) ?>
                    </span>
                  <?php endif; ?>
                  <?php if ($a['card_last4']): ?>
                    <span class="card-chip">*<?= htmlspecialchars($a['card_last4']) ?></span>
                  <?php endif; ?>
                  <?php if ($fromCustomer): ?>
                    <span class="source-chip source-customer" style="margin-top:4px">📦 Submitted</span>
                  <?php endif; ?>
                </div>
              <?php else: ?>
                <span style="color:var(--muted);font-size:12px">No booking</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($hasTimer): ?>
                <div class="timer-cell" id="timer-<?= $a['id'] ?>"
                     data-ends="<?= $a['booking_ends_at'] ?>"
                     data-id="<?= $a['id'] ?>">
                  <div class="timer-ring">
                    <div class="timer-time" id="timerText-<?= $a['id'] ?>">--:--:--</div>
                    <div class="timer-label">remaining</div>
                  </div>
                  <div class="timer-bar-wrap">
                    <div class="timer-bar" id="timerBar-<?= $a['id'] ?>"
                         data-total="<?= $a['booking_hours'] * 3600 ?>"></div>
                  </div>
                  <div class="timer-booked">
                    Booked: <?= date('M d H:i', strtotime($a['booked_at'])) ?><br>
                    Ends: <?= date('M d H:i', strtotime($a['booking_ends_at'])) ?>
                  </div>
                </div>
              <?php elseif ($isExpired): ?>
                <div class="timer-expired">
                  ✅ Time Complete<br>
                  <small>Adoption period ended</small><br>
                  <small><?= date('M d H:i', strtotime($a['booking_ends_at'])) ?></small>
                </div>
              <?php else: ?>
                <span style="color:var(--muted);font-size:12px">—</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="table-actions">
                <a href="edit_animal.php?id=<?= $a['id'] ?>" class="btn-action btn-edit" title="Edit">✏️</a>
                <a href="delete_animal.php?id=<?= $a['id'] ?>"
                   class="btn-action btn-delete" title="Delete"
                   onclick="return confirm('Delete <?= htmlspecialchars($a['name']) ?>?')">🗑</a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pricing legend -->
    <div class="pricing-legend">
      <h4>💰 Pricing Rules — Base $5/hr</h4>
      <div class="legend-grid">
        <div class="legend-item"><span>🦅 Eagle</span><strong>×3.0</strong></div>
        <div class="legend-item"><span>🦜 Parrot</span><strong>×2.0</strong></div>
        <div class="legend-item"><span>🐕 Dog</span><strong>×1.6</strong></div>
        <div class="legend-item"><span>🐱 Cat</span><strong>×1.2</strong></div>
        <div class="legend-item"><span>Large</span><strong>×1.6</strong></div>
        <div class="legend-item"><span>Medium</span><strong>×1.3</strong></div>
        <div class="legend-item"><span>≤6 months</span><strong>×2.5</strong></div>
        <div class="legend-item"><span>≤1 year</span><strong>×2.0</strong></div>
        <div class="legend-item"><span>8+ years</span><strong>×0.8</strong></div>
      </div>
    </div>
  <?php endif; ?>
</main>

<script>
// ── Live Countdown Timers ─────────────────────────────────────────────────────
const timers = document.querySelectorAll('.timer-cell');

function formatTime(seconds) {
  if (seconds <= 0) return '00:00:00';
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  const s = seconds % 60;
  return [h,m,s].map(v => String(v).padStart(2,'0')).join(':');
}

function updateTimers() {
  const now = Math.floor(Date.now() / 1000);

  timers.forEach(cell => {
    const id      = cell.dataset.id;
    const endsAt  = Math.floor(new Date(cell.dataset.ends).getTime() / 1000);
    const totalSec = parseInt(document.getElementById('timerBar-' + id)?.dataset.total || 0);
    const remaining = endsAt - now;

    const textEl = document.getElementById('timerText-' + id);
    const barEl  = document.getElementById('timerBar-'  + id);
    const rowEl  = document.getElementById('row-' + id);

    if (remaining <= 0) {
      // Timer expired — show Finished
      if (textEl) textEl.textContent = '00:00:00';
      if (barEl)  barEl.style.width  = '0%';
      if (rowEl)  rowEl.classList.add('timer-done-row');
      cell.innerHTML = `<div class="timer-expired">✅ Finished<br><small>Care period complete</small></div>`;

      // Update the status badge in the same row
      const statusEl = document.getElementById('status-' + id);
      if (statusEl) {
        statusEl.textContent = 'Finished';
        statusEl.className   = 'badge badge-finished';
      }

      // Tell server to expire this booking
      fetch('expire_booking.php?animal_id=' + id);
    } else {
      if (textEl) {
        textEl.textContent = formatTime(remaining);
        // Color warning when < 1 hour
        if (remaining < 3600)       textEl.style.color = '#ef4444';
        else if (remaining < 7200)  textEl.style.color = '#eab308';
        else                        textEl.style.color = 'var(--accent)';
      }
      if (barEl && totalSec > 0) {
        const pct = Math.min(100, (remaining / totalSec) * 100);
        barEl.style.width = pct + '%';
        if (pct < 20)       barEl.style.background = '#ef4444';
        else if (pct < 40)  barEl.style.background = '#eab308';
        else                barEl.style.background = 'var(--accent)';
      }
    }
  });
}

// Run immediately and every second
updateTimers();
setInterval(updateTimers, 1000);

// ── Sync indicator pulse ──────────────────────────────────────────────────────
const syncDot = document.querySelector('.sync-dot');
setInterval(() => {
  syncDot?.classList.add('pulse');
  setTimeout(() => syncDot?.classList.remove('pulse'), 600);
}, 5000);

// ── Auto-refresh page every 60s to re-check DB state ─────────────────────────
setTimeout(() => location.reload(), 60000);
</script>

<div class="sound-toast" id="soundToast"></div>
<?php include 'sound_engine.php'; ?>
</body>
</html>
