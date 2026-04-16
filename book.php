<?php
require_once 'config.php';
requireLogin();
if (isAdmin()) { header("Location: dashboard.php"); exit(); }

$user_id   = $_SESSION['user_id'];
$animal_id = (int)($_GET['animal_id'] ?? 0);
$error = $success = '';

// Ensure bookings table exists
$conn->query("CREATE TABLE IF NOT EXISTS `bookings` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `user_id`     INT NOT NULL,
  `animal_id`   INT NOT NULL,
  `hours`       INT NOT NULL DEFAULT 1,
  `hourly_rate` DECIMAL(6,2) NOT NULL,
  `total_cost`  DECIMAL(8,2) NOT NULL,
  `card_last4`  CHAR(4) DEFAULT NULL,
  `card_name`   VARCHAR(100) DEFAULT NULL,
  `status`      ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at`  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Ensure hourly_rate column exists
$conn->query("ALTER TABLE animals ADD COLUMN IF NOT EXISTS hourly_rate DECIMAL(6,2) NOT NULL DEFAULT 5.00");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS booking_ends_at TIMESTAMP NULL DEFAULT NULL");
$conn->query("ALTER TABLE bookings MODIFY COLUMN status ENUM('pending','confirmed','cancelled','expired') NOT NULL DEFAULT 'pending'");

// Fetch animal
$stmt = $conn->prepare("SELECT * FROM animals WHERE id=? AND status='available'");
$stmt->bind_param("i", $animal_id);
$stmt->execute();
$animal = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$animal) {
    header("Location: browse.php"); exit();
}

// Auto-calc rate
$rate = calcHourlyRate($animal['species'], $animal['size'], $animal['age']);

// Individual multipliers for the breakdown display
$speciesMult = ['Dog'=>1.6,'Cat'=>1.2,'Parrot'=>2.0,'Bird'=>1.1,'Eagle'=>3.0,'Other'=>1.0][$animal['species']] ?? 1.0;
$sizeMult    = ['Small'=>1.0,'Medium'=>1.3,'Large'=>1.6][$animal['size']] ?? 1.0;
$ageMult     = 1.0;
if (preg_match('/(\d+)\s*month/i', $animal['age'], $m)) {
    $ageMult = (int)$m[1] <= 6 ? 2.5 : 2.0;
} elseif (preg_match('/(\d+)\s*year/i', $animal['age'], $m)) {
    $y = (int)$m[1];
    if ($y <= 1)      $ageMult = 2.0;
    elseif ($y <= 2)  $ageMult = 1.7;
    elseif ($y <= 4)  $ageMult = 1.3;
    elseif ($y <= 7)  $ageMult = 1.0;
    else              $ageMult = 0.8;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hours     = max(1, min(72, (int)($_POST['hours'] ?? 1)));
    $card_name = trim($_POST['card_name'] ?? '');
    $card_num  = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
    $card_exp  = trim($_POST['card_exp'] ?? '');
    $card_cvv  = trim($_POST['card_cvv'] ?? '');
    $last4     = strlen($card_num) >= 4 ? substr($card_num, -4) : '';

    if (!$card_name || strlen($card_num) < 13 || !$card_exp || !$card_cvv)
        $error = "Please fill in all payment details.";
    elseif (!preg_match('/^\d{2}\/\d{2}$/', $card_exp))
        $error = "Card expiry must be MM/YY format.";
    else {
        $total   = round($rate * $hours, 2);
        $ends_at = date('Y-m-d H:i:s', strtotime("+{$hours} hours"));
        $stmt = $conn->prepare("INSERT INTO bookings (user_id,animal_id,hours,hourly_rate,total_cost,card_last4,card_name,status,booking_ends_at) VALUES(?,?,?,?,?,?,?,'confirmed',?)");
        $stmt->bind_param("iiiddsss", $user_id, $animal_id, $hours, $rate, $total, $last4, $card_name, $ends_at);
        if ($stmt->execute()) {
            $conn->query("UPDATE animals SET status='pending' WHERE id=$animal_id");
            header("Location: my_bookings.php?booked=1"); exit();
        } else {
            $error = "Booking failed. Please try again.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Book <?= htmlspecialchars($animal['name']) ?> — Happy Paws</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="dash-body">
<aside class="sidebar">
  <div class="sidebar-brand">🐾 <span>HappyPaws</span></div>
  <nav class="sidebar-nav">
    <a href="browse.php"          class="nav-item active">🐾 Animals</a>
    <a href="submit_animal.php"   class="nav-item">➕ Submit Animal</a>
    <a href="my_submissions.php"  class="nav-item">📦 My Submissions</a>
    <a href="my_bookings.php"     class="nav-item">💳 My Bookings</a>
    <a href="logout.php"          class="nav-item nav-logout">🚪 Logout</a>
  </nav>
  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($_SESSION['full_name'],0,1)) ?></div>
    <div><div class="user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
    <div class="user-role">Customer</div></div>
  </div>
</aside>

<main class="dash-main">
  <div class="dash-topbar">
    <div>
      <h1 class="dash-title">Reserve <?= htmlspecialchars($animal['name']) ?></h1>
      <p class="dash-sub">Choose your hours and complete payment</p>
    </div>
    <a href="browse.php" class="btn btn-ghost">← Back</a>
  </div>

  <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="booking-layout">

    <!-- Animal summary -->
    <div class="booking-animal-card">
      <div class="booking-emoji"><?= speciesEmoji($animal['species']) ?></div>
      <h2><?= htmlspecialchars($animal['name']) ?></h2>
      <p class="card-breed"><?= htmlspecialchars($animal['species']) ?><?= $animal['breed'] ? ' · '.htmlspecialchars($animal['breed']) : '' ?></p>
      <div class="card-meta" style="justify-content:center;margin-top:12px">
        <span>🎂 <?= htmlspecialchars($animal['age']) ?></span>
        <span>📏 <?= $animal['size'] ?></span>
        <span>⚡ <?= $animal['energy'] ?></span>
      </div>
      <div class="rate-display">
        <div class="rate-big">$<?= number_format($rate, 2) ?></div>
        <div class="rate-sub">per hour</div>
      </div>
      <div class="rate-breakdown">
        <div class="breakdown-row"><span>Base rate</span><span>$5.00</span></div>
        <div class="breakdown-row"><span>Species (<?= $animal['species'] ?>)</span><span>×<?= $speciesMult ?></span></div>
        <div class="breakdown-row"><span>Size (<?= $animal['size'] ?>)</span><span>×<?= $sizeMult ?></span></div>
        <div class="breakdown-row"><span>Age (<?= htmlspecialchars($animal['age']) ?>)</span><span>×<?= $ageMult ?></span></div>
        <div class="breakdown-row"><span>Rate/hr</span><span>$<?= number_format($rate, 2) ?></span></div>
      </div>
    </div>

    <!-- Booking form -->
    <div class="form-card booking-form-card">
      <form method="POST" id="bookForm">

        <h3 class="booking-section-title">⏱ Select Hours</h3>
        <div class="hours-selector">
          <?php foreach ([1,2,3,6,12,24,48] as $h): ?>
            <label class="hour-btn">
              <input type="radio" name="hours_radio" value="<?= $h ?>" <?= $h===1?'checked':'' ?> onchange="updateTotal(parseInt(this.value))">
              <span><?= $h ?>h</span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="field" style="margin-top:12px">
          <label>Or enter custom hours (1–72)</label>
          <input type="number" id="customHours" min="1" max="72" placeholder="e.g. 5"
                 oninput="setCustomHours(this.value)">
        </div>

        <div class="cost-summary" id="costSummary">
          <div class="cost-row"><span>Rate</span><span>$<?= number_format($rate,2) ?>/hr</span></div>
          <div class="cost-row"><span>Hours</span><span id="hoursDisplay">1</span></div>
          <div class="cost-row cost-total"><span>Total</span><span id="totalDisplay">$<?= number_format($rate,2) ?></span></div>
        </div>

        <h3 class="booking-section-title" style="margin-top:24px">💳 Payment Details</h3>
        <p class="booking-note">This is a demo — no real charge will be made.</p>

        <div class="field">
          <label>Cardholder Name</label>
          <input type="text" name="card_name" placeholder="Jane Smith" required>
        </div>
        <div class="field">
          <label>Card Number</label>
          <input type="text" name="card_number" placeholder="1234 5678 9012 3456"
                 maxlength="19" oninput="formatCard(this)" required>
        </div>
        <div class="field-row">
          <div class="field">
            <label>Expiry (MM/YY)</label>
            <input type="text" name="card_exp" placeholder="12/26" maxlength="5" required>
          </div>
          <div class="field">
            <label>CVV</label>
            <input type="text" name="card_cvv" placeholder="123" maxlength="4" required>
          </div>
        </div>

        <!-- Hidden hours field updated by JS -->
        <input type="hidden" name="hours" id="hoursInput" value="1">

        <button type="submit" class="btn btn-glow btn-full" style="margin-top:16px">
          Confirm Booking & Pay →
        </button>
      </form>
    </div>
  </div>
</main>

<script>
const rate = <?= $rate ?>;
let selectedHours = 1;

function updateTotal(h) {
  if (h) selectedHours = parseInt(h);
  document.getElementById('hoursInput').value   = selectedHours;
  document.getElementById('hoursDisplay').textContent = selectedHours + 'h';
  document.getElementById('totalDisplay').textContent = '$' + (rate * selectedHours).toFixed(2);
  document.getElementById('customHours').value = '';
}

function setCustomHours(val) {
  val = Math.max(1, Math.min(72, parseInt(val) || 1));
  selectedHours = val;
  document.getElementById('hoursInput').value   = val;
  document.getElementById('hoursDisplay').textContent = val + 'h';
  document.getElementById('totalDisplay').textContent = '$' + (rate * val).toFixed(2);
  document.querySelectorAll('input[name="hours_radio"]').forEach(r => r.checked = false);
}

function formatCard(input) {
  let v = input.value.replace(/\D/g,'').substring(0,16);
  input.value = v.replace(/(.{4})/g,'$1 ').trim();
}
</script>
</body>
</html>
