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

$user_id = $user['id'];

// Fetch predefined skills and hobbies
$predefined_skills = $userObj->getPredefinedSkills();
$predefined_hobbies = $userObj->getPredefinedHobbies();

// Handle profile update POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['update_profile']) || isAjaxRequest())) {
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
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
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

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
} else {
    if (isset($_POST['search_username']) && !empty($_POST['search_username'])) {
        $searched_user = $userObj->getUserByUsername($_POST['search_username']);
        if ($searched_user) {
            $user = $searched_user;
        }
    } elseif (isset($_GET['username'])) {
        $user = $userObj->getUserByUsername($_GET['username']);
    } else {
        echo "No profile selected.";
        exit();
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
    <title><?php echo $user['username']; ?>'s Profile</title>
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
                <div class="flex items-center space-x-8">
                    <a href="profile.php?username=<?php echo urlencode($_SESSION['username']); ?>" class="text-white hover:text-blue-300 transition duration-200">Home</a>
                    <a href="#portfolio" class="text-white hover:text-blue-300 transition duration-200">Portfolio</a>
                    <a href="#projects" class="text-white hover:text-blue-300 transition duration-200">Projects</a>
                    <a href="#about" class="text-white hover:text-blue-300 transition duration-200">About</a>
                    <a href="#contact" class="text-white hover:text-blue-300 transition duration-200">Contact</a>
                </div>
                <div class="flex items-center space-x-4">
                    <!-- Search Bar -->
                    <form method="POST" class="flex">
                        <input type="text" name="search_username" placeholder="Search username" required class="px-3 py-2 rounded-l-lg border-0 focus:ring-2 focus:ring-blue-500 text-gray-900 dark:text-white">
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
    <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 mx-auto max-w-4xl mt-8">
        <img src="uploads/<?php echo $user['profile_pic']; ?>" alt="Profile Picture" class="w-32 h-32 rounded-full mx-auto mb-4 shadow-md border-4 border-gray-200 dark:border-gray-600">
        <h2 class="text-3xl font-bold text-center text-gray-800 dark:text-white mb-2"><?php echo $user['username']; ?></h2>
        <p class="text-center text-gray-600 dark:text-gray-300 mb-4 profile-email"><?php echo $user['email']; ?></p>

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
                <a href="<?php echo $social['url']; ?>" target="_blank" class="hover:opacity-75 transition duration-200">
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
                <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 section-bio">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white"><i class="fas fa-user mr-2"></i>Bio</h3>
                        <?php if ($current_user_id == $user_id): ?>
                        <button class="edit-btn bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 p-2 rounded transition duration-200" title="Edit Bio">&#9998;</button>
                        <?php endif; ?>
                    </div>
                    <p class="text-gray-700 dark:text-gray-300"><?php echo $user['bio']; ?></p>
                </div>
                <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 section-background">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white"><i class="fas fa-info-circle mr-2"></i>Background</h3>
                        <?php if ($current_user_id == $user_id): ?>
                        <button class="edit-btn bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 p-2 rounded transition duration-200" title="Edit Background">&#9998;</button>
                        <?php endif; ?>
                    </div>
                    <p class="text-gray-700 dark:text-gray-300"><?php echo $user['background']; ?></p>
                </div>
                <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 section-email">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white"><i class="fas fa-envelope mr-2"></i>Email</h3>
                        <?php if ($current_user_id == $user_id): ?>
                        <button class="edit-btn bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 p-2 rounded transition duration-200" title="Edit Email">&#9998;</button>
                        <?php endif; ?>
                    </div>
                    <p class="text-gray-700 dark:text-gray-300"><?php echo $user['email']; ?></p>
                </div>
                <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 section-hobbies">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white"><i class="fas fa-heart mr-2"></i>Hobbies</h3>
                        <?php if ($current_user_id == $user_id): ?>
                        <button class="edit-btn bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 p-2 rounded transition duration-200" data-modal="modal-hobbies" title="Edit Hobbies">&#9998;</button>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-wrap gap-2 hobbies-container">
                        <?php foreach ($hobbies as $hobby): ?>
                            <span class="bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-3 py-1 rounded-full text-sm"><?php echo $hobby; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 section-skills">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white"><i class="fas fa-tools mr-2"></i>Skills</h3>
                        <?php if ($current_user_id == $user_id): ?>
                        <button class="edit-btn bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 p-2 rounded transition duration-200" title="Edit Skills">&#9998;</button>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-wrap gap-2 skills-container">
                        <?php foreach ($skills as $skill): ?>
                            <span class="bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-3 py-1 rounded-full text-sm"><?php echo $skill; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Right side -->
            <div class="space-y-6">
                <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 section-projects">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white"><i class="fas fa-folder-open mr-2"></i>Projects</h3>
                        <?php if ($current_user_id == $user_id): ?>
                        <button class="edit-btn bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 p-2 rounded transition duration-200" title="Edit Projects">&#9998;</button>
                        <?php endif; ?>
                    </div>
                    <ul class="text-gray-700 dark:text-gray-300">
                        <?php foreach ($projects as $project): ?>
                            <li><?php echo $project['project_name']; ?> - <?php echo $project['description']; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 section-awards">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white"><i class="fas fa-trophy mr-2"></i>Awards</h3>
                        <?php if ($current_user_id == $user_id): ?>
                        <button class="edit-btn bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 p-2 rounded transition duration-200" data-modal="modal-awards" title="Edit Awards">&#9998;</button>
                        <?php endif; ?>
                    </div>
                    <ul class="text-gray-700 dark:text-gray-300">
                        <?php foreach ($awards as $award): ?>
                            <li><?php echo $award['award_name']; ?> (<?php echo $award['year']; ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 section-experience">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white"><i class="fas fa-briefcase mr-2"></i>Experience</h3>
                        <?php if ($current_user_id == $user_id): ?>
                        <button class="edit-btn bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 p-2 rounded transition duration-200" data-modal="modal-experience" title="Edit Experience">&#9998;</button>
                        <?php endif; ?>
                    </div>
                    <p class="text-gray-700 dark:text-gray-300"><?php echo $user['years_experience']; ?> Years of Experience</p>
                </div>
                <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 section-certificates">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white"><i class="fas fa-certificate mr-2"></i>Certificates</h3>
                        <?php if ($current_user_id == $user_id): ?>
                        <button class="edit-btn bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 p-2 rounded transition duration-200" data-modal="modal-certificates" title="Edit Certificates">&#9998;</button>
                        <?php endif; ?>
                    </div>
                    <ul class="text-gray-700 dark:text-gray-300">
                        <?php foreach ($certificates as $cert): ?>
                            <li><?php echo $cert['certificate_name']; ?> by <?php echo $cert['issuer']; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 section-socialmedia">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white"><i class="fas fa-share-alt mr-2"></i>Social Media</h3>
                        <?php if ($current_user_id == $user_id): ?>
                        <button class="edit-btn bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 p-2 rounded transition duration-200" data-modal="modal-socialmedia" title="Edit Social Media">&#9998;</button>
                        <?php endif; ?>
                    </div>
                    <ul class="text-gray-700 dark:text-gray-300">
                        <?php foreach ($social_media as $social): ?>
                            <li><a href="<?php echo $social['url']; ?>" target="_blank" class="text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"><?php echo $social['platform']; ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Modals for editing sections -->
        <?php if ($current_user_id == $user_id): ?>
        <div id="modal-bio" class="modal">
            <div class="modal-content bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                <span class="close" data-modal="modal-bio">&times;</span>
                <h2>Edit Bio</h2>
                <form method="POST">
                    <input type="hidden" name="update_profile" value="1">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <textarea name="bio" rows="4"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                    <button type="submit" class="btn btn-save">Save</button>
                    <button type="button" class="btn btn-cancel" data-modal="modal-bio">Cancel</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($current_user_id == $user_id): ?>
        <div id="modal-background" class="modal">
            <div class="modal-content bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                <span class="close" data-modal="modal-background">&times;</span>
                <h2>Edit Background</h2>
                <form method="POST">
                    <input type="hidden" name="update_profile" value="1">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <textarea name="background" rows="4"><?php echo htmlspecialchars($user['background'] ?? ''); ?></textarea>
                    <button type="submit" class="btn btn-save">Save</button>
                    <button type="button" class="btn btn-cancel" data-modal="modal-background">Cancel</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($current_user_id == $user_id): ?>
        <div id="modal-email" class="modal">
            <div class="modal-content bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                <span class="close" data-modal="modal-email">&times;</span>
                <h2>Edit Email</h2>
                <form method="POST">
                    <input type="hidden" name="update_profile" value="1">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" placeholder="Email">
                    <button type="submit" class="btn btn-save">Save</button>
                    <button type="button" class="btn btn-cancel" data-modal="modal-email">Cancel</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($current_user_id == $user_id): ?>
        <div id="modal-skills" class="modal">
            <div class="modal-content bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                <span class="close" data-modal="modal-skills">&times;</span>
                <h2>Edit Skills</h2>
                <button type="button" class="btn btn-small" onclick="openSubModal('submodal-new-skill')">Add New Skill</button>
                <form method="POST">
                    <input type="hidden" name="update_profile" value="1">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <select id="skills-select-modal" name="skills[]" multiple="multiple" class="skills-select">
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
                    <button type="submit" class="btn btn-save">Save</button>
                    <button type="button" class="btn btn-cancel" data-modal="modal-skills">Cancel</button>
                </form>
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
        <div id="modal-hobbies" class="modal">
            <div class="modal-content bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                <span class="close" data-modal="modal-hobbies">&times;</span>
                <h2>Edit Hobbies</h2>
                <button type="button" class="btn btn-small" onclick="openSubModal('submodal-new-hobby')">Add New Hobby</button>
                <form method="POST">
                    <input type="hidden" name="update_profile" value="1">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <select id="hobbies-select-modal" name="hobbies[]" multiple="multiple" class="skills-select">
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
                    <button type="submit" class="btn btn-save">Save</button>
                    <button type="button" class="btn btn-cancel" data-modal="modal-hobbies">Cancel</button>
                </form>
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
        <div id="modal-projects" class="modal">
            <div class="modal-content bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                <span class="close" data-modal="modal-projects">&times;</span>
                <h2>Edit Projects</h2>
                <form method="POST" action="profile.php">
                    <input type="hidden" name="update_profile" value="1">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <div id="projects-modal-container">
                        <?php foreach ($projects as $project): ?>
                            <input type="text" name="projects[]" value="<?php echo htmlspecialchars($project['project_name']); ?>" placeholder="Project Name">
                            <input type="text" name="project_descriptions[]" value="<?php echo htmlspecialchars($project['description']); ?>" placeholder="Description">
                        <?php endforeach; ?>
                    </div>
                    <button type="button" onclick="addInput('projects-modal-container', [{name: 'projects[]', placeholder: 'Project Name'}, {name: 'project_descriptions[]', placeholder: 'Description'}])">Add Project</button>
                    <button type="submit" class="btn btn-save">Save</button>
                    <button type="button" class="btn btn-cancel" data-modal="modal-projects">Cancel</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($current_user_id == $user_id): ?>
        <div id="modal-awards" class="modal">
            <div class="modal-content bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                <span class="close" data-modal="modal-awards">&times;</span>
                <h2>Edit Awards</h2>
                <form method="POST" action="profile.php">
                    <input type="hidden" name="update_profile" value="1">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <div id="awards-modal-container">
                        <?php foreach ($awards as $award): ?>
                            <input type="text" name="awards[]" value="<?php echo htmlspecialchars($award['award_name']); ?>" placeholder="Award Name">
                            <input type="number" name="award_years[]" value="<?php echo htmlspecialchars($award['year']); ?>" placeholder="Year">
                        <?php endforeach; ?>
                    </div>
                    <button type="button" onclick="addInput('awards-modal-container', [{name: 'awards[]', placeholder: 'Award Name'}, {name: 'award_years[]', placeholder: 'Year'}])">Add Award</button>
                    <button type="submit" class="btn btn-save">Save</button>
                    <button type="button" class="btn btn-cancel" data-modal="modal-awards">Cancel</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($current_user_id == $user_id): ?>
        <div id="modal-experience" class="modal">
            <div class="modal-content bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                <span class="close" data-modal="modal-experience">&times;</span>
                <h2>Edit Experience</h2>
                <form method="POST" action="profile.php">
                    <input type="hidden" name="update_profile" value="1">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <input type="number" name="years_experience" value="<?php echo $user['years_experience']; ?>" min="0" placeholder="Years of Experience">
                    <button type="submit" class="btn btn-save">Save</button>
                    <button type="button" class="btn btn-cancel" data-modal="modal-experience">Cancel</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($current_user_id == $user_id): ?>
        <div id="modal-certificates" class="modal">
            <div class="modal-content bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                <span class="close" data-modal="modal-certificates">&times;</span>
                <h2>Edit Certificates</h2>
                <form method="POST" action="profile.php">
                    <input type="hidden" name="update_profile" value="1">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <div id="certificates-modal-container">
                        <?php foreach ($certificates as $cert): ?>
                            <input type="text" name="certificates[]" value="<?php echo htmlspecialchars($cert['certificate_name']); ?>" placeholder="Certificate Name">
                            <input type="text" name="certificate_issuers[]" value="<?php echo htmlspecialchars($cert['issuer']); ?>" placeholder="Issuer">
                        <?php endforeach; ?>
                    </div>
                    <button type="button" onclick="addInput('certificates-modal-container', [{name: 'certificates[]', placeholder: 'Certificate Name'}, {name: 'certificate_issuers[]', placeholder: 'Issuer'}])">Add Certificate</button>
                    <button type="submit" class="btn btn-save">Save</button>
                    <button type="button" class="btn btn-cancel" data-modal="modal-certificates">Cancel</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($current_user_id == $user_id): ?>
        <div id="modal-socialmedia" class="modal">
            <div class="modal-content bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                <span class="close" data-modal="modal-socialmedia">&times;</span>
                <h2>Edit Social Media</h2>
                <form method="POST" action="profile.php">
                    <input type="hidden" name="update_profile" value="1">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <div id="socialmedia-modal-container">
                        <?php foreach ($social_media as $social): ?>
                            <input type="text" name="social_media_platforms[]" value="<?php echo htmlspecialchars($social['platform']); ?>" placeholder="Platform">
                            <input type="text" name="social_media_urls[]" value="<?php echo htmlspecialchars($social['url']); ?>" placeholder="URL">
                        <?php endforeach; ?>
                    </div>
                    <button type="button" onclick="addInput('socialmedia-modal-container', [{name: 'social_media_platforms[]', placeholder: 'Platform'}, {name: 'social_media_urls[]', placeholder: 'URL'}])">Add Social Media</button>
                    <button type="submit" class="btn btn-save">Save</button>
                    <button type="button" class="btn btn-cancel" data-modal="modal-socialmedia">Cancel</button>
                </form>
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
                    console.log("Form submitted!");
                    e.preventDefault(); // Prevent default form submission

                    var form = $(this);
                    var button = form.find('.btn-save');
                    var formData = new FormData(form[0]);
                    var modalId = form.closest('.modal').attr('id');

                    console.log('Modal ID:', modalId);
                    console.log('Form data:', formData);

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
                                    skillsContainer.append('<span class="bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-3 py-1 rounded-full text-sm">' + skill + '</span>');
                                });

                                const hobbiesContainer = $('.section-hobbies .hobbies-container');
                                hobbiesContainer.empty();
                                response.hobbies.forEach(function(hobby) {
                                    hobbiesContainer.append('<span class="bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-3 py-1 rounded-full text-sm">' + hobby + '</span>');
                                });

                                const projectsUl = $('.section-projects ul');
                                projectsUl.empty();
                                response.projects.forEach(function(project) {
                                    projectsUl.append('<li>' + project.project_name + ' - ' + project.description + '</li>');
                                });

                                const awardsUl = $('.section-awards ul');
                                awardsUl.empty();
                                response.awards.forEach(function(award) {
                                    awardsUl.append('<li>' + award.award_name + ' (' + award.year + ')</li>');
                                });

                                $('.section-experience p').text(response.user.years_experience + ' Years of Experience');

                                const certUl = $('.section-certificates ul');
                                certUl.empty();
                                response.certificates.forEach(function(cert) {
                                    certUl.append('<li>' + cert.certificate_name + ' by ' + cert.issuer + '</li>');
                                });

                                const socialUl = $('.section-socialmedia ul');
                                socialUl.empty();
                                response.social_media.forEach(function(social) {
                                    socialUl.append('<li><a href="' + social.url + '" target="_blank" class="text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">' + social.platform + '</a></li>');
                                });

                                const socialLinks = $('.social-links');
                                socialLinks.empty();
                                response.social_media.forEach(function(social) {
                                    var icon = '';
                                    if (social.platform == 'Facebook') icon = 'facebook.png';
                                    else if (social.platform == 'Instagram') icon = 'instagram.png';
                                    else if (social.platform == 'X') icon = 'twitter.png';
                                    socialLinks.append('<a href="' + social.url + '" target="_blank"><img src="icons/' + icon + '" alt="' + social.platform + '" class="w-8 h-8"></a>');
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
                            console.log('AJAX Error:', status, error);
                            console.log('Response Text:', xhr.responseText);
                            // Show error notification
                            showNotification('Error updating profile. Please try again.', 'error');

                            // Re-enable submit button
                            button.prop('disabled', false).text('Save');
                        }
                    });
                    console.log('AJAX request sent');
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
        </script>


    </div>

</body>
</html>
