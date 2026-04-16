<?php
require_once 'config.php';
if (isset($_SESSION['user_id']) && isCustomer()) { header("Location: browse.php"); exit(); }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please fill in both fields.";
    } else {
        // Check if role column exists; if not, add it automatically
        $colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
        if ($colCheck->num_rows === 0) {
            $conn->query("ALTER TABLE users ADD COLUMN role ENUM('admin','customer') NOT NULL DEFAULT 'customer' AFTER password");
            $conn->query("UPDATE users SET role='admin' WHERE username='admin'");
        }

        $stmt = $conn->prepare("SELECT id,username,password,full_name,role FROM users WHERE (username=? OR email=?) AND role='customer'");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && password_verify($password, $row['password'])) {
            $_SESSION['user_id']   = $row['id'];
            $_SESSION['username']  = $row['username'];
            $_SESSION['full_name'] = $row['full_name'];
            $_SESSION['role']      = 'customer';
            header("Location: browse.php"); exit();
        }
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Customer Login — Happy Paws</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">
<div class="auth-bg">
  <div class="auth-particles" id="particles"></div>
  <div class="auth-card">
    <div class="auth-brand">
      <span class="brand-paw">🏠</span>
      <h1>Welcome <span>Back</span></h1>
      <p>Find your perfect companion</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['registered'])): ?>
      <div class="alert alert-success">Account created! You can now log in. 🐾</div>
    <?php endif; ?>

    <form method="POST">
      <div class="field">
        <label>Username or Email</label>
        <input type="text" name="username" placeholder="Enter username or email…"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-glow btn-full">Login →</button>
    </form>

    <div class="auth-footer">
      No account? <a href="register.php">Register here</a>
      &nbsp;·&nbsp; <a href="index.php">← Back</a>
    </div>
  </div>
</div>
<script>
const c=document.getElementById('particles');
const emojis=['🐾','🐕','🐱','🦜','🐦','🦅','🏠'];
for(let i=0;i<18;i++){const el=document.createElement('div');el.className='particle';el.textContent=emojis[Math.floor(Math.random()*emojis.length)];el.style.cssText=`left:${Math.random()*100}%;top:${Math.random()*100}%;animation-delay:${Math.random()*8}s;animation-duration:${6+Math.random()*8}s;font-size:${1+Math.random()*1.5}rem;opacity:${0.08+Math.random()*0.2}`;c.appendChild(el);}
</script>
</body>
</html>
