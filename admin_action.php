<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    exit("Unauthorized access.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mobile = $_POST['mobile'];
    $userPath = "users/$mobile/";

    if (!is_dir($userPath)) {
        exit("User folder not found.");
    }

    // Update wallet balance
    if ($_POST['action'] === 'update_wallet') {
        $balance = intval($_POST['balance']);
        file_put_contents($userPath . 'wallet.json', json_encode(["balance" => $balance], JSON_PRETTY_PRINT));
        header("Location: admin.php");
        exit();
    }

    // Approve or reject deposit/withdrawal
    if (isset($_POST['index'], $_POST['type']) && in_array($_POST['action'], ['approve', 'reject'])) {
        $index = intval($_POST['index']);
        $type = $_POST['type']; // 'deposit' or 'withdrawal'
        $action = $_POST['action']; // 'approve' or 'reject'

        $file = $type === 'deposit' ? 'deposits.json' : 'withdrawals.json';
        $filePath = $userPath . $file;

        if (!file_exists($filePath)) {
            exit(ucfirst($type) . " file not found.");
        }

        $data = json_decode(file_get_contents($filePath), true);
        if (!isset($data[$index])) {
            exit(ucfirst($type) . " request index not found.");
        }

        $data[$index]['status'] = $action;
        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));

        // Handle deposit wallet update
        if ($type === 'deposit' && $action === 'approve') {
            $walletPath = $userPath . 'wallet.json';
            $wallet = json_decode(file_get_contents($walletPath), true);
            $wallet['balance'] += intval($data[$index]['amount']);
            file_put_contents($walletPath, json_encode($wallet, JSON_PRETTY_PRINT));
        }

        // Save notification for user
        $amount = intval($data[$index]['amount']);
        $message = strtoupper($type) . " ₹$amount has been " . strtoupper($action);
        file_put_contents($userPath . 'notification.txt', $message);

        header("Location: admin.php");
        exit();
    }
}

exit("Invalid request.");
