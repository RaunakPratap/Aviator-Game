<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user = $_SESSION['user'];
$user_folder = "users/" . $user . "/";
$wallet_file = $user_folder . "wallet.json";

// Initialize wallet if not exists
if (!file_exists($wallet_file)) {
    if (!is_dir($user_folder)) {
        mkdir($user_folder, 0755, true);
    }
    file_put_contents($wallet_file, json_encode(['balance' => 1000]));
}

// Load wallet data
$wallet = json_decode(file_get_contents($wallet_file), true);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Utility: Transaction Logger
function logTransaction($folder, $type, $data) {
    $logFile = $folder . $type . ".json";
    $existing = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
    $existing[] = $data;
    file_put_contents($logFile, json_encode($existing, JSON_PRETTY_PRINT));
}

// Start Bet
if ($action === 'start_bet' && isset($_GET['bet'])) {
    $bet = (int)$_GET['bet'];
    if ($bet > $wallet['balance']) {
        echo json_encode(['error' => 'Insufficient balance']);
        exit();
    }
    $wallet['balance'] -= $bet;
    file_put_contents($wallet_file, json_encode($wallet));
    echo json_encode(['balance' => $wallet['balance']]);
    exit();
}

// Cashout
if ($action === 'cashout' && isset($_GET['amount'])) {
    $amount = (int)$_GET['amount'];
    $wallet['balance'] += $amount;
    file_put_contents($wallet_file, json_encode($wallet));
    
    logTransaction($user_folder, 'wins', [
        'amount' => $amount,
        'date' => date('Y-m-d H:i:s')
    ]);
    
    echo json_encode([
        'message' => 'Cashout successful!',
        'balance' => $wallet['balance']
    ]);
    exit();
}

// Crash
if ($action === 'crash' && isset($_GET['bet'])) {
    $bet = (int)$_GET['bet'];
    
    logTransaction($user_folder, 'losses', [
        'amount' => $bet,
        'date' => date('Y-m-d H:i:s')
    ]);
    
    echo json_encode(['balance' => $wallet['balance']]);
    exit();
}

// Withdraw (handles both UPI and Bank Transfer)
if ($action === 'withdraw') {
    $amount = (int)($_POST['amount'] ?? 0);
    if ($amount < 100 || $amount > $wallet['balance']) {
        echo json_encode(['error' => 'Invalid or insufficient balance']);
        exit();
    }
    
    $wallet['balance'] -= $amount;
    file_put_contents($wallet_file, json_encode($wallet));
    
    $method = $_POST['method'] ?? '';
    if ($method === 'upi') {
        $details = $_POST['upi_id'] ?? '';
    } elseif ($method === 'bank') {
        // Store bank details as an associative array instead of a concatenated string.
        $details = [
            'account_holder' => $_POST['account_holder'] ?? '',
            'account_no'       => $_POST['account_no'] ?? '',
            'ifsc'             => $_POST['ifsc'] ?? ''
        ];
    } else {
        $details = '';
    }
    
    logTransaction($user_folder, 'withdrawals', [
        'amount' => $amount,
        'method' => $method,
        'details' => $details,
        'date' => date('Y-m-d H:i:s'),
        'status' => 'pending'
    ]);
    
    echo json_encode([
        'message' => 'Withdrawal request submitted!',
        'balance' => $wallet['balance']
    ]);
    exit();
}

// Deposit
if ($action === 'deposit') {
    $amount = (int)($_POST['amount'] ?? 0);
    if ($amount < 100) {
        echo json_encode(['error' => 'Minimum deposit is ₹100']);
        exit();
    }

    if (!isset($_FILES['screenshot'])) {
        echo json_encode(['error' => 'Screenshot is required']);
        exit();
    }

    $file = $_FILES['screenshot'];

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['error' => 'Only JPG, PNG, and WEBP files are allowed']);
        exit();
    }

    // File size check
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['error' => 'File is too large. Max 5MB allowed.']);
        exit();
    }

    $upload_dir = $user_folder . 'deposits/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = date('Ymd_His') . "_" . uniqid() . "." . $ext;
    $targetFile = $upload_dir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
        echo json_encode(['error' => 'Screenshot upload failed']);
        exit();
    }

    // Load wallet to return current balance
    $wallet = json_decode(file_get_contents($wallet_file), true);

    logTransaction($user_folder, 'deposits', [
        'amount' => $amount,
        'screenshot' => $fileName,
        'date' => date('Y-m-d H:i:s')
    ]);

    echo json_encode([
        'message' => '✅ Deposit submitted! Admin will verify and update your wallet balance shortly.',
        'balance' => $wallet['balance']
    ]);
    exit();
}

// Invalid Action
echo json_encode(['error' => 'Invalid action']);
exit();
?>
