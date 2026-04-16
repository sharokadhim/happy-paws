<?php
require_once 'config.php';

// Already logged in → redirect to right place
if (isset($_SESSION['user_id'])) {
    header("Location: " . (isAdmin() ? "dashboard.php" : "browse.php"));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Happy Paws — Welcome</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">
<div class="auth-bg">
  <div class="auth-particles" id="particles"></div>

  <div class="landing-card">
    <div class="auth-brand">
      <span class="brand-paw">🐾</span>
      <h1>Happy<span>Paws</span></h1>
      <p>Animal Adoption Portal</p>
    </div>

    <p class="landing-intro">Who are you today?</p>

    <div class="role-cards">

      <!-- Admin -->
      <a href="admin_login.php" class="role-card role-admin">
        <div class="role-icon">🛡️</div>
        <div class="role-title">Admin</div>
        <div class="role-desc">Manage animals, review and approve adoption applications</div>
        <div class="role-btn">Admin Login →</div>
      </a>

      <!-- Customer -->
      <a href="customer_login.php" class="role-card role-customer">
        <div class="role-icon">🏠</div>
        <div class="role-title">Customer</div>
        <div class="role-desc">Browse available animals and apply to adopt your perfect companion</div>
        <div class="role-btn">Login / Register →</div>
      </a>

    </div>
  </div>
</div>

<script>
const c = document.getElementById('particles');
const emojis = ['🐾','🐕','🐱','🦜','🐦','🦅'];
for (let i = 0; i < 20; i++) {
  const el = document.createElement('div'); el.className = 'particle';
  el.textContent = emojis[Math.floor(Math.random()*emojis.length)];
  el.style.cssText = `left:${Math.random()*100}%;top:${Math.random()*100}%;animation-delay:${Math.random()*8}s;animation-duration:${6+Math.random()*8}s;font-size:${1+Math.random()*1.8}rem;opacity:${0.08+Math.random()*0.2}`;
  c.appendChild(el);
}
</script>
</body>
</html>
