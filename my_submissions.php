<?php
require_once 'config.php';
requireLogin();
if (isAdmin()) { header("Location: dashboard.php"); exit(); }

$user_id = $_SESSION['user_id'];

// Ensure columns exist
$conn->query("ALTER TABLE animals ADD COLUMN IF NOT EXISTS submission_status ENUM('approved','awaiting','rejected') NOT NULL DEFAULT 'approved' AFTER status");
$conn->query("ALTER TABLE animals ADD COLUMN IF NOT EXISTS admin_note VARCHAR(255) DEFAULT NULL AFTER submission_status");
$conn->query("ALTER TABLE animals ADD COLUMN IF NOT EXISTS submitted_by INT DEFAULT NULL AFTER admin_note");

$stmt = $conn->prepare("SELECT * FROM animals WHERE submitted_by = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$msg = isset($_GET['submitted']) ? "Animal submitted! Awaiting admin review. 🐾" : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Submissions — Happy Paws</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="dash-body">
<aside class="sidebar">
  <div class="sidebar-brand">🐾 <span>HappyPaws</span></div>
  <nav class="sidebar-nav">
    <a href="browse.php"          class="nav-item">🐾 Animals</a>
    <a href="submit_animal.php"   class="nav-item">➕ Submit Animal</a>
    <a href="my_submissions.php"  class="nav-item active">📦 My Submissions</a>
    <a href="my_bookings.php"     class="nav-item">💳 My Bookings</a>
    <a href="logout.php" class="nav-item nav-logout">🚪 Logout</a>
  </nav>
  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($_SESSION['full_name'],0,1)) ?></div>
    <div>
      <div class="user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
      <div class="user-role">Customer</div>
    </div>
  </div>
</aside>

<main class="dash-main">
  <div class="dash-topbar">
    <div>
      <h1 class="dash-title">My Submissions</h1>
      <p class="dash-sub">Track the review status of animals you submitted</p>
    </div>
    <a href="submit_animal.php" class="btn btn-glow">+ Submit New Animal</a>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <?php if (empty($submissions)): ?>
    <div class="empty-state">
      <div class="empty-icon">📦</div>
      <h2>No submissions yet</h2>
      <p>Submit an animal and track its review status here.</p>
      <a href="submit_animal.php" class="btn btn-glow">Submit an Animal</a>
    </div>
  <?php else: ?>
    <div class="animal-grid">
      <?php foreach ($submissions as $a):
        $ss = $a['submission_status'] ?? 'awaiting';
      ?>
        <div class="animal-card submission-card-<?= $ss ?>">
          <div class="card-glow"></div>
          <div class="card-top">
            <div class="card-emoji"><?= speciesEmoji($a['species']) ?></div>
            <!-- Submission status badge -->
            <?php if ($ss === 'approved'): ?>
              <span class="badge badge-available">✅ Approved</span>
            <?php elseif ($ss === 'rejected'): ?>
              <span class="badge badge-rejected">❌ Rejected</span>
            <?php else: ?>
              <span class="badge badge-awaiting">⏳ Awaiting Review</span>
            <?php endif; ?>
          </div>

          <div class="card-name"><?= htmlspecialchars($a['name']) ?></div>
          <div class="card-breed"><?= htmlspecialchars($a['species']) ?><?= $a['breed'] ? ' · '.htmlspecialchars($a['breed']) : '' ?></div>

          <div class="card-meta">
            <span>🎂 <?= htmlspecialchars($a['age']) ?></span>
            <span><?= $a['gender']==='Male'?'♂':'♀' ?> <?= $a['gender'] ?></span>
            <span>📏 <?= $a['size'] ?></span>
          </div>

          <!-- Status message -->
          <div class="submission-status-box status-<?= $ss ?>">
            <?php if ($ss === 'approved'): ?>
              <strong>🎉 Your animal is now live on the site!</strong>
              <p>People can browse and apply to adopt <?= htmlspecialchars($a['name']) ?>.</p>
            <?php elseif ($ss === 'rejected'): ?>
              <strong>Your submission was not approved.</strong>
              <?php if (!empty($a['admin_note'])): ?>
                <p><strong>Admin note:</strong> <?= htmlspecialchars($a['admin_note']) ?></p>
              <?php else: ?>
                <p>Please contact us for more information.</p>
              <?php endif; ?>
            <?php else: ?>
              <strong>Under review</strong>
              <p>The admin will review your submission shortly. Check back soon!</p>
            <?php endif; ?>
          </div>

          <div class="info-item" style="margin-top:8px;font-size:12px;color:var(--muted)">
            Submitted: <?= date('M d, Y', strtotime($a['created_at'])) ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
