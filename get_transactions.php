<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit();
}

$user = $_SESSION['user'];
$user_folder = "users/" . $user . "/";

$deposits = file_exists($user_folder . "deposits.json") ? json_decode(file_get_contents($user_folder . "deposits.json"), true) : [];
$withdrawals = file_exists($user_folder . "withdrawals.json") ? json_decode(file_get_contents($user_folder . "withdrawals.json"), true) : [];

echo json_encode([
    'deposits' => $deposits,
    'withdrawals' => $withdrawals
]);
?>
