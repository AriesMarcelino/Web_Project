<?php
session_start();
include "classes.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    $errors = [];
    if (empty($username) || strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters.";
    }
    if (empty($full_name) || strlen($full_name) < 2) {
        $errors[] = "Full name must be at least 2 characters.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }
    if (empty($password) || strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {
        $userObj = new User();
        if ($userObj->register($username, $full_name, $email, $password)) {
            $success = "Account created successfully! You can now log in.";
            // Optionally redirect to login after delay, but show message for now
        } else {
            $error = "Username or email already exists. Please choose different ones.";
        }
    } else {
        $error = implode(" ", $errors);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sign Up</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gradient-to-br from-blue-500 via-purple-400 to-pink-400 min-h-screen flex flex-col md:flex-row">
    <!-- Left half: Welcome/Branding -->
    <div class="md:w-1/2 flex flex-col justify-center items-center p-8 bg-gradient-to-br from-blue-500 to-purple-600 text-white">
        <img src="uploads/login.png" alt="Sign Up Icon" class="w-24 h-24 mb-6 rounded-full shadow-lg">
        <h1 class="text-3xl font-bold mb-4 text-center">Join Multi-Portfolio</h1>
        <p class="text-lg text-center mb-6">Create an account to showcase your skills, hobbies, and achievements in your personalized portfolio.</p>
        <div class="text-center">
            <p class="text-sm opacity-90">Already have an account? <a href="login.php" class="underline hover:opacity-80">Login here</a></p>
        </div>
    </div>

    <!-- Right half: Sign Up Form -->
    <div class="md:w-1/2 flex flex-col justify-center items-center p-8 bg-white">
        <div class="w-full max-w-md">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Create Account</h2>

            <?php if (isset($success)) echo "<p class='text-green-500 text-center mb-4 bg-green-100 p-3 rounded-lg'>$success</p>"; ?>
            <?php if (isset($error)) echo "<p class='text-red-500 text-center mb-4 bg-red-100 p-3 rounded-lg'>$error</p>"; ?>

            <form method="post" class="space-y-4">
                <div>
                    <input type="text" name="username" placeholder="Username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
                </div>

                <div>
                    <input type="text" name="full_name" placeholder="Full Name" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" required class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
                </div>

                <div>
                    <input type="email" name="email" placeholder="Email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
                </div>

                <div class="relative">
                    <input type="password" name="password" placeholder="Password" required class="w-full p-3 pr-12 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200 password-field">
                    <button type="button" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 password-toggle">
                        👁
                    </button>
                </div>

                <div class="relative">
                    <input type="password" name="confirm_password" placeholder="Confirm Password" required class="w-full p-3 pr-12 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200 confirm-password-field">
                    <button type="button" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 confirm-password-toggle">
                        👁
                    </button>
                </div>

                <button type="submit" class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white p-3 rounded-lg hover:from-blue-600 hover:to-purple-700 transition duration-300 transform hover:scale-105 shadow-lg">
                    Sign Up
                </button>
            </form>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Password toggle for password
            $('.password-toggle').click(function(e) {
                e.preventDefault();
                var passwordField = $('input[name="password"]');
                if (passwordField.attr('type') === 'password') {
                    passwordField.attr('type', 'text');
                    $(this).text('🙈');
                } else {
                    passwordField.attr('type', 'password');
                    $(this).text('👁');
                }
            });

            // Password toggle for confirm password
            $('.confirm-password-toggle').click(function(e) {
                e.preventDefault();
                var confirmField = $('input[name="confirm_password"]');
                if (confirmField.attr('type') === 'password') {
                    confirmField.attr('type', 'text');
                    $(this).text('🙈');
                } else {
                    confirmField.attr('type', 'password');
                    $(this).text('👁');
                }
            });

            // Real-time password match check
            $('input[name="confirm_password"]').on('input', function() {
                var password = $('input[name="password"]').val();
                var confirm = $(this).val();
                if (confirm.length > 0) {
                    if (password === confirm) {
                        $(this).removeClass('border-red-500').addClass('border-green-500');
                    } else {
                        $(this).removeClass('border-green-500').addClass('border-red-500');
                    }
                } else {
                    $(this).removeClass('border-green-500 border-red-500');
                }
            });

            // Form submission validation
            $('form').on('submit', function(e) {
                var password = $('input[name="password"]').val();
                var confirm = $('input[name="confirm_password"]').val();
                if (password !== confirm) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                    return false;
                }
            });
        });
    </script>
</body>
</html>
