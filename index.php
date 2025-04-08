<?php
// Set session cookie parameters to persist for 30 days
session_set_cookie_params(30 * 24 * 3600);
ini_set('session.gc_maxlifetime', 30 * 24 * 3600);
session_start();
include 'config.php';

// If user is not logged in, redirect to signup/login page.
if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    header("Location: signup.html");
    exit();
}

$user = $_SESSION['user'];
$user_folder = "users/" . $user . "/";
$wallet_file = $user_folder . "wallet.json";

// Ensure the user's folder exists
if (!is_dir($user_folder)) {
    mkdir($user_folder, 0755, true);
}

// Initialize wallet if it does not exist and give a bonus of ₹10.
if (!file_exists($wallet_file)) {
    file_put_contents($wallet_file, json_encode(["balance" => 10], JSON_PRETTY_PRINT));
    // Set a session flag for bonus alert.
    $_SESSION['new_user_bonus'] = true;
}

$wallet_data = json_decode(file_get_contents($wallet_file), true);
$balance = isset($wallet_data['balance']) ? $wallet_data['balance'] : 0;

// Load transactions data for "My Transactions" modal on initial load
$depositsData = file_exists($user_folder . "deposits.json") ? json_decode(file_get_contents($user_folder . "deposits.json"), true) : [];
$withdrawalsData = file_exists($user_folder . "withdrawals.json") ? json_decode(file_get_contents($user_folder . "withdrawals.json"), true) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Aviator Game</title>
  <link rel="stylesheet" href="style.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <!-- Inline styles for notifications and modal (move to style.css later) -->
  <style>
    /* Notification Toasts */
    .notification {
      position: fixed;
      top: 20px;
      right: 20px;
      background: #222;
      color: #fff;
      padding: 12px 20px;
      border-radius: 10px;
      font-size: 16px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      z-index: 9999;
      transition: all 0.3s ease;
    }
    .notification.success { background: #2ecc71; }
    .notification.error   { background: #e74c3c; }
    .notification.hidden { opacity: 0; pointer-events: none; }
    .notification.show   { opacity: 1; }
    .user-toast {
      position: fixed;
      top: 20px;
      right: 20px;
      background: #4CAF50;
      color: white;
      padding: 14px 20px;
      border-radius: 10px;
      box-shadow: 0 0 10px rgba(0,0,0,0.2);
      font-family: 'Poppins', sans-serif;
      font-size: 15px;
      z-index: 9999;
      opacity: 1;
      transition: opacity 0.5s ease;
    }
    .user-toast.hide { opacity: 0; }

    /* Modal styles for transactions */
    #transModal {
      display: none;
      position: fixed;
      z-index: 10000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow-y: auto;
      background: rgba(0, 0, 0, 0.6);
      padding-top: 60px;
    }
    #transModal .trans-container {
      background: #074777;
      margin: auto;
      padding: 20px;
      width: 90%;
      max-width: 600px;
      border-radius: 10px;
    }
    #transModal h2 {
      text-align: center;
      margin-bottom: 20px;
    }
    #transModal ul {
      list-style-type: none;
      padding: 0;
    }
    #transModal li {
      padding: 10px;
      border-bottom: 1px solid #eee;
      font-size: 14px;
    }
    #transModal .close {
      position: absolute;
      top: 20px;
      right: 40px;
      color: #fff;
      font-size: 30px;
      cursor: pointer;
    }
    
    /* Transaction item style */
    .transaction-item {
      padding: 8px;
      margin: 5px 0;
      border-radius: 5px;
      background:rgb(84, 5, 121);
    }
    .transaction-approved { border-left: 4px solid #2ecc71; }
    .transaction-pending  { border-left: 4px solid #f39c12; }
    .transaction-rejected { border-left: 4px solid #e74c3c; }
    
    /* Status label styling */
    .status-label {
      font-weight: bold;
      margin-left: 10px;
    }
    .status-label.pending { color: #f39c12; }
    .status-label.approved { color: #2ecc71; }
    .status-label.rejected { color: #e74c3c; }
  </style>
</head>
<body>
  <!-- Notification Container (Toast) -->
  <div id="notification" class="notification hidden"></div>

  <!-- Hamburger Menu -->
  <div class="hamburger-menu">
    <div class="menu-icon" id="menuIcon">☰</div>
    <div id="sidebar" class="menu-content">
      <h3>Wallet: ₹<span id="menu-balance"><?= $balance ?></span></h3>
      <!-- My Transactions Button -->
      <hr>
      <div class="transactions-section" style="text-align:center; margin-bottom:10px;">
        <button id="viewTransactionsBtn">My Transactions</button>
      </div>
      <hr>
      <!-- Logout Button -->
      <div class="logout-section">
        <form action="logout.php" method="POST">
          <button type="submit">Logout 🚪</button>
        </form>
      </div>
      <hr>
      <!-- Withdrawal Form -->
      <div class="withdraw-section">
        <h4>Withdraw Money</h4>
        <form id="withdrawForm">
          <input type="number" name="amount" min="100" required placeholder="Amount">
          <select name="method" id="withdrawMethod" required>
            <option value="upi">UPI</option>
            <option value="bank">Bank Transfer</option>
          </select>
          <div class="upi-fields">
            <input type="text" name="upi_id" id="upi_id" placeholder="UPI ID" required>
          </div>
          <div class="bank-fields" style="display:none;">
            <input type="text" name="account_holder" id="account_holder" placeholder="Account Holder Name">
            <input type="text" name="account_no" id="account_no" placeholder="Account Number">
            <input type="text" name="ifsc" id="ifsc" placeholder="IFSC Code">
          </div>
          <button type="submit">Request Withdrawal</button>
        </form>
      </div>
      <hr>
      <!-- Deposit Section -->
      <div class="deposit-section">
        <h4>Add Money</h4>
        <form id="depositForm" enctype="multipart/form-data">
          <input type="number" name="amount" min="100" required placeholder="Amount">
          <input type="file" name="screenshot" accept="image/*" required>
          <button type="submit">Submit Deposit</button>
        </form>
        <div class="deposit-info">
          <p>Pay Through Upi, QR. Upload Screenshot Of Payment:</p>
          <p class="upi-id">raunak428@ybl</p>
          <img src="assets/qr.png" alt="Deposit QR Code" class="qr-code">
        </div>
      </div>
    </div>
  </div>

  <!-- Overlay for Sidebar -->
  <div id="overlay"></div>

  <!-- Main Game Area -->
  <div class="game-container">
    <h1>Welcome, <?= htmlspecialchars($user) ?>!</h1>
    <div class="balance">Balance: ₹<span id="balance"><?= $balance ?></span></div>
    <div class="game-area" id="gameArea">
      <div class="multiplier" id="multiplier">x1.00</div>
      <img src="assets/plane.png" id="plane" alt="Game Plane">
    </div>
    <div class="bet-controls">
      <input type="number" id="betAmount" min="10" value="10" max="<?= $balance ?>">
      <button id="startBtn">Start Game</button>
      <button id="cashoutBtn" disabled>Cash Out</button>
    </div>
  </div>

  <!-- Transactions Modal -->
  <div id="transModal" class="modal">
    <span class="close" id="transClose">&times;</span>
    <h2>My Transactions</h2>
    <div class="trans-container">
      <h3>Deposits</h3>
      <ul id="depositsList">
        <?php if (!empty($depositsData)): ?>
          <?php foreach ($depositsData as $dep): ?>
            <li class="transaction-item <?php echo isset($dep['status']) ? 'transaction-' . $dep['status'] : 'transaction-pending'; ?>">
              ₹<?= $dep['amount'] ?> - <?= $dep['date'] ?> -
              <span class="status-label <?= isset($dep['status']) ? $dep['status'] : 'pending'; ?>">
                [<?= ucfirst(isset($dep['status']) ? $dep['status'] : 'pending'); ?>]
              </span>
            </li>
          <?php endforeach; ?>
        <?php else: ?>
          <li>No deposit transactions yet.</li>
        <?php endif; ?>
      </ul>
      <h3>Withdrawals</h3>
      <ul id="withdrawalsList">
        <?php if (!empty($withdrawalsData)): ?>
          <?php foreach ($withdrawalsData as $wd): ?>
            <li class="transaction-item <?php echo isset($wd['status']) ? 'transaction-' . $wd['status'] : 'transaction-pending'; ?>">
              ₹<?= $wd['amount'] ?> - <?= $wd['date'] ?> - <?= $wd['method'] ?> -
              <span class="status-label <?= isset($wd['status']) ? $wd['status'] : 'pending'; ?>">
                [<?= ucfirst(isset($wd['status']) ? $wd['status'] : 'pending'); ?>]
              </span>
            </li>
          <?php endforeach; ?>
        <?php else: ?>
          <li>No withdrawal transactions yet.</li>
        <?php endif; ?>
      </ul>
    </div>
  </div>

  <!-- Include External JavaScript -->
  <script src="game.js"></script>
  <!-- Inline JS for Transactions Modal Toggle -->
  <script>
    // Toggle Transactions Modal
    document.addEventListener('DOMContentLoaded', () => {
      const transBtn = document.getElementById('viewTransactionsBtn');
      const transModal = document.getElementById('transModal');
      const transClose = document.getElementById('transClose');

      // Toggle modal on button click
      document.getElementById('viewTransactionsBtn').addEventListener('click', () => {
        transModal.style.display = (transModal.style.display === 'block') ? 'none' : 'block';
      });

      transClose.addEventListener('click', () => {
        transModal.style.display = 'none';
      });

      // Optional: close modal on clicking outside the container
      window.addEventListener('click', (event) => {
        if (event.target === transModal) {
          transModal.style.display = 'none';
        }
      });
    });
  </script>
  <!-- Game & Notification Scripts (in game.js) are loaded via game.js -->
</body>
</html>
