<?php
session_start();
include "classes.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Attempt admin login first
    $admin = new Admin();
    $adminUser = $admin->login($username, $password);

    
    if ($adminUser) {
        header("Location: admin_dashboard.php");
        exit();
    }

    // If admin login fails, attempt user login
    $userObj = new User();
    $user = $userObj->login($username, $password);

    if ($user) {
        header("Location: profile.php?username=" . urlencode($user['username']));
        exit();
    }

    // If both fail
    $error = "Invalid username or password!";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>
     <div class="login-image">
        <img src="uploads/login.png" alt="Login Icon">
    </div>

    <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>

    <form method="post">
        <input type="text" name="username" placeholder="Username" required><br>
        <input type="password" name="password" placeholder="Password" required><br>
        <input type="submit" value="Login">
        
        <!-- <button type = "button" onclick="window.location.href='admin.php'">Admin Login</button> -->
    </form>

   
</body>
</html>
