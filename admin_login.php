<?php
require_once 'config.php';
if (isset($_SESSION['user_id']) && isAdmin()) { header("Location: dashboard.php"); exit(); }

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

        $stmt = $conn->prepare("SELECT id,username,password,full_name,role FROM users WHERE (username=? OR email=?) AND role='admin'");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && password_verify($password, $row['password'])) {
            $_SESSION['user_id']   = $row['id'];
            $_SESSION['username']  = $row['username'];
            $_SESSION['full_name'] = $row['full_name'];
            $_SESSION['role']      = 'admin';
            header("Location: dashboard.php"); exit();
        }
        $error = "Invalid admin credentials.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Login — Happy Paws</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">
<div class="auth-bg">
  <div class="auth-particles" id="particles"></div>
  <div class="auth-card">
    <div class="auth-brand">
      <span class="brand-paw">🛡️</span>
      <h1>Admin <span>Login</span></h1>
      <p>Shelter management portal</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="field">
        <label>Admin Username</label>
        <input type="text" name="username" placeholder="admin"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-glow btn-full">Sign In as Admin →</button>
    </form>

    <div class="auth-footer">
      <a href="index.php">← Back to home</a>
    </div>
  </div>
</div>
<script>
const c=document.getElementById('particles');
const emojis=['🛡️','🐾','🐕','🐱','🦜','🦅'];
for(let i=0;i<16;i++){const el=document.createElement('div');el.className='particle';el.textContent=emojis[Math.floor(Math.random()*emojis.length)];el.style.cssText=`left:${Math.random()*100}%;top:${Math.random()*100}%;animation-delay:${Math.random()*8}s;animation-duration:${6+Math.random()*8}s;font-size:${1+Math.random()*1.5}rem;opacity:${0.08+Math.random()*0.18}`;c.appendChild(el);}
</script>
</body>
</html>
