<?php
require_once 'config.php';
// Called by JS timer when countdown hits zero
// Updates booking to expired and animal back to available
$animal_id = (int)($_GET['animal_id'] ?? 0);
if ($animal_id > 0) {
    $conn->query("
        UPDATE bookings SET status='expired'
        WHERE animal_id=$animal_id AND status='confirmed' AND booking_ends_at < NOW()
    ");
    $conn->query("
        UPDATE animals SET status='available'
        WHERE id=$animal_id
          AND NOT EXISTS (
              SELECT 1 FROM bookings
              WHERE animal_id=$animal_id AND status='confirmed' AND booking_ends_at > NOW()
          )
    ");
}
echo json_encode(['ok'=>true]);
