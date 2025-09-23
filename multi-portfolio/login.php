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
    <link rel="stylesheet" href="login.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
     <div class="login-image">
        <img src="uploads/login.png" alt="Login Icon">
    </div>

    <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>

    <form method="post">
        <input type="text" name="username" placeholder="Username" required><br>
        
<div class="password-container">
  <input type="password" name="password" placeholder="Password" required class="password-field">
  
</div>

        <input type="submit" value="Login">

    </form>

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
                    var errorDiv = $('<div class="error-message"></div>')
                        .text(message)
                        .hide()
                        .prependTo('body')
                        .slideDown(300);

                    // Remove error after 5 seconds
                    setTimeout(function() {
                        errorDiv.slideUp(300, function() {
                            $(this).remove();
                        });
                    }, 5000);
                }

                function showSuccess(message) {
                    // Remove existing messages
                    $('.error-message').remove();
                    $('.success-message').remove();

                    // Add success message
                    var successDiv = $('<div class="success-message"></div>')
                        .text(message)
                        .hide()
                        .prependTo('body')
                        .slideDown(300);
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

            // Enhanced form interactions
            $('input').on('focus', function() {
                $(this).parent().addClass('focused');
            }).on('blur', function() {
                if (!$(this).val().trim()) {
                    $(this).parent().removeClass('focused');
                }
            });

            
        var passwordField = $('input[name="password"]');
        var passwordContainer = $('.password-container');
        var toggleBtn = $('<button type="button" class="password-toggle" title="Show Password">👁</button>'); // UNCOMMENTED

        passwordField.css({
            'padding-right': '40px', 
            'width': '75%',
            'box-sizing': 'border-box'
        });

        passwordContainer.css({
            'position': 'relative',
            'display': 'inline-block',
            'width': '100%'
        });

        toggleBtn.css({
            'position': 'absolute',
            'right': '40px',
            'top': '50%',
            'transform': 'translateY(-50%)',
            'background': 'none',
            'border': 'none',
            'cursor': 'pointer',
            'font-size': '16px',
            'z-index': '10',
            'color': '#666',
            'padding': '0',
            'height': '24px',
            'width': '24px',
            'line-height': '24px'
        });

        passwordContainer.append(toggleBtn);

        toggleBtn.click(function(e) {
            e.preventDefault(); // Prevent form submission
            if (passwordField.attr('type') === 'password') {
                passwordField.attr('type', 'text');
                $(this).attr('title', 'Hide Password').text('👁');
            } else {
                passwordField.attr('type', 'password');
                $(this).attr('title', 'Show Password').text('👁');
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
