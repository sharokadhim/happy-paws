<?php
require_once 'config.php';
requireAdmin();

// Ensure columns exist
$conn->query("ALTER TABLE animals ADD COLUMN IF NOT EXISTS submission_status ENUM('approved','awaiting','rejected') NOT NULL DEFAULT 'approved' AFTER status");
$conn->query("ALTER TABLE animals ADD COLUMN IF NOT EXISTS admin_note VARCHAR(255) DEFAULT NULL AFTER submission_status");
$conn->query("ALTER TABLE animals ADD COLUMN IF NOT EXISTS submitted_by INT DEFAULT NULL AFTER admin_note");

// Handle approve / reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['animal_id'], $_POST['action'])) {
    $aid    = (int)$_POST['animal_id'];
    $action = $_POST['action'];
    $note   = trim($_POST['admin_note'] ?? '');

    if ($action === 'approve') {
        // Approved → set submission_status=approved, status=available so it shows on browse
        $stmt = $conn->prepare("UPDATE animals SET submission_status='approved', status='available', admin_note=? WHERE id=?");
        $stmt->bind_param("si", $note, $aid);
        $stmt->execute(); $stmt->close();
    } elseif ($action === 'reject') {
        // Rejected → hide from browse, store note
        $stmt = $conn->prepare("UPDATE animals SET submission_status='rejected', status='pending', admin_note=? WHERE id=?");
        $stmt->bind_param("si", $note, $aid);
        $stmt->execute(); $stmt->close();
    }
    header("Location: review_submissions.php?done=1"); exit();
}

// Get filter
$filter = $_GET['filter'] ?? 'awaiting';
$allowed = ['awaiting','approved','rejected','all'];
if (!in_array($filter, $allowed)) $filter = 'awaiting';

$where = $filter === 'all' ? "WHERE submitted_by IS NOT NULL" : "WHERE submitted_by IS NOT NULL AND submission_status=?";
if ($filter === 'all') {
    $stmt = $conn->prepare("SELECT a.*, u.full_name AS submitter_name, u.email AS submitter_email, b.hours AS booking_hours, b.hourly_rate AS booking_rate, b.total_cost AS booking_total, b.card_last4, b.booking_ends_at, b.status AS booking_status FROM animals a LEFT JOIN users u ON a.submitted_by = u.id LEFT JOIN bookings b ON b.animal_id = a.id AND b.user_id = a.submitted_by $where ORDER BY a.created_at DESC");
    $stmt->execute();
} else {
    $stmt = $conn->prepare("SELECT a.*, u.full_name AS submitter_name, u.email AS submitter_email, b.hours AS booking_hours, b.hourly_rate AS booking_rate, b.total_cost AS booking_total, b.card_last4, b.booking_ends_at, b.status AS booking_status FROM animals a LEFT JOIN users u ON a.submitted_by = u.id LEFT JOIN bookings b ON b.animal_id = a.id AND b.user_id = a.submitted_by $where ORDER BY a.created_at DESC");
    $stmt->bind_param("s", $filter);
    $stmt->execute();
}
$submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count awaiting for badge
$awaitCount = $conn->query("SELECT COUNT(*) c FROM animals WHERE submitted_by IS NOT NULL AND submission_status='awaiting'")->fetch_assoc()['c'] ?? 0;

$msg = isset($_GET['done']) ? 'Submission updated successfully!' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Review Submissions — Happy Paws</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="dash-body">
<aside class="sidebar">
  <div class="sidebar-brand">🐾 <span>HappyPaws</span></div>
  <nav class="sidebar-nav">
    <a href="dashboard.php"          class="nav-item">🏠 Dashboard</a>
    <a href="add_animal.php"         class="nav-item">➕ Add Animal</a>
    <a href="review_submissions.php" class="nav-item active">
      📦 Submissions
      <?php if ($awaitCount > 0): ?><span class="nav-badge"><?= $awaitCount ?></span><?php endif; ?>
    </a>
    <a href="logout.php"             class="nav-item nav-logout">🚪 Logout</a>
  </nav>
  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($_SESSION['full_name'],0,1)) ?></div>
    <div>
      <div class="user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
      <div class="user-role">Admin</div>
    </div>
  </div>
</aside>

