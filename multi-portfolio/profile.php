<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
});
set_exception_handler(function ($e) {
    error_log("Uncaught Exception: " . $e->getMessage());
    http_response_code(500);
    echo "An unexpected error occurred. Please try again later.";
});

session_start();
include "classes.php";

function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

$userObj = new User();
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Fetch current user data
$user = $userObj->getUserByUsername($_SESSION['username']);

if (!$user) {
    echo "User not found!";
        exit();
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_name_username') {
        // AJAX handling for updating name and username
        if (!$current_user_id) {
            echo json_encode(['success' => false, 'message' => 'Session expired.']);
            exit();
        }

        $full_name = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');

        if (empty($full_name) || empty($username)) {
            echo json_encode(['success' => false, 'message' => 'Full name and username are required.']);
            exit();
        }

        error_log("Update name/username attempt: user_id=$current_user_id, full_name='$full_name', username='$username'");

        // Check if username is already taken by another user
        if ($username !== $_SESSION['username']) {
            $existing_user = $userObj->getUserByUsername($username);
            if ($existing_user) {
                echo json_encode(['success' => false, 'message' => 'Username already taken. Please choose a different one.']);
                exit();
            }
        }

        // Update using updateUserInfo
        $success = $userObj->updateUserInfo($current_user_id, null, null, null, null, $full_name, $username);

        if ($success) {
            error_log("Update success");
            // Reload user data
            $user = $userObj->getUserByUsername($username);
            if ($user) {
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                echo json_encode(['success' => true, 'full_name' => $user['full_name'], 'username' => $user['username']]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to reload user data.']);
            }
        } else {
            error_log("Update failed");
            echo json_encode(['success' => false, 'message' => 'Invalid update. Please try again.']);
        }
        exit();
    }

$user_id = $user['id'];

// Fetch predefined skills and hobbies
$predefined_skills = $userObj->getPredefinedSkills();
$predefined_hobbies = $userObj->getPredefinedHobbies();

// Handle profile update POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
    // Check session for AJAX
    if (isAjaxRequest() && !$current_user_id) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit();
    }
    if (!$current_user_id) {
        $error_msg = "Unauthorized access.";
        if (isAjaxRequest()) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error_msg]);
            exit();
        } else {
            echo $error_msg;
            exit();
        }
    }
    if ($current_user_id != $_POST['user_id']) {
        $error_msg = "Unauthorized update attempt.";
        if (isAjaxRequest()) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error_msg]);
            exit();
        } else {
            echo $error_msg;
            exit();
        }
    }

    try {
        // Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_result = $userObj->uploadProfilePicture($current_user_id, $_FILES['profile_picture']);
            if ($upload_result !== true) {
                $error_msg = "Error uploading profile picture: " . htmlspecialchars($upload_result);
                if (isAjaxRequest()) {
                    ob_clean();
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => $error_msg]);
                    exit();
                } else {
                    echo "<p style='color:red;'>$error_msg</p>";
                    exit();
                }
            }
        }

        // Sanitize and collect user inputs, preserving existing values if not provided
        $bio = isset($_POST['bio']) ? $_POST['bio'] : $user['bio'];
        $background = isset($_POST['background']) ? $_POST['background'] : $user['background'];
        $years_experience = isset($_POST['years_experience']) ? intval($_POST['years_experience']) : $user['years_experience'];
        $email = isset($_POST['email']) ? $_POST['email'] : $user['email'];

        // Update main user info
        $success = $userObj->updateUserInfo($current_user_id, $bio, $background, $years_experience, $email);

        // Update pivot tables only if data is provided
        if (isset($_POST['skills'])) {
            $success &= $userObj->updateSkills($current_user_id, $_POST['skills']);
        }
        if (isset($_POST['hobbies'])) {
            $success &= $userObj->updateHobbies($current_user_id, $_POST['hobbies']);
        }
        if (isset($_POST['projects'])) {
            $success &= $userObj->updateProjects($current_user_id, $_POST['projects'], $_POST['project_descriptions'] ?? []);
        }
        if (isset($_POST['awards'])) {
            $success &= $userObj->updateAwards($current_user_id, $_POST['awards'], $_POST['award_years'] ?? []);
        }
        if (isset($_POST['certificates'])) {
            $success &= $userObj->updateCertificates($current_user_id, $_POST['certificates'], $_POST['certificate_issuers'] ?? []);
        }

        // Build social media array only if provided
        if (isset($_POST['social_media_platforms'])) {
            $social_media = [];
            $platforms = $_POST['social_media_platforms'];
            $urls = $_POST['social_media_urls'] ?? [];
            foreach ($platforms as $index => $platform) {
                $url = $urls[$index] ?? '';
                if (trim($platform) !== '' && trim($url) !== '') {
                    $social_media[] = ['platform' => $platform, 'url' => $url];
                }
            }
            $success &= $userObj->updateSocialMedia($current_user_id, $social_media);
        }

        // Reload updated user data only if successful
        if ($success) {
            $user = $userObj->getUserByUsername($_SESSION['username']);
            if (!$user) {
                $success = false;
                $error_msg = "Failed to reload user data after update.";
            } else {
                $skills = $userObj->getSkills($user_id);
                $hobbies = $userObj->getHobbies($user_id);
                $projects = $userObj->getProjects($user_id);
                $awards = $userObj->getAwards($user_id);
                $certificates = $userObj->getCertificates($user_id);
                $social_media = $userObj->getSocialMedia($user_id);
            }
        }
    } catch (Exception $e) {
        $success = false;
        $error_msg = $e->getMessage();
    }

    // Return appropriate response based on request type
    if (isAjaxRequest()) {
        ob_clean();
        header('Content-Type: application/json');
        if ($success) {
            echo json_encode([
                'success' => true,
                'message' => 'Profile updated successfully!',
                'user' => $user,
                'skills' => $skills,
                'hobbies' => $hobbies,
                'projects' => $projects,
                'awards' => $awards,
                'certificates' => $certificates,
                'social_media' => $social_media
            ]);
        } else {
            $message = isset($error_msg) ? $error_msg : 'Failed to update profile. Please try again.';
            echo json_encode(['success' => false, 'message' => $message]);
        }
        exit();
    }
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($_POST['action'] === 'create_skill' || $_POST['action'] === 'create_hobby')) {
    // AJAX handling for creating skills/hobbies
    ob_clean();
    header('Content-Type: application/json');

    if ($_POST['action'] === 'create_skill' && isset($_POST['skill_name'])) {
        $skill_name = trim($_POST['skill_name']);
        if (!empty($skill_name)) {
            $skill_id = $userObj->addSkill($skill_name);
            if ($skill_id) {
                echo json_encode(['success' => true, 'id' => $skill_id, 'name' => $skill_name]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add skill']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Skill name is required']);
        }
        exit();
    }

    if ($_POST['action'] === 'create_hobby' && isset($_POST['hobby_name'])) {
        $hobby_name = trim($_POST['hobby_name']);
        if (!empty($hobby_name)) {
            $hobby_id = $userObj->addHobby($hobby_name);
            if ($hobby_id) {
                echo json_encode(['success' => true, 'id' => $hobby_id, 'name' => $hobby_name]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add hobby']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Hobby name is required']);
        }
        exit();
    }
}
    if (isset($_POST['action']) && $_POST['action'] === 'update_name_username') {
        // AJAX handling for updating name and username
        ob_clean();
        header('Content-Type: application/json');

        if (!$current_user_id) {
            echo json_encode(['success' => false, 'message' => 'Session expired.']);
            exit();
        }

        $full_name = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');

        if (empty($full_name) || empty($username)) {
            echo json_encode(['success' => false, 'message' => 'Full name and username are required.']);
            exit();
        }

    error_log("Update name/username attempt: user_id=$current_user_id, full_name='$full_name', username='$username'");

    // Check if username is already taken by another user
    if ($username !== $_SESSION['username']) {
        $existing_user = $userObj->getUserByUsername($username);
        if ($existing_user) {
            echo json_encode(['success' => false, 'message' => 'Username already taken. Please choose a different one.']);
            exit();
        }
    }

    // Update using updateUserInfo
    $success = $userObj->updateUserInfo($current_user_id, null, null, null, null, $full_name, $username);

        if ($success) {
            error_log("Update success");
            // Reload user data
            $user = $userObj->getUserByUsername($username);
            if ($user) {
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                echo json_encode(['success' => true, 'full_name' => $user['full_name'], 'username' => $user['username']]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to reload user data.']);
            }
        } else {
            error_log("Update failed");
            echo json_encode(['success' => false, 'message' => 'Invalid update. Please try again.']);
        }
        exit();
    }
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
} else {
    if (isset($_GET['username'])) {
        $user = $userObj->getUserByUsername($_GET['username']);
        if (!$user) {
            $_SESSION['notification'] = "User not found.";
            $_SESSION['notification_type'] = 'error';
            header("Location: profile.php?username=" . $_SESSION['username']);
            exit();
        }
    } else {
        // No GET, use current user (already set)
    }
}

