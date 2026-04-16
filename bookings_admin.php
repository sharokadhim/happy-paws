<?php
require_once 'config.php';
requireAdmin();

$conn->query("CREATE TABLE IF NOT EXISTS `bookings` (
  `id` INT NOT NULL AUTO_INCREMENT, `user_id` INT NOT NULL, `animal_id` INT NOT NULL,
  `hours` INT NOT NULL DEFAULT 1, `hourly_rate` DECIMAL(6,2) NOT NULL,
  `total_cost` DECIMAL(8,2) NOT NULL, `card_last4` CHAR(4) DEFAULT NULL,
  `card_name` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$stmt = $conn->prepare("SELECT b.*, a.name AS animal_name, a.species, a.breed, u.full_name AS customer_name, u.email AS customer_email FROM bookings b JOIN animals a ON b.animal_id=a.id JOIN users u ON b.user_id=u.id ORDER BY b.created_at DESC");
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalRevenue = array_sum(array_column($bookings, 'total_cost'));
$confirmed    = count(array_filter($bookings, fn($b) => $b['status']==='confirmed'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bookings — Happy Paws Admin</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="dash-body">
<aside class="sidebar">
  <div class="sidebar-brand">🐾 <span>HappyPaws</span></div>
  <nav class="sidebar-nav">
    <a href="dashboard.php"          class="nav-item">🏠 Dashboard</a>
    <a href="review_submissions.php" class="nav-item">📦 Submissions</a>
    <a href="bookings_admin.php"     class="nav-item active">💳 Bookings</a>
    <a href="logout.php"             class="nav-item nav-logout">🚪 Logout</a>
  </nav>
  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($_SESSION['full_name'],0,1)) ?></div>
    <div><div class="user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
    <div class="user-role">Admin</div></div>
  </div>
</aside>
<main class="dash-main">
  <div class="dash-topbar">
    <div><h1 class="dash-title">All Bookings</h1>
    <p class="dash-sub">Customer reservations and revenue</p></div>
  </div>

  <div class="stats-row">
    <div class="stat-card"><div class="stat-icon">💳</div><div class="stat-num"><?= count($bookings) ?></div><div class="stat-label">Total Bookings</div></div>
    <div class="stat-card available"><div class="stat-icon">✅</div><div class="stat-num"><?= $confirmed ?></div><div class="stat-label">Confirmed</div></div>
    <div class="stat-card adopted"><div class="stat-icon">💰</div><div class="stat-num">$<?= number_format($totalRevenue,2) ?></div><div class="stat-label">Total Revenue</div></div>
  </div>

  <?php if (empty($bookings)): ?>
    <div class="empty-state"><div class="empty-icon">💳</div><h2>No bookings yet</h2><p>Customer bookings will appear here.</p></div>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>Customer</th><th>Animal</th><th>Hours</th><th>Rate</th><th>Total</th><th>Card</th><th>Status</th><th>Date</th></tr>
        </thead>
        <tbody>
          <?php foreach ($bookings as $b): ?>
          <tr>
            <td><strong><?= htmlspecialchars($b['customer_name']) ?></strong><br><small><?= htmlspecialchars($b['customer_email']) ?></small></td>
            <td><span class="table-emoji"><?= speciesEmoji($b['species']) ?></span> <?= htmlspecialchars($b['animal_name']) ?></td>
            <td><?= $b['hours'] ?>h</td>
            <td>$<?= number_format($b['hourly_rate'],2) ?>/hr</td>
            <td><strong style="color:var(--accent)">$<?= number_format($b['total_cost'],2) ?></strong></td>
            <td>*<?= htmlspecialchars($b['card_last4'] ?? '----') ?></td>
            <td><span class="badge badge-<?= $b['status']==='confirmed'?'available':'pending' ?>"><?= ucfirst($b['status']) ?></span></td>
            <td><?= date('M d, Y', strtotime($b['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
