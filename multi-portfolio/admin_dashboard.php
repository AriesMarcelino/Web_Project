<?php
session_start();
include "db.php";
include "classes.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create'])) {
        $username = $conn->real_escape_string($_POST['username']);
        $password = $_POST['password'];
        $email = $conn->real_escape_string($_POST['email']);
        $sql = "INSERT INTO users (username, password, email) VALUES ('$username', '$password', '$email')";
        $conn->query($sql);
    } elseif (isset($_POST['update'])) {
        $id = (int)$_POST['id'];
        $username = $conn->real_escape_string($_POST['username']);
        $email = $conn->real_escape_string($_POST['email']);
        $sql = "UPDATE users SET username='$username', email='$email' WHERE id=$id";
        $conn->query($sql);

    // Fetch updated user data
    $result = $conn->query("SELECT id, username, email FROM users WHERE id=$id");
    $user = $result->fetch_assoc();

    // Return JSON response for AJAX
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
    exit();
}
    } elseif (isset($_POST['delete'])) {
        $id = (int)$_POST['id'];
        $sql = "UPDATE users SET deleted_at=NOW() WHERE id=$id";
        $conn->query($sql);
    }

    // Fetch users
    $sql = "SELECT * FROM users WHERE deleted_at IS NULL ORDER BY id";
    $result = $conn->query($sql);
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="admin.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .loading {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 20px;
            border-radius: 5px;
            z-index: 1000;
        }
        .success-message, .error-message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            display: none;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .user-form input {
            margin: 5px 0;
            padding: 8px;
            width: 200px;
        }
        .user-form button {
            margin: 5px;
            padding: 8px 15px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .user-form button:hover {
            background: #0056b3;
        }
        .user-form button:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="loading" id="loading">
        <div>Processing...</div>
    </div>

    <header>
        <button class="menu-toggle">☰</button>
        <h1>Admin Dashboard</h1>
        <a href="logout.php" class="logout-btn">Logout</a>
    </header>

    <aside class="sidebar">
        <ul>
            <li><a href="admin_dashboard.php">Dashboard</a></li>
            <li><a href="admin_users.php">Users</a></li>
            <li><a href="admin_settings.php">Settings</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <h2>Dashboard Overview</h2>

        <div class="dashboard-cards">
            <div class="card">
                <h3>Total Users</h3>
                <p><?php echo count($users); ?></p>
            </div>
            <div class="card">
                <h3>Active Users</h3>
                <p><?php echo count(array_filter($users, function($u) { return $u['is_admin'] == 0; })); ?></p>
            </div>
            <div class="card">
                <h3>Admins</h3>
                <p><?php echo count(array_filter($users, function($u) { return $u['is_admin'] == 1; })); ?></p>
            </div>
        </div>

        <div id="message-container"></div>

        <h3>Create User</h3>
        <form class="user-form" id="create-user-form">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="email" name="email" placeholder="Email">
            <button type="submit" id="create-btn">Create</button>
        </form>

        <h3>Users</h3>
        <table id="users-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr data-user-id="<?php echo $user['id']; ?>">
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo $user['username']; ?></td>
                    <td><?php echo $user['email']; ?></td>
                    <td>
                        <form class="user-form update-form" data-user-id="<?php echo $user['id']; ?>">
                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                            <input type="text" name="username" value="<?php echo $user['username']; ?>" required>
                            <input type="email" name="email" value="<?php echo $user['email']; ?>">
                            <button type="submit" class="update-btn">Update</button>
                        </form>
                        <button class="delete-btn" data-user-id="<?php echo $user['id']; ?>">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>

    <script>
        $(document).ready(function() {
            // Sidebar toggle functionality
            $('.menu-toggle').click(function() {
                $('.sidebar').toggleClass('collapsed');
                $('.main-content').toggleClass('expanded');
            });

            // Set active menu item
            const currentPage = window.location.pathname.split('/').pop();
            $('.sidebar a').each(function() {
                if ($(this).attr('href') === currentPage) {
                    $(this).parent().addClass('active');
                }
            });

            // Show loading spinner
            function showLoading() {
                $('#loading').show();
            }

            // Hide loading spinner
            function hideLoading() {
                $('#loading').hide();
            }

            // Show message
            function showMessage(message, type = 'success') {
                const messageDiv = $('<div>')
                    .addClass(type + '-message')
                    .text(message)
                    .hide();
                $('#message-container').html(messageDiv);
                messageDiv.fadeIn();

                setTimeout(function() {
                    messageDiv.fadeOut();
                }, 3000);
            }

            // Create user AJAX
            $('#create-user-form').on('submit', function(e) {
                e.preventDefault();

                const formData = $(this).serialize();
                const createBtn = $('#create-btn');
                const originalText = createBtn.text();

                createBtn.text('Creating...').prop('disabled', true);
                showLoading();

                $.ajax({
                    url: 'admin_dashboard.php',
                    type: 'POST',
                    data: formData + '&create=1',
                    success: function(response) {
                        hideLoading();
                        createBtn.text(originalText).prop('disabled', false);

                        if (response.success) {
                            showMessage('User created successfully!');
                            $('#create-user-form')[0].reset();

                            // Add new user to table
                            const newRow = `
                                <tr data-user-id="${response.user_id}">
                                    <td>${response.user_id}</td>
                                    <td>${response.username}</td>
                                    <td>${response.email}</td>
                                    <td>
                                        <form class="user-form update-form" data-user-id="${response.user_id}">
                                            <input type="hidden" name="id" value="${response.user_id}">
                                            <input type="text" name="username" value="${response.username}" required>
                                            <input type="email" name="email" value="${response.email}">
                                            <button type="submit" class="update-btn">Update</button>
                                        </form>
                                        <button class="delete-btn" data-user-id="${response.user_id}">Delete</button>
                                    </td>
                                </tr>
                            `;
                            $('#users-table tbody').append(newRow);

                            // Update dashboard cards
                            updateDashboardCards();
                        } else {
                            showMessage(response.message || 'Error creating user', 'error');
                        }
                    },
                    error: function() {
                        hideLoading();
                        createBtn.text(originalText).prop('disabled', false);
                        showMessage('Error creating user', 'error');
                    }
                });
            });

            // Update user AJAX
            $(document).on('submit', '.update-form', function(e) {
                e.preventDefault();

                const form = $(this);
                const formData = form.serialize();
                const updateBtn = form.find('.update-btn');
                const originalText = updateBtn.text();
                const userId = form.data('user-id');

                updateBtn.text('Updating...').prop('disabled', true);
                showLoading();

                $.ajax({
                    url: 'admin_dashboard.php',
                    type: 'POST',
                    data: formData + '&update=1',
                    success: function(response) {
                        hideLoading();
                        updateBtn.text(originalText).prop('disabled', false);

                        if (response.success) {
                            showMessage('User updated successfully!');

                            // Update the table row
                            const row = form.closest('tr');
                            const newData = response.user;
                            row.find('td:nth-child(2)').text(newData.username);
                            row.find('td:nth-child(3)').text(newData.email);
                            form.find('input[name="username"]').val(newData.username);
                            form.find('input[name="email"]').val(newData.email);
                        } else {
                            showMessage(response.message || 'Error updating user', 'error');
                        }
                    },
                    error: function() {
                        hideLoading();
                        updateBtn.text(originalText).prop('disabled', false);
                        showMessage('Error updating user', 'error');
                    }
                });
            });

            // Delete user AJAX
            $(document).on('click', '.delete-btn', function() {
                const userId = $(this).data('user-id');
                const row = $(this).closest('tr');

                if (confirm('Are you sure you want to delete this user?')) {
                    const deleteBtn = $(this);
                    const originalText = deleteBtn.text();

                    deleteBtn.text('Deleting...').prop('disabled', true);
                    showLoading();

                    $.ajax({
                        url: 'admin_dashboard.php',
                        type: 'POST',
                        data: { id: userId, delete: 1 },
                        success: function(response) {
                            hideLoading();
                            deleteBtn.text(originalText).prop('disabled', false);

                            if (response.success) {
                                showMessage('User deleted successfully!');
                                row.fadeOut(function() {
                                    $(this).remove();
                                    updateDashboardCards();
                                });
                            } else {
                                showMessage(response.message || 'Error deleting user', 'error');
                            }
                        },
                        error: function() {
                            hideLoading();
                            deleteBtn.text(originalText).prop('disabled', false);
                            showMessage('Error deleting user', 'error');
                        }
                    });
                }
            });

            // Update dashboard cards
            function updateDashboardCards() {
                const totalUsers = $('#users-table tbody tr').length;
                const activeUsers = $('#users-table tbody tr').filter(function() {
                    return $(this).find('td:nth-child(2)').text().indexOf('admin') === -1;
                }).length;
                const admins = totalUsers - activeUsers;

                $('.card:nth-child(1) p').text(totalUsers);
                $('.card:nth-child(2) p').text(activeUsers);
                $('.card:nth-child(3) p').text(admins);
            }

            // Form validation
            function validateForm(form) {
                const inputs = form.find('input[required]');
                let isValid = true;

                inputs.each(function() {
                    if ($(this).val().trim() === '') {
                        isValid = false;
                        $(this).addClass('error');
                    } else {
                        $(this).removeClass('error');
                    }
                });

                return isValid;
            }

            // Add CSS for error inputs
            const style = $('<style>').text(`
                input.error {
                    border-color: #dc3545;
                    background-color: #f8d7da;
                }
            `);
            $('head').append(style);
        });
    </script>
</body>
</html>
