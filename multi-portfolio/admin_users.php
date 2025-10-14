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

// Handle POST requests for global skills/hobbies management
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userObj = new User();

    // Handle global skill management
    if (isset($_POST['add_skill'])) {
        $skill_name = trim($_POST['skill_name'] ?? '');
        if ($skill_name !== '') {
            $success = $userObj->addSkill($skill_name);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Skill added successfully!' : 'Error adding skill.'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Skill name cannot be empty.']);
        }
        exit();
    }

    if (isset($_POST['update_skill'])) {
        $skill_id = (int)$_POST['skill_id'];
        $new_name = trim($_POST['new_skill_name'] ?? '');
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

    if (isset($_POST['delete_skill'])) {
        $skill_id = (int)$_POST['skill_id'];
        $success = $userObj->deleteSkill($skill_id);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Skill deleted successfully!' : 'Error deleting skill.'
        ]);
        exit();
    }

    // Handle global hobby management
    if (isset($_POST['add_hobby'])) {
        $hobby_name = trim($_POST['hobby_name'] ?? '');
        if ($hobby_name !== '') {
            $success = $userObj->addHobby($hobby_name);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Hobby added successfully!' : 'Error adding hobby.'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Hobby name cannot be empty.']);
        }
        exit();
    }

    if (isset($_POST['update_hobby'])) {
        $hobby_id = (int)$_POST['hobby_id'];
        $new_name = trim($_POST['new_hobby_name'] ?? '');
        if ($new_name !== '') {
            $success = $userObj->updateHobbyName($hobby_id, $new_name);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Hobby updated successfully!' : 'Error updating hobby.'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Hobby name cannot be empty.']);
        }
        exit();
    }

    if (isset($_POST['delete_hobby'])) {
        $hobby_id = (int)$_POST['hobby_id'];
        $success = $userObj->deleteHobby($hobby_id);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Hobby deleted successfully!' : 'Error deleting hobby.'
        ]);
        exit();
    }


}

