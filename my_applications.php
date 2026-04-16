<?php
require_once 'config.php';
requireLogin();
if (isAdmin()) { header("Location: dashboard.php"); exit(); }

$user_id = $_SESSION['user_id'];
// Get user email for fallback
$eStmt = $conn->prepare("SELECT email FROM users WHERE id=?");
$eStmt->bind_param("i", $user_id); $eStmt->execute();
$userEmail = $eStmt->get_result()->fetch_assoc()['email'] ?? '';
$eStmt->close();

// Check if user_id column exists
$colCheck = $conn->query("SHOW COLUMNS FROM adoption_applications LIKE 'user_id'");
if ($colCheck && $colCheck->num_rows > 0) {
    $stmt = $conn->prepare("
        SELECT aa.*, a.name AS animal_name, a.species, a.breed, a.age, a.sound_file
        FROM adoption_applications aa
        JOIN animals a ON aa.animal_id = a.id
        WHERE aa.user_id = ?
        ORDER BY aa.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
} else {
    $stmt = $conn->prepare("
        SELECT aa.*, a.name AS animal_name, a.species, a.breed, a.age, a.sound_file
        FROM adoption_applications aa
        JOIN animals a ON aa.animal_id = a.id
        WHERE aa.applicant_email = ?
        ORDER BY aa.created_at DESC
    ");
    $stmt->bind_param("s", $userEmail);
}
$stmt->execute();
$apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Applications — Happy Paws</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="dash-body">
<aside class="sidebar">
  <div class="sidebar-brand">🐾 <span>HappyPaws</span></div>
  <nav class="sidebar-nav">
    <a href="browse.php" class="nav-item">🐾 Browse Animals</a>
    <a href="my_applications.php" class="nav-item active">📋 My Applications</a>
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
      <h1 class="dash-title">My Applications</h1>
      <p class="dash-sub">Track your adoption requests</p>
    </div>
    <a href="browse.php" class="btn btn-ghost">← Browse More</a>
  </div>

  <?php if (empty($apps)): ?>
    <div class="empty-state">
      <div class="empty-icon">📋</div>
      <h2>No applications yet</h2>
      <p>Browse our animals and submit your first adoption application!</p>
      <a href="browse.php" class="btn btn-glow">Browse Animals</a>
    </div>
  <?php else: ?>
    <div class="animal-grid">
      <?php foreach ($apps as $app): ?>
        <div class="animal-card">
          <div class="card-glow"></div>
          <div class="card-top">
            <div class="card-emoji"><?= speciesEmoji($app['species']) ?></div>
            <span class="badge badge-<?= $app['status'] ?>"><?= ucfirst($app['status']) ?></span>
          </div>
          <div class="card-name"><?= htmlspecialchars($app['animal_name']) ?></div>
          <div class="card-breed"><?= htmlspecialchars($app['species']) ?><?= $app['breed'] ? ' · '.htmlspecialchars($app['breed']) : '' ?></div>
          <div class="plan-info">
            <div class="info-item"><strong>Applied:</strong> <?= date('M d, Y', strtotime($app['created_at'])) ?></div>
            <?php if ($app['status'] === 'approved'): ?>
              <div class="info-item" style="color:#22c55e"><strong>🎉 Congratulations! Your application was approved.</strong></div>
            <?php elseif ($app['status'] === 'rejected'): ?>
              <div class="info-item" style="color:#ef4444"><strong>Your application was not approved this time.</strong></div>
            <?php else: ?>
              <div class="info-item" style="color:#eab308"><strong>⏳ Under review — we'll contact you soon.</strong></div>
            <?php endif; ?>
          </div>
          <?php if ($app['reason']): ?>
            <p class="card-desc">"<?= htmlspecialchars($app['reason']) ?>"</p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
