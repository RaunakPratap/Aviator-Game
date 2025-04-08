<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $mobile = $_POST['mobile'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    if (empty($email) || empty($mobile) || empty($new_password)) {
        echo "<script>alert('All fields are required!'); window.history.back();</script>";
        exit();
    }

    $folder = "users/$mobile";
    $file = "$folder/data.json";

    if (!file_exists($file)) {
        echo "<script>alert('User not found!'); window.history.back();</script>";
        exit();
    }

    $user_data = json_decode(file_get_contents($file), true);

    if ($user_data['email'] === $email && $user_data['mobile'] === $mobile) {
        $user_data['password'] = $new_password;
        file_put_contents($file, json_encode($user_data));
        echo "<script>alert('Password updated successfully!'); window.location.href='signup.php';</script>";
    } else {
        echo "<script>alert('Email or mobile does not match!'); window.history.back();</script>";
    }
}
?>