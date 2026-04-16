<?php
require_once 'config.php';
requireAdmin();

$user_id = $_SESSION['user_id'];

// Update status
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['app_id'],$_POST['new_status'])) {
    $aid = (int)$_POST['app_id'];
    $ns  = $_POST['new_status'];
    if (in_array($ns,['pending','approved','rejected'])) {
        $upd = $conn->prepare("UPDATE adoption_applications SET status=? WHERE id=?");
        $upd->bind_param("si",$ns,$aid); $upd->execute(); $upd->close();
        // If approved, mark animal adopted; if rejected, mark available
        if ($ns==='approved') {
            $anm = $conn->prepare("UPDATE animals a JOIN adoption_applications aa ON aa.animal_id=a.id SET a.status='adopted' WHERE aa.id=?");
            $anm->bind_param("i",$aid); $anm->execute(); $anm->close();
        } elseif ($ns==='rejected') {
            $anm = $conn->prepare("UPDATE animals a JOIN adoption_applications aa ON aa.animal_id=a.id SET a.status='available' WHERE aa.id=?");
            $anm->bind_param("i",$aid); $anm->execute(); $anm->close();
        }
        header("Location: applications.php?success=1"); exit();
    }
}

// Filter
$filterStatus = $_GET['status'] ?? 'all';
$where = "WHERE a.user_id=?";
$types = "i"; $params = [$user_id];
if ($filterStatus !== 'all') { $where .= " AND aa.status=?"; $types .= "s"; $params[] = $filterStatus; }

$stmt = $conn->prepare("SELECT aa.*, a.name AS animal_name, a.species FROM adoption_applications aa JOIN animals a ON aa.animal_id=a.id $where ORDER BY aa.created_at DESC");
$stmt->bind_param($types,...$params);
$stmt->execute();
$apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$msg = isset($_GET['success']) ? 'Application updated!' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Applications — Happy Paws</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="dash-body">
<aside class="sidebar">
  <div class="sidebar-brand">🐾 <span>HappyPaws</span></div>
  <nav class="sidebar-nav">
    <a href="dashboard.php" class="nav-item">🏠 Dashboard</a>
    <a href="add_animal.php" class="nav-item">➕ Add Animal</a>
    <a href="applications.php" class="nav-item active">📋 Applications</a>
    <a href="logout.php" class="nav-item nav-logout">🚪 Logout</a>
  </nav>
  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($_SESSION['full_name'],0,1)) ?></div>
    <div>
      <div class="user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
      <div class="user-role">Shelter Admin</div>
    </div>
  </div>
</aside>
<main class="dash-main">
  <div class="dash-topbar">
    <div>
      <h1 class="dash-title">Adoption Applications</h1>
      <p class="dash-sub">Review and manage incoming requests</p>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <div class="filter-bar">
    <div class="filter-group">
      <span class="filter-label">Filter:</span>
      <?php foreach (['all','pending','approved','rejected'] as $s): ?>
        <a href="?status=<?= $s ?>" class="filter-chip <?= $filterStatus===$s?'active':'' ?>"><?= ucfirst($s) ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (empty($apps)): ?>
    <div class="empty-state">
      <div class="empty-icon">📋</div>
      <h2>No applications found</h2>
      <p>Applications submitted via the Adopt button will appear here.</p>
    </div>
  <?php else: ?>
    <div class="animal-grid">
      <?php foreach ($apps as $app): ?>
        <div class="animal-card app-card">
          <div class="card-top">
            <div class="card-emoji"><?= speciesEmoji($app['species']) ?></div>
            <span class="badge badge-<?= $app['status'] ?>"><?= ucfirst($app['status']) ?></span>
          </div>
          <div class="card-name"><?= htmlspecialchars($app['applicant_name']) ?></div>
          <div class="card-breed">Applying for: <?= htmlspecialchars($app['animal_name']) ?></div>
          <div class="plan-info">
            <div class="info-item"><strong>Email:</strong> <?= htmlspecialchars($app['applicant_email']) ?></div>
            <?php if ($app['applicant_phone']): ?>
              <div class="info-item"><strong>Phone:</strong> <?= htmlspecialchars($app['applicant_phone']) ?></div>
            <?php endif; ?>
            <?php if ($app['housing_type']): ?>
              <div class="info-item"><strong>Housing:</strong> <?= htmlspecialchars($app['housing_type']) ?></div>
            <?php endif; ?>
            <?php if ($app['has_children']): ?>
              <div class="info-item"><strong>Children:</strong> <?= htmlspecialchars($app['has_children']) ?></div>
            <?php endif; ?>
            <?php if ($app['experience']): ?>
              <div class="info-item"><strong>Experience:</strong> <?= htmlspecialchars($app['experience']) ?></div>
            <?php endif; ?>
            <div class="info-item"><strong>Applied:</strong> <?= date('M d, Y', strtotime($app['created_at'])) ?></div>
          </div>
          <?php if ($app['reason']): ?>
            <p class="card-desc">"<?= htmlspecialchars($app['reason']) ?>"</p>
          <?php endif; ?>
          <?php if ($app['status']==='pending'): ?>
            <div class="card-actions">
              <form method="POST" style="display:contents">
                <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                <button name="new_status" value="approved" class="btn-action btn-edit">✓ Approve</button>
                <button name="new_status" value="rejected" class="btn-action btn-delete">✗ Reject</button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
