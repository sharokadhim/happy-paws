<?php
require_once 'config.php';
requireAdmin();

$user_id   = $_SESSION['user_id'];
$error     = '';
$animal_id = (int)($_GET['id'] ?? 0);
$allSpecies = ['Dog','Cat','Bird','Parrot','Eagle','Other'];

$stmt = $conn->prepare("SELECT * FROM animals WHERE id=?");
$stmt->bind_param("i", $animal_id);
$stmt->execute();
$animal = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$animal) { header("Location: dashboard.php"); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']        ?? '');
    $species     = $_POST['species']           ?? '';
    $breed       = trim($_POST['breed']       ?? '');
    $age         = trim($_POST['age']         ?? '');
    $gender      = $_POST['gender']            ?? '';
    $size        = $_POST['size']              ?? '';
    $energy      = $_POST['energy']            ?? '';
    $good_with   = trim($_POST['good_with']   ?? '');
    $description = trim($_POST['description'] ?? '');
    $status      = $_POST['status']            ?? 'available';

    if (!$name || !$species || !$age || !$gender || !$size)
        $error = "Please fill all required fields.";
    else {
        $stmt = $conn->prepare("UPDATE animals SET name=?,species=?,breed=?,age=?,gender=?,size=?,energy=?,good_with=?,description=?,status=? WHERE id=?");
        $stmt->bind_param("ssssssssssi", $name,$species,$breed,$age,$gender,$size,$energy,$good_with,$description,$status,$animal_id);
        if ($stmt->execute()) { header("Location: dashboard.php?success=updated"); exit(); }
        else $error = "Update failed: " . htmlspecialchars($conn->error);
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit Animal — Happy Paws</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="dash-body">
<aside class="sidebar">
  <div class="sidebar-brand">🐾 <span>HappyPaws</span></div>
  <nav class="sidebar-nav">
    <a href="dashboard.php" class="nav-item active">🏠 Dashboard</a>
    <a href="add_animal.php" class="nav-item">➕ Add Animal</a>
    <a href="review_submissions.php" class="nav-item">📦 Submissions</a>
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
      <h1 class="dash-title">Edit — <?= htmlspecialchars($animal['name']) ?></h1>
      <p class="dash-sub">Update this animal's details</p>
    </div>
    <a href="dashboard.php" class="btn btn-ghost">← Back</a>
  </div>

  <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="form-card">
    <form method="POST">
      <div class="form-grid">

        <div class="form-col">
          <div class="field">
            <label>Animal Name *</label>
            <input type="text" name="name" value="<?= htmlspecialchars($animal['name']) ?>" required>
          </div>
          <div class="field">
            <label>Species *</label>
            <select name="species" required>
              <?php foreach ($allSpecies as $s): ?>
                <option value="<?= $s ?>" <?= $animal['species']===$s?'selected':'' ?>><?= speciesEmoji($s) ?> <?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>Breed</label>
            <input type="text" name="breed" value="<?= htmlspecialchars($animal['breed'] ?? '') ?>">
          </div>
          <div class="field">
            <label>Description</label>
            <textarea name="description" rows="5"><?= htmlspecialchars($animal['description'] ?? '') ?></textarea>
          </div>
          <div class="field">
            <label>Good With</label>
            <input type="text" name="good_with" value="<?= htmlspecialchars($animal['good_with'] ?? '') ?>">
          </div>
        </div>

        <div class="form-col">
          <div class="field">
            <label>Age *</label>
            <input type="text" name="age" value="<?= htmlspecialchars($animal['age']) ?>" required>
          </div>
          <div class="field-row">
            <div class="field">
              <label>Gender *</label>
              <select name="gender" required>
                <option value="Male"   <?= $animal['gender']==='Male'  ?'selected':'' ?>>♂ Male</option>
                <option value="Female" <?= $animal['gender']==='Female'?'selected':'' ?>>♀ Female</option>
              </select>
            </div>
            <div class="field">
              <label>Size *</label>
              <select name="size" required>
                <?php foreach (['Small','Medium','Large'] as $sz): ?>
                  <option value="<?= $sz ?>" <?= $animal['size']===$sz?'selected':'' ?>><?= $sz ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="field-row">
            <div class="field">
              <label>Energy</label>
              <select name="energy">
                <?php foreach (['Low','Medium','High'] as $e): ?>
                  <option value="<?= $e ?>" <?= $animal['energy']===$e?'selected':'' ?>><?= $e ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label>Status</label>
              <select name="status">
                <option value="available" <?= $animal['status']==='available'?'selected':'' ?>>✅ Available</option>
                <option value="pending"   <?= $animal['status']==='pending'  ?'selected':'' ?>>⏳ Pending</option>
                <option value="adopted"   <?= $animal['status']==='adopted'  ?'selected':'' ?>>🏠 Adopted</option>
              </select>
            </div>
          </div>

        </div>
      </div>

      <div class="form-footer-btns">
        <button type="submit" class="btn btn-glow">Save Changes</button>
        <a href="dashboard.php" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
</main>
</body>
</html>
