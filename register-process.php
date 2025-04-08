<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $email = $_POST['email'];
  $mobile = $_POST['mobile'];
  $password = $_POST['password'];

  $user_folder = "users/" . $mobile . "/";
  if (!is_dir($user_folder)) {
    mkdir($user_folder, 0755, true);
    $data = [
      "email" => $email,
      "mobile" => $mobile,
      "password" => $password
    ];
    file_put_contents($user_folder . "data.json", json_encode($data, JSON_PRETTY_PRINT));
    file_put_contents($user_folder . "wallet.json", json_encode(["balance" => 10]));

    // ❌ Auto-login mat karo
    // ✅ Redirect to login page instead
    echo "<script>alert('Registration successful. Please login.'); window.location.href='signup.html';</script>";
    exit();
  } else {
    echo "<script>alert('User already exists. Try logging in.'); window.location.href='signup.html';</script>";
    exit();
  }
}
?>