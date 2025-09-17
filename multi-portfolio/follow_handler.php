<?php
session_start();
require_once "classes.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$follower_id = $_SESSION['user_id'];
$following_id = intval($_POST['following_id']);
$action = $_POST['action'];

if ($follower_id == $following_id) {
    header("Location: profile.php?username=" . $_SESSION['username']);
    exit();
}

$user = new User();
$user->getProfileById(user_id: $following_id);

if ($action === "follow") {
    $user->follow($follower_id, $following_id);
} elseif ($action === "unfollow") {
    $user->unfollow($follower_id, $following_id);
}

$profile_username = $_POST['profile_username'] ?? $_SESSION['username'];
header("Location: profile.php?username=" . urlencode($profile_username));
exit();
