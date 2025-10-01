<?php
// Start session only if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection and classes
include "db.php";
include "classes.php";

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    // Detect AJAX request
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Session expired. Please log in again.'
        ]);
        exit();
    } else {
        header("Location: login.php");
        exit();
    }
}

// Handle POST requests (AJAX operations)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle user creation
    if (isset($_POST['create'])) {
        $username = $conn->real_escape_string($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash the password
        $email = $conn->real_escape_string($_POST['email']);
        
        try {
            $sql = "INSERT INTO users (username, password, email) VALUES ('$username', '$password', '$email')";
            if ($conn->query($sql)) {
                $user_id = $conn->insert_id;
                // Fetch the newly created user data
                $result = $conn->query("SELECT id, username, email FROM users WHERE id=$user_id");
                $user = $result->fetch_assoc();
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'user_id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email']
                ]);
            } else {
                throw new Exception($conn->error);
            }
        } catch (mysqli_sql_exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Username or email already exists. Please choose a different one.'
                ]);
            } else {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Error creating user: ' . $e->getMessage()
                ]);
            }
        }
        exit();
        }

    // Handle user update
    elseif (isset($_POST['update'])) {
        $id = (int)$_POST['id'];
        $username = $conn->real_escape_string($_POST['username']);
        $email = $conn->real_escape_string($_POST['email']);
        $sql = "UPDATE users SET username='$username', email='$email' WHERE id=$id";
        $success = $conn->query($sql);
        if (!$success) {
            error_log("Update failed: " . $conn->error);
        }
        header('Content-Type: application/json');
        if ($success) {
            // Fetch updated user data
            $result = $conn->query("SELECT id, username, email FROM users WHERE id=$id");
            $user = $result->fetch_assoc();
            echo json_encode([
                'success' => true,
                'user' => $user
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error updating user: ' . $conn->error
            ]);
        }
        exit();
    }

    // Handle user deletion
    elseif (isset($_POST['delete'])) {
        $id = (int)$_POST['id'];
        // Validate user ID
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Invalid user ID'
            ]);
            exit();
        }
        // Check if user exists before deletion
        $check_sql = "SELECT id, username FROM users WHERE id = ? AND deleted_at IS NULL";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows === 0) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'User not found or already deleted'
            ]);
            exit();
        }
        $user = $check_result->fetch_assoc();
        // Start transaction for data integrity
        $conn->begin_transaction();
        try {
            // Delete from pivot tables first (to avoid foreign key constraint issues)
            $pivot_tables = [
                'skill_user',
                'hobby_user',
                'project_user',
                'award_user',
                'certificate_user',
                'social_media_user',
            ];
            foreach ($pivot_tables as $table) {
                $delete_sql = "DELETE FROM $table WHERE user_id = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("i", $id);
                $delete_stmt->execute();
            }
            // Finally, mark user as deleted
            $sql = "UPDATE users SET deleted_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $conn->commit();
                error_log("User deleted successfully: ID $id, Username: " . $user['username']);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'User deleted successfully'
                ]);
                exit();
            } else {
                throw new Exception("Failed to delete user");
            }

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error deleting user $id: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Error deleting user: ' . $e->getMessage()
            ]);
            exit();
        }
    }
}

        // Fetch users for display with pagination
        $limit = 5;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $page = max(1, $page); // Ensure page is at least 1

        // Get total users count
        $total_users_result = $conn->query("SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL");
        $total_users_row = $total_users_result->fetch_assoc();
        $total_users = $total_users_row['total'];
        $total_pages = ceil($total_users / $limit);

        $offset = ($page - 1) * $limit;
        $sql = "SELECT * FROM users WHERE deleted_at IS NULL ORDER BY id LIMIT $limit OFFSET $offset";
        $result = $conn->query($sql);
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Management - Admin</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 400px;
            border-radius: 5px;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: black;
        }
        .modal input {
            width: 100%;
            padding: 8px;
            margin: 5px 0;
            box-sizing: border-box;
        }
        .modal button {
            padding: 8px 15px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }
        .modal button:hover {
            background: #0056b3;
        }
        .modal button:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        .create-user-btn {
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 20px;
            width: 20%;
            float: inline-end;
        }
        .create-user-btn:hover {
            background: #218838;
        }
        .users-table {
            width: 100%;
            border-collapse: collapse;
        }
        .users-table th, .users-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .users-table th {
            background-color: #f8f9fa;
        }
        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            margin-right: 0;
            background: transparent;
            float: left;
        }
        .edit-btn {
            color: #ffc107;
        }
        .edit-btn:hover {
            color: #e0a800;
        }
        .delete-btn {
            color: #dc3545;
        }
        .delete-btn:hover {
            color: #c82333;
        }
        .pagination {
            margin-top: 20px;
            text-align: center;
        }
        .pagination a, .pagination span {
            padding: 8px 12px;
            margin: 0 2px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #007bff;
        }
        .pagination a:hover {
            background-color: #f8f9fa;
        }
        .pagination .current {
            background-color: #007bff;
            color: white;
            border: 1px solid #007bff;
        }
        .pagination .disabled {
            color: #6c757d;
            cursor: not-allowed;
        }
        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .nav-link {
            display: block;
            padding: 10px 15px;
            text-decoration: none;
            color: #333;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }
        .nav-link:hover {
            background-color: #f0f0f0;
        }
        .nav-link.active {
            background-color: #e0e0e0;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="loading" id="loading">
        <div>Processing...</div>
    </div>
    <header>
        <h1>Admin Panel</h1>
        <a href="logout.php" class="logout-btn">Logout</a>
    </header>
    <aside class="sidebar">
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
            <a href="admin_user_management.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_user_management.php' ? 'active' : ''; ?>">User Management</a>
            <a href="admin_skills.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_skills.php' ? 'active' : ''; ?>">Skills Management</a>
            <a href="admin_hobbies.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_hobbies.php' ? 'active' : ''; ?>">Hobbies Management</a>
            <a href="admin_manage_users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_manage_users.php' ? 'active' : ''; ?>">Per-User Skills & Hobbies</a>
        </nav>
    </aside>

    <main class="main-content">
        <h2>User Management</h2>

        <div id="message-container"></div>

        <button class="create-user-btn" id="create-user-btn">Create User</button>

        <table class="users-table" id="users-table">
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
                        <button class="action-btn edit-btn" data-user-id="<?php echo $user['id']; ?>" data-username="<?php echo htmlspecialchars($user['username']); ?>" data-email="<?php echo htmlspecialchars($user['email']); ?>">
                            <i class="fas fa-pencil-alt"></i>
                        </button>
                        <button class="action-btn delete-btn" data-user-id="<?php echo $user['id']; ?>">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <!-- <a href="?page=1">&laquo; First</a> -->
                <a href="?page=<?php echo $page - 1; ?>">&lsaquo; Previous</a>
            <?php else: ?>
                <!-- <span class="disabled">&laquo; First</span> -->
                <span class="disabled">&lsaquo; Previous</span>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            for ($i = $start; $i <= $end; $i++):
                if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                <?php endif;
            endfor;
            ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>">Next &rsaquo;</a>
                <!-- <a href="?page=<?php echo $total_pages; ?>">Last &raquo;</a> -->
            <?php else: ?>
                <span class="disabled">Next &rsaquo;</span>
                <!-- <span class="disabled">Last &raquo;</span> -->
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>

    <!-- Create User Modal -->
    <div id="create-modal" class="modal">
        <div class="modal-content">
            <span class="close" id="create-close">&times;</span>
            <h3>Create New User</h3>
            <form id="create-user-form">
                <input type="text" name="username" placeholder="Username" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" id="create-submit-btn">Create</button>
                <button type="button" id="create-cancel-btn">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="edit-modal" class="modal">
        <div class="modal-content">
            <span class="close" id="edit-close">&times;</span>
            <h3>Edit User</h3>
            <form id="edit-user-form">
                <input type="hidden" name="id" id="edit-user-id">
                <input type="text" name="username" id="edit-username" placeholder="Username" required>
                <input type="email" name="email" id="edit-email" placeholder="Email" required>
                <button type="submit" id="edit-submit-btn">Update</button>
                <button type="button" id="edit-cancel-btn">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        $(document).ready(function() {

            // Modal functions
            function openModal(modalId) {
                $('#' + modalId).show();
            }
            function closeModal(modalId) {
                $('#' + modalId).hide();
            }

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

            // Create user button
            $('#create-user-btn').on('click', function() {
                $('#create-user-form')[0].reset();
                openModal('create-modal');
            });

            // Close modals
            $('#create-close, #create-cancel-btn').on('click', function() {
                closeModal('create-modal');
            });
            $('#edit-close, #edit-cancel-btn').on('click', function() {
                closeModal('edit-modal');
            });

            // Close modal when clicking outside
            $(window).on('click', function(event) {
                if ($(event.target).hasClass('modal')) {
                    $('.modal').hide();
                }
            });

            // Create user AJAX
            $('#create-user-form').on('submit', function(e) {
                e.preventDefault();
                const formData = $(this).serialize();
                const createBtn = $('#create-submit-btn');
                const originalText = createBtn.text();
                createBtn.text('Creating...').prop('disabled', true);
                showLoading();
                $.ajax({
                    url: 'admin_user_management.php',
                    type: 'POST',
                    data: formData + '&create=1',
                    dataType: 'json',
                    success: function(response) {
                        hideLoading();
                        createBtn.text(originalText).prop('disabled', false);
                        if (response.success) {
                            showMessage('User created successfully!');
                            closeModal('create-modal');
                            // Add new user to table
                            const newRow = `
                                <tr data-user-id="${response.user_id}">
                                    <td>${response.user_id}</td>
                                    <td>${response.username}</td>
                                    <td>${response.email}</td>
                                    <td>
                                        <button class="action-btn edit-btn" data-user-id="${response.user_id}" data-username="${response.username}" data-email="${response.email}">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>
                                        <button class="action-btn delete-btn" data-user-id="${response.user_id}">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            `;
                            $('#users-table tbody').append(newRow);
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

            // Edit user button
            $(document).on('click', '.edit-btn', function() {
                const userId = $(this).data('user-id');
                const username = $(this).data('username');
                const email = $(this).data('email');
                $('#edit-user-id').val(userId);
                $('#edit-username').val(username);
                $('#edit-email').val(email);
                openModal('edit-modal');
            });

            // Update user AJAX
            $('#edit-user-form').on('submit', function(e) {
                e.preventDefault();
                const formData = $(this).serialize();
                const updateBtn = $('#edit-submit-btn');
                const originalText = updateBtn.text();
                updateBtn.text('Updating...').prop('disabled', true);
                showLoading();
                $.ajax({
                    url: 'admin_user_management.php',
                    type: 'POST',
                    data: formData + '&update=1',
                    dataType: 'json',
                    success: function(response) {
                        hideLoading();
                        updateBtn.text(originalText).prop('disabled', false);
                        if (response.success) {
                            showMessage('User updated successfully!');
                            closeModal('edit-modal');
                            // Update the table row
                            const row = $(`tr[data-user-id="${response.user.id}"]`);
                            row.find('td:nth-child(2)').text(response.user.username);
                            row.find('td:nth-child(3)').text(response.user.email);
                            row.find('.edit-btn').data('username', response.user.username).data('email', response.user.email);
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
                    const originalIcon = deleteBtn.html();
                    deleteBtn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
                    showLoading();
                    $.ajax({
                        url: 'admin_user_management.php',
                        type: 'POST',
                        data: { id: userId, delete: 1 },
                        dataType: 'json',
                        success: function(response) {
                            hideLoading();
                            deleteBtn.html(originalIcon).prop('disabled', false);
                            if (response.success) {
                                showMessage('User deleted successfully!');
                                row.fadeOut(function() {
                                    $(this).remove();
                                });
                            } else {
                                showMessage(response.message || 'Error deleting user', 'error');
                            }
                        },
                        error: function() {
                            hideLoading();
                            deleteBtn.html(originalIcon).prop('disabled', false);
                            showMessage('Error deleting user', 'error');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>