// Initialize User object for global management
$userObj = new User();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Skills & Hobbies - Admin</title>
    <link rel="stylesheet" href="admin.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            display: none;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .toast.show {
            display: block;
            opacity: 1;
        }

        .global-management-section {
            margin-top: 40px;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .global-management-section h2 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .global-management-section p {
            color: #666;
            margin-bottom: 30px;
        }
        .management-panel {
            margin-bottom: 40px;
        }
        .management-panel h3 {
            color: #007bff;
            margin-bottom: 15px;
        }
        .add-new-section {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .new-item-input {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .add-btn:disabled, .edit-btn:disabled, .save-btn:disabled, .delete-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .operation-loading {
            display: none;
            margin-left: 10px;
            color: #007bff;
            font-size: 12px;
        }
        .global-message {
            margin-top: 10px;
            padding: 8px;
            border-radius: 4px;
            display: none;
        }
        .global-success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .global-error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .management-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .management-table th, .management-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .management-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        .management-table tr:hover {
            background-color: #f5f5f5;
        }
        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .edit-btn {
            padding: 6px 12px;
            background: #ffc107;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        .edit-btn:hover {
            background: #e0a800;
        }
        .save-btn {
            padding: 6px 12px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        .save-btn:hover {
            background: #218838;
        }
        .cancel-btn {
            padding: 6px 12px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        .cancel-btn:hover {
            background: #5a6268;
        }
        .delete-btn {
            padding: 6px 12px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        .delete-btn:hover {
            background: #c82333;
        }
        .edit-input {
            width: 100%;
            padding: 6px;
            border: 1px solid #007bff;
            border-radius: 3px;
            font-size: 14px;
        }

        /* Dropdown styles */
        .sidebar-dropdown {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
            font-size: 16px;
            cursor: pointer;
        }
        .sidebar-dropdown:focus {
            outline: none;
            border-color: #007bff;
        }
    </style>
</head>
<body>
    <header>
        <h1>Admin Panel</h1>
        <a href="logout.php" class="logout-btn">Logout</a>
    </header>
    <aside class="w-64 bg-gradient-to-b from-slate-800 to-slate-900 h-screen fixed left-0 top-16 shadow-xl md:block hidden">
        <nav class="flex flex-col py-6">
            <div class="px-6 mb-8">
                <h2 class="text-white text-lg font-semibold tracking-wide">Admin Panel</h2>
                <p class="text-slate-400 text-sm mt-1">Management Tools</p>
            </div>

            <div class="space-y-2 px-4">
                <a href="admin_dashboard.php" class="group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'bg-slate-700 text-white shadow-lg' : 'text-slate-300 hover:bg-slate-700 hover:text-white'; ?>">
                    <svg class="w-5 h-5 mr-3 transition-colors duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'text-blue-400' : 'text-slate-400 group-hover:text-blue-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v2H8V5z"></path>
                    </svg>
                    Dashboard
                </a>

                <a href="admin_user_management.php" class="group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'admin_user_management.php' ? 'bg-slate-700 text-white shadow-lg' : 'text-slate-300 hover:bg-slate-700 hover:text-white'; ?>">
                    <svg class="w-5 h-5 mr-3 transition-colors duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'admin_user_management.php' ? 'text-green-400' : 'text-slate-400 group-hover:text-green-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                    </svg>
                    User Management
                </a>

                <a href="admin_users.php" class="group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'admin_users.php' ? 'bg-slate-700 text-white shadow-lg' : 'text-slate-300 hover:bg-slate-700 hover:text-white'; ?>">
                    <svg class="w-5 h-5 mr-3 transition-colors duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'admin_users.php' ? 'text-red-400' : 'text-slate-400 group-hover:text-red-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    Global Skills & Hobbies
                </a>

                <a href="admin_manage_users.php" class="group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'admin_manage_users.php' ? 'bg-slate-700 text-white shadow-lg' : 'text-slate-300 hover:bg-slate-700 hover:text-white'; ?>">
                    <svg class="w-5 h-5 mr-3 transition-colors duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'admin_manage_users.php' ? 'text-indigo-400' : 'text-slate-400 group-hover:text-indigo-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Per-User Skills & Hobbies
                </a>

                <a href="admin_skills.php" class="group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'admin_skills.php' ? 'bg-slate-700 text-white shadow-lg' : 'text-slate-300 hover:bg-slate-700 hover:text-white'; ?>">
                    <svg class="w-5 h-5 mr-3 transition-colors duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'admin_skills.php' ? 'text-purple-400' : 'text-slate-400 group-hover:text-purple-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                    Skills Management
                </a>

                <a href="admin_hobbies.php" class="group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'admin_hobbies.php' ? 'bg-slate-700 text-white shadow-lg' : 'text-slate-300 hover:bg-slate-700 hover:text-white'; ?>">
                    <svg class="w-5 h-5 mr-3 transition-colors duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'admin_hobbies.php' ? 'text-yellow-400' : 'text-slate-400 group-hover:text-yellow-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1.586a1 1 0 01.707.293l.707.707A1 1 0 0012.414 11H15m-3-3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Hobbies Management
                </a>
            </div>

            <div class="mt-8 px-4">
                <div class="border-t border-slate-700 pt-4">
                    <div class="flex items-center px-4 py-2 text-xs text-slate-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Admin Version 1.0
                    </div>
                </div>
            </div>
        </nav>
    </aside>

    <main class="main-content">
        <h2>Manage Skills & Hobbies</h2>

        <!-- Global Skills and Hobbies Management -->
        <div class="global-management-section">
            <h2>Global Skills & Hobbies Management</h2>
            <p>Manage all skills and hobbies in the system. Changes here affect all users.</p>

            <!-- Skills Management -->
            <div class="management-panel">
                <h3>Skills</h3>
                <div class="add-new-section">
                    <input type="text" id="new-skill-input" placeholder="Enter new skill name" class="new-item-input">
                    <button id="add-skill-btn" class="add-btn">Add Skill</button>
                    <span class="operation-loading" id="skill-add-loading">Adding...</span>
                </div>
                <div class="global-message" id="skills-global-message"></div>
                <table class="management-table" id="skills-table">
                    <thead>
                        <tr>
                            <th>Skill Name</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $all_skills = $userObj->getAllSkills();
                        foreach ($all_skills as $skill): ?>
                            <tr data-id="<?php echo $skill['id']; ?>" data-type="skill">
                                <td>
                                    <span class="item-text"><?php echo htmlspecialchars($skill['skill_name']); ?></span>
                                    <input type="text" class="edit-input" value="<?php echo htmlspecialchars($skill['skill_name']); ?>" style="display: none;">
                                </td>
                                <td class="actions">
                                    <button class="edit-btn">Edit</button>
                                    <button class="save-btn" style="display: none;">Save</button>
                                    <button class="cancel-btn" style="display: none;">Cancel</button>
                                    <button class="delete-btn">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Hobbies Management -->
            <div class="management-panel">
                <h3>Hobbies</h3>
                <div class="add-new-section">
                    <input type="text" id="new-hobby-input" placeholder="Enter new hobby name" class="new-item-input">
                    <button id="add-hobby-btn" class="add-btn">Add Hobby</button>
                    <span class="operation-loading" id="hobby-add-loading">Adding...</span>
                </div>
                <div class="global-message" id="hobbies-global-message"></div>
                <table class="management-table" id="hobbies-table">
                    <thead>
                        <tr>
                            <th>Hobby Name</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $all_hobbies = $userObj->getAllHobbies();
                        foreach ($all_hobbies as $hobby): ?>
                            <tr data-id="<?php echo $hobby['id']; ?>" data-type="hobby">
                                <td>
                                    <span class="item-text"><?php echo htmlspecialchars($hobby['hobby_name']); ?></span>
                                    <input type="text" class="edit-input" value="<?php echo htmlspecialchars($hobby['hobby_name']); ?>" style="display: none;">
                                </td>
                                <td class="actions">
                                    <button class="edit-btn">Edit</button>
                                    <button class="save-btn" style="display: none;">Save</button>
                                    <button class="cancel-btn" style="display: none;">Cancel</button>
                                    <button class="delete-btn">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="toast" class="toast"></div>

    <script>
        $(document).ready(function() {
            function showToast(message) {
                const toast = $('#toast');
                toast.text(message).addClass('show');
                setTimeout(function() {
                    toast.removeClass('show');
                }, 3000);
            }

            // Global Skills and Hobbies Management
            // Add new skill
            $('#add-skill-btn').on('click', function() {
                const skillName = $('#new-skill-input').val().trim();
                const addBtn = $(this);
                const loading = $('#skill-add-loading');
                const globalMessage = $('#skills-global-message');

                if (skillName === '') {
                    globalMessage.removeClass('global-success-message global-error-message').addClass('global-error-message').text('Please enter a skill name.').show();
                    return;
                }

                addBtn.prop('disabled', true);
                loading.show();
                globalMessage.hide();

                $.ajax({
                    url: 'admin_users.php',
                    type: 'POST',
                    data: { add_skill: 1, skill_name: skillName },
                    dataType: 'json',
                    success: function(response) {
                        loading.hide();
                        addBtn.prop('disabled', false);
                        globalMessage.removeClass('global-success-message global-error-message');
                        if (response.success) {
                            globalMessage.addClass('global-success-message').text(response.message).show();
                            showToast(response.message);
                            setTimeout(function() {
                                location.reload(); // Reload to refresh the table
                            }, 8000); // Delay reload to ensure toast is visible
                        } else {
                            globalMessage.addClass('global-error-message').text(response.message).show();
                        }
                    },
                    error: function() {
                        loading.hide();
                        addBtn.prop('disabled', false);
                        globalMessage.addClass('global-error-message').text('Error adding skill.').show();
                    }
                });
            });

            // Add new hobby
            $('#add-hobby-btn').on('click', function() {
                const hobbyName = $('#new-hobby-input').val().trim();
                const addBtn = $(this);
                const loading = $('#hobby-add-loading');
                const globalMessage = $('#hobbies-global-message');

                if (hobbyName === '') {
                    globalMessage.removeClass('global-success-message global-error-message').addClass('global-error-message').text('Please enter a hobby name.').show();
                    return;
                }

                addBtn.prop('disabled', true);
                loading.show();
                globalMessage.hide();

                $.ajax({
                    url: 'admin_users.php',
                    type: 'POST',
                    data: { add_hobby: 1, hobby_name: hobbyName },
                    dataType: 'json',
                    success: function(response) {
                        loading.hide();
                        addBtn.prop('disabled', false);
                        globalMessage.removeClass('global-success-message global-error-message');
                        if (response.success) {
                            globalMessage.addClass('global-success-message').text(response.message).show();
                            showToast(response.message);
                            setTimeout(function() {
                                location.reload(); // Reload to refresh the table
                            }, 8000); // Delay reload to ensure toast is visible
                        } else {
                            globalMessage.addClass('global-error-message').text(response.message).show();
                        }
                    },
                    error: function() {
                        loading.hide();
                        addBtn.prop('disabled', false);
                        globalMessage.addClass('global-error-message').text('Error adding hobby.').show();
                    }
                });
            });

            // Edit item
            $(document).on('click', '.edit-btn', function() {
                const row = $(this).closest('tr');
                const itemText = row.find('.item-text');
                const editInput = row.find('.edit-input');
                const saveBtn = row.find('.save-btn');
                const cancelBtn = row.find('.cancel-btn');
                const editBtn = $(this);

                // Store original value
                editInput.data('original', editInput.val());

                // Show input, hide text
                itemText.hide();
                editInput.show().focus().select();

                // Show save/cancel, hide edit
                editBtn.hide();
                saveBtn.show();
                cancelBtn.show();
            });

            // Save item
            $(document).on('click', '.save-btn', function() {
                const row = $(this).closest('tr');
                const itemText = row.find('.item-text');
                const editInput = row.find('.edit-input');
                const saveBtn = row.find('.save-btn');
                const cancelBtn = row.find('.cancel-btn');
                const editBtn = row.find('.edit-btn');
                const type = row.data('type');
                const id = row.data('id');
                const newName = editInput.val().trim();

                if (newName === '') {
                    alert('Name cannot be empty.');
                    return;
                }

                if (newName === editInput.data('original')) {
                    // No change, just cancel
                    cancelEdit(row);
                    return;
                }

                saveBtn.prop('disabled', true);
                cancelBtn.prop('disabled', true);

                $.ajax({
                    url: 'admin_users.php',
                    type: 'POST',
                    data: {
                        [type === 'skill' ? 'update_skill' : 'update_hobby']: 1,
                        [type === 'skill' ? 'skill_id' : 'hobby_id']: id,
                        [type === 'skill' ? 'new_skill_name' : 'new_hobby_name']: newName
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showToast(response.message);
                            setTimeout(function() {
                                location.reload(); // Reload to refresh the table
                            }, 8000); // Delay reload to ensure toast is visible
                        } else {
                            alert('Error: ' + response.message);
                            saveBtn.prop('disabled', false);
                            cancelBtn.prop('disabled', false);
                        }
                    },
                    error: function() {
                        alert('Error updating ' + type + '.');
                        saveBtn.prop('disabled', false);
                        cancelBtn.prop('disabled', false);
                    }
                });
            });

            // Cancel edit
            $(document).on('click', '.cancel-btn', function() {
                const row = $(this).closest('tr');
                cancelEdit(row);
            });

            function cancelEdit(row) {
                const itemText = row.find('.item-text');
                const editInput = row.find('.edit-input');
                const saveBtn = row.find('.save-btn');
                const cancelBtn = row.find('.cancel-btn');
                const editBtn = row.find('.edit-btn');

                // Reset input to original value
                editInput.val(editInput.data('original'));

                // Show text, hide input
                itemText.show();
                editInput.hide();

                // Show edit, hide save/cancel
                editBtn.show();
                saveBtn.hide();
                cancelBtn.hide();
            }

            // Delete item
            $(document).on('click', '.delete-btn', function() {
                const row = $(this).closest('tr');
                const type = row.data('type');
                const id = row.data('id');
                const deleteBtn = $(this);

                if (!confirm('Are you sure you want to delete this ' + type + '? This action cannot be undone.')) {
                    return;
                }

                deleteBtn.prop('disabled', true);

                $.ajax({
                    url: 'admin_users.php',
                    type: 'POST',
                    data: {
                        [type === 'skill' ? 'delete_skill' : 'delete_hobby']: 1,
                        [type === 'skill' ? 'skill_id' : 'hobby_id']: id
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showToast(response.message);
                            setTimeout(function() {
                                location.reload(); // Reload to refresh the table
                            }, 8000); // Delay reload to ensure toast is visible
                        } else {
                            alert('Error: ' + response.message);
                            deleteBtn.prop('disabled', false);
                        }
                    },
                    error: function() {
                        alert('Error deleting ' + type + '.');
                        deleteBtn.prop('disabled', false);
                    }
                });
            });
        });
    </script>
</body>
</html>
