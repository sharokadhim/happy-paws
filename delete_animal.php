<?php
require_once 'config.php';
requireAdmin();
$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("DELETE FROM animals WHERE id=?");
$stmt->bind_param("i", $id);
header("Location: dashboard.php?success=" . ($stmt->execute() ? "deleted" : "error=delete_failed"));
$stmt->close(); exit();
?>
