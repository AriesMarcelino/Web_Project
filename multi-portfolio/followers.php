<?php
session_start();
include "classes.php";

$userObj = new User();
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

if (!isset($_GET['username']) || !isset($_GET['type'])) {
    echo "Invalid request.";
    exit();
}

$username = $_GET['username'];
$type = $_GET['type'];

$user = $userObj->getUserByUsername($username);
if (!$user) {
    echo "User not found.";
    exit();
}

$user_id = $user['id'];

if ($type === 'followers') {
    $list = $userObj->getFollowersList($user_id);
    $title = $username . "'s Followers";
} elseif ($type === 'following') {
    $list = $userObj->getFollowingList($user_id);
    $title = $username . "'s Following";
} else {
    echo "Invalid type.";
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo $title; ?></title>
    <link rel="stylesheet" href="profile.css">
    <style>
        .user-list {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
        }
        .user-item {
            text-align: center;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 8px;
            width: 150px;
        }
        .user-item img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
        }
        .user-item a {
            text-decoration: none;
            color: #333;
        }
        .user-item a:hover {
            color: #007bff;
        }
    </style>
</head>
<body>

    <!-- Navigation Bar -->
    <div class="navbar">
        <ul>
            <li><a href="profile.php?username=<?php echo urlencode($_SESSION['username'] ?? ''); ?>">Home</a></li>
            <li><a href="#portfolio">Portfolio</a></li>
            <li><a href="#projects">Projects</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="#contact">Contact</a></li>
            <a href="logout.php" class="logout-btn nav-logout">Logout</a>
        </ul>

        <!-- Search Bar -->
        <form method="POST" action="profile.php" class="search-form">
            <input type="text" name="search_username" placeholder="Search username" required>
            <button type="submit">Search</button>
        </form>
    </div>

    <div class="profile">
        <h2><?php echo $title; ?> (<?php echo count($list); ?>)</h2>
        <a href="profile.php?username=<?php echo urlencode($username); ?>">Back to Profile</a>
    </div>

    <div class="user-list">
        <?php if (empty($list)): ?>
            <p>No users found.</p>
        <?php else: ?>
            <?php foreach ($list as $u): ?>
                <div class="user-item">
                    <a href="profile.php?username=<?php echo urlencode($u['username']); ?>">
                        <img src="uploads/<?php echo $u['profile_pic']; ?>" alt="Profile Picture">
                        <p><?php echo $u['username']; ?></p>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</body>
</html>
