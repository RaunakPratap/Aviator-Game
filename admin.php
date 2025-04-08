<?php
session_start();

$admin_username = "admin";
$admin_password = "admin123";

$users_folder = "users/";
$users = array_filter(glob($users_folder . '*'), 'is_dir');

// Handle login
if (isset($_POST['login'])) {
    if ($_POST['username'] === $admin_username && $_POST['password'] === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $error = "Invalid credentials.";
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit();
}

// Show login form if not logged in
if (!isset($_SESSION['admin_logged_in'])):
?>
<!DOCTYPE html>
<html>
<head>
  <title>Admin Login</title>
  <link rel="stylesheet" href="admin.css">
</head>
<body>
  <div class="login-container">
    <h1>Admin Login</h1>
    <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
    <form method="POST">
      <label>Username:</label>
      <input type="text" name="username" required>
      <label>Password:</label>
      <input type="password" name="password" required>
      <button type="submit" name="login">Login</button>
    </form>
  </div>
</body>
</html>
<?php exit(); endif; ?>
<!-- Admin Dashboard -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="admin.css">
</head>
<body>
  <header>
    <h1>Admin Dashboard</h1>
    <p><a href="?logout=1">🔒 Logout</a></p>
  </header>

  <!-- Dashboard Summary -->
  <section style="text-align:center; margin-bottom: 30px;">
    <strong>Total Users:</strong> <?= count($users) ?> |
    <strong>Total Balance:</strong> ₹<?= array_sum(array_map(function($path) {
        $wallet = json_decode(@file_get_contents("$path/wallet.json"), true);
        return $wallet['balance'] ?? 0;
    }, $users)) ?>
  </section>

  <!-- Search Box -->
  <div style="text-align:center; margin-bottom: 20px;">
    <input type="text" id="searchBox" placeholder="Search by mobile..." style="padding: 6px 12px; width: 300px;">
  </div>

  <div class="users-container" id="usersContainer">
<?php foreach ($users as $userPath):
  $mobile = basename($userPath);
  $wallet = json_decode(@file_get_contents("$userPath/wallet.json"), true);
  $deposits = json_decode(@file_get_contents("$userPath/deposits.json"), true) ?? [];
  $withdrawals = json_decode(@file_get_contents("$userPath/withdrawals.json"), true) ?? [];
?>
  <div class="user-section" data-mobile="<?= $mobile ?>">
    <h2>User: <?= htmlspecialchars($mobile) ?></h2>

    <div class="wallet-box">
      <div class="wallet-display"><strong>Wallet Balance:</strong> ₹<?= $wallet['balance'] ?? 0 ?></div>
      <form action="admin_action.php" method="POST" class="wallet-form">
        <label>Update Wallet:</label>
        <input type="number" name="balance" value="<?= $wallet['balance'] ?? 0 ?>">
        <input type="hidden" name="mobile" value="<?= htmlspecialchars($mobile) ?>">
        <button type="submit" name="action" value="update_wallet">Update</button>
      </form>
    </div>

    <h3>Deposits</h3>
<ul>
  <?php foreach ($deposits as $index => $dep): ?>
    <li>
      ₹<?= $dep['amount'] ?> - <?= $dep['date'] ?> -
      <button class="view-btn" onclick="showImage('<?= $userPath ?>/deposits/<?= $dep['screenshot'] ?>')">View Screenshot</button>
      <?php if (!isset($dep['status']) || $dep['status'] === 'pending'): ?>
        <span class="status-label pending">[Pending]</span>
        <form action="admin_action.php" method="POST" class="inline-form">
          <input type="hidden" name="mobile" value="<?= $mobile ?>">
          <input type="hidden" name="index" value="<?= $index ?>">
          <input type="hidden" name="type" value="deposit">
          <button name="action" value="approve">Approve</button>
          <button name="action" value="reject">Reject</button>
        </form>
      <?php else: ?>
        <span class="status-label <?= $dep['status'] ?>">[<?= ucfirst($dep['status']) ?>]</span>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ul>

<h3>Withdrawals</h3>
<ul>
  <?php foreach ($withdrawals as $index => $wd): ?>
    <li>
      ₹<?= $wd['amount'] ?> - <?= $wd['date'] ?> - <?= $wd['method'] ?> -
      <?php if (!isset($wd['status']) || $wd['status'] === 'pending'): ?>
        <span class="status-label pending">[Pending]</span>
        <form action="admin_action.php" method="POST" class="inline-form">
          <input type="hidden" name="mobile" value="<?= $mobile ?>">
          <input type="hidden" name="index" value="<?= $index ?>">
          <input type="hidden" name="type" value="withdrawal">
          <button name="action" value="approve">Approve</button>
          <button name="action" value="reject">Reject</button>
        </form>
      <?php else: ?>
        <span class="status-label <?= $wd['status'] ?>">[<?= ucfirst($wd['status']) ?>]</span>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ul>
  </div>
<?php endforeach; ?>
</div>

<!-- Modal for viewing screenshots -->
<div id="imageModal" class="modal">
  <span class="close" onclick="hideImage()">&times;</span>
  <img class="modal-content" id="modalImg">
</div>

<script>
function showImage(src) {
  document.getElementById("modalImg").src = src;
  document.getElementById("imageModal").style.display = "block";
}
function hideImage() {
  document.getElementById("imageModal").style.display = "none";
}

// Filter users
document.getElementById('searchBox').addEventListener('input', function() {
  const value = this.value.trim().toLowerCase();
  document.querySelectorAll('.user-section').forEach(section => {
    const mobile = section.dataset.mobile;
    section.style.display = mobile.includes(value) ? "block" : "none";
  });
});
</script>
<script>
function fetchNewRequests() {
  fetch('fetch_requests.php')
    .then(res => res.json())
    .then(data => {
      data.forEach(user => {
        const section = document.querySelector(`.user-section[data-mobile="${user.mobile}"]`);
        if (section) {
          const depositList = section.querySelector('h3:nth-of-type(1) + ul');
          const withdrawalList = section.querySelector('h3:nth-of-type(2) + ul');

          // Only remove previous pending items (not approved/rejected)
          const cleanList = (list, type) => {
            [...list.children].forEach(li => {
              const status = li.querySelector('.status-label');
              if (!status || status.textContent.includes('Pending')) {
                li.remove();
              }
            });
          };

          cleanList(depositList, 'deposit');
          cleanList(withdrawalList, 'withdrawal');

          user.deposits.forEach(dep => {
            const li = document.createElement('li');
            li.innerHTML = `₹${dep.amount} - ${dep.date} -
              <button class="view-btn" onclick="showImage('users/${user.mobile}/deposits/${dep.screenshot}')">View Screenshot</button>
              <form action="admin_action.php" method="POST" class="inline-form">
                <input type="hidden" name="mobile" value="${user.mobile}">
                <input type="hidden" name="index" value="${dep.index}">
                <input type="hidden" name="type" value="deposit">
                <button name="action" value="approve">Approve</button>
                <button name="action" value="reject">Reject</button>
              </form>`;
            depositList.appendChild(li);
          });

          user.withdrawals.forEach(wd => {
            const li = document.createElement('li');
            li.innerHTML = `₹${wd.amount} - ${wd.date} - ${wd.method} -
              <form action="admin_action.php" method="POST" class="inline-form">
                <input type="hidden" name="mobile" value="${user.mobile}">
                <input type="hidden" name="index" value="${wd.index}">
                <input type="hidden" name="type" value="withdrawal">
                <button name="action" value="approve">Approve</button>
                <button name="action" value="reject">Reject</button>
              </form>`;
            withdrawalList.appendChild(li);
          });
        }
      });
    })
    .catch(err => console.error("Error fetching requests:", err));
}

// Check every 10 seconds
setInterval(fetchNewRequests, 10000);
</script>

</body>
</html>
