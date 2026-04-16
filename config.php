<?php
// ── Database ──────────────────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'animal_adoption');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) die("DB connection failed: " . $conn->connect_error);
$conn->set_charset("utf8mb4");

// ── Session ───────────────────────────────────────────────────────────────────
session_start();

// ── Role helpers ──────────────────────────────────────────────────────────────
function isAdmin(): bool {
    return ($_SESSION['role'] ?? '') === 'admin';
}

function isCustomer(): bool {
    return ($_SESSION['role'] ?? '') === 'customer';
}

function requireAdmin(): void {
    if (!isset($_SESSION['user_id']) || !isAdmin()) {
        header("Location: index.php"); exit();
    }
}

function requireLogin(): void {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php"); exit();
    }
}

// ── Species helpers ───────────────────────────────────────────────────────────
function speciesEmoji(string $s): string {
    return ['Dog'=>'🐕','Cat'=>'🐱','Bird'=>'🐦',
            'Parrot'=>'🦜','Eagle'=>'🦅','Other'=>'🐾'][$s] ?? '🐾';
}

function statusClass(string $s): string {
    return ['available'=>'badge-available','pending'=>'badge-pending','adopted'=>'badge-adopted'][$s] ?? '';
}

// ── Pricing Engine ────────────────────────────────────────────────────────────
// Base rate: $5/hour
// Multipliers stack: species × size × age
// Younger animals cost MORE (harder to adopt = higher demand)

function calcHourlyRate(string $species, string $size, string $age): float {
    // Base
    $base = 5.00;

    // Species multiplier
    $speciesMult = [
        'Dog'    => 1.6,
        'Cat'    => 1.2,
        'Parrot' => 2.0,   // rare & intelligent
        'Bird'   => 1.1,
        'Eagle'  => 3.0,   // exotic/majestic
        'Other'  => 1.0,
    ][$species] ?? 1.0;

    // Size multiplier
    $sizeMult = [
        'Small'  => 1.0,
        'Medium' => 1.3,
        'Large'  => 1.6,
    ][$size] ?? 1.0;

    // Age multiplier — extract number from strings like "2 years", "6 months"
    $ageMult = 1.0;
    if (preg_match('/(\d+)\s*month/i', $age, $m)) {
        $months = (int)$m[1];
        // Under 1 year → very young → highest price
        if ($months <= 6)  $ageMult = 2.5;
        else               $ageMult = 2.0;
    } elseif (preg_match('/(\d+)\s*year/i', $age, $m)) {
        $years = (int)$m[1];
        if ($years <= 1)    $ageMult = 2.0;
        elseif ($years <= 2) $ageMult = 1.7;
        elseif ($years <= 4) $ageMult = 1.3;
        elseif ($years <= 7) $ageMult = 1.0;
        else                 $ageMult = 0.8; // older animals cost less
    }

    return round($base * $speciesMult * $sizeMult * $ageMult, 2);
}

// Format duration since a timestamp
function timeSinceListed(string $created_at): string {
    $diff = time() - strtotime($created_at);
    if ($diff < 3600)       return round($diff/60) . ' min ago';
    if ($diff < 86400)      return round($diff/3600) . 'h ago';
    if ($diff < 86400*30)   return round($diff/86400) . 'd ago';
    return round($diff/(86400*30)) . ' months ago';
}
