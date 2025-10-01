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

// Handle POST requests for AJAX operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userObj = new User();

    // Handle skill creation
    if (isset($_POST['create'])) {
        $skill_name = trim($_POST['skill_name']);
        if ($skill_name !== '') {
            $skill_id = $userObj->addSkill($skill_name);
            if ($skill_id) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'skill_id' => $skill_id,
                    'skill_name' => $skill_name
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error adding skill.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Skill name cannot be empty.']);
        }
        exit();
    }

    // Handle skill update
    if (isset($_POST['update'])) {
        $skill_id = (int)$_POST['id'];
        $new_name = trim($_POST['skill_name']);
        if ($new_name !== '') {
            $success = $userObj->updateSkillName($skill_id, $new_name);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Skill updated successfully!' : 'Error updating skill.'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Skill name cannot be empty.']);
        }
        exit();
    }

    // Handle skill deletion
    if (isset($_POST['delete'])) {
        $skill_id = (int)$_POST['id'];
        $success = $userObj->deleteSkill($skill_id);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Skill deleted successfully!' : 'Error deleting skill.'
        ]);
        exit();
    }
}

// Get current page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 5;
$userObj = new User();
$skills = $userObj->getSkillsPaginated($page, $limit);
$total_skills = $userObj->getTotalSkills();
$total_pages = ceil($total_skills / $limit);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Skills Management - Admin</title>
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
        .create-skill-btn {
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
        .create-skill-btn:hover {
            background: #218838;
        }
        .skills-table {
            width: 100%;
            border-collapse: collapse;
        }
        .skills-table th, .skills-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .skills-table th {
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
        <h2>Skills Management</h2>

        <div id="message-container"></div>

        <button class="create-skill-btn" id="create-skill-btn">Add Skill</button>

        <table class="skills-table" id="skills-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Skill Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($skills as $skill): ?>
                <tr data-skill-id="<?php echo $skill['id']; ?>">
                    <td><?php echo $skill['id']; ?></td>
                    <td><?php echo htmlspecialchars($skill['skill_name']); ?></td>
                    <td>
                        <button class="action-btn edit-btn" data-skill-id="<?php echo $skill['id']; ?>" data-skill-name="<?php echo htmlspecialchars($skill['skill_name']); ?>">
                            <i class="fas fa-pencil-alt"></i>
                        </button>
                        <button class="action-btn delete-btn" data-skill-id="<?php echo $skill['id']; ?>">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>">&laquo; Previous</a>
            <?php else: ?>
                <span class="disabled">&laquo; Previous</span>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>">Next &raquo;</a>
            <?php else: ?>
                <span class="disabled">Next &raquo;</span>
            <?php endif; ?>
        </div>
    </main>

    <!-- Create Skill Modal -->
    <div id="create-modal" class="modal">
        <div class="modal-content">
            <span class="close" id="create-close">&times;</span>
            <h3>Add New Skill</h3>
            <form id="create-skill-form">
                <input type="text" name="skill_name" placeholder="Skill Name" required>
                <button type="submit" id="create-submit-btn">Add</button>
                <button type="button" id="create-cancel-btn">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Edit Skill Modal -->
    <div id="edit-modal" class="modal">
        <div class="modal-content">
            <span class="close" id="edit-close">&times;</span>
            <h3>Edit Skill</h3>
            <form id="edit-skill-form">
                <input type="hidden" name="id" id="edit-skill-id">
                <input type="text" name="skill_name" id="edit-skill-name" placeholder="Skill Name" required>
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

            // Create skill button
            $('#create-skill-btn').on('click', function() {
                $('#create-skill-form')[0].reset();
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

            // Create skill AJAX
            $('#create-skill-form').on('submit', function(e) {
                e.preventDefault();
                const formData = $(this).serialize();
                const createBtn = $('#create-submit-btn');
                const originalText = createBtn.text();
                createBtn.text('Adding...').prop('disabled', true);
                showLoading();
                $.ajax({
                    url: 'admin_skills.php',
                    type: 'POST',
                    data: formData + '&create=1',
                    dataType: 'json',
                    success: function(response) {
                        hideLoading();
                        createBtn.text(originalText).prop('disabled', false);
                        if (response.success) {
                            showMessage('Skill added successfully!');
                            closeModal('create-modal');
                            // Add new skill to table
                            const newRow = `
                                <tr data-skill-id="${response.skill_id}">
                                    <td>${response.skill_id}</td>
                                    <td>${response.skill_name}</td>
                                    <td>
                                        <button class="action-btn edit-btn" data-skill-id="${response.skill_id}" data-skill-name="${response.skill_name}">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>
                                        <button class="action-btn delete-btn" data-skill-id="${response.skill_id}">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            `;
                            $('#skills-table tbody').append(newRow);
                        } else {
                            showMessage(response.message || 'Error adding skill', 'error');
                        }
                    },
                    error: function() {
                        hideLoading();
                        createBtn.text(originalText).prop('disabled', false);
                        showMessage('Error adding skill', 'error');
                    }
                });
            });

            // Edit skill button
            $(document).on('click', '.edit-btn', function() {
                const skillId = $(this).data('skill-id');
                const skillName = $(this).data('skill-name');
                $('#edit-skill-id').val(skillId);
                $('#edit-skill-name').val(skillName);
                openModal('edit-modal');
            });

            // Update skill AJAX
            $('#edit-skill-form').on('submit', function(e) {
                e.preventDefault();
                const formData = $(this).serialize();
                const updateBtn = $('#edit-submit-btn');
                const originalText = updateBtn.text();
                updateBtn.text('Updating...').prop('disabled', true);
                showLoading();
                $.ajax({
                    url: 'admin_skills.php',
                    type: 'POST',
                    data: formData + '&update=1',
                    dataType: 'json',
                    success: function(response) {
                        hideLoading();
                        updateBtn.text(originalText).prop('disabled', false);
                        if (response.success) {
                            showMessage('Skill updated successfully!');
                            closeModal('edit-modal');
                            // Update the table row
                            const skillId = $('#edit-skill-id').val();
                            const newName = $('#edit-skill-name').val();
                            const row = $(`tr[data-skill-id="${skillId}"]`);
                            row.find('td:nth-child(2)').text(newName);
                            row.find('.edit-btn').data('skill-name', newName);
                        } else {
                            showMessage(response.message || 'Error updating skill', 'error');
                        }
                    },
                    error: function() {
                        hideLoading();
                        updateBtn.text(originalText).prop('disabled', false);
                        showMessage('Error updating skill', 'error');
                    }
                });
            });

            // Delete skill AJAX
            $(document).on('click', '.delete-btn', function() {
                const skillId = $(this).data('skill-id');
                const row = $(this).closest('tr');
                if (confirm('Are you sure you want to delete this skill?')) {
                    const deleteBtn = $(this);
                    const originalIcon = deleteBtn.html();
                    deleteBtn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
                    showLoading();
                    $.ajax({
                        url: 'admin_skills.php',
                        type: 'POST',
                        data: { id: skillId, delete: 1 },
                        dataType: 'json',
                        success: function(response) {
                            hideLoading();
                            deleteBtn.html(originalIcon).prop('disabled', false);
                            if (response.success) {
                                showMessage('Skill deleted successfully!');
                                row.fadeOut(function() {
                                    $(this).remove();
                                });
                            } else {
                                showMessage(response.message || 'Error deleting skill', 'error');
                            }
                        },
                        error: function() {
                            hideLoading();
                            deleteBtn.html(originalIcon).prop('disabled', false);
                            showMessage('Error deleting skill', 'error');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>
