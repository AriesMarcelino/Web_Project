<?php
session_start();
include "classes.php";

$userObj = new User();
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

if (!isset($_GET['username'])) {
    echo "Invalid request.";
    exit();
}

$username = $_GET['username'];
$type = $_GET['type'] ?? 'followers'; // Default to followers

$user = $userObj->getUserByUsername($username);
if (!$user) {
    echo "User not found.";
    exit();
}

$user_id = $user['id'];

if (isset($_POST['search_username']) && !empty($_POST['search_username'])) {
    $searched_user = $userObj->getUserByUsername($_POST['search_username']);
    if ($searched_user) {
        // Redirect to the GET version to update URL
        header("Location: profile.php?username=" . urlencode($searched_user['username']));
        exit();
    } else {
        $_SESSION['notification'] = "User '{$_POST['search_username']}' not found. Showing your profile.";
        $_SESSION['notification_type'] = 'info';
        // Redirect back to current user's profile
        header("Location: profile.php?username=" . urlencode($_SESSION['username']));
        exit();
    }
}

$followers_list = $userObj->getFollowersList($user_id);
$following_list = $userObj->getFollowingList($user_id);

if ($type == 'followers') {
    $list = $followers_list;
    $title = $username . "'s Followers";
} else {
    $list = $following_list;
    $title = $username . "'s Following";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo $title; ?></title>
    <link rel="stylesheet" href="profile.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class'
        }
    </script>
</head>
<body>

    <!-- Navigation Bar -->
    <nav class="bg-gray-800 dark:bg-gray-900 text-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-11">
                    <a href="profile.php?username=<?php echo urlencode($_SESSION['username']); ?>" class="text-white hover:text-blue-300 transition duration-200">Home</a>
                    <a href="#portfolio" class="text-white hover:text-blue-300 transition duration-200">Portfolio</a>
                    <a href="#projects" class="text-white hover:text-blue-300 transition duration-200">Projects</a>
                    <a href="#about" class="text-white hover:text-blue-300 transition duration-200">About</a>
                    <a href="#contact" class="text-white hover:text-blue-300 transition duration-200">Contact</a>
                </div>
                <div class="flex items-center space-x-4">
                    <!-- Search Bar -->
                    <form method="POST" class="flex">
                        <input type="text" name="search_username" placeholder="Search username" required class="px-3 py-2 rounded-l-lg border-0 focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 px-4 py-2 rounded-r-lg transition duration-200">Search</button>
                    </form>
                    <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg transition duration-200">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto p-4">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white"><?php echo $title; ?> (<?php echo count($list); ?>)</h1>
            <a href="profile.php?username=<?php echo urlencode($username); ?>" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Back to Profile</a>
        </div>

        <!-- Tab Navigation -->
        <div class="flex justify-center mb-6 border-b border-gray-200 dark:border-gray-700">
            <button onclick="switchTab('followers')" class="tab-button px-6 py-3 mx-2 font-semibold text-gray-600 dark:text-gray-300 hover:text-blue-500 dark:hover:text-blue-400 border-b-2 border-transparent <?php echo $type == 'followers' ? 'text-blue-500 dark:text-blue-400 border-blue-500' : ''; ?> transition duration-300">Followers</button>
            <button onclick="switchTab('following')" class="tab-button px-6 py-3 mx-2 font-semibold text-gray-600 dark:text-gray-300 hover:text-blue-500 dark:hover:text-blue-400 border-b-2 border-transparent <?php echo $type == 'following' ? 'text-blue-500 dark:text-blue-400 border-blue-500' : ''; ?> transition duration-300">Following</button>
        </div>

        <?php if (empty($list)): ?>
            <div class="text-center py-12">
                <div class="text-6xl mb-4"><?php echo $type == 'followers' ? '👥' : '🔍'; ?></div>
                <h3 class="text-xl font-semibold text-gray-700 dark:text-gray-300 mb-2"><?php echo $type == 'followers' ? 'No followers yet' : 'Not following anyone'; ?></h3>
                <p class="text-gray-500 dark:text-gray-400"><?php echo $type == 'followers' ? "👥 No followers yet. Be the first to follow $username!" : "🔍 $username hasn't followed anyone yet."; ?></p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach ($list as $u): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md hover:shadow-lg transition duration-300 p-4 text-center">
                        <a href="profile.php?username=<?php echo urlencode($u['username']); ?>" class="block">
                            <img src="uploads/<?php echo $u['profile_pic']; ?>" alt="Profile Picture" class="w-20 h-20 rounded-full mx-auto mb-3 object-cover border-2 border-gray-200 dark:border-gray-600">
                            <h4 class="font-semibold text-gray-800 dark:text-white hover:text-blue-500 dark:hover:text-blue-400 transition duration-300"><?php echo htmlspecialchars($u['username']); ?></h4>
                            <?php if (!empty($u['bio'])): ?>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2 line-clamp-2"><?php echo htmlspecialchars(substr($u['bio'], 0, 50)); ?>...</p>
                            <?php endif; ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function switchTab(type) {
            const url = new URL(window.location);
            url.searchParams.set('type', type);
            window.location.href = url.toString();
        }
    </script>

</body>
</html>
