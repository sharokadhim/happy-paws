<?php
require_once 'config.php';
requireAdmin();

$error   = '';
$user_id = $_SESSION['user_id'];
$allSpecies = ['Dog','Cat','Bird','Parrot','Eagle','Other'];

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
    $sound_file  = trim($_POST['sound_file']  ?? '');

    if (!$name || !$species || !$age || !$gender || !$size)
        $error = "Please fill all required fields.";
    else {
        // Verify user actually exists in DB before inserting
        $check = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $check->bind_param("i", $user_id);
        $check->execute();
        $checkResult = $check->get_result();
        $check->close();

        if ($checkResult->num_rows === 0) {
            // User not found in DB — session is stale, force re-login
            session_unset(); session_destroy();
            header("Location: index.php?error=session_expired"); exit();
        }

        $stmt = $conn->prepare("INSERT INTO animals (user_id,name,species,breed,age,gender,size,energy,good_with,description,sound_file) VALUES(?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("issssssssss", $user_id,$name,$species,$breed,$age,$gender,$size,$energy,$good_with,$description,$sound_file);
        if ($stmt->execute()) { header("Location: dashboard.php?success=added"); exit(); }
        else $error = "Failed to add animal. (DB error: " . htmlspecialchars($conn->error) . ")";
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Add Animal — Happy Paws</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="dash-body">
<aside class="sidebar">
  <div class="sidebar-brand">🐾 <span>HappyPaws</span></div>
  <nav class="sidebar-nav">
    <a href="dashboard.php" class="nav-item">🏠 Dashboard</a>
    <a href="add_animal.php" class="nav-item active">➕ Add Animal</a>
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
      <h1 class="dash-title">Add New Animal</h1>
      <p class="dash-sub">Register an animal for adoption</p>
    </div>
    <a href="dashboard.php" class="btn btn-ghost">← Back</a>
  </div>

  <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="form-card">
    <form method="POST">
      <div class="form-grid">

        <!-- Left -->
        <div class="form-col">
          <div class="field">
            <label>Animal Name *</label>
            <input type="text" name="name" placeholder="e.g. Luna"
                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
          </div>
          <div class="field">
            <label>Species *</label>
            <select name="species" required>
              <option value="">Select species…</option>
              <?php foreach ($allSpecies as $s): ?>
                <option value="<?= $s ?>" <?= ($_POST['species']??'')===$s?'selected':'' ?>><?= speciesEmoji($s) ?> <?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>Breed</label>
            <input type="text" name="breed" placeholder="e.g. Golden Retriever"
                   value="<?= htmlspecialchars($_POST['breed'] ?? '') ?>">
          </div>
          <div class="field">
            <label>Description</label>
            <textarea name="description" rows="5" placeholder="Personality, history, special needs…"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
          </div>
          <div class="field">
            <label>Good With <small>(comma-separated: kids, dogs, cats, adults)</small></label>
            <input type="text" name="good_with" placeholder="kids, dogs, adults"
                   value="<?= htmlspecialchars($_POST['good_with'] ?? '') ?>">
          </div>
        </div>

        <!-- Right -->
        <div class="form-col">
          <div class="field">
            <label>Age *</label>
            <input type="text" name="age" placeholder="e.g. 2 years"
                   value="<?= htmlspecialchars($_POST['age'] ?? '') ?>" required>
          </div>
          <div class="field-row">
            <div class="field">
              <label>Gender *</label>
              <select name="gender" required>
                <option value="">Select…</option>
                <option value="Male"   <?= ($_POST['gender']??'')==='Male'  ?'selected':'' ?>>♂ Male</option>
                <option value="Female" <?= ($_POST['gender']??'')==='Female'?'selected':'' ?>>♀ Female</option>
              </select>
            </div>
            <div class="field">
              <label>Size *</label>
              <select name="size" required>
                <option value="">Select…</option>
                <?php foreach (['Small','Medium','Large'] as $sz): ?>
                  <option value="<?= $sz ?>" <?= ($_POST['size']??'')===$sz?'selected':'' ?>><?= $sz ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="field">
            <label>Energy Level</label>
            <select name="energy">
              <?php foreach (['Low','Medium','High'] as $e): ?>
                <option value="<?= $e ?>" <?= ($_POST['energy']??'Medium')===$e?'selected':'' ?>><?= $e ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- ── SOUND FILE FIELD ── -->
          <div class="field sound-field">
            <label>🔊 Custom Sound File</label>
            <input type="text" name="sound_file" placeholder="e.g. luna_bark.mp3 (leave empty for built-in)"
                   value="<?= htmlspecialchars($_POST['sound_file'] ?? '') ?>">
            <div class="field-hint">
              <strong>How to use your own sound:</strong><br>
              1. Drop your <code>.mp3</code> or <code>.ogg</code> file into the <code>sounds/</code> folder.<br>
              2. Type the filename here, e.g. <code>my_dog_bark.mp3</code><br>
              3. Leave empty to use the built-in synthesized sound.
            </div>
          </div>

          <div class="info-box">
            <h4>🐾 Species → Built-in Sound</h4>
            <div class="sound-map">
              <span>🐕 Dog → Bark</span><span>🐱 Cat → Meow</span>
              <span>🐇 Rabbit → Squeak</span><span>🐹 Hamster → Squeak</span>
              <span>🐦 Bird → Tweet</span><span>🦜 Parrot → Squawk</span>
              <span>🦅 Eagle → Screech</span>
            </div>
          </div>
        </div>
      </div>

      <div class="form-footer-btns">
        <button type="submit" class="btn btn-glow">Add Animal</button>
        <a href="dashboard.php" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
</main>
</body>
</html>
