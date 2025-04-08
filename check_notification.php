<?php
session_start();

if (!isset($_SESSION['user'])) {
    echo json_encode(['message' => null]);
    exit();
}

$user = $_SESSION['user'];
$notif_file = "users/$user/notification.txt";

if (file_exists($notif_file)) {
    $message = trim(file_get_contents($notif_file));
    unlink($notif_file); // Delete after showing
    echo json_encode(['message' => $message]);
} else {
    echo json_encode(['message' => null]);
}
?>
