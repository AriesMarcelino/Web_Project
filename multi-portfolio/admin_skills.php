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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="font-sans m-0 p-0 bg-gray-100">
    <div class="hidden fixed inset-0 flex items-center justify-center bg-black bg-opacity-80 text-white p-5 rounded z-50" id="loading">
        <div>Processing...</div>
    </div>
    <header class="bg-gray-800 text-white p-4 flex justify-between items-center sticky top-0 z-10">
        <h1 class="flex-1 text-center">Skills Management</h1>
        <a href="logout.php" class="text-white bg-gray-600 px-3 py-1 rounded transition hover:bg-gray-800">Logout</a>
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

                <a href="admin_manage_users.php" class="group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'admin_manage_users.php' ? 'bg-slate-700 text-white shadow-lg' : 'text-slate-300 hover:bg-slate-700 hover:text-white'; ?>">
                    <svg class="w-5 h-5 mr-3 transition-colors duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'admin_manage_users.php' ? 'text-indigo-400' : 'text-slate-400 group-hover:text-indigo-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Per-User Skills & Hobbies
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

    <main class="md:ml-64 p-5 bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-800 mb-2">Skills Management</h2>
            <p class="text-gray-600">Manage predefined skills that users can select for their profiles.</p>
        </div>

        <div id="message-container"></div>

        <div class="mb-6">
            <button class="bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-3 rounded-lg hover:from-green-600 hover:to-green-700 transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center space-x-2" id="create-skill-btn">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                <span>Add Skill</span>
            </button>
        </div>

        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full" id="skills-table">
                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Skill Name</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($skills as $skill): ?>
                        <tr data-skill-id="<?php echo $skill['id']; ?>" class="hover:bg-gray-50 transition-colors duration-200">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $skill['id']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center mr-3">
                                        <span class="text-white text-xs font-bold"><?php echo strtoupper(substr($skill['skill_name'], 0, 1)); ?></span>
                                    </div>
                                    <?php echo htmlspecialchars($skill['skill_name']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <button class="bg-yellow-100 hover:bg-yellow-200 text-yellow-700 px-3 py-1 rounded-lg transition-all duration-200 transform hover:scale-105 edit-btn flex items-center space-x-1" data-skill-id="<?php echo $skill['id']; ?>" data-skill-name="<?php echo htmlspecialchars($skill['skill_name']); ?>">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        <span>Edit</span>
                                    </button>
                                    <button class="bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1 rounded-lg transition-all duration-200 transform hover:scale-105 delete-btn flex items-center space-x-1" data-skill-id="<?php echo $skill['id']; ?>">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        <span>Delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="mt-8 flex justify-center">
            <nav class="flex items-center space-x-1">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>" class="px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-all duration-200 flex items-center space-x-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        <span>Previous</span>
                    </a>
                <?php else: ?>
                    <span class="px-4 py-2 text-sm font-medium text-gray-300 bg-gray-100 border border-gray-200 rounded-lg cursor-not-allowed flex items-center space-x-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        <span>Previous</span>
                    </span>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                    if ($i == $page): ?>
                        <span class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-blue-500 to-blue-600 border border-blue-500 rounded-lg"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>" class="px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-all duration-200"><?php echo $i; ?></a>
                    <?php endif;
                endfor;
                ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>" class="px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-all duration-200 flex items-center space-x-1">
                        <span>Next</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                <?php else: ?>
                    <span class="px-4 py-2 text-sm font-medium text-gray-300 bg-gray-100 border border-gray-200 rounded-lg cursor-not-allowed flex items-center space-x-1">
                        <span>Next</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </span>
                <?php endif; ?>
            </nav>
        </div>
        <?php endif; ?>
    </main>

    <!-- Create Skill Modal -->
    <div id="create-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 transform transition-all duration-300 scale-100">
            <div class="bg-gradient-to-r from-green-500 to-green-600 p-6 rounded-t-2xl">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-bold text-white flex items-center space-x-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        <span>Add New Skill</span>
                    </h3>
                    <button class="text-white hover:text-gray-200 text-2xl font-bold" id="create-close">&times;</button>
                </div>
            </div>
            <div class="p-6">
                <form id="create-skill-form">
                    <div class="mb-4">
                        <label for="create-skill-name" class="block text-sm font-medium text-gray-700 mb-2">Skill Name</label>
                        <div class="relative">
                            <input type="text" name="skill_name" id="create-skill-name" placeholder="Enter skill name" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="flex space-x-3">
                        <button type="submit" id="create-submit-btn" class="flex-1 bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-3 rounded-lg hover:from-green-600 hover:to-green-700 transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Add Skill</span>
                        </button>
                        <button type="button" id="create-cancel-btn" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all duration-300 transform hover:scale-105">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Skill Modal -->
    <div id="edit-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 transform transition-all duration-300 scale-100">
            <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 p-6 rounded-t-2xl">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-bold text-white flex items-center space-x-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        <span>Edit Skill</span>
                    </h3>
                    <button class="text-white hover:text-gray-200 text-2xl font-bold" id="edit-close">&times;</button>
                </div>
            </div>
            <div class="p-6">
                <form id="edit-skill-form">
                    <input type="hidden" name="id" id="edit-skill-id">
                    <div class="mb-4">
                        <label for="edit-skill-name" class="block text-sm font-medium text-gray-700 mb-2">Skill Name</label>
                        <div class="relative">
                            <input type="text" name="skill_name" id="edit-skill-name" placeholder="Enter skill name" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition-all duration-200">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="flex space-x-3">
                        <button type="submit" id="edit-submit-btn" class="flex-1 bg-gradient-to-r from-yellow-500 to-yellow-600 text-white px-6 py-3 rounded-lg hover:from-yellow-600 hover:to-yellow-700 transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Update Skill</span>
                        </button>
                        <button type="button" id="edit-cancel-btn" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all duration-300 transform hover:scale-105">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Modal functions
            function openModal(modalId) {
                $('#' + modalId).removeClass('hidden').addClass('animate-fade-in');
                $('body').addClass('overflow-hidden');
            }
            function closeModal(modalId) {
                $('#' + modalId).addClass('hidden').removeClass('animate-fade-in');
                $('body').removeClass('overflow-hidden');
            }

            // Show loading spinner
            function showLoading() {
                $('#loading').removeClass('hidden');
            }
            // Hide loading spinner
            function hideLoading() {
                $('#loading').addClass('hidden');
            }
            // Show message
            function showMessage(message, type = 'success') {
                const classes = type === 'success' ? 'bg-green-100 text-green-800 border border-green-200 p-3 mb-3 rounded' : 'bg-red-100 text-red-800 border border-red-200 p-3 mb-3 rounded';
                const messageDiv = $('<div>')
                    .addClass(classes)
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
            $('#create-modal').on('click', function(event) {
                if (event.target === this) {
                    closeModal('create-modal');
                }
            });
            $('#edit-modal').on('click', function(event) {
                if (event.target === this) {
                    closeModal('edit-modal');
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
                                <tr data-skill-id="${response.skill_id}" class="hover:bg-gray-50 transition-colors duration-200">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${response.skill_id}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center mr-3">
                                                <span class="text-white text-xs font-bold">${response.skill_name.charAt(0).toUpperCase()}</span>
                                            </div>
                                            ${response.skill_name}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button class="bg-yellow-100 hover:bg-yellow-200 text-yellow-700 px-3 py-1 rounded-lg transition-all duration-200 transform hover:scale-105 edit-btn flex items-center space-x-1" data-skill-id="${response.skill_id}" data-skill-name="${response.skill_name}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                                <span>Edit</span>
                                            </button>
                                            <button class="bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1 rounded-lg transition-all duration-200 transform hover:scale-105 delete-btn flex items-center space-x-1" data-skill-id="${response.skill_id}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                                <span>Delete</span>
                                            </button>
                                        </div>
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
                            row.find('td:nth-child(2) .flex.items-center').contents().last().replaceWith(newName);
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
