<?php
// Build absolute URLs for sound files directly from PHP — no guessing
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$dir      = rtrim(dirname($_SERVER['PHP_SELF']), '/');
$soundUrl = $protocol . '://' . $host . $dir . '/sounds/';
?>
<div id="soundUnlock" style="
  position:fixed;bottom:20px;right:20px;z-index:9999;
  background:#1e293b;color:#f97316;border:1px solid #f97316;
  padding:10px 16px;border-radius:10px;font-size:13px;cursor:pointer;
  box-shadow:0 4px 12px rgba(0,0,0,0.4);">
  🔊 Click here to enable sounds
</div>

<script>
// ── Sound Engine ──────────────────────────────────────────────
const AUDIO = {
  'Dog':    new Audio('<?= $soundUrl ?>dog.mp3'),
  'Cat':    new Audio('<?= $soundUrl ?>cat.mp3'),
  'Bird':   new Audio('<?= $soundUrl ?>bird.mp3'),
  'Parrot': new Audio('<?= $soundUrl ?>parrot.mp3'),
  'Eagle':  new Audio('<?= $soundUrl ?>eagle.mp3'),
};

// Set preload and volume
Object.values(AUDIO).forEach(a => { a.preload = 'auto'; a.volume = 0.75; });

let currentAudio  = null;
let soundUnlocked = false;

// Unlock audio on first click (required by all modern browsers)
const unlockBtn = document.getElementById('soundUnlock');
unlockBtn.addEventListener('click', () => {
  // Play & immediately pause all to unlock them in the browser
  Object.values(AUDIO).forEach(a => { a.play().catch(()=>{}); a.pause(); a.currentTime = 0; });
  soundUnlocked = true;
  unlockBtn.remove();
});

function stopCurrent() {
  if (currentAudio) {
    currentAudio.pause();
    currentAudio.currentTime = 0;
    currentAudio = null;
  }
}

document.querySelectorAll('.animal-card').forEach(card => {
  card.addEventListener('mouseenter', () => {
    if (!soundUnlocked) return;
    const a = AUDIO[card.dataset.species];
    if (!a) return;
    stopCurrent();
    a.currentTime = 0;
    a.play().catch(() => {});
    currentAudio = a;
  });
  card.addEventListener('mouseleave', () => stopCurrent());
});
</script>
