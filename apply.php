<?php
require_once 'config.php';
requireLogin();
if (isAdmin()) { header('Location: dashboard.php'); exit(); }

$animal_id = (int)($_GET['animal_id'] ?? 0);
$user_id = $_SESSION['user_id'];
$error = $success = '';

// Fetch animal
$stmt = $conn->prepare("SELECT * FROM animals WHERE id=? AND status='available'");
$stmt->bind_param("i", $animal_id);
$stmt->execute();
$animal = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$animal) {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center"><h2>Animal not found or no longer available.</h2><a href="dashboard.php">← Back</a></div>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['applicant_name']  ?? '');
    $email    = trim($_POST['applicant_email'] ?? '');
    $phone    = trim($_POST['applicant_phone'] ?? '');
    $housing  = $_POST['housing_type']          ?? '';
    $children = $_POST['has_children']          ?? '';
    $pets     = trim($_POST['other_pets']      ?? '');
    $exp      = $_POST['experience']            ?? '';
    $reason   = trim($_POST['reason']          ?? '');

    if (!$name || !$email) {
        $error = "Name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } else {
        // Use user_id column if it exists, otherwise fall back to email-only insert
        $colCheck = $conn->query("SHOW COLUMNS FROM adoption_applications LIKE 'user_id'");
        if ($colCheck && $colCheck->num_rows > 0) {
            $stmt = $conn->prepare("INSERT INTO adoption_applications (user_id,animal_id,applicant_name,applicant_email,applicant_phone,housing_type,has_children,other_pets,experience,reason) VALUES(?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("iissssssss", $user_id,$animal_id,$name,$email,$phone,$housing,$children,$pets,$exp,$reason);
        } else {
            $stmt = $conn->prepare("INSERT INTO adoption_applications (animal_id,applicant_name,applicant_email,applicant_phone,housing_type,has_children,other_pets,experience,reason) VALUES(?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("issssssss", $animal_id,$name,$email,$phone,$housing,$children,$pets,$exp,$reason);
        }
        if ($stmt->execute()) {
            // Mark animal as pending
            $upd = $conn->prepare("UPDATE animals SET status='pending' WHERE id=?");
            $upd->bind_param("i", $animal_id); $upd->execute(); $upd->close();
            $success = "Application submitted! We'll contact you within 2 business days. 🐾";
        } else {
            $error = "Submission failed. Please try again.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Adopt <?= htmlspecialchars($animal['name']) ?> — Happy Paws</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">
<div class="auth-bg">
  <div class="auth-particles" id="particles"></div>
  <div class="auth-card auth-card-wide">

    <div class="apply-header">
      <div class="apply-animal-emoji"><?= speciesEmoji($animal['species']) ?></div>
      <div>
        <h1>Adopt <?= htmlspecialchars($animal['name']) ?></h1>
        <p><?= htmlspecialchars($animal['species']) ?><?= $animal['breed'] ? ' · '.htmlspecialchars($animal['breed']) : '' ?> · <?= htmlspecialchars($animal['age']) ?></p>
      </div>
    </div>

    <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <div style="text-align:center;margin-top:20px"><a href="dashboard.php" class="btn btn-glow">← Back to Animals</a></div>
    <?php else: ?>

    <form method="POST">
      <div class="field-row">
        <div class="field">
          <label>Your Full Name *</label>
          <input type="text" name="applicant_name" placeholder="Jane Smith" required>
        </div>
        <div class="field">
          <label>Email Address *</label>
          <input type="email" name="applicant_email" placeholder="you@email.com" required>
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label>Phone</label>
          <input type="text" name="applicant_phone" placeholder="+1 555 000 0000">
        </div>
        <div class="field">
          <label>Housing Type</label>
          <select name="housing_type">
            <option value="">Select…</option>
            <option>Apartment</option><option>House with yard</option>
            <option>House without yard</option><option>Condo</option>
          </select>
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label>Children at Home?</label>
          <select name="has_children">
            <option value="">Select…</option>
            <option>No children</option><option>Children under 5</option>
            <option>Children 5–12</option><option>Teenagers</option>
          </select>
        </div>
        <div class="field">
          <label>Pet Experience</label>
          <select name="experience">
            <option value="">Select…</option>
            <option>First-time owner</option><option>Some experience</option><option>Very experienced</option>
          </select>
        </div>
      </div>
      <div class="field">
        <label>Other Pets</label>
        <input type="text" name="other_pets" placeholder="e.g. 1 dog, 2 cats">
      </div>
      <div class="field">
        <label>Why do you want to adopt <?= htmlspecialchars($animal['name']) ?>?</label>
        <textarea name="reason" rows="4" placeholder="Tell us about yourself and why you'd be a great match…"></textarea>
      </div>
      <div class="form-footer-btns">
        <button type="submit" class="btn btn-glow">Submit Application →</button>
        <a href="dashboard.php" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
    <?php endif; ?>

  </div>
</div>
<script>
const c = document.getElementById('particles');
const emojis = ['🐾','<?= speciesEmoji($animal['species']) ?>'];
for (let i=0;i<14;i++){
  const el=document.createElement('div'); el.className='particle';
  el.textContent=emojis[Math.floor(Math.random()*emojis.length)];
  el.style.cssText=`left:${Math.random()*100}%;top:${Math.random()*100}%;animation-delay:${Math.random()*8}s;animation-duration:${6+Math.random()*8}s;font-size:${1+Math.random()*1.5}rem;opacity:${0.1+Math.random()*0.2}`;
  c.appendChild(el);
}
</script>
</body>
</html>
