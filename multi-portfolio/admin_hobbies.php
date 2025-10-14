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

    // Handle hobby creation
    if (isset($_POST['create'])) {
        $hobby_name = trim($_POST['hobby_name']);
        if ($hobby_name !== '') {
            $hobby_id = $userObj->addHobby($hobby_name);
            if ($hobby_id) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'hobby_id' => $hobby_id,
                    'hobby_name' => $hobby_name
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error adding hobby.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Hobby name cannot be empty.']);
        }
        exit();
    }

    // Handle hobby update
    if (isset($_POST['update'])) {
        $hobby_id = (int)$_POST['id'];
        $new_name = trim($_POST['hobby_name']);
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

    // Handle hobby deletion
    if (isset($_POST['delete'])) {
        $hobby_id = (int)$_POST['id'];
        $success = $userObj->deleteHobby($hobby_id);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Hobby deleted successfully!' : 'Error deleting hobby.'
        ]);
        exit();
    }
}

// Get current page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 5;
$userObj = new User();
$hobbies = $userObj->getHobbiesPaginated($page, $limit);
$total_hobbies = $userObj->getTotalHobbies();
$total_pages = ceil($total_hobbies / $limit);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Hobbies Management - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="font-sans m-0 p-0 bg-gray-100">
    <div class="hidden fixed inset-0 flex items-center justify-center bg-black bg-opacity-80 text-white p-5 rounded z-50" id="loading">
        <div>Processing...</div>
    </div>
    <header class="bg-gray-800 text-white p-4 flex justify-between items-center sticky top-0 z-10">
        <h1 class="flex-1 text-center">Hobbies Management</h1>
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
            <h2 class="text-3xl font-bold text-gray-800 mb-2">Hobbies Management</h2>
            <p class="text-gray-600">Manage predefined hobbies that users can select for their profiles.</p>
        </div>

        <div id="message-container"></div>

        <div class="mb-6">
            <button class="bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-3 rounded-lg hover:from-green-600 hover:to-green-700 transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center space-x-2" id="create-hobby-btn">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                <span>Add Hobby</span>
            </button>
        </div>

        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full" id="hobbies-table">
                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Hobby Name</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($hobbies as $hobby): ?>
                        <tr data-hobby-id="<?php echo $hobby['id']; ?>" class="hover:bg-gray-50 transition-colors duration-200">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $hobby['id']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-gradient-to-r from-purple-500 to-purple-600 rounded-full flex items-center justify-center mr-3">
                                        <span class="text-white text-xs font-bold"><?php echo strtoupper(substr($hobby['hobby_name'], 0, 1)); ?></span>
                                    </div>
                                    <?php echo htmlspecialchars($hobby['hobby_name']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <button class="bg-yellow-100 hover:bg-yellow-200 text-yellow-700 px-3 py-1 rounded-lg transition-all duration-200 transform hover:scale-105 edit-btn flex items-center space-x-1" data-hobby-id="<?php echo $hobby['id']; ?>" data-hobby-name="<?php echo htmlspecialchars($hobby['hobby_name']); ?>">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        <span>Edit</span>
                                    </button>
                                    <button class="bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1 rounded-lg transition-all duration-200 transform hover:scale-105 delete-btn flex items-center space-x-1" data-hobby-id="<?php echo $hobby['id']; ?>">
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

        <div class="mt-8 flex justify-center">
            <nav class="flex items-center space-x-1">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>" class="flex items-center px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-l-lg hover:bg-gray-50 hover:text-gray-700 transition-colors duration-200">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Previous
                    </a>
                <?php else: ?>
                    <span class="flex items-center px-3 py-2 text-sm font-medium text-gray-300 bg-gray-100 border border-gray-300 rounded-l-lg cursor-not-allowed">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Previous
                    </span>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-gradient-to-r from-blue-500 to-blue-600 border border-blue-500 rounded-md shadow-sm"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>" class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-blue-600 transition-colors duration-200"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>" class="flex items-center px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-r-lg hover:bg-gray-50 hover:text-gray-700 transition-colors duration-200">
                        Next
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                <?php else: ?>
                    <span class="flex items-center px-3 py-2 text-sm font-medium text-gray-300 bg-gray-100 border border-gray-300 rounded-r-lg cursor-not-allowed">
                        Next
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </span>
                <?php endif; ?>
            </nav>
        </div>
    </main>

    <!-- Create Hobby Modal -->
    <div id="create-modal" class="hidden fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm flex items-center justify-center z-50 animate-fade-in">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 transform transition-all duration-300 scale-100 animate-modal-appear">
            <div class="bg-gradient-to-r from-green-500 to-green-600 p-6 rounded-t-2xl">
                <div class="flex justify-between items-center">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white">Add New Hobby</h3>
                    </div>
                    <button class="text-white hover:text-gray-200 text-2xl transition-colors duration-200" id="create-close">&times;</button>
                </div>
            </div>
            <div class="p-6">
                <form id="create-hobby-form">
                    <div class="mb-4">
                        <label for="create-hobby-name" class="block text-sm font-medium text-gray-700 mb-2">Hobby Name</label>
                        <input type="text" name="hobby_name" id="create-hobby-name" placeholder="Enter hobby name" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200">
                    </div>
                    <div class="flex space-x-3">
                        <button type="submit" id="create-submit-btn" class="flex-1 bg-gradient-to-r from-green-500 to-green-600 text-white py-3 px-4 rounded-lg hover:from-green-600 hover:to-green-700 transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            <span>Add Hobby</span>
                        </button>
                        <button type="button" id="create-cancel-btn" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-all duration-200 transform hover:scale-105">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Hobby Modal -->
    <div id="edit-modal" class="hidden fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm flex items-center justify-center z-50 animate-fade-in">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 transform transition-all duration-300 scale-100 animate-modal-appear">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6 rounded-t-2xl">
                <div class="flex justify-between items-center">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white">Edit Hobby</h3>
                    </div>
                    <button class="text-white hover:text-gray-200 text-2xl transition-colors duration-200" id="edit-close">&times;</button>
                </div>
            </div>
            <div class="p-6">
                <form id="edit-hobby-form">
                    <input type="hidden" name="id" id="edit-hobby-id">
                    <div class="mb-4">
                        <label for="edit-hobby-name" class="block text-sm font-medium text-gray-700 mb-2">Hobby Name</label>
                        <input type="text" name="hobby_name" id="edit-hobby-name" placeholder="Enter hobby name" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                    </div>
                    <div class="flex space-x-3">
                        <button type="submit" id="edit-submit-btn" class="flex-1 bg-gradient-to-r from-blue-500 to-blue-600 text-white py-3 px-4 rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Update Hobby</span>
                        </button>
                        <button type="button" id="edit-cancel-btn" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-all duration-200 transform hover:scale-105">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {

            // Modal functions
            function openModal(modalId) {
                $('#' + modalId).removeClass('hidden');
            }
            function closeModal(modalId) {
                $('#' + modalId).addClass('hidden');
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

            // Create hobby button
            $('#create-hobby-btn').on('click', function() {
                $('#create-hobby-form')[0].reset();
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

            // Create hobby AJAX
            $('#create-hobby-form').on('submit', function(e) {
                e.preventDefault();
                const formData = $(this).serialize();
                const createBtn = $('#create-submit-btn');
                const originalText = createBtn.text();
                createBtn.text('Adding...').prop('disabled', true);
                showLoading();
                $.ajax({
                    url: 'admin_hobbies.php',
                    type: 'POST',
                    data: formData + '&create=1',
                    dataType: 'json',
                    success: function(response) {
                        hideLoading();
                        createBtn.text(originalText).prop('disabled', false);
                        if (response.success) {
                            showMessage('Hobby added successfully!');
                            closeModal('create-modal');
                            // Add new hobby to table
                            const newRow = `
                                <tr data-hobby-id="${response.hobby_id}" class="hover:bg-gray-50 transition-colors duration-200">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${response.hobby_id}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-gradient-to-r from-purple-500 to-purple-600 rounded-full flex items-center justify-center mr-3">
                                                <span class="text-white text-xs font-bold">${response.hobby_name.charAt(0).toUpperCase()}</span>
                                            </div>
                                            ${response.hobby_name}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button class="bg-yellow-100 hover:bg-yellow-200 text-yellow-700 px-3 py-1 rounded-lg transition-all duration-200 transform hover:scale-105 edit-btn flex items-center space-x-1" data-hobby-id="${response.hobby_id}" data-hobby-name="${response.hobby_name}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                                <span>Edit</span>
                                            </button>
                                            <button class="bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1 rounded-lg transition-all duration-200 transform hover:scale-105 delete-btn flex items-center space-x-1" data-hobby-id="${response.hobby_id}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                                <span>Delete</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            `;
                            $('#hobbies-table tbody').append(newRow);
                        } else {
                            showMessage(response.message || 'Error adding hobby', 'error');
                        }
                    },
                    error: function() {
                        hideLoading();
                        createBtn.text(originalText).prop('disabled', false);
                        showMessage('Error adding hobby', 'error');
                    }
                });
            });

            // Edit hobby button
            $(document).on('click', '.edit-btn', function() {
                const hobbyId = $(this).data('hobby-id');
                const hobbyName = $(this).data('hobby-name');
                $('#edit-hobby-id').val(hobbyId);
                $('#edit-hobby-name').val(hobbyName);
                openModal('edit-modal');
            });

            // Update hobby AJAX
            $('#edit-hobby-form').on('submit', function(e) {
                e.preventDefault();
                const formData = $(this).serialize();
                const updateBtn = $('#edit-submit-btn');
                const originalText = updateBtn.text();
                updateBtn.text('Updating...').prop('disabled', true);
                showLoading();
                $.ajax({
                    url: 'admin_hobbies.php',
                    type: 'POST',
                    data: formData + '&update=1',
                    dataType: 'json',
                    success: function(response) {
                        hideLoading();
                        updateBtn.text(originalText).prop('disabled', false);
                        if (response.success) {
                            showMessage('Hobby updated successfully!');
                            closeModal('edit-modal');
                            // Update the table row
                            const hobbyId = $('#edit-hobby-id').val();
                            const newName = $('#edit-hobby-name').val();
                            const row = $(`tr[data-hobby-id="${hobbyId}"]`);
                            const nameCell = row.find('td:nth-child(2)');
                            nameCell.html(`
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-gradient-to-r from-purple-500 to-purple-600 rounded-full flex items-center justify-center mr-3">
                                        <span class="text-white text-xs font-bold">${newName.charAt(0).toUpperCase()}</span>
                                    </div>
                                    ${newName}
                                </div>
                            `);
                            row.find('.edit-btn').data('hobby-name', newName);
                        } else {
                            showMessage(response.message || 'Error updating hobby', 'error');
                        }
                    },
                    error: function() {
                        hideLoading();
                        updateBtn.text(originalText).prop('disabled', false);
                        showMessage('Error updating hobby', 'error');
                    }
                });
            });

            // Delete hobby AJAX
            $(document).on('click', '.delete-btn', function() {
                const hobbyId = $(this).data('hobby-id');
                const row = $(this).closest('tr');
                if (confirm('Are you sure you want to delete this hobby?')) {
                    const deleteBtn = $(this);
                    const originalIcon = deleteBtn.html();
                    deleteBtn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
                    showLoading();
                    $.ajax({
                        url: 'admin_hobbies.php',
                        type: 'POST',
                        data: { id: hobbyId, delete: 1 },
                        dataType: 'json',
                        success: function(response) {
                            hideLoading();
                            deleteBtn.html(originalIcon).prop('disabled', false);
                            if (response.success) {
                                showMessage('Hobby deleted successfully!');
                                row.fadeOut(function() {
                                    $(this).remove();
                                });
                            } else {
                                showMessage(response.message || 'Error deleting hobby', 'error');
                            }
                        },
                        error: function() {
                            hideLoading();
                            deleteBtn.html(originalIcon).prop('disabled', false);
                            showMessage('Error deleting hobby', 'error');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>
