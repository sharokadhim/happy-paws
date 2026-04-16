<?php
require_once 'config.php';
requireLogin();
if (isAdmin()) { header("Location: dashboard.php"); exit(); }

$user_id = $_SESSION['user_id'];

// Ensure tables/columns
$conn->query("CREATE TABLE IF NOT EXISTS `bookings` (
  `id` INT NOT NULL AUTO_INCREMENT, `user_id` INT NOT NULL, `animal_id` INT NOT NULL,
  `hours` INT NOT NULL DEFAULT 1, `hourly_rate` DECIMAL(6,2) NOT NULL,
  `total_cost` DECIMAL(8,2) NOT NULL, `card_last4` CHAR(4) DEFAULT NULL,
  `card_name` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("ALTER TABLE animals ADD COLUMN IF NOT EXISTS submission_status ENUM('approved','awaiting','rejected') NOT NULL DEFAULT 'approved' AFTER status");
$conn->query("ALTER TABLE animals ADD COLUMN IF NOT EXISTS admin_note VARCHAR(255) DEFAULT NULL AFTER submission_status");
$conn->query("ALTER TABLE animals ADD COLUMN IF NOT EXISTS submitted_by INT DEFAULT NULL AFTER admin_note");

// All bookings for this user with full animal + submission info
$stmt = $conn->prepare("
    SELECT
        b.id            AS booking_id,
        b.hours,
        b.hourly_rate,
        b.total_cost,
        b.card_last4,
        b.card_name,
        b.status        AS booking_status,
        b.booking_ends_at,
        b.created_at    AS booked_at,
        a.id            AS animal_id,
        a.name          AS animal_name,
        a.species,
        a.breed,
        a.age,
        a.size,
        a.gender,
        a.status        AS animal_status,
        a.submission_status,
        a.admin_note,
        a.submitted_by
    FROM bookings b
    JOIN animals a ON b.animal_id = a.id
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Total spent
$totalSpent = array_sum(array_column($bookings, 'total_cost'));

$msg = '';
if (isset($_GET['submitted'])) $msg = "Animal submitted & payment confirmed! 🎉 Admin will review shortly.";
if (isset($_GET['booked']))    $msg = "Booking confirmed! 🎉";

// Check for any newly finished bookings to notify the customer
$notifStmt = $conn->prepare("
    SELECT b.id, b.booking_ends_at, a.name AS animal_name, a.species
    FROM bookings b
    JOIN animals a ON b.animal_id = a.id
    WHERE b.user_id = ?
      AND b.status = 'expired'
      AND b.booking_ends_at >= NOW() - INTERVAL 10 MINUTE
    ORDER BY b.booking_ends_at DESC
    LIMIT 3
");
$notifStmt->bind_param("i", $user_id);
$notifStmt->execute();
$notifications = $notifStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$notifStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Bookings — Happy Paws</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="dash-body">
<aside class="sidebar">
  <div class="sidebar-brand">🐾 <span>HappyPaws</span></div>
  <nav class="sidebar-nav">
    <a href="browse.php"         class="nav-item">🐾 Animals</a>
    <a href="submit_animal.php"  class="nav-item">➕ Submit Animal</a>
    <a href="my_submissions.php" class="nav-item">📦 My Submissions</a>
    <a href="my_bookings.php"    class="nav-item active">💳 My Bookings</a>
    <a href="logout.php"         class="nav-item nav-logout">🚪 Logout</a>
  </nav>
  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($_SESSION['full_name'],0,1)) ?></div>
    <div><div class="user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
    <div class="user-role">Customer</div></div>
  </div>
</aside>

<main class="dash-main">
  <div class="dash-topbar">
    <div>
      <h1 class="dash-title">My Bookings</h1>
      <p class="dash-sub">All your payments, reservations, and adoption outcomes</p>
    </div>
    <a href="submit_animal.php" class="btn btn-glow">+ Submit Animal</a>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- Live JS notification area (timers fire here) -->
  <div id="liveNotifArea"></div>

  <!-- ── Finished notifications ── -->
  <?php foreach ($notifications as $notif): ?>
    <div class="notif-banner">
      <div class="notif-icon">🔔</div>
      <div class="notif-body">
        <strong>Your care period for <?= htmlspecialchars($notif['animal_name']) ?> is complete!</strong><br>
        <span>The adoption period ended at <?= date('M d, Y · H:i', strtotime($notif['booking_ends_at'])) ?>.
        You can now come and pick up your animal. 🐾</span>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (!empty($bookings)): ?>
    <!-- Financial summary bar -->
    <div class="financial-summary">
      <div class="fin-item">
        <div class="fin-num"><?= count($bookings) ?></div>
        <div class="fin-label">Total Bookings</div>
      </div>
      <div class="fin-item">
        <div class="fin-num">$<?= number_format($totalSpent, 2) ?></div>
        <div class="fin-label">Total Spent</div>
      </div>
      <div class="fin-item">
        <div class="fin-num"><?= count(array_filter($bookings, fn($b) => $b['submission_status']==='approved')) ?></div>
        <div class="fin-label">Approved</div>
      </div>
      <div class="fin-item">
        <div class="fin-num"><?= count(array_filter($bookings, fn($b) => $b['submission_status']==='awaiting')) ?></div>
        <div class="fin-label">Awaiting Review</div>
      </div>
    </div>
  <?php endif; ?>

  <?php if (empty($bookings)): ?>
    <div class="empty-state">
      <div class="empty-icon">💳</div>
      <h2>No bookings yet</h2>
      <p>Submit an animal or browse and book a reservation.</p>
      <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
        <a href="submit_animal.php" class="btn btn-glow">Submit Animal</a>
        <a href="browse.php"        class="btn btn-ghost">Browse Animals</a>
      </div>
    </div>
  <?php else: ?>
    <div class="bookings-list">
      <?php foreach ($bookings as $b):
        $ss = $b['submission_status'] ?? 'approved';
        $as = $b['animal_status'];
        $fromSubmit = !empty($b['submitted_by']);
      ?>
        <div class="booking-full-card">

          <!-- Left: Animal info -->
          <div class="bfc-left">
            <div class="bfc-emoji"><?= speciesEmoji($b['species']) ?></div>
            <div class="bfc-animal">
              <div class="bfc-name"><?= htmlspecialchars($b['animal_name']) ?></div>
              <div class="bfc-breed">
                <?= htmlspecialchars($b['species']) ?>
                <?= $b['breed'] ? ' · '.htmlspecialchars($b['breed']) : '' ?>
              </div>
              <div class="bfc-meta">
                <span>🎂 <?= htmlspecialchars($b['age']) ?></span>
                <span>📏 <?= $b['size'] ?></span>
                <span><?= $b['gender']==='Male'?'♂':'♀' ?> <?= $b['gender'] ?></span>
              </div>
              <!-- Source tag -->
              <div style="margin-top:6px">
                <?php if ($fromSubmit): ?>
                  <span class="source-chip source-customer">📦 You submitted this</span>
                <?php else: ?>
                  <span class="source-chip source-admin">🐾 Booked from browse</span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Middle: Financial details -->
          <div class="bfc-finance">
            <div class="bfc-section-title">💰 Payment</div>
            <div class="bfc-finance-grid">
              <div class="bfc-fin-item">
                <div class="bfc-fin-label">Hourly Rate</div>
                <div class="bfc-fin-value">$<?= number_format($b['hourly_rate'], 2) ?>/hr</div>
              </div>
              <div class="bfc-fin-item">
                <div class="bfc-fin-label">Hours</div>
                <div class="bfc-fin-value"><?= $b['hours'] ?> hr<?= $b['hours']>1?'s':'' ?></div>
              </div>
              <div class="bfc-fin-item highlight">
                <div class="bfc-fin-label">Total Paid</div>
                <div class="bfc-fin-value accent">$<?= number_format($b['total_cost'], 2) ?></div>
              </div>
              <div class="bfc-fin-item">
                <div class="bfc-fin-label">Card Used</div>
                <div class="bfc-fin-value">**** <?= htmlspecialchars($b['card_last4'] ?? '——') ?></div>
              </div>
            </div>
            <div class="bfc-date">
              Booked <?= date('M d, Y · H:i', strtotime($b['booked_at'])) ?>
            </div>
          </div>

          <!-- Right: Status & outcome -->
          <div class="bfc-status">
            <div class="bfc-section-title">📋 Status</div>

            <!-- Booking status -->
            <span class="badge <?= $b['booking_status']==='confirmed'?'badge-available':'badge-pending' ?>" style="margin-bottom:10px;display:inline-block">
              <?= ucfirst($b['booking_status']) ?>
            </span>

            <!-- Submission review status -->
            <?php if ($fromSubmit): ?>
              <div class="bfc-outcome-label">Admin Review</div>
              <?php if ($ss === 'approved'): ?>
                <div class="outcome-box outcome-adopted" style="margin-bottom:8px">
                  ✅ <strong>Approved!</strong><br>
                  <small>Your animal is now live on the site.</small>
                </div>
              <?php elseif ($ss === 'rejected'): ?>
                <div class="outcome-box outcome-pending" style="background:rgba(239,68,68,.1);color:#ef4444;border-color:rgba(239,68,68,.2);margin-bottom:8px">
                  ❌ <strong>Rejected</strong><br>
                  <?php if (!empty($b['admin_note'])): ?>
                    <small>Note: <?= htmlspecialchars($b['admin_note']) ?></small>
                  <?php else: ?>
                    <small>Contact us for details.</small>
                  <?php endif; ?>
                </div>
              <?php else: ?>
                <div class="outcome-box outcome-pending" style="margin-bottom:8px">
                  ⏳ <strong>Awaiting Review</strong><br>
                  <small>Admin will review your submission shortly.</small>
                </div>
              <?php endif; ?>
            <?php endif; ?>

            <!-- Live timer -->
            <?php
              $endsAt  = $b['booking_ends_at'] ?? null;
              $bStatus = $b['booking_status'];
              $totalSec = ($b['hours'] ?? 1) * 3600;
            ?>
            <?php if ($endsAt && $bStatus === 'confirmed'): ?>
              <div class="customer-timer"
                   data-ends="<?= htmlspecialchars($endsAt) ?>"
                   data-total="<?= $totalSec ?>">
                <div class="ctimer-label">⏰ Time Remaining</div>
                <div class="ctimer-time" id="ct-<?= $b['booking_id'] ?>">--:--:--</div>
                <div class="ctimer-bar-wrap">
                  <div class="ctimer-bar" id="ctb-<?= $b['booking_id'] ?>"></div>
                </div>
                <div class="ctimer-ends">Ends: <?= date('M d, Y · H:i', strtotime($endsAt)) ?></div>
              </div>
            <?php elseif ($bStatus === 'expired'): ?>
              <div class="customer-timer-done">
                ✅ <strong>Care period complete!</strong><br>
                <small>Your animal is ready to be picked up.</small>
              </div>
            <?php endif; ?>

            <!-- Adoption outcome -->
            <div class="bfc-outcome-label">Adoption</div>
            <?php if ($as === 'adopted'): ?>
              <div class="outcome-box outcome-adopted">🎉 <strong>Adopted!</strong></div>
            <?php elseif ($as === 'pending'): ?>
              <div class="outcome-box outcome-pending">⏳ <strong>Pending</strong></div>
            <?php else: ?>
              <div class="outcome-box outcome-available">
                ✅ <strong>Available</strong>
                <a href="apply.php?animal_id=<?= $b['animal_id'] ?>" class="outcome-link">Apply →</a>
              </div>
            <?php endif; ?>
          </div>

        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<script>
function formatTime(s) {
  if (s <= 0) return '00:00:00';
  const h = Math.floor(s/3600), m = Math.floor((s%3600)/60), sec = s%60;
  return [h,m,sec].map(v => String(v).padStart(2,'0')).join(':');
}

// Track which timers have already fired notification
const notifiedTimers = new Set();

function showFinishedNotification(animalName) {
  // Create a top notification banner dynamically
  const existing = document.getElementById('liveNotifArea');
  const banner = document.createElement('div');
  banner.className = 'notif-banner notif-live';
  banner.innerHTML = `
    <div class="notif-icon">🔔</div>
    <div class="notif-body">
      <strong>Care period for ${animalName} is now complete!</strong><br>
      <span>You can come and pick up your animal now. 🐾</span>
    </div>
    <button onclick="this.parentElement.remove()" class="notif-close">✕</button>
  `;
  if (existing) existing.appendChild(banner);

  // Also browser notification if permission granted
  if (Notification && Notification.permission === 'granted') {
    new Notification('Happy Paws 🐾', {
      body: `Care period for ${animalName} is complete! Come pick them up.`,
      icon: 'sounds/dog.mp3'
    });
  }
}

// Request browser notification permission once
if (Notification && Notification.permission === 'default') {
  Notification.requestPermission();
}

function runCustomerTimers() {
  document.querySelectorAll('.customer-timer').forEach(el => {
    const ends    = Math.floor(new Date(el.dataset.ends).getTime()/1000);
    const total   = parseInt(el.dataset.total||3600);
    const now     = Math.floor(Date.now()/1000);
    const rem     = ends - now;
    const timeEl  = el.querySelector('.ctimer-time');
    const barEl   = el.querySelector('.ctimer-bar');
    const timerId = el.dataset.ends; // unique key

    if (rem <= 0) {
      if (timeEl) timeEl.textContent = '00:00:00';
      if (barEl)  barEl.style.width  = '0%';

      // Fire notification only once per timer
      if (!notifiedTimers.has(timerId)) {
        notifiedTimers.add(timerId);
        // Get animal name from nearest card
        const card = el.closest('.booking-full-card');
        const name = card?.querySelector('.bfc-name')?.textContent || 'your animal';
        showFinishedNotification(name);
      }

      el.innerHTML = '<div class="customer-timer-done">✅ <strong>Care period complete!</strong><br><small>Your animal is ready to be picked up.</small></div>';
    } else {
      if (timeEl) {
        timeEl.textContent = formatTime(rem);
        timeEl.style.color = rem < 3600 ? '#ef4444' : rem < 7200 ? '#eab308' : 'var(--accent)';
      }
      if (barEl) {
        const pct = Math.min(100, (rem/total)*100);
        barEl.style.width = pct + '%';
        barEl.style.background = pct < 20 ? '#ef4444' : pct < 40 ? '#eab308' : 'var(--accent)';
      }
    }
  });
}

runCustomerTimers();
setInterval(runCustomerTimers, 1000);
</script>
</body>
</html>
