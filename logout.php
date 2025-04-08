<?php
session_start();
session_destroy(); // Destroy all session data
header("Location: signup.html"); // Redirect to login
exit();
?>
