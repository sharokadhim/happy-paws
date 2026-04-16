<?php
require_once 'config.php';
requireLogin();
if (isAdmin()) { header("Location: dashboard.php"); exit(); }

$error   = '';
$user_id = $_SESSION['user_id'];
$allSpecies = ['Dog','Cat','Bird','Parrot','Eagle','Other'];

// Ensure columns
$conn->query("ALTER TABLE animals ADD COLUMN IF NOT EXISTS submission_status ENUM('approved','awaiting','rejected') NOT NULL DEFAULT 'approved' AFTER status");
$conn->query("ALTER TABLE animals ADD COLUMN IF NOT EXISTS admin_note VARCHAR(255) DEFAULT NULL AFTER submission_status");
$conn->query("ALTER TABLE animals ADD COLUMN IF NOT EXISTS submitted_by INT DEFAULT NULL AFTER admin_note");
$conn->query("ALTER TABLE animals ADD COLUMN IF NOT EXISTS hourly_rate DECIMAL(6,2) NOT NULL DEFAULT 5.00");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS booking_ends_at TIMESTAMP NULL DEFAULT NULL");
$conn->query("ALTER TABLE bookings MODIFY COLUMN status ENUM('pending','confirmed','cancelled','expired') NOT NULL DEFAULT 'pending'");

// Ensure bookings table
$conn->query("CREATE TABLE IF NOT EXISTS `bookings` (
  `id` INT NOT NULL AUTO_INCREMENT, `user_id` INT NOT NULL, `animal_id` INT NOT NULL,
  `hours` INT NOT NULL DEFAULT 1, `hourly_rate` DECIMAL(6,2) NOT NULL,
  `total_cost` DECIMAL(8,2) NOT NULL, `card_last4` CHAR(4) DEFAULT NULL,
  `card_name` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']        ?? '');
    $species     = $_POST['species']           ?? '';
    $breed       = trim($_POST['breed']       ?? '');
    $age         = trim($_POST['age']         ?? '');
    $gender      = $_POST['gender']            ?? '';
    $size        = $_POST['size']              ?? '';
    $energy      = $_POST['energy']            ?? 'Medium';
    $good_with   = trim($_POST['good_with']   ?? '');
    $description = trim($_POST['description'] ?? '');
    $hours       = max(1, min(72, (int)($_POST['hours'] ?? 1)));
    $phone       = trim($_POST['phone']       ?? '');
    $address     = trim($_POST['address']     ?? '');
    $adopt_email = trim($_POST['adopt_email'] ?? '');
    $reason      = trim($_POST['reason']      ?? '');
    $card_name   = trim($_POST['card_name']   ?? '');
    $card_num    = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
    $card_exp    = trim($_POST['card_exp']    ?? '');
    $card_cvv    = trim($_POST['card_cvv']    ?? '');
    $last4       = strlen($card_num) >= 4 ? substr($card_num, -4) : '';

    if (!$name || !$species || !$age || !$gender || !$size)
        $error = "Please fill all animal details.";
    elseif (!$phone)
        $error = "Phone number is required.";
    elseif (!$card_name || strlen($card_num) < 13 || !$card_exp || !$card_cvv)
        $error = "Please fill in all payment details.";
    elseif (!preg_match('/^\d{2}\/\d{2}$/', $card_exp))
        $error = "Card expiry must be MM/YY (e.g. 12/26).";
    else {
        $rate  = calcHourlyRate($species, $size, $age);
        $total = round($rate * $hours, 2);

        // 1. Insert animal as awaiting review
        $stmt = $conn->prepare("INSERT INTO animals (user_id, submitted_by, name, species, breed, age, gender, size, energy, good_with, description, hourly_rate, status, submission_status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,'pending','awaiting')");
        $stmt->bind_param("iisssssssssd", $user_id, $user_id, $name, $species, $breed, $age, $gender, $size, $energy, $good_with, $description, $rate);

        if ($stmt->execute()) {
            $animal_id = $conn->insert_id;
            $stmt->close();

            // 2. Insert booking/payment record with end time
            $ends_at = date('Y-m-d H:i:s', strtotime("+{$hours} hours"));
            $bStmt = $conn->prepare("INSERT INTO bookings (user_id, animal_id, hours, hourly_rate, total_cost, card_last4, card_name, status, booking_ends_at) VALUES(?,?,?,?,?,?,?,'confirmed',?)");
            $bStmt->bind_param("iiiddsss", $user_id, $animal_id, $hours, $rate, $total, $last4, $card_name, $ends_at);
            $bStmt->execute();
            $bStmt->close();

            header("Location: my_bookings.php?submitted=1"); exit();
        } else {
            $error = "Submission failed: " . htmlspecialchars($conn->error);
            $stmt->close();
        }
    }
}

// Pricing table for JS — all combinations pre-calculated
$pricingTable = [];
foreach (['Dog','Cat','Bird','Parrot','Eagle','Other'] as $sp) {
    foreach (['Small','Medium','Large'] as $sz) {
        foreach (['6 months','1 year','2 years','4 years','7 years','10 years'] as $ag) {
            $pricingTable[$sp][$sz][$ag] = calcHourlyRate($sp, $sz, $ag);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Submit Animal — Happy Paws</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="dash-body">
<aside class="sidebar">
  <div class="sidebar-brand">🐾 <span>HappyPaws</span></div>
  <nav class="sidebar-nav">
    <a href="browse.php"         class="nav-item">🐾 Animals</a>
    <a href="submit_animal.php"  class="nav-item active">➕ Submit Animal</a>
    <a href="my_submissions.php" class="nav-item">📦 My Submissions</a>
    <a href="my_bookings.php"    class="nav-item">💳 My Bookings</a>
    <a href="logout.php"         class="nav-item nav-logout">🚪 Logout</a>
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
      <h1 class="dash-title">Submit an Animal</h1>
      <p class="dash-sub">Fill in the details, set your hours, and complete payment — admin will review and approve</p>
    </div>
  </div>

  <!-- Info banner -->
  <div class="info-banner">
    <span class="info-banner-icon">ℹ️</span>
    <div>
      <strong>How it works:</strong> You submit animal details + pay for the care hours upfront →
      Admin reviews and approves → Animal goes live for adoption.
      Track everything in <a href="my_bookings.php">My Bookings</a>.
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="submit-layout">

    <!-- ── Left: Animal Details ── -->
    <div class="form-card">
      <form method="POST" id="submitForm">

        <h3 class="booking-section-title">🐾 Animal Information</h3>

        <div class="field">
          <label>Animal Name *</label>
          <input type="text" name="name" placeholder="e.g. Max"
                 value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
        </div>

        <div class="field-row">
          <div class="field">
            <label>Species *</label>
            <select name="species" id="sel_species" required onchange="updatePrice()">
              <option value="">Select…</option>
              <?php foreach ($allSpecies as $s): ?>
                <option value="<?= $s ?>" <?= ($_POST['species']??'')===$s?'selected':'' ?>><?= speciesEmoji($s) ?> <?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>Breed</label>
            <input type="text" name="breed" placeholder="e.g. Labrador"
                   value="<?= htmlspecialchars($_POST['breed'] ?? '') ?>">
          </div>
        </div>

        <div class="field-row">
          <div class="field">
            <label>Age *</label>
            <select name="age" id="sel_age" required onchange="updatePrice()">
              <option value="">Select…</option>
              <option value="6 months"  <?= ($_POST['age']??'')==='6 months' ?'selected':'' ?>>Under 6 months</option>
              <option value="1 year"    <?= ($_POST['age']??'')==='1 year'   ?'selected':'' ?>>1 year</option>
              <option value="2 years"   <?= ($_POST['age']??'')==='2 years'  ?'selected':'' ?>>2 years</option>
              <option value="4 years"   <?= ($_POST['age']??'')==='4 years'  ?'selected':'' ?>>3–4 years</option>
              <option value="7 years"   <?= ($_POST['age']??'')==='7 years'  ?'selected':'' ?>>5–7 years</option>
              <option value="10 years"  <?= ($_POST['age']??'')==='10 years' ?'selected':'' ?>>8+ years</option>
            </select>
          </div>
          <div class="field">
            <label>Gender *</label>
            <select name="gender" required>
              <option value="">Select…</option>
              <option value="Male"   <?= ($_POST['gender']??'')==='Male'  ?'selected':'' ?>>♂ Male</option>
              <option value="Female" <?= ($_POST['gender']??'')==='Female'?'selected':'' ?>>♀ Female</option>
            </select>
          </div>
        </div>

        <div class="field-row">
          <div class="field">
            <label>Size * <small>(affects price)</small></label>
            <select name="size" id="sel_size" required onchange="updatePrice()">
              <option value="">Select…</option>
              <option value="Small"  <?= ($_POST['size']??'')==='Small' ?'selected':'' ?>>Small</option>
              <option value="Medium" <?= ($_POST['size']??'')==='Medium'?'selected':'' ?>>Medium</option>
              <option value="Large"  <?= ($_POST['size']??'')==='Large' ?'selected':'' ?>>Large</option>
            </select>
          </div>
          <div class="field">
            <label>Energy Level</label>
            <select name="energy">
              <?php foreach (['Low','Medium','High'] as $e): ?>
                <option value="<?= $e ?>" <?= ($_POST['energy']??'Medium')===$e?'selected':'' ?>><?= $e ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field">
          <label>Good With <small>(kids, dogs, cats, adults)</small></label>
          <input type="text" name="good_with" placeholder="e.g. kids, adults"
                 value="<?= htmlspecialchars($_POST['good_with'] ?? '') ?>">
        </div>

        <div class="field">
          <label>Description</label>
          <textarea name="description" rows="4"
                    placeholder="Personality, history, medical info, special needs…"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <h3 class="booking-section-title" style="margin-top:24px">⏱ Care Hours</h3>
        <p class="booking-note">Choose how many hours the animal needs care before adoption review.</p>

        <div class="hours-selector">
          <?php foreach ([1,2,4,8,12,24,48] as $h): ?>
            <label class="hour-btn">
              <input type="radio" name="hours_radio" value="<?= $h ?>"
                     <?= $h===1?'checked':'' ?> onchange="updateTotal(<?= $h ?>)">
              <span><?= $h ?>h</span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="field" style="margin-top:10px">
          <label>Or enter custom hours (1–72)</label>
          <input type="number" id="customHours" min="1" max="72"
                 placeholder="e.g. 6" oninput="setCustomHours(this.value)">
        </div>
        <input type="hidden" name="hours" id="hoursInput" value="1">

        <!-- Live price summary -->
        <div class="cost-summary" id="costSummary">
          <div class="cost-row">
            <span>Species</span>
            <span id="sumSpecies">—</span>
          </div>
          <div class="cost-row">
            <span>Size</span>
            <span id="sumSize">—</span>
          </div>
          <div class="cost-row">
            <span>Age</span>
            <span id="sumAge">—</span>
          </div>
          <div class="cost-row">
            <span>Hourly Rate</span>
            <span id="sumRate">$—/hr</span>
          </div>
          <div class="cost-row">
            <span>Hours Selected</span>
            <span id="sumHours">1 hr</span>
          </div>
          <div class="cost-row cost-total">
            <span>Total to Pay</span>
            <span id="sumTotal">$—</span>
          </div>
        </div>

        <h3 class="booking-section-title" style="margin-top:24px">📋 Your Information</h3>
        <p class="booking-note">Required before payment — so the shelter can contact you.</p>

        <div class="field-row">
          <div class="field">
            <label>Phone Number *</label>
            <input type="text" name="phone" placeholder="+1 555 000 0000"
                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
          </div>
          <div class="field">
            <label>Email Address</label>
            <input type="email" name="adopt_email" placeholder="you@email.com"
                   value="<?= htmlspecialchars($_POST['adopt_email'] ?? '') ?>">
          </div>
        </div>
        <div class="field">
          <label>Home Address</label>
          <input type="text" name="address" placeholder="123 Main St, City, Country"
                 value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Why do you want this animal adopted? *</label>
          <textarea name="reason" rows="3"
                    placeholder="Tell us your reason for submitting this animal for adoption…"><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
        </div>

        <h3 class="booking-section-title" style="margin-top:24px">💳 Payment</h3>
        <p class="booking-note">Demo only — no real charge will be made.</p>

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

        <button type="submit" class="btn btn-glow btn-full" style="margin-top:20px">
          Submit Animal & Pay →
        </button>
      </form>
    </div>

  </div><!-- end submit-layout -->
</main>

<script>
const PRICING = <?= json_encode($pricingTable) ?>;

const SPECIES_MULT = {Dog:1.6,Cat:1.2,Parrot:2.0,Bird:1.1,Eagle:3.0,Other:1.0};
const SIZE_MULT    = {Small:1.0,Medium:1.3,Large:1.6};
const AGE_LABELS   = {
  '6 months':'≤6 months ×2.5',
  '1 year':'1 year ×2.0',
  '2 years':'2 years ×1.7',
  '4 years':'3–4 years ×1.3',
  '7 years':'5–7 years ×1.0',
  '10 years':'8+ years ×0.8'
};
const AGE_MULT = {
  '6 months':2.5,'1 year':2.0,'2 years':1.7,
  '4 years':1.3,'7 years':1.0,'10 years':0.8
};

let currentRate = 0;
let currentHours = 1;

function calcRate(species, size, age) {
  if (!species || !size || !age) return 0;
  const sm = SPECIES_MULT[species] || 1.0;
  const sz = SIZE_MULT[size]        || 1.0;
  const am = AGE_MULT[age]          || 1.0;
  return Math.round(5 * sm * sz * am * 100) / 100;
}

function updatePrice() {
  const species = document.getElementById('sel_species').value;
  const size    = document.getElementById('sel_size').value;
  const age     = document.getElementById('sel_age').value;

  currentRate = calcRate(species, size, age);

  // Update summary left column
  document.getElementById('sumSpecies').textContent =
    species ? species + ' ×' + (SPECIES_MULT[species]||1).toFixed(1) : '—';
  document.getElementById('sumSize').textContent =
    size ? size + ' ×' + (SIZE_MULT[size]||1).toFixed(1) : '—';
  document.getElementById('sumAge').textContent =
    age ? AGE_LABELS[age] || age : '—';
  document.getElementById('sumRate').textContent =
    currentRate > 0 ? '$' + currentRate.toFixed(2) + '/hr' : '$—/hr';

  updateTotal();

  // Live example in guide
  if (currentRate > 0) {
    document.getElementById('liveExample').innerHTML =
      `<strong>${species} · ${size} · ${age}</strong><br>` +
      `$5 × ${(SPECIES_MULT[species]||1).toFixed(1)} × ${(SIZE_MULT[size]||1).toFixed(1)} × ${(AGE_MULT[age]||1).toFixed(1)}` +
      `<br><span style="font-size:1.4rem;font-weight:800;color:#f97316">= $${currentRate.toFixed(2)}/hr</span>`;
  }
}

function updateTotal(h) {
  if (h) currentHours = parseInt(h);
  document.getElementById('hoursInput').value = currentHours;
  document.getElementById('sumHours').textContent = currentHours + ' hr' + (currentHours > 1 ? 's' : '');
  const total = currentRate > 0 ? '$' + (currentRate * currentHours).toFixed(2) : '$—';
  document.getElementById('sumTotal').textContent = total;
}

function setCustomHours(val) {
  val = Math.max(1, Math.min(72, parseInt(val) || 1));
  currentHours = val;
  document.getElementById('hoursInput').value = val;
  document.getElementById('sumHours').textContent = val + ' hr' + (val > 1 ? 's' : '');
  if (currentRate > 0)
    document.getElementById('sumTotal').textContent = '$' + (currentRate * val).toFixed(2);
  document.querySelectorAll('input[name="hours_radio"]').forEach(r => r.checked = false);
}

function formatCard(input) {
  let v = input.value.replace(/\D/g,'').substring(0,16);
  input.value = v.replace(/(.{4})/g,'$1 ').trim();
}

// Init on load if values already set (after error)
window.addEventListener('load', () => { updatePrice(); updateTotal(); });
</script>
</body>
</html>
