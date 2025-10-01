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

// Handle POST requests for updating skills/hobbies
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userObj = new User();

    if (isset($_POST['update_skills'])) {
        $user_id = (int)$_POST['user_id'];
        $skills = $_POST['skills'] ?? [];
        $success = $userObj->updateSkills($user_id, $skills);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Skills updated successfully!' : 'Error updating skills.'
        ]);
        exit();
    }

    if (isset($_POST['update_hobbies'])) {
        $user_id = (int)$_POST['user_id'];
        $hobbies = $_POST['hobbies'] ?? [];
        $success = $userObj->updateHobbies($user_id, $hobbies);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Hobbies updated successfully!' : 'Error updating hobbies.'
        ]);
        exit();
    }
}

// Fetch users for display with pagination
$userObj = new User();
$adminObj = new Admin();
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1

$total_users = $adminObj->getTotalUsers();
$total_pages = ceil($total_users / $limit);

$users = $adminObj->getUsersPaginated($page, $limit);

// Fetch predefined skills and hobbies
$predefined_skills = $userObj->getPredefinedSkills();
$predefined_hobbies = $userObj->getPredefinedHobbies();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage User Skills & Hobbies - Admin</title>
    <link rel="stylesheet" href="admin.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        .user-card {
            border: 1px solid #ddd;
            margin: 10px 0;
            padding: 15px;
            border-radius: 5px;
        }
        .user-card h3 {
            margin-top: 0;
        }
        .skills-hobbies-section {
            margin-top: 15px;
        }
        .skills-hobbies-section h4 {
            margin-bottom: 10px;
        }
        .skills-list, .hobbies-list {
            margin-bottom: 10px;
        }
        .skill-item, .hobby-item {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
            gap: 10px;
        }
        .skill-input, .hobby-input, .new-skill-input, .new-hobby-input {
            flex: 1;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .remove-btn, .add-btn {
            padding: 5px 10px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        .add-btn {
            background: #28a745;
        }
        .add-section {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .update-btn {
            margin-top: 10px;
            padding: 8px 15px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .update-btn:hover {
            background: #0056b3;
        }
        .update-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        .loading {
            display: none;
            margin-left: 10px;
            color: #007bff;
        }
        .message {
            margin-top: 10px;
            padding: 8px;
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
        } */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
        }
        .page-link {
            padding: 8px 12px;
            text-decoration: none;
            color: #007bff;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #fff;
        }
        .page-link:hover {
            background-color: #f8f9fa;
        }
        .page-link.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
    </style>
</head>
<body>
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
        <h2>Manage User Skills & Hobbies</h2>

        <?php foreach ($users as $user): ?>
            <?php
            $user_id = $user['id'];
            $skills = $userObj->getSkills($user_id);
            $hobbies = $userObj->getHobbies($user_id);
            ?>
            <div class="user-card" data-user-id="<?php echo $user_id; ?>">
                <h3><?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['email']); ?>)</h3>

                <div class="skills-hobbies-section">
                    <h4>Skills</h4>
                    <form class="skills-form" data-user-id="<?php echo $user_id; ?>">
                        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                        <select name="skills[]" multiple="multiple" class="skills-select">
                            <?php
                            foreach ($predefined_skills as $skill):
                                $selected = in_array($skill, $skills) ? 'selected' : '';
                            ?>
                                <option value="<?php echo htmlspecialchars($skill); ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($skill); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="update-btn skills-update-btn">Update Skills</button>
                        <span class="loading skills-loading">Updating...</span>
                    </form>
                    <div class="message skills-message"></div>
                </div>

                <div class="skills-hobbies-section">
                    <h4>Hobbies</h4>
                    <form class="hobbies-form" data-user-id="<?php echo $user_id; ?>">
                        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                        <select name="hobbies[]" multiple="multiple" class="hobbies-select">
                            <?php
                            foreach ($predefined_hobbies as $hobby):
                                $selected = in_array($hobby, $hobbies) ? 'selected' : '';
                            ?>
                                <option value="<?php echo htmlspecialchars($hobby); ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($hobby); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="update-btn hobbies-update-btn">Update Hobbies</button>
                        <span class="loading hobbies-loading">Updating...</span>
                    </form>
                    <div class="message hobbies-message"></div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>" class="page-link">Previous</a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>" class="page-link">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <div id="toast" class="toast"></div>

    <script>
        $(document).ready(function() {

            // Initialize Select2 for skills
            $('.skills-select').select2({
                placeholder: 'Select skills or type to create new ones...',
                tags: true,
                tokenSeparators: [','],
                width: '100%',
                createTag: function (params) {
                    if (params.term && params.term.length > 0) {
                        return {
                            id: params.term,
                            text: params.term,
                            newTag: true
                        };
                    }
                    return null;
                }
            });

            // Initialize Select2 for hobbies
            $('.hobbies-select').select2({
                placeholder: 'Select hobbies or type to create new ones...',
                tags: true,
                tokenSeparators: [','],
                width: '100%',
                createTag: function (params) {
                    if (params.term && params.term.length > 0) {
                        return {
                            id: params.term,
                            text: params.term,
                            newTag: true
                        };
                    }
                    return null;
                }
            });

            function showToast(message) {
                const toast = $('#toast');
                toast.text(message).addClass('show');
                setTimeout(function() {
                    toast.removeClass('show');
                }, 3000);
            }

            // Handle skills form submission
            $('.skills-form').on('submit', function(e) {
                e.preventDefault();
                const form = $(this);
                const userId = form.data('user-id');
                const updateBtn = form.find('.skills-update-btn');
                const loading = form.find('.skills-loading');
                const message = form.find('.skills-message');

                const startTime = new Date().getTime();

                updateBtn.prop('disabled', true);
                loading.show();
                message.hide();

                const formData = form.serialize();

                $.ajax({
                    url: 'admin_manage_users.php',
                    type: 'POST',
                    data: formData + '&update_skills=1',
                    dataType: 'json',
                    success: function(response) {
                        const endTime = new Date().getTime();
                        const elapsed = endTime - startTime;
                        const delay = Math.max(0, 1000 - elapsed);

                        setTimeout(function() {
                            loading.hide();
                            updateBtn.prop('disabled', false);
                            message.removeClass('success-message error-message');
                            if (response.success) {
                                message.addClass('success-message').text(response.message).show();
                                showToast('Skills updated successfully!');
                                setTimeout(function() {
                                    message.hide();
                                }, 5000);
                            } else {
                                message.addClass('error-message').text(response.message).show();
                            }
                        }, delay);
                    },
                    error: function() {
                        const endTime = new Date().getTime();
                        const elapsed = endTime - startTime;
                        const delay = Math.max(0, 1000 - elapsed);

                        setTimeout(function() {
                            loading.hide();
                            updateBtn.prop('disabled', false);
                            message.addClass('error-message').text('Error updating skills.').show();
                        }, delay);
                    }
                });
            });

            // Handle hobbies form submission
            $('.hobbies-form').on('submit', function(e) {
                e.preventDefault();
                const form = $(this);
                const userId = form.data('user-id');
                const updateBtn = form.find('.hobbies-update-btn');
                const loading = form.find('.hobbies-loading');
                const message = form.find('.hobbies-message');

                const startTime = new Date().getTime();

                updateBtn.prop('disabled', true);
                loading.show();
                message.hide();

                const formData = form.serialize();

                $.ajax({
                    url: 'admin_manage_users.php',
                    type: 'POST',
                    data: formData + '&update_hobbies=1',
                    dataType: 'json',
                    success: function(response) {
                        const endTime = new Date().getTime();
                        const elapsed = endTime - startTime;
                        const delay = Math.max(0, 1000 - elapsed);

                        setTimeout(function() {
                            loading.hide();
                            updateBtn.prop('disabled', false);
                            message.removeClass('success-message error-message');
                            if (response.success) {
                                message.addClass('success-message').text(response.message).show();
                                showToast('Hobbies updated successfully!');
                                setTimeout(function() {
                                    message.hide();
                                }, 5000);
                            } else {
                                message.addClass('error-message').text(response.message).show();
                            }
                        }, delay);
                    },
                    error: function() {
                        const endTime = new Date().getTime();
                        const elapsed = endTime - startTime;
                        const delay = Math.max(0, 1000 - elapsed);

                        setTimeout(function() {
                            loading.hide();
                            updateBtn.prop('disabled', false);
                            message.addClass('error-message').text('Error updating hobbies.').show();
                        }, delay);
                    }
                });
            });
        });
    </script>
</body>
</html>