<main class="dash-main">
  <div class="dash-topbar">
    <div>
      <h1 class="dash-title">Customer Submissions</h1>
      <p class="dash-sub">Review animals submitted by customers — approve or reject each one</p>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- Filter tabs -->
  <div class="filter-bar">
    <div class="filter-group">
      <span class="filter-label">Show:</span>
      <?php foreach (['awaiting'=>'⏳ Awaiting','approved'=>'✅ Approved','rejected'=>'❌ Rejected','all'=>'All'] as $k=>$label): ?>
        <a href="?filter=<?= $k ?>" class="filter-chip <?= $filter===$k?'active':'' ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (empty($submissions)): ?>
    <div class="empty-state">
      <div class="empty-icon">📦</div>
      <h2>No submissions here</h2>
      <p>Customer animal submissions will appear here for your review.</p>
    </div>
  <?php else: ?>
    <div class="review-grid">
      <?php foreach ($submissions as $a):
        $ss = $a['submission_status'] ?? 'awaiting';
      ?>
        <div class="review-card review-card-<?= $ss ?>">

          <!-- Card header -->
          <div class="review-card-header">
            <div class="card-emoji-sm"><?= speciesEmoji($a['species']) ?></div>
            <div class="review-card-title">
              <div class="card-name"><?= htmlspecialchars($a['name']) ?></div>
              <div class="card-breed"><?= htmlspecialchars($a['species']) ?><?= $a['breed'] ? ' · '.htmlspecialchars($a['breed']) : '' ?></div>
            </div>
            <?php if ($ss === 'approved'): ?>
              <span class="badge badge-available">✅ Approved</span>
            <?php elseif ($ss === 'rejected'): ?>
              <span class="badge badge-rejected">❌ Rejected</span>
            <?php else: ?>
              <span class="badge badge-awaiting">⏳ Awaiting</span>
            <?php endif; ?>
          </div>

          <!-- Animal details -->
          <div class="plan-info">
            <div class="info-item"><strong>Age:</strong> <?= htmlspecialchars($a['age']) ?> &nbsp;|&nbsp; <strong>Gender:</strong> <?= $a['gender'] ?> &nbsp;|&nbsp; <strong>Size:</strong> <?= $a['size'] ?></div>
            <?php if ($a['description']): ?>
              <div class="info-item"><strong>Description:</strong> <?= htmlspecialchars($a['description']) ?></div>
            <?php endif; ?>
            <div class="info-item" style="margin-top:6px;padding-top:6px;border-top:1px solid var(--border)">
              <strong>Submitted by:</strong> <?= htmlspecialchars($a['submitter_name'] ?? 'Unknown') ?>
              &nbsp;·&nbsp; <?= htmlspecialchars($a['submitter_email'] ?? '') ?>
              &nbsp;·&nbsp; <?= date('M d, Y', strtotime($a['created_at'])) ?>
            </div>
            <?php if (!empty($a['booking_hours'])): ?>
            <div class="booking-info-strip">
              <span class="binfo-item">⏱ <strong><?= $a['booking_hours'] ?>h</strong> booked</span>
              <span class="binfo-item">💰 <strong>$<?= number_format($a['booking_rate'],2) ?>/hr</strong></span>
              <span class="binfo-item">💳 Total: <strong>$<?= number_format($a['booking_total'],2) ?></strong></span>
              <?php if ($a['card_last4']): ?>
                <span class="binfo-item">Card: <strong>*<?= htmlspecialchars($a['card_last4']) ?></strong></span>
              <?php endif; ?>
              <?php if ($a['booking_ends_at'] && $a['booking_status']==='confirmed'): ?>
                <span class="binfo-item" style="color:var(--accent)">
                  ⏰ Ends: <strong><?= date('M d H:i', strtotime($a['booking_ends_at'])) ?></strong>
                </span>
              <?php elseif ($a['booking_status']==='expired'): ?>
                <span class="binfo-item" style="color:var(--green)">✅ Care complete</span>
              <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($a['admin_note'])): ?>
              <div class="info-item" style="color:var(--accent)"><strong>Your note:</strong> <?= htmlspecialchars($a['admin_note']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Action form — only show for awaiting -->
          <?php if ($ss === 'awaiting'): ?>
            <form method="POST" class="review-action-form">
              <input type="hidden" name="animal_id" value="<?= $a['id'] ?>">
              <div class="field">
                <label>Note to customer <small>(optional — shown if rejected)</small></label>
                <input type="text" name="admin_note" placeholder="e.g. Missing vaccination records, please resubmit…">
              </div>
              <div class="review-btns">
                <button type="submit" name="action" value="approve" class="btn btn-approve">✅ Approve</button>
                <button type="submit" name="action" value="reject"  class="btn btn-reject"  onclick="return confirm('Reject this submission?')">❌ Reject</button>
              </div>
            </form>
          <?php elseif ($ss === 'approved'): ?>
            <div class="review-done approved-done">✅ You approved this — it is now live on the site.</div>
          <?php else: ?>
            <div class="review-done rejected-done">❌ You rejected this submission.</div>
          <?php endif; ?>

        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
