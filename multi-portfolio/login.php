<?php
session_start();
include "classes.php";

// Check if this is an AJAX request
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Attempt admin login first
    $admin = new Admin();
    $adminUser = $admin->login($username, $password);

    if ($adminUser) {
        // Successful admin login
        if ($isAjax) {
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'isAdmin' => true,
                'message' => 'Admin login successful',
                'redirect' => 'admin_dashboard.php'
            ]);
            exit();
        } else {
            header("Location: admin_dashboard.php");
            exit();
        }
    }

    // If admin login fails, attempt regular user login
    $userObj = new User();
    $user = $userObj->login($username, $password);

    if ($user) {
        // Successful user login
        if ($isAjax) {
            // Set JSON header and return clean JSON response
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'isAdmin' => false,
                'username' => $user['username'],
                'message' => 'Login successful',
                'redirect' => 'profile.php?username=' . urlencode($user['username'])
            ]);
            exit();
        } else {
            header("Location: profile.php?username=" . urlencode($user['username']));
            exit();
        }
    }

    // If both fail, return error
    if ($isAjax) {
        // Set JSON header and return clean JSON response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid username or password!'
        ]);
        exit();
    } else {
        $error = "Invalid username or password!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gradient-to-br from-blue-400 via-purple-500 to-pink-500 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-md transform transition-all duration-300 hover:scale-105">
        <div class="text-center mb-6">
            <img src="uploads/login.png" alt="Login Icon" class="w-20 h-20 mx-auto mb-4 rounded-full shadow-lg">
            <h2 class="text-2xl font-bold text-gray-800">Welcome Back!</h2>
            <br>
            <p class="text-gray-600">Sign in to your account</p>
        </div>

        <?php if (isset($error)) echo "<p class='text-red-500 text-center mb-4 bg-red-100 p-3 rounded-lg'>$error</p>"; ?>

        <form method="post" class="space-y-4">
            <div>
                <input type="text" name="username" placeholder="Username" required class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
            </div>

            <div class="relative">
                <input type="password" name="password" placeholder="Password" required class="w-full p-3 pr-12 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200 password-field">
                <button type="button" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 password-toggle">
                    👁
                </button>
            </div>

            <button type="submit" class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white p-3 rounded-lg hover:from-blue-600 hover:to-purple-700 transition duration-300 transform hover:scale-105 shadow-lg">
                Login
            </button>
        </form>
    </div>

    <script>
        $(document).ready(function() {
            // AJAX Login functionality
            $('form').on('submit', function(e) {
                e.preventDefault();

                var form = $(this);
                var submitBtn = form.find('input[type="submit"]');
                var originalText = submitBtn.val();

                // Get form data
                var formData = {
                    username: form.find('input[name="username"]').val(),
                    password: form.find('input[name="password"]').val()
                };

                // Validate form
                if (!formData.username.trim() || !formData.password.trim()) {
                    showError('Please fill in all fields');
                    return;
                }

                // Show loading state
                submitBtn.val('Logging in...').prop('disabled', true);
                form.addClass('loading');

                // AJAX request
                $.ajax({
                    url: 'login.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    },
                    success: function(response) {
                        if (response.success) {
                            // Successful login
                            showSuccess('Login successful! Redirecting...');

                            // Redirect after short delay
                            setTimeout(function() {
                                if (response.isAdmin) {
                                    window.location.href = 'admin_dashboard.php';
                                } else {
                                    window.location.href = 'profile.php?username=' + encodeURIComponent(response.username);
                                }
                            }, 1000);
                        } else {
                            // Login failed
                            showError(response.message || 'Login failed. Please try again.');
                            resetForm();
                        }
                    },
                    error: function(xhr, status, error) {
                        showError('Network error. Please check your connection and try again.');
                        resetForm();
                    }
                });

                function resetForm() {
                    submitBtn.val(originalText).prop('disabled', false);
                    form.removeClass('loading');
                }

                function showError(message) {
                    // Remove existing error messages
                    $('.error-message').remove();
                    $('.success-message').remove();

                    // Add error message
                    var errorDiv = $('<div class="fixed top-4 left-1/2 transform -translate-x-1/2 bg-red-500 text-white px-4 py-2 rounded-lg shadow-lg z-50"></div>')
                        .text(message)
                        .hide()
                        .appendTo('body')
                        .fadeIn(300);

                    // Remove error after 5 seconds
                    setTimeout(function() {
                        errorDiv.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }, 5000);
                }

                function showSuccess(message) {
                    // Remove existing messages
                    $('.error-message').remove();
                    $('.success-message').remove();

                    // Add success message
                    var successDiv = $('<div class="fixed top-4 left-1/2 transform -translate-x-1/2 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50"></div>')
                        .text(message)
                        .hide()
                        .appendTo('body')
                        .fadeIn(300);
                }
            });

            // Real-time validation
            $('input[name="username"]').on('input', function() {
                var username = $(this).val().trim();

                // Remove previous validation styling
                $(this).removeClass('valid invalid');

                if (username.length > 0) {
                    if (username.length < 3) {
                        $(this).addClass('invalid');
                    } else {
                        $(this).addClass('valid');
                    }
                }
            });

            $('input[name="password"]').on('input', function() {
                var password = $(this).val();

                // Remove previous validation styling
                $(this).removeClass('valid invalid');

                if (password.length > 0) {
                    if (password.length < 4) {
                        $(this).addClass('invalid');
                    } else {
                        $(this).addClass('valid');
                    }
                }
            });

            // Password toggle
            $('.password-toggle').click(function(e) {
                e.preventDefault();
                var passwordField = $('input[name="password"]');
                if (passwordField.attr('type') === 'password') {
                    passwordField.attr('type', 'text');
                    $(this).attr('title', 'Hide Password');
                } else {
                    passwordField.attr('type', 'password');
                    $(this).attr('title', 'Show Password');
                }
            });
            // Keyboard shortcuts
            $(document).on('keydown', function(e) {
                // Enter key submits form
                if (e.keyCode === 13 && $('input:focus').length > 0) {
                    $('form').submit();
                }

                // Escape key clears form
                if (e.keyCode === 27) {
                    $('input').val('');
                    $('.error-message, .success-message').remove();
                }
            });
        });
    </script>

</body>
</html>
