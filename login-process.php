<?php
// Set session cookie parameters for 30 days
session_set_cookie_params(30*24*3600);
ini_set('session.gc_maxlifetime', 30*24*3600);
session_start();

// If a user is already logged in, redirect them to the main page.
if (isset($_SESSION['user']) && !empty($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $users = glob("users/*", GLOB_ONLYDIR);

    foreach ($users as $userFolder) {
        $dataPath = $userFolder . "/data.json";
        if (file_exists($dataPath)) {
            $user = json_decode(file_get_contents($dataPath), true);
            if ($user['email'] === $email && $user['password'] === $password) {
                $_SESSION['user'] = $user['mobile'];

                // Check and create wallet with bonus if not exists
                $walletFile = $userFolder . "/wallet.json";
                if (!file_exists($walletFile)) {
                    file_put_contents($walletFile, json_encode(["balance" => 10]));
                    $_SESSION['new_user_bonus'] = true; // Set session for alert
                }

                header("Location: index.php");
                exit();
            }
        }
    }

    echo "<script>alert('Account not found. Please create an account.'); window.location.href = 'signup.html';</script>";
}
?>