if (!$user) {
    echo "User not found!";
    exit();
}

$user_id = $user['id'];

$skills = $userObj->getSkills($user_id);
$hobbies = $userObj->getHobbies($user_id);
$projects = $userObj->getProjects($user_id);
$awards = $userObj->getAwards($user_id);
$certificates = $userObj->getCertificates($user_id);
$social_media = $userObj->getSocialMedia($user_id);

$followers_count = $userObj->getFollowersCount($user_id);
$following_count = $userObj->getFollowingCount($user_id);

$isFollowing = false;
if ($current_user_id && $current_user_id != $user['id']) {
    $isFollowing = $userObj->isFollowing(follower_id: $current_user_id, following_id: $user_id);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo $user['full_name']; ?>'s Profile</title>
    <link rel="stylesheet" href="profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 CSS and JS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        // Notification display function
        function showNotification(message, type = 'info', duration = 3000) {
            var notification = $('#notification');
            notification.removeClass('success error info fade-out');
            notification.addClass(type);
            notification.text(message);
            notification.show();
 
            setTimeout(function() {
                notification.addClass('fade-out');
                setTimeout(function() {
                    notification.hide();
                }, 500);
            }, duration);
        }

        $(document).ready(function() {

            // Improved addInput function with jQuery
            window.addInput = function(containerId, fields) {
                var container = $('#' + containerId);
                var inputGroup = $('<div class="input-group"></div>');

                fields.forEach(function(field) {
                    var input = $('<input>', {
                        type: field.type || 'text',
                        name: field.name,
                        placeholder: field.placeholder || 'Add new',
                        class: 'dynamic-input'
                    });
                    inputGroup.append(input);
                });

                // Add remove button
                var removeBtn = $('<button type="button" class="remove-btn">Remove</button>');
                removeBtn.click(function() {
                    inputGroup.fadeOut(300, function() {
                        $(this).remove();
                    });
                });
                inputGroup.append(removeBtn);

                container.append(inputGroup);
                inputGroup.hide().slideDown(300);
            };

            // Handle follower/following clicks with smooth transitions
            $('#followers').click(function() {
                $(this).fadeTo(200, 0.5).fadeTo(200, 1.0);
                window.location.href = 'followers.php?username=<?php echo urlencode($user['username']); ?>&type=followers';
            });

            $('#following').click(function() {
                $(this).fadeTo(200, 0.5).fadeTo(200, 1.0);
                window.location.href = 'followers.php?username=<?php echo urlencode($user['username']); ?>&type=following';
            });

            // Real-time search functionality
            $('.search-form input[name="search_username"]').on('input', function() {
                var query = $(this).val();
                if (query.length >= 2) {

                    $(this).addClass('searching');
                } else {
                    $(this).removeClass('searching');
                }
            });

            $('.navbar a[href^="#"]').click(function(e) {
                e.preventDefault();
                var target = $($(this).attr('href'));
                if (target.length) {
                    $('html, body').animate({
                        scrollTop: target.offset().top - 50
                    }, 500);
                }
            });

            // Example usage: showNotification('Profile updated successfully!', 'success');
            <?php if (isset($_SESSION['notification'])): ?>
                showNotification("<?php echo addslashes($_SESSION['notification']); ?>", "<?php echo $_SESSION['notification_type'] ?? 'info'; ?>");
                <?php unset($_SESSION['notification'], $_SESSION['notification_type']); ?>
            <?php endif; ?>
        });
    </script>

</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100">

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
                    <button id="theme-toggle" class="p-2 rounded-lg bg-gray-700 hover:bg-gray-600 dark:bg-gray-600 dark:hover:bg-gray-500 transition duration-200">
                        <i class="fas fa-moon"></i>
                    </button>
                    <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg transition duration-200">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Profile Header -->
    <div class="bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-800 dark:to-gray-900 shadow-xl rounded-xl p-8 mx-auto max-w-4xl mt-10 transform transition-all duration-300 hover:shadow-2xl hover:scale-105">
        <div class="flex flex-col items-center justify-center mb-6">
            <img src="uploads/<?php echo $user['profile_pic']; ?>" alt="Profile Picture" class="w-40 h-40 rounded-full shadow-lg border-4 border-white dark:border-gray-700 transform transition-transform duration-300 hover:scale-110">
            <?php if ($current_user_id == $user_id): ?>
            <button id="edit-profile-pic" class="mt-4 bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded-lg shadow-lg transform transition-transform duration-300 hover:scale-110 flex items-center" title="Change Profile Picture"><i class="fas fa-camera mr-2"></i>Change Profile Picture</button>
            <?php endif; ?>
        </div>
        <input type="file" id="profile-pic-input" accept="image/*" style="display: none;">
        <div class="text-center mb-3">
            <h2 class="text-4xl font-extrabold text-gray-900 dark:text-white inline-block"><?php echo $user['full_name']; ?></h2>
            <?php if ($current_user_id == $user_id): ?>
            <button class="edit-name-btn ml-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 p-1 rounded transition duration-200" title="Edit Full Name">&#9998;</button>
            <?php endif; ?>
        </div>
        <div class="text-center mb-6">
            <p class="text-gray-700 dark:text-gray-200 text-lg profile-username inline-block">@<?php echo $user['username']; ?></p>
            <?php if ($current_user_id == $user_id): ?>
            <button class="edit-username-btn ml-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 p-1 rounded transition duration-200" title="Edit Username">&#9998;</button>
            <?php endif; ?>
        </div>
        <p class="text-center text-gray-700 dark:text-gray-200 mb-6 text-lg profile-email"><?php echo $user['email']; ?></p>

        <!-- Stats & Buttons -->
        <div class="text-center mb-4">
            <span class="text-gray-600 dark:text-gray-300 cursor-pointer hover:text-blue-500 transition duration-200" id="followers">Followers: <?php echo $followers_count; ?></span> |
            <span class="text-gray-600 dark:text-gray-300 cursor-pointer hover:text-blue-500 transition duration-200" id="following">Following: <?php echo $following_count; ?></span>
        </div>

        <!-- Social Media -->
        <div class="flex justify-center space-x-4 mb-4 social-links">
            <?php foreach ($social_media as $social): ?>
                <?php
                $icon = '';
                if ($social['platform'] == 'Facebook') $icon = 'facebook.png';
                elseif ($social['platform'] == 'Instagram') $icon = 'instagram.png';
                elseif ($social['platform'] == 'X') $icon = 'twitter.png';
                ?>
                <a href="<?php echo $social['url']; ?>" target="_blank" class="hover:opacity-75 transition duration-200 transform hover:scale-110">
                    <img src="icons/<?php echo $icon; ?>" alt="<?php echo $social['platform']; ?>" class="w-8 h-8">
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($current_user_id && $current_user_id != $user_id): ?>
            <form method="POST" action="follow_handler.php" class="text-center">
                <input type="hidden" name="following_id" value="<?php echo $user_id; ?>">
                <input type="hidden" name="profile_username" value="<?php echo $user['username']; ?>">
                <button class="bg-blue-500 hover:bg-blue-600 dark:bg-blue-600 dark:hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition duration-200 shadow-md" type="submit" name="action" value="<?php echo $isFollowing ? 'unfollow' : 'follow'; ?>">
                    <?php echo $isFollowing ? 'Unfollow' : 'Follow'; ?>
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Notification container -->
    <div id="notification" class="notification" style="display: none;"></div>

    <!-- Layout: Left + Right -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8">
        <div id="view-mode" class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Left side -->
            <div class="space-y-6 mb-8">
                <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-700 shadow-lg rounded-xl p-6 section-bio transform transition-all duration-300 hover:shadow-2xl hover:scale-105 hover:-translate-y-1">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white"><i class="fas fa-user mr-2 text-blue-500"></i>Bio</h3>
                        <?php if ($current_user_id == $user_id): ?>
                        <button class="edit-btn bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 p-2 rounded transition duration-200" title="Edit Bio">&#9998;</button>
                        <?php endif; ?>
                    </div>
                    <p class="text-gray-700 dark:text-gray-200 leading-relaxed"><?php echo $user['bio']; ?></p>
                </div>
                <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-700 shadow-lg rounded-xl p-6 section-background transform transition-all duration-300 hover:shadow-2xl hover:scale-105 hover:-translate-y-1">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white"><i class="fas fa-info-circle mr-2 text-green-500"></i>Background</h3>
                        <?php if ($current_user_id == $user_id): ?>
                        <button class="edit-btn bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 p-2 rounded transition duration-200" title="Edit Background">&#9998;</button>
                        <?php endif; ?>
                    </div>
                    <p class="text-gray-700 dark:text-gray-200 leading-relaxed"><?php echo $user['background']; ?></p>
                </div>
                <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-700 shadow-lg rounded-xl p-6 section-email transform transition-all duration-300 hover:shadow-2xl hover:scale-105 hover:-translate-y-1">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white"><i class="fas fa-envelope mr-2 text-purple-500"></i>Email</h3>
                        <?php if ($current_user_id == $user_id): ?>
                        <button class="edit-btn bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 p-2 rounded transition duration-200" title="Edit Email">&#9998;</button>
                        <?php endif; ?>
                    </div>
                    <p class="text-gray-700 dark:text-gray-200 leading-relaxed"><?php echo $user['email']; ?></p>
                </div>
                <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-700 shadow-lg rounded-xl p-6 section-hobbies transform transition-all duration-300 hover:shadow-2xl hover:scale-105 hover:-translate-y-1">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white"><i class="fas fa-heart mr-2 text-red-500"></i>Hobbies</h3>
                        <?php if ($current_user_id == $user_id): ?>
                        <button class="edit-btn bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 p-2 rounded transition duration-200" data-modal="modal-hobbies" title="Edit Hobbies">&#9998;</button>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-wrap gap-3 hobbies-container">
                        <?php foreach ($hobbies as $hobby): ?>
                            <span class="bg-gradient-to-r from-red-400 to-pink-500 dark:from-red-500 dark:to-pink-600 text-white px-4 py-2 rounded-full text-sm font-medium transform transition-all duration-200 hover:scale-110 hover:shadow-lg"><?php echo $hobby; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-700 shadow-lg rounded-xl p-6 section-skills transform transition-all duration-300 hover:shadow-2xl hover:scale-105 hover:-translate-y-1">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white"><i class="fas fa-tools mr-2 text-indigo-500"></i>Skills</h3>
                        <?php if ($current_user_id == $user_id): ?>
                        <button class="edit-btn bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 p-2 rounded transition duration-200" title="Edit Skills">&#9998;</button>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-wrap gap-3 skills-container">
                        <?php foreach ($skills as $skill): ?>
                            <span class="bg-gradient-to-r from-blue-400 to-blue-600 dark:from-blue-500 dark:to-blue-700 text-white px-4 py-2 rounded-full text-sm font-medium transform transition-all duration-200 hover:scale-110 hover:shadow-lg"><?php echo $skill; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Right side -->
            <div class="space-y-6">
                <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-700 shadow-lg rounded-xl p-6 section-projects transform transition-all duration-300 hover:shadow-2xl hover:scale-105 hover:-translate-y-1">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white"><i class="fas fa-folder-open mr-2 text-orange-500"></i>Projects</h3>
                        <?php if ($current_user_id == $user_id): ?>
                        <button class="edit-btn bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 p-2 rounded transition duration-200" title="Edit Projects">&#9998;</button>
                        <?php endif; ?>
                    </div>
                    <ul class="text-gray-700 dark:text-gray-200 leading-relaxed">
                        <?php foreach ($projects as $project): ?>
                            <li class="mb-2"><strong><?php echo $project['project_name']; ?></strong> - <?php echo $project['description']; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-700 shadow-lg rounded-xl p-6 section-awards transform transition-all duration-300 hover:shadow-2xl hover:scale-105 hover:-translate-y-1">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white"><i class="fas fa-trophy mr-2 text-yellow-500"></i>Awards</h3>
                        <?php if ($current_user_id == $user_id): ?>
                        <button class="edit-btn bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 p-2 rounded transition duration-200" data-modal="modal-awards" title="Edit Awards">&#9998;</button>
                        <?php endif; ?>
                    </div>
                    <ul class="text-gray-700 dark:text-gray-200 leading-relaxed">
                        <?php foreach ($awards as $award): ?>
                            <li class="mb-2"><strong><?php echo $award['award_name']; ?></strong> (<?php echo $award['year']; ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-700 shadow-lg rounded-xl p-6 section-experience transform transition-all duration-300 hover:shadow-2xl hover:scale-105 hover:-translate-y-1">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white"><i class="fas fa-briefcase mr-2 text-teal-500"></i>Experience</h3>
                        <?php if ($current_user_id == $user_id): ?>
                        <button class="edit-btn bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 p-2 rounded transition duration-200" data-modal="modal-experience" title="Edit Experience">&#9998;</button>
                        <?php endif; ?>
                    </div>
                    <p class="text-gray-700 dark:text-gray-200 leading-relaxed text-lg"><?php echo $user['years_experience']; ?> Years of Experience</p>
                </div>
                <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-700 shadow-lg rounded-xl p-6 section-certificates transform transition-all duration-300 hover:shadow-2xl hover:scale-105 hover:-translate-y-1">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white"><i class="fas fa-certificate mr-2 text-emerald-500"></i>Certificates</h3>
                        <?php if ($current_user_id == $user_id): ?>
                        <button class="edit-btn bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 p-2 rounded transition duration-200" data-modal="modal-certificates" title="Edit Certificates">&#9998;</button>
                        <?php endif; ?>
                    </div>
                    <ul class="text-gray-700 dark:text-gray-200 leading-relaxed">
                        <?php foreach ($certificates as $cert): ?>
                            <li class="mb-2"><strong><?php echo $cert['certificate_name']; ?></strong> by <?php echo $cert['issuer']; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-700 shadow-lg rounded-xl p-6 section-socialmedia transform transition-all duration-300 hover:shadow-2xl hover:scale-105 hover:-translate-y-1">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white"><i class="fas fa-share-alt mr-2 text-cyan-500"></i>Social Media</h3>
                        <?php if ($current_user_id == $user_id): ?>
                        <button class="edit-btn bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 p-2 rounded transition duration-200" data-modal="modal-socialmedia" title="Edit Social Media">&#9998;</button>
                        <?php endif; ?>
                    </div>
                    <ul class="text-gray-700 dark:text-gray-200 leading-relaxed">
                        <?php foreach ($social_media as $social): ?>
                            <li class="mb-2"><a href="<?php echo $social['url']; ?>" target="_blank" class="text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition duration-200"><?php echo $social['platform']; ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Modals for editing sections -->
        <?php if ($current_user_id == $user_id): ?>
        <div id="modal-bio" class="modal hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full mx-auto transform transition-all duration-300 scale-100">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6 rounded-t-2xl">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-bold text-white flex items-center space-x-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            <span>Edit Bio</span>
                        </h3>
                        <button class="text-white hover:text-gray-200 text-2xl font-bold close" data-modal="modal-bio">&times;</button>
                    </div>
                </div>
                <div class="p-6">
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                        <div class="mb-4">
                            <label for="bio-textarea" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Bio</label>
                            <textarea name="bio" id="bio-textarea" rows="4" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        </div>
                        <div class="flex space-x-3">
                            <button type="submit" class="flex-1 bg-gradient-to-r from-blue-500 to-blue-600 text-white px-6 py-3 rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Save</span>
                            </button>
                            <button type="button" class="px-6 py-3 bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-500 transition-all duration-300 transform hover:scale-105 btn-cancel" data-modal="modal-bio">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($current_user_id == $user_id): ?>
        <div id="modal-background" class="modal hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full mx-auto transform transition-all duration-300 scale-100">
                <div class="bg-gradient-to-r from-green-500 to-green-600 p-6 rounded-t-2xl">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-bold text-white flex items-center space-x-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>Edit Background</span>
                        </h3>
                        <button class="text-white hover:text-gray-200 text-2xl font-bold close" data-modal="modal-background">&times;</button>
                    </div>
                </div>
                <div class="p-6">
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                        <div class="mb-4">
                            <label for="background-textarea" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Background</label>
                            <textarea name="background" id="background-textarea" rows="4" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"><?php echo htmlspecialchars($user['background'] ?? ''); ?></textarea>
                        </div>
                        <div class="flex space-x-3">
                            <button type="submit" class="flex-1 bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-3 rounded-lg hover:from-green-600 hover:to-green-700 transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Save</span>
                            </button>
                            <button type="button" class="px-6 py-3 bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-500 transition-all duration-300 transform hover:scale-105 btn-cancel" data-modal="modal-background">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($current_user_id == $user_id): ?>
        <div id="modal-email" class="modal hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full mx-auto transform transition-all duration-300 scale-100">
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 p-6 rounded-t-2xl">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-bold text-white flex items-center space-x-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            <span>Edit Email</span>
                        </h3>
                        <button class="text-white hover:text-gray-200 text-2xl font-bold close" data-modal="modal-email">&times;</button>
                    </div>
                </div>
                <div class="p-6">
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                        <div class="mb-4">
                            <label for="email-input" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email Address</label>
                            <input type="email" name="email" id="email-input" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" placeholder="Enter your email address" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                        </div>
                        <div class="flex space-x-3">
                            <button type="submit" class="flex-1 bg-gradient-to-r from-purple-500 to-purple-600 text-white px-6 py-3 rounded-lg hover:from-purple-600 hover:to-purple-700 transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Save</span>
                            </button>
                            <button type="button" class="px-6 py-3 bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-500 transition-all duration-300 transform hover:scale-105 btn-cancel" data-modal="modal-email">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($current_user_id == $user_id): ?>
        <div id="modal-skills" class="modal hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full mx-auto transform transition-all duration-300 scale-100">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6 rounded-t-2xl">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-bold text-white flex items-center space-x-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <span>Edit Skills</span>
                        </h3>
                        <button class="text-white hover:text-gray-200 text-2xl font-bold close" data-modal="modal-skills">&times;</button>
                    </div>
                </div>
                <div class="p-6">
                    <button type="button" class="mb-4 bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 px-4 py-2 rounded-lg hover:bg-blue-200 dark:hover:bg-blue-800 transition-all duration-300 transform hover:scale-105" onclick="openSubModal('submodal-new-skill')">Add New Skill</button>
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                        <div class="mb-4">
                            <label for="skills-select-modal" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Skills</label>
                            <select id="skills-select-modal" name="skills[]" multiple="multiple" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                                <?php
                                $current_skills = array_map('trim', $skills);
                                foreach ($predefined_skills as $skill_option):
                                    $selected = in_array(trim($skill_option), $current_skills) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo htmlspecialchars($skill_option); ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($skill_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex space-x-3">
                            <button type="submit" class="flex-1 bg-gradient-to-r from-blue-500 to-blue-600 text-white px-6 py-3 rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Save</span>
                            </button>
                            <button type="button" class="px-6 py-3 bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-500 transition-all duration-300 transform hover:scale-105 btn-cancel" data-modal="modal-skills">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Sub-modal for adding new skill -->
        <div id="submodal-new-skill" class="submodal">
            <div class="submodal-content bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                <span class="close" data-submodal="submodal-new-skill">&times;</span>
                <h3>Add New Skill</h3>
                <input type="text" id="new-skill-name" placeholder="Enter skill name" class="custom-skill-input">
                <button type="button" class="btn btn-small" onclick="addNewSkill()">Add Skill</button>
            </div>
        </div>

        <?php if ($current_user_id == $user_id): ?>
        <div id="modal-hobbies" class="modal hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full mx-auto transform transition-all duration-300 scale-100">
                <div class="bg-gradient-to-r from-red-500 to-red-600 p-6 rounded-t-2xl">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-bold text-white flex items-center space-x-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                            </svg>
                            <span>Edit Hobbies</span>
                        </h3>
                        <button class="text-white hover:text-gray-200 text-2xl font-bold close" data-modal="modal-hobbies">&times;</button>
                    </div>
                </div>
                <div class="p-6">
                    <button type="button" class="mb-4 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 px-4 py-2 rounded-lg hover:bg-red-200 dark:hover:bg-red-800 transition-all duration-300 transform hover:scale-105" onclick="openSubModal('submodal-new-hobby')">Add New Hobby</button>
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                        <div class="mb-4">
                            <label for="hobbies-select-modal" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Hobbies</label>
                            <select id="hobbies-select-modal" name="hobbies[]" multiple="multiple" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all duration-200 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                                <?php
                                $current_hobbies = array_map('trim', $hobbies);
                                foreach ($predefined_hobbies as $hobby_option):
                                    $selected = in_array(trim($hobby_option), $current_hobbies) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo htmlspecialchars($hobby_option); ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($hobby_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex space-x-3">
                            <button type="submit" class="flex-1 bg-gradient-to-r from-red-500 to-red-600 text-white px-6 py-3 rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Save</span>
                            </button>
                            <button type="button" class="px-6 py-3 bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-500 transition-all duration-300 transform hover:scale-105 btn-cancel" data-modal="modal-hobbies">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Sub-modal for adding new hobby -->
        <div id="submodal-new-hobby" class="submodal">
            <div class="submodal-content bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                <span class="close" data-submodal="submodal-new-hobby">&times;</span>
                <h3>Add New Hobby</h3>
                <input type="text" id="new-hobby-name" placeholder="Enter hobby name" class="custom-skill-input">
                <button type="button" class="btn btn-small" onclick="addNewHobby()">Add Hobby</button>
            </div>
        </div>

        <?php if ($current_user_id == $user_id): ?>
        <div id="modal-projects" class="modal hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full mx-auto transform transition-all duration-300 scale-100">
                <div class="bg-gradient-to-r from-orange-500 to-orange-600 p-6 rounded-t-2xl">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-bold text-white flex items-center space-x-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v2H8V5z"></path>
                            </svg>
                            <span>Edit Projects</span>
                        </h3>
                        <button class="text-white hover:text-gray-200 text-2xl font-bold close" data-modal="modal-projects">&times;</button>
                    </div>
                </div>
                <div class="p-6">
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                        <div id="projects-modal-container" class="space-y-4 mb-4">
                            <?php foreach ($projects as $project): ?>
                                <div class="input-group bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                    <input type="text" name="projects[]" value="<?php echo htmlspecialchars($project['project_name']); ?>" placeholder="Project Name" class="w-full px-4 py-2 mb-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all duration-200 bg-white dark:bg-gray-600 text-gray-900 dark:text-white">
                                    <input type="text" name="project_descriptions[]" value="<?php echo htmlspecialchars($project['description']); ?>" placeholder="Description" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all duration-200 bg-white dark:bg-gray-600 text-gray-900 dark:text-white">
                                    <button type="button" class="remove-btn mt-2 text-red-500 hover:text-red-700 transition duration-200">Remove</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="mb-4 bg-orange-100 dark:bg-orange-900 text-orange-700 dark:text-orange-300 px-4 py-2 rounded-lg hover:bg-orange-200 dark:hover:bg-orange-800 transition-all duration-300 transform hover:scale-105" onclick="addInput('projects-modal-container', [{name: 'projects[]', placeholder: 'Project Name'}, {name: 'project_descriptions[]', placeholder: 'Description'}])">Add Project</button>
                        <div class="flex space-x-3">
                            <button type="submit" class="flex-1 bg-gradient-to-r from-orange-500 to-orange-600 text-white px-6 py-3 rounded-lg hover:from-orange-600 hover:to-orange-700 transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Save</span>
                            </button>
                            <button type="button" class="px-6 py-3 bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-500 transition-all duration-300 transform hover:scale-105 btn-cancel" data-modal="modal-projects">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($current_user_id == $user_id): ?>
        <div id="modal-awards" class="modal hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full mx-auto transform transition-all duration-300 scale-100">
                <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 p-6 rounded-t-2xl">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-bold text-white flex items-center space-x-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                            </svg>
                            <span>Edit Awards</span>
                        </h3>
                        <button class="text-white hover:text-gray-200 text-2xl font-bold close" data-modal="modal-awards">&times;</button>
                    </div>
                </div>
                <div class="p-6">
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                        <div id="awards-modal-container" class="space-y-4 mb-4">
                            <?php foreach ($awards as $award): ?>
                                <div class="input-group bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                    <input type="text" name="awards[]" value="<?php echo htmlspecialchars($award['award_name']); ?>" placeholder="Award Name" class="w-full px-4 py-2 mb-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition-all duration-200 bg-white dark:bg-gray-600 text-gray-900 dark:text-white">
                                    <input type="number" name="award_years[]" value="<?php echo htmlspecialchars($award['year']); ?>" placeholder="Year" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-transparent transition-all duration-200 bg-white dark:bg-gray-600 text-gray-900 dark:text-white">
                                    <button type="button" class="remove-btn mt-2 text-red-500 hover:text-red-700 transition duration-200">Remove</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="mb-4 bg-yellow-100 dark:bg-yellow-900 text-yellow-700 dark:text-yellow-300 px-4 py-2 rounded-lg hover:bg-yellow-200 dark:hover:bg-yellow-800 transition-all duration-300 transform hover:scale-105" onclick="addInput('awards-modal-container', [{name: 'awards[]', placeholder: 'Award Name'}, {name: 'award_years[]', placeholder: 'Year'}])">Add Award</button>
                        <div class="flex space-x-3">
                            <button type="submit" class="flex-1 bg-gradient-to-r from-yellow-500 to-yellow-600 text-white px-6 py-3 rounded-lg hover:from-yellow-600 hover:to-yellow-700 transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Save</span>
                            </button>
                            <button type="button" class="px-6 py-3 bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-500 transition-all duration-300 transform hover:scale-105 btn-cancel" data-modal="modal-awards">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($current_user_id == $user_id): ?>
        <div id="modal-experience" class="modal hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full mx-auto transform transition-all duration-300 scale-100">
                <div class="bg-gradient-to-r from-teal-500 to-teal-600 p-6 rounded-t-2xl">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-bold text-white flex items-center space-x-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m8 0V8a2 2 0 01-2 2H8a2 2 0 01-2-2V6m8 0H8m0 0V4"></path>
                            </svg>
                            <span>Edit Experience</span>
                        </h3>
                        <button class="text-white hover:text-gray-200 text-2xl font-bold close" data-modal="modal-experience">&times;</button>
                    </div>
                </div>
                <div class="p-6">
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                        <div class="mb-4">
                            <label for="years-experience-input" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Years of Experience</label>
                            <input type="number" name="years_experience" id="years-experience-input" value="<?php echo htmlspecialchars($user['years_experience']); ?>" min="0" placeholder="Years of Experience" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent transition-all duration-200 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                        </div>
                        <div class="flex space-x-3">
                            <button type="submit" class="flex-1 bg-gradient-to-r from-teal-500 to-teal-600 text-white px-6 py-3 rounded-lg hover:from-teal-600 hover:to-teal-700 transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Save</span>
                            </button>
                            <button type="button" class="px-6 py-3 bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-500 transition-all duration-300 transform hover:scale-105 btn-cancel" data-modal="modal-experience">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($current_user_id == $user_id): ?>
        <div id="modal-certificates" class="modal hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full mx-auto transform transition-all duration-300 scale-100">
                <div class="bg-gradient-to-r from-emerald-500 to-emerald-600 p-6 rounded-t-2xl">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-bold text-white flex items-center space-x-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                            </svg>
                            <span>Edit Certificates</span>
                        </h3>
                        <button class="text-white hover:text-gray-200 text-2xl font-bold close" data-modal="modal-certificates">&times;</button>
                    </div>
                </div>
                <div class="p-6">
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                        <div id="certificates-modal-container" class="space-y-4 mb-4">
                            <?php foreach ($certificates as $cert): ?>
                                <div class="input-group bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                    <input type="text" name="certificates[]" value="<?php echo htmlspecialchars($cert['certificate_name']); ?>" placeholder="Certificate Name" class="w-full px-4 py-2 mb-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all duration-200 bg-white dark:bg-gray-600 text-gray-900 dark:text-white">
                                    <input type="text" name="certificate_issuers[]" value="<?php echo htmlspecialchars($cert['issuer']); ?>" placeholder="Issuer" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all duration-200 bg-white dark:bg-gray-600 text-gray-900 dark:text-white">
                                    <button type="button" class="remove-btn mt-2 text-red-500 hover:text-red-700 transition duration-200">Remove</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="mb-4 bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 px-4 py-2 rounded-lg hover:bg-emerald-200 dark:hover:bg-emerald-800 transition-all duration-300 transform hover:scale-105" onclick="addInput('certificates-modal-container', [{name: 'certificates[]', placeholder: 'Certificate Name'}, {name: 'certificate_issuers[]', placeholder: 'Issuer'}])">Add Certificate</button>
                        <div class="flex space-x-3">
                            <button type="submit" class="flex-1 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white px-6 py-3 rounded-lg hover:from-emerald-600 hover:to-emerald-700 transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Save</span>
                            </button>
                            <button type="button" class="px-6 py-3 bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-500 transition-all duration-300 transform hover:scale-105 btn-cancel" data-modal="modal-certificates">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($current_user_id == $user_id): ?>
        <div id="modal-profile-pic" class="modal hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full mx-auto transform transition-all duration-300 scale-100">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6 rounded-t-2xl">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-bold text-white flex items-center space-x-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <span>Confirm Profile Picture</span>
                        </h3>
                        <button class="text-white hover:text-gray-200 text-2xl font-bold close" data-modal="modal-profile-pic">&times;</button>
                    </div>
                </div>
                <div class="p-6">
                    <div class="text-center mb-4">
                        <img id="profile-pic-preview" src="" alt="Preview" class="w-32 h-32 rounded-full mx-auto border-4 border-gray-300 dark:border-gray-600">
                    </div>
                    <p class="text-gray-700 dark:text-gray-300 text-center mb-6">Are you sure you want to upload this profile picture?</p>
                    <div class="flex space-x-3">
                        <button type="button" id="confirm-upload" class="flex-1 bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-3 rounded-lg hover:from-green-600 hover:to-green-700 transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Confirm</span>
                        </button>
                        <button type="button" class="px-6 py-3 bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-500 transition-all duration-300 transform hover:scale-105 btn-cancel" data-modal="modal-profile-pic">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($current_user_id == $user_id): ?>
        <div id="modal-socialmedia" class="modal hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full mx-auto transform transition-all duration-300 scale-100">
                <div class="bg-gradient-to-r from-cyan-500 to-cyan-600 dark:from-cyan-600 dark:to-cyan-700 p-6 rounded-t-2xl">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-bold text-white flex items-center space-x-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z"></path>
                            </svg>
                            <span>Edit Social Media</span>
                        </h3>
                        <button class="text-white hover:text-gray-200 text-2xl font-bold close" data-modal="modal-socialmedia">&times;</button>
                    </div>
                </div>
                <div class="p-6">
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                        <div id="socialmedia-modal-container" class="space-y-4 mb-4">
                            <?php foreach ($social_media as $social): ?>
                                <div class="input-group bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                    <input type="text" name="social_media_platforms[]" value="<?php echo htmlspecialchars($social['platform']); ?>" placeholder="Platform" class="w-full px-4 py-2 mb-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all duration-200 bg-white dark:bg-gray-600 text-gray-900 dark:text-white">
                                    <input type="text" name="social_media_urls[]" value="<?php echo htmlspecialchars($social['url']); ?>" placeholder="URL" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all duration-200 bg-white dark:bg-gray-600 text-gray-900 dark:text-white">
                                    <button type="button" class="remove-btn mt-2 text-red-500 hover:text-red-700 transition duration-200">Remove</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="mb-4 bg-cyan-100 dark:bg-cyan-900 text-cyan-700 dark:text-cyan-300 px-4 py-2 rounded-lg hover:bg-cyan-200 dark:hover:bg-cyan-800 transition-all duration-300 transform hover:scale-105" onclick="addInput('socialmedia-modal-container', [{name: 'social_media_platforms[]', placeholder: 'Platform'}, {name: 'social_media_urls[]', placeholder: 'URL'}])">Add Social Media</button>
                        <div class="flex space-x-3">
                            <button type="submit" class="flex-1 bg-gradient-to-r from-cyan-500 to-cyan-600 text-white px-6 py-3 rounded-lg hover:from-cyan-600 hover:to-cyan-700 transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Save</span>
                            </button>
                            <button type="button" class="px-6 py-3 bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-500 transition-all duration-300 transform hover:scale-105 btn-cancel" data-modal="modal-socialmedia">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <script>
            $(document).ready(function() {
            // Modal open
            $('.edit-btn').click(function() {
                var modalId = $(this).data('modal');
                if (modalId) {
                    $('#' + modalId).fadeIn();
                } else {
                    // Handle buttons without data-modal attribute explicitly
                    var btnTitle = $(this).attr('title');
                    if (btnTitle === 'Edit Bio') {
                        $('#modal-bio').fadeIn();
                    } else if (btnTitle === 'Edit Background') {
                        $('#modal-background').fadeIn();
                    } else if (btnTitle === 'Edit Email') {
                        $('#modal-email').fadeIn();
                    } else if (btnTitle === 'Edit Skills') {
                        $('#modal-skills').fadeIn();
                    } else if (btnTitle === 'Edit Projects') {
                        $('#modal-projects').fadeIn();
                    }
                }
            });

                // Modal close
                $('.close').click(function() {
                    var modalId = $(this).data('modal');
                    $('#' + modalId).fadeOut();
                });

                // Cancel button close
                $('.btn-cancel').click(function() {
                    var modalId = $(this).data('modal');
                    if (modalId === 'modal-profile-pic') {
                        $('#profile-pic-input').val('');
                    }
                    $('#' + modalId).fadeOut();
                });

                // Close modal on outside click
                $('.modal').click(function(e) {
                    if (e.target === this) {
                        $(this).fadeOut();
                    }
                });

                // Initialize Select2 in modals
                $('#skills-select-modal').select2({
                    placeholder: 'Select skills or type to create new ones...',
                    allowClear: true,
                    tags: true,
                    tokenSeparators: [','],
                    width: '100%'
                });

                $('#hobbies-select-modal').select2({
                    placeholder: 'Select hobbies or type to create new ones...',
                    allowClear: true,
                    tags: true,
                    tokenSeparators: [','],
                    width: '100%'
                });

                // Sub-modal open
                window.openSubModal = function(submodalId) {
                    $('#' + submodalId).fadeIn();
                };

                // Sub-modal close
                $('.close[data-submodal]').click(function() {
                    var submodalId = $(this).data('submodal');
                    $('#' + submodalId).fadeOut();
                });

                // Close sub-modal on outside click
                $('.submodal').click(function(e) {
                    if (e.target === this) {
                        $(this).fadeOut();
                    }
                });

                // Add new skill via AJAX
                window.addNewSkill = function() {
                    var skillName = $('#new-skill-name').val().trim();
                    if (skillName === '') {
                        alert('Please enter a skill name.');
                        return;
                    }

                    // Preserve current selections
                    var currentValues = $('#skills-select-modal').val() || [];

                    $.ajax({
                        url: 'profile.php',
                        type: 'POST',
                        data: {
                            action: 'create_skill',
                            skill_name: skillName
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                // Add new option to Select2
                                var newOption = new Option(response.name, response.name, false, false);
                                $('#skills-select-modal').append(newOption);
                                // Restore previous selections and add the new one
                                currentValues.push(response.name);
                                $('#skills-select-modal').val(currentValues).trigger('change');
                                $('#new-skill-name').val('');
                                $('#submodal-new-skill').fadeOut();
                                alert('Skill added successfully!');
                            } else {
                                alert('Error: ' + response.message);
                            }
                        },
                        error: function() {
                            alert('An error occurred while adding the skill.');
                        }
                    });
                };

                // Add new hobby via AJAX
                window.addNewHobby = function() {
                    var hobbyName = $('#new-hobby-name').val().trim();
                    if (hobbyName === '') {
                        alert('Please enter a hobby name.');
                        return;
                    }

                    // Preserve current selections
                    var currentValues = $('#hobbies-select-modal').val() || [];

                    $.ajax({
                        url: 'profile.php',
                        type: 'POST',
                        data: {
                            action: 'create_hobby',
                            hobby_name: hobbyName
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                // Add new option to Select2
                                var newOption = new Option(response.name, response.name, false, false);
                                $('#hobbies-select-modal').append(newOption);
                                // Restore previous selections and add the new one
                                currentValues.push(response.name);
                                $('#hobbies-select-modal').val(currentValues).trigger('change');
                                $('#new-hobby-name').val('');
                                $('#submodal-new-hobby').fadeOut();
                                alert('Hobby added successfully!');
                            } else {
                                alert('Error: ' + response.message);
                            }
                        },
                        error: function() {
                            alert('An error occurred while adding the hobby.');
                        }
                    });
                };

                // AJAX form submission for all modal forms
                $('.modal form').on('submit', function(e) {
                    e.preventDefault(); // Prevent default form submission

                    var form = $(this);
                    var button = form.find('button[type="submit"]');
                    var formData = new FormData(form[0]);
                    var modalId = form.closest('.modal').attr('id');

                    // Show loading state
                    button.prop('disabled', true).text('Saving...');
                    showNotification('Saving changes...', 'info');
                    $.ajax({
                        url: 'profile.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                // Show success notification
                                showNotification(response.message, 'success');

                                // Update the view with new data
                                $('.section-bio p').text(response.user.bio);
                                $('.section-background p').text(response.user.background);
                                $('.section-email p').text(response.user.email);
                                $('.profile-email').text(response.user.email);

                                const skillsContainer = $('.section-skills .skills-container');
                                skillsContainer.empty();
                                response.skills.forEach(function(skill) {
                                    skillsContainer.append('<span class="bg-gradient-to-r from-blue-400 to-blue-600 dark:from-blue-500 dark:to-blue-700 text-white px-4 py-2 rounded-full text-sm font-medium transform transition-all duration-200 hover:scale-110 hover:shadow-lg">' + skill + '</span>');
                                });

                                const hobbiesContainer = $('.section-hobbies .hobbies-container');
                                hobbiesContainer.empty();
                                response.hobbies.forEach(function(hobby) {
                                    hobbiesContainer.append('<span class="bg-gradient-to-r from-red-400 to-pink-500 dark:from-red-500 dark:to-pink-600 text-white px-4 py-2 rounded-full text-sm font-medium transform transition-all duration-200 hover:scale-110 hover:shadow-lg">' + hobby + '</span>');
                                });

                                const projectsUl = $('.section-projects ul');
                                projectsUl.empty();
                                response.projects.forEach(function(project) {
                                    projectsUl.append('<li class="mb-2"><strong>' + project.project_name + '</strong> - ' + project.description + '</li>');
                                });

                                const awardsUl = $('.section-awards ul');
                                awardsUl.empty();
                                response.awards.forEach(function(award) {
                                    awardsUl.append('<li class="mb-2"><strong>' + award.award_name + '</strong> (' + award.year + ')</li>');
                                });

                                $('.section-experience p').text(response.user.years_experience + ' Years of Experience');

                                const certUl = $('.section-certificates ul');
                                certUl.empty();
                                response.certificates.forEach(function(cert) {
                                    certUl.append('<li class="mb-2"><strong>' + cert.certificate_name + '</strong> by ' + cert.issuer + '</li>');
                                });

                                const socialUl = $('.section-socialmedia ul');
                                socialUl.empty();
                                response.social_media.forEach(function(social) {
                                    socialUl.append('<li class="mb-2"><a href="' + social.url + '" target="_blank" class="text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition duration-200">' + social.platform + '</a></li>');
                                });

                                const socialLinks = $('.social-links');
                                socialLinks.empty();
                                response.social_media.forEach(function(social) {
                                    var icon = '';
                                    if (social.platform == 'Facebook') icon = 'facebook.png';
                                    else if (social.platform == 'Instagram') icon = 'instagram.png';
                                    else if (social.platform == 'X') icon = 'twitter.png';
                                    socialLinks.append('<a href="' + social.url + '" target="_blank"><img src="icons/' + icon + '" alt="' + social.platform + '" class="w-8 h-8 hover:opacity-75 transition duration-200 transform hover:scale-110"></a>');
                                });

                                // Close modal
                                $('#' + modalId).fadeOut();

                                // Re-enable submit button
                                button.prop('disabled', false).text('Save');
                            } else {
                                // Show error notification
                                showNotification(response.message, 'error');

                                // Re-enable submit button
                                button.prop('disabled', false).text('Save');
                            }
                        },
                        error: function(xhr, status, error) {
                            // Show error notification
                            showNotification('Error updating profile. Please try again.', 'error');

                            // Re-enable submit button
                            button.prop('disabled', false).text('Save');
                        }
                    });
                });

                // Profile picture edit functionality
                $('#edit-profile-pic').click(function() {
                    $('#profile-pic-input').click();
                });

                $('#profile-pic-input').on('change', function() {
                    var file = this.files[0];
                    if (file) {
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            $('#modal-profile-pic img').attr('src', e.target.result);
                            $('#modal-profile-pic').fadeIn();
                        };
                        reader.readAsDataURL(file);
                    }
                });

                $('#confirm-upload').on('click', function() {
                    var file = $('#profile-pic-input')[0].files[0];
                    if (file) {
                        var formData = new FormData();
                        formData.append('profile_picture', file);
                        formData.append('update_profile', '1');
                        formData.append('user_id', '<?php echo $user_id; ?>');

                        showNotification('Uploading profile picture...', 'info');

                        $.ajax({
                            url: 'profile.php',
                            type: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    showNotification('Profile picture updated successfully!', 'success');
                                    // Update the image src with cache buster
                                    $('img[alt="Profile Picture"]').attr('src', 'uploads/' + response.user.profile_pic + '?t=' + new Date().getTime());
                                    $('#modal-profile-pic').fadeOut();
                                } else {
                                    showNotification(response.message, 'error');
                                }
                            },
                            error: function() {
                                showNotification('Error uploading profile picture.', 'error');
                            }
                        });
                    }
                });

                $('#cancel-upload').on('click', function() {
                    $('#profile-pic-input').val('');
                    $('#modal-profile-pic').modal('hide');
                });

                // Inline editing for full_name
                $('.edit-name-btn').on('click', function() {
                    var $h2 = $('h2.text-4xl');
                    var originalName = $h2.text().trim();
                    var $container = $h2.parent();
                    $h2.hide();
                    var $input = $('<input type="text" value="' + originalName + '" class="inline-input bg-white dark:bg-gray-700 text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 rounded px-2 py-1">');
                    var $saveBtn = $('<button class="ml-2 bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">Save</button>');
                    var $cancelBtn = $('<button class="ml-2 bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded text-sm">Cancel</button>');
                    $container.append($input).append($saveBtn).append($cancelBtn);
                    $input.focus();

                    $saveBtn.on('click', function() {
                        var newName = $input.val().trim();
                        if (newName === '') {
                            showNotification('Full name cannot be empty.', 'error');
                            return;
                        }
                        var currentUsername = $('.profile-username').text().replace('@', '').trim();
                        $.ajax({
                            url: 'profile.php',
                            type: 'POST',
                            data: {
                                action: 'update_name_username',
                                full_name: newName,
                                username: currentUsername
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    $h2.text(response.full_name);
                                    $('.profile-username').text('@' + response.username);
                                    showNotification('Full name updated successfully!', 'success');
                                } else {
                                    showNotification(response.message, 'error');
                                }
                                $input.remove();
                                $saveBtn.remove();
                                $cancelBtn.remove();
                                $h2.show();
                            },
                            error: function() {
                                showNotification('Error updating full name.', 'error');
                                $input.remove();
                                $saveBtn.remove();
                                $cancelBtn.remove();
                                $h2.show();
                            }
                        });
                    });

                    $cancelBtn.on('click', function() {
                        $input.remove();
                        $saveBtn.remove();
                        $cancelBtn.remove();
                        $h2.show();
                    });
                });

                // Inline editing for username
                $('.edit-username-btn').on('click', function() {
                    var $p = $('.profile-username');
                    var originalUsername = $p.text().replace('@', '').trim();
                    var $container = $p.parent();
                    $p.hide();
                    var $input = $('<input type="text" value="' + originalUsername + '" class="inline-input bg-white dark:bg-gray-700 text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600 rounded px-2 py-1">');
                    var $saveBtn = $('<button class="ml-2 bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">Save</button>');
                    var $cancelBtn = $('<button class="ml-2 bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded text-sm">Cancel</button>');
                    $container.append($input).append($saveBtn).append($cancelBtn);
                    $input.focus();

                    $saveBtn.on('click', function() {
                        var newUsername = $input.val().trim();
                        if (newUsername === '') {
                            showNotification('Username cannot be empty.', 'error');
                            return;
                        }
                        var currentName = $('h2.text-4xl').text().trim();
                        $.ajax({
                            url: 'profile.php',
                            type: 'POST',
                            data: {
                                action: 'update_name_username',
                                full_name: currentName,
                                username: newUsername
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    $('h2.text-4xl').text(response.full_name);
                                    $p.text('@' + response.username);
                                    showNotification('Username updated successfully!', 'success');
                                } else {
                                    showNotification(response.message, 'error');
                                }
                                $input.remove();
                                $saveBtn.remove();
                                $cancelBtn.remove();
                                $p.show();
                            },
                            error: function() {
                                showNotification('Error updating username.', 'error');
                                $input.remove();
                                $saveBtn.remove();
                                $cancelBtn.remove();
                                $p.show();
                            }
                        });
                    });

                    $cancelBtn.on('click', function() {
                        $input.remove();
                        $saveBtn.remove();
                        $cancelBtn.remove();
                        $p.show();
                    });
                });

                // Theme toggle
            const themeToggle = $('#theme-toggle');
            const html = $('html');

            // Load theme from localStorage
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                html.addClass('dark');
                themeToggle.find('i').removeClass('fa-moon').addClass('fa-sun');
            }

            themeToggle.click(function() {
                html.toggleClass('dark');
                const isDark = html.hasClass('dark');
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
                themeToggle.find('i').toggleClass('fa-moon fa-sun');
            });
        });
        </script>


    </div>
</body>
</html>
