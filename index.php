<?php
session_set_cookie_params(30 * 24 * 3600);
ini_set('session.gc_maxlifetime', 30 * 24 * 3600);
session_start();
include 'config.php';

if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    header("Location: signup.html");
    exit();
}

$user = $_SESSION['user'];
$user_folder = "users/" . $user . "/";
$wallet_file = $user_folder . "wallet.json";

if (!is_dir($user_folder)) {
    mkdir($user_folder, 0755, true);
}

if (!file_exists($wallet_file)) {
    file_put_contents($wallet_file, json_encode(["balance" => 10], JSON_PRETTY_PRINT));
    $_SESSION['new_user_bonus'] = true;
}

$wallet_data = json_decode(file_get_contents($wallet_file), true);
$balance = isset($wallet_data['balance']) ? $wallet_data['balance'] : 0;

$depositsData = file_exists($user_folder . "deposits.json") ? json_decode(file_get_contents($user_folder . "deposits.json"), true) : [];
$withdrawalsData = file_exists($user_folder . "withdrawals.json") ? json_decode(file_get_contents($user_folder . "withdrawals.json"), true) : [];

$show_bonus_alert = isset($_SESSION['new_user_bonus']);
?>
<!DOCTYPE html>
<html lang="en">
<head> 
  <meta charset="UTF-8">
  <title>Aviator Game</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css">
  <style>
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
    /* Toast alert style */
.toast {
  position: fixed;
  top: 20px;
  right: 20px;
  background: linear-gradient(135deg, #00c853, #64dd17);
  color: white;
  padding: 15px 25px;
  border-radius: 12px;
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
  font-size: 16px;
  font-weight: 600;
  z-index: 9999;
  animation: toastPop 0.5s ease-out, fadeOut 0.5s ease-in 2.5s forwards;
  transform: perspective(600px) rotateX(0deg);
}

/* Toast pop-in animation */
@keyframes toastPop {
  0% {
    opacity: 0;
    transform: perspective(600px) rotateX(-90deg) translateY(-20px);
  }
  100% {
    opacity: 1;
    transform: perspective(600px) rotateX(0deg) translateY(0);
  }
}

/* Toast fade-out */
@keyframes fadeOut {
  to {
    opacity: 0;
    transform: translateY(-30px);
  }
}
  </style>
</head>
<body>
  <div id="notification" class="notification hidden"></div>

  <div class="hamburger-menu">
    <div class="menu-icon" id="menuIcon">☰</div>
    <div id="sidebar" class="menu-content">
      <h3>Wallet: ₹<span id="menu-balance"><?= $balance ?></span></h3>
      <hr>
      <div style="text-align:center; margin-bottom:10px;">
        <button id="viewTransactionsBtn">My Transactions</button>
      </div>
      <hr>
      <form action="logout.php" method="POST">
        <button type="submit">Logout 🚪</button>
      </form>
      <hr>
      <div class="withdraw-section">
        <h4>Withdraw Money</h4>
        <form id="withdrawForm">
          <input type="number" name="amount" min="100" required placeholder="Amount">
          <select name="method" id="withdrawMethod" required>
            <option value="upi">UPI</option>
            <option value="bank">Bank Transfer</option>
          </select>
          <div class="upi-fields">
            <input type="text" name="upi_id" placeholder="UPI ID" required>
          </div>
          <div class="bank-fields" style="display:none;">
            <input type="text" name="account_holder" placeholder="Account Holder Name">
            <input type="text" name="account_no" placeholder="Account Number">
            <input type="text" name="ifsc" placeholder="IFSC Code">
          </div>
          <button type="submit">Request Withdrawal</button>
        </form>
      </div>
      <hr>
      <div class="deposit-section">
        <h4>Add Money</h4>
        <form id="depositForm" enctype="multipart/form-data">
          <input type="number" name="amount" min="100" required placeholder="Amount">
          <input type="file" name="screenshot" accept="image/*" required>
          <button type="submit">Submit Deposit</button>
        </form>
        <div>
          <p>Pay through UPI/QR and upload screenshot:</p>
          <p class="upi-id">raunak428@ybl</p>
          <img src="assets/qr.png" alt="QR Code" class="qr-code">
        </div>
      </div>
    </div>
  </div>
  <div id="overlay"></div>

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

  <div id="transModal" class="modal">
    <span class="close" id="transClose">&times;</span>
    <h2>My Transactions</h2>
    <div class="trans-container">
      <h3>Deposits</h3>
      <ul id="depositsList">
        <?php if (!empty($depositsData)): ?>
          <?php foreach ($depositsData as $dep): ?>
            <li>₹<?= $dep['amount'] ?> - <?= $dep['date'] ?> - <?= ucfirst($dep['status'] ?? 'pending') ?></li>
          <?php endforeach; ?>
        <?php else: ?>
          <li>No deposit transactions yet.</li>
        <?php endif; ?>
      </ul>
      <h3>Withdrawals</h3>
      <ul id="withdrawalsList">
        <?php if (!empty($withdrawalsData)): ?>
          <?php foreach ($withdrawalsData as $wd): ?>
            <li>₹<?= $wd['amount'] ?> - <?= $wd['date'] ?> - <?= $wd['method'] ?> - <?= ucfirst($wd['status'] ?? 'pending') ?></li>
          <?php endforeach; ?>
        <?php else: ?>
          <li>No withdrawal transactions yet.</li>
        <?php endif; ?>
      </ul>
    </div>
  </div>

  <?php if ($show_bonus_alert): ?>
  <div id="bonusToast" class="toast">Congratulations! You received ₹10 for free gameplay.</div>
  <script>
    setTimeout(() => {
      document.getElementById('bonusToast')?.remove();
    }, 3000);
  </script>
  <?php unset($_SESSION['new_user_bonus']); ?>
  <?php endif; ?>

  <script src="game.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const transBtn = document.getElementById('viewTransactionsBtn');
      const transModal = document.getElementById('transModal');
      const transClose = document.getElementById('transClose');

      transBtn.addEventListener('click', () => {
        transModal.style.display = 'block';
      });

      transClose.addEventListener('click', () => {
        transModal.style.display = 'none';
      });

      window.addEventListener('click', (event) => {
        if (event.target === transModal) {
          transModal.style.display = 'none';
        }
      });
    });
  </script>
</body>
</html>
