<?php
require_once 'config.php';
if (isset($_SESSION['user_id'])) { header("Location: browse.php"); exit(); }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username']  ?? '');
    $email     = trim($_POST['email']     ?? '');
    $pass      = $_POST['password']        ?? '';
    $pass2     = $_POST['confirm_password'] ?? '';

    if (!$full_name || !$username || !$email || !$pass)
        $error = "All fields are required.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $error = "Invalid email format.";
    elseif (strlen($pass) < 6)
        $error = "Password must be at least 6 characters.";
    elseif ($pass !== $pass2)
        $error = "Passwords do not match.";
    else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username=? OR email=?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "Username or email already taken.";
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            // Ensure role column exists before inserting
            $colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
            if ($colCheck->num_rows === 0) {
                $conn->query("ALTER TABLE users ADD COLUMN role ENUM('admin','customer') NOT NULL DEFAULT 'customer' AFTER password");
                $conn->query("UPDATE users SET role='admin' WHERE username='admin'");
            }
            // Always registers as customer — admin accounts are created manually
            $ins = $conn->prepare("INSERT INTO users (full_name,username,email,password,role) VALUES(?,?,?,?,'customer')");
            $ins->bind_param("ssss", $full_name, $username, $email, $hash);
            if ($ins->execute()) {
                header("Location: customer_login.php?registered=1"); exit();
            }
            $error = "Registration failed. Please try again.";
            $ins->close();
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Register — Happy Paws</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">
<div class="auth-bg">
  <div class="auth-particles" id="particles"></div>
  <div class="auth-card auth-card-wide">
    <div class="auth-brand">
      <span class="brand-paw">🐾</span>
      <h1>Create <span>Account</span></h1>
      <p>Join Happy Paws and find your companion</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="field-row">
        <div class="field">
          <label>Full Name</label>
          <input type="text" name="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" placeholder="Jane Smith" required>
        </div>
        <div class="field">
          <label>Username</label>
          <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="janesmith" required>
        </div>
      </div>
      <div class="field">
        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="you@email.com" required>
      </div>
      <div class="field-row">
        <div class="field">
          <label>Password</label>
          <input type="password" name="password" placeholder="Min 6 characters" required>
        </div>
        <div class="field">
          <label>Confirm Password</label>
          <input type="password" name="confirm_password" placeholder="Repeat password" required>
        </div>
      </div>
      <button type="submit" class="btn btn-glow btn-full">Create Account →</button>
    </form>

    <div class="auth-footer">
      Already have an account? <a href="customer_login.php">Login here</a>
      &nbsp;·&nbsp; <a href="index.php">← Back</a>
    </div>
  </div>
</div>
<script>
const c=document.getElementById('particles');
const emojis=['🐾','🐕','🐱','🦜','🐦','🦅'];
for(let i=0;i<18;i++){const el=document.createElement('div');el.className='particle';el.textContent=emojis[Math.floor(Math.random()*emojis.length)];el.style.cssText=`left:${Math.random()*100}%;top:${Math.random()*100}%;animation-delay:${Math.random()*8}s;animation-duration:${6+Math.random()*8}s;font-size:${1+Math.random()*1.5}rem;opacity:${0.08+Math.random()*0.2}`;c.appendChild(el);}
</script>
</body>
</html>
