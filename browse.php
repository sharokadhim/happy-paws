<?php
require_once 'config.php';
requireLogin();
if (isAdmin()) { header("Location: dashboard.php"); exit(); }

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Ensure columns exist
$conn->query("ALTER TABLE animals ADD COLUMN IF NOT EXISTS submission_status ENUM('approved','awaiting','rejected') NOT NULL DEFAULT 'approved' AFTER status");
$conn->query("ALTER TABLE animals ADD COLUMN IF NOT EXISTS hourly_rate DECIMAL(6,2) NOT NULL DEFAULT 5.00");

// Species filter
$filterSpecies = $_GET['species'] ?? 'all';

// ONLY available animals
$where  = "WHERE status = 'available'";
$types  = "";
$params = [];
if ($filterSpecies !== 'all') {
    $where .= " AND species=?";
    $types  = "s";
    $params[] = $filterSpecies;
}

if ($params) {
    $stmt = $conn->prepare("SELECT * FROM animals $where ORDER BY created_at DESC");
    $stmt->bind_param($types, ...$params);
} else {
    $stmt = $conn->prepare("SELECT * FROM animals $where ORDER BY created_at DESC");
}
$stmt->execute();
$animals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$allSpecies = ['Dog','Cat','Bird','Parrot','Eagle','Other'];
$msg = isset($_GET['applied']) ? "Application submitted! We'll be in touch. 🐾" : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Animals — Happy Paws</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="dash-body">

<aside class="sidebar">
  <div class="sidebar-brand">🐾 <span>HappyPaws</span></div>
  <nav class="sidebar-nav">
    <a href="browse.php"         class="nav-item active">🐾 Animals In Our Care</a>
    <a href="submit_animal.php"  class="nav-item">➕ Submit Animal</a>
    <a href="my_submissions.php" class="nav-item">📦 My Submissions</a>
<a href="logout.php"         class="nav-item nav-logout">🚪 Logout</a>
  </nav>
  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($full_name,0,1)) ?></div>
    <div>
      <div class="user-name"><?= htmlspecialchars($full_name) ?></div>
      <div class="user-role">Customer</div>
    </div>
  </div>
</aside>

<main class="dash-main">
  <div class="dash-topbar">
    <div>
      <h1 class="dash-title">Animals In Our Care</h1>
      <p class="dash-sub">Animals currently being looked after by our organization · <?= count($animals) ?> listed</p>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- Species filter -->
  <div class="filter-bar">
    <div class="filter-group">
      <span class="filter-label">Species:</span>
      <a href="?species=all" class="filter-chip <?= $filterSpecies==='all'?'active':'' ?>">🐾 All</a>
      <?php foreach ($allSpecies as $sp): ?>
        <a href="?species=<?= $sp ?>"
           class="filter-chip <?= $filterSpecies===$sp?'active':'' ?>">
          <?= speciesEmoji($sp) ?> <?= $sp ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (empty($animals)): ?>
    <div class="empty-state">
      <div class="empty-icon">🐾</div>
      <h2>No animals available right now</h2>
      <p>Check back soon — we update listings regularly!</p>
    </div>
  <?php else: ?>
    <div class="animal-grid">
      <?php foreach ($animals as $a):
        $tags = array_filter(array_map('trim', explode(',', $a['good_with'] ?? '')));
      ?>
      <div class="animal-card"
           data-species="<?= htmlspecialchars($a['species']) ?>">

        <div class="card-glow"></div>

        <div class="card-top">
          <div class="card-emoji"><?= speciesEmoji($a['species']) ?></div>
        </div>

        <div class="card-name"><?= htmlspecialchars($a['name']) ?></div>
        <div class="card-breed">
          <?= htmlspecialchars($a['species']) ?>
          <?= $a['breed'] ? ' · '.htmlspecialchars($a['breed']) : '' ?>
        </div>

        <div class="card-meta">
          <span>🎂 <?= htmlspecialchars($a['age']) ?></span>
          <span><?= $a['gender']==='Male'?'♂':'♀' ?> <?= $a['gender'] ?></span>
          <span>📏 <?= $a['size'] ?></span>
          <span>⚡ <?= $a['energy'] ?></span>
        </div>

        <?php if (!empty($tags)): ?>
        <div class="card-tags">
          <?php foreach ($tags as $t): ?>
            <span class="tag">✓ <?= htmlspecialchars($t) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($a['description']): ?>
          <p class="card-desc"><?= htmlspecialchars($a['description']) ?></p>
        <?php endif; ?>

      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<!-- Sound toast -->
<div class="sound-toast" id="soundToast"></div>
<?php include 'sound_engine.php'; ?>
</body>
</html>
