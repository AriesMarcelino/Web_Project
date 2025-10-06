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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
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
        $skills = $userObj->getSkills($user_id);
        $hobbies = $userObj->getHobbies($user_id);
        $projects = $userObj->getProjects($user_id);
        $awards = $userObj->getAwards($user_id);
        $certificates = $userObj->getCertificates($user_id);
        $social_media = $userObj->getSocialMedia($user_id);
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
            echo json_encode(['success' => false, 'message' => 'Failed to update profile. Please try again.']);
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 CSS and JS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
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

            // Example usage: showNotification('Profile updated successfully!', 'success');
            <?php if (isset($_SESSION['notification'])): ?>
                showNotification("<?php echo addslashes($_SESSION['notification']); ?>", "<?php echo $_SESSION['notification_type'] ?? 'info'; ?>");
                <?php unset($_SESSION['notification'], $_SESSION['notification_type']); ?>
            <?php endif; ?>
        });
    </script>
</head>
<body>

    <!-- Navigation Bar -->
    <div class="navbar">

        <ul>
            <li><a href="profile.php?username=<?php echo urlencode($_SESSION['username']); ?>">Home</a></li>
            <li><a href="#portfolio">Portfolio</a></li>
            <li><a href="#projects">Projects</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="#contact">Contact</a></li>
            <a href="logout.php" class="logout-btn nav-logout">Logout</a>
        </ul>

        <!-- Search Bar -->
        <form method="POST" class="search-form">
            <input type="text" name="search_username" placeholder="Search username" required>
            <button type="submit">Search</button>
        </form>

    </div>

    <!-- Profile Header -->
    <div class="profile">
        <img src="uploads/<?php echo $user['profile_pic']; ?>" alt="Profile Picture">
        <h2><?php echo $user['username']; ?></h2>
        <p class="email"><?php echo $user['email']; ?></p>

        <!-- Social Media -->
        <div class="social-links">
            <?php foreach ($social_media as $social): ?>
                <?php
                $icon = '';
                if ($social['platform'] == 'Facebook') $icon = 'facebook.png';
                elseif ($social['platform'] == 'Instagram') $icon = 'instagram.png';
                elseif ($social['platform'] == 'X') $icon = 'twitter.png';
                ?>
                <a href="<?php echo $social['url']; ?>" target="_blank">
                    <img src="icons/<?php echo $icon; ?>" alt="<?php echo $social['platform']; ?>">
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Stats & Buttons -->
        <div class="stats">
            <span class="follower-following" id="followers">Followers: <?php echo $followers_count; ?></span> |
            <span class="follower-following" id="following">Following: <?php echo $following_count; ?></span>
        </div>

        <?php if ($current_user_id && $current_user_id != $user_id): ?>
            <form method="POST" action="follow_handler.php" class="follow-form">
                <input type="hidden" name="following_id" value="<?php echo $user_id; ?>">
                <input type="hidden" name="profile_username" value="<?php echo $user['username']; ?>">
                <button class = "btn btn-follow" type="submit" name="action" value="<?php echo $isFollowing ? 'unfollow' : 'follow'; ?>">
                    <?php echo $isFollowing ? 'Unfollow' : 'Follow'; ?>
                </button>
            </form>
        <?php endif; ?>
        
        <hr class="separator">
    </div>

    <!-- Notification container -->
    <div id="notification" class="notification" style="display: none;"></div>

    <!-- Layout: Left + Right -->
    <div class="layout">
        <div id="view-mode">
            <!-- Left side -->
            <div class="left-side">
                <div class="section bio">
                    <div class="section-header">
                        <h3>Bio</h3>
                        <button class="edit-btn" title="Edit Bio">&#9998;</button>
                    </div>
                    <p><?php echo $user['bio']; ?></p>
                </div>
                <div class="section background">
                    <div class="section-header">
                        <h3>Background</h3>
                        <button class="edit-btn" title="Edit Background">&#9998;</button>
                    </div>
                    <p><?php echo $user['background']; ?></p>
                </div>
                <div class="section email">
                    <div class="section-header">
                        <h3>Email</h3>
                        <button class="edit-btn" title="Edit Email">&#9998;</button>
                    </div>
                    <p><?php echo $user['email']; ?></p>
                </div>
                <div class="section skills">
                    <div class="section-header">
                        <h3>Skills</h3>
                        <button class="edit-btn" title="Edit Skills">&#9998;</button>
                    </div>
                    <div class="oval-container">
                        <?php foreach ($skills as $skill): ?>
                            <span class="oval"><?php echo $skill; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="section hobbies">
                    <div class="section-header">
                        <h3>Hobbies</h3>
                        <button class="edit-btn" data-modal="modal-hobbies" title="Edit Hobbies">&#9998;</button>
                    </div>
                    <div class="oval-container">
                        <?php foreach ($hobbies as $hobby): ?>
                            <span class="oval"><?php echo $hobby; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Right side -->
            <div class="right-side">
                <div class="section projects">
                    <div class="section-header">
                        <h3>Projects</h3>
                        <button class="edit-btn" title="Edit Projects">&#9998;</button>
                    </div>
                    <ul>
                        <?php foreach ($projects as $project): ?>
                            <li><?php echo $project['project_name']; ?> - <?php echo $project['description']; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="section">
                    <div class="section-header">
                        <h3>Awards</h3>
                        <button class="edit-btn" data-modal="modal-awards" title="Edit Awards">&#9998;</button>
                    </div>
                    <ul>
                        <?php foreach ($awards as $award): ?>
                            <li><?php echo $award['award_name']; ?> (<?php echo $award['year']; ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="section">
                    <div class="section-header">
                        <h3>Experience</h3>
                        <button class="edit-btn" data-modal="modal-experience" title="Edit Experience">&#9998;</button>
                    </div>
                    <p><?php echo $user['years_experience']; ?> Years of Experience</p>
                </div>
                <div class="section">
                    <div class="section-header">
                        <h3>Certificates</h3>
                        <button class="edit-btn" data-modal="modal-certificates" title="Edit Certificates">&#9998;</button>
                    </div>
                    <ul>
                        <?php foreach ($certificates as $cert): ?>
                            <li><?php echo $cert['certificate_name']; ?> by <?php echo $cert['issuer']; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="section">
                    <div class="section-header">
                        <h3>Social Media</h3>
                        <button class="edit-btn" data-modal="modal-socialmedia" title="Edit Social Media">&#9998;</button>
                    </div>
                    <ul>
                        <?php foreach ($social_media as $social): ?>
                            <li><a href="<?php echo $social['url']; ?>" target="_blank"><?php echo $social['platform']; ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Modals for editing sections -->
        <div id="modal-bio" class="modal">
            <div class="modal-content">
                <span class="close" data-modal="modal-bio">&times;</span>
                <h2>Edit Bio</h2>
                <form method="POST" action="profile.php">
                    <input type="hidden" name="update_profile" value="1">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <textarea name="bio" rows="4"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                    <button type="submit" class="btn btn-save">Save</button>
                    <button type="button" class="btn btn-cancel" data-modal="modal-bio">Cancel</button>
                </form>
            </div>
        </div>

        <div id="modal-background" class="modal">
            <div class="modal-content">
                <span class="close" data-modal="modal-background">&times;</span>
                <h2>Edit Background</h2>
                <form method="POST" action="profile.php">
                    <input type="hidden" name="update_profile" value="1">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <textarea name="background" rows="4"><?php echo htmlspecialchars($user['background'] ?? ''); ?></textarea>
                    <button type="submit" class="btn btn-save">Save</button>
                    <button type="button" class="btn btn-cancel" data-modal="modal-background">Cancel</button>
                </form>
            </div>
        </div>

        <div id="modal-email" class="modal">
            <div class="modal-content">
                <span class="close" data-modal="modal-email">&times;</span>
                <h2>Edit Email</h2>
                <form method="POST" action="profile.php">
                    <input type="hidden" name="update_profile" value="1">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" placeholder="Email">
                    <button type="submit" class="btn btn-save">Save</button>
                    <button type="button" class="btn btn-cancel" data-modal="modal-email">Cancel</button>
                </form>
            </div>
        </div>

        <div id="modal-skills" class="modal">
            <div class="modal-content">
                <span class="close" data-modal="modal-skills">&times;</span>
                <h2>Edit Skills</h2>
                <button type="button" class="btn btn-small" onclick="openSubModal('submodal-new-skill')">Add New Skill</button>
                <form method="POST" action="profile.php">
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

        <!-- Sub-modal for adding new skill -->
        <div id="submodal-new-skill" class="submodal">
            <div class="submodal-content">
                <span class="close" data-submodal="submodal-new-skill">&times;</span>
                <h3>Add New Skill</h3>
                <input type="text" id="new-skill-name" placeholder="Enter skill name" class="custom-skill-input">
                <button type="button" class="btn btn-small" onclick="addNewSkill()">Add Skill</button>
            </div>
        </div>

        <div id="modal-hobbies" class="modal">
            <div class="modal-content">
                <span class="close" data-modal="modal-hobbies">&times;</span>
                <h2>Edit Hobbies</h2>
                <button type="button" class="btn btn-small" onclick="openSubModal('submodal-new-hobby')">Add New Hobby</button>
                <form method="POST" action="profile.php">
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

        <!-- Sub-modal for adding new hobby -->
        <div id="submodal-new-hobby" class="submodal">
            <div class="submodal-content">
                <span class="close" data-submodal="submodal-new-hobby">&times;</span>
                <h3>Add New Hobby</h3>
                <input type="text" id="new-hobby-name" placeholder="Enter hobby name" class="custom-skill-input">
                <button type="button" class="btn btn-small" onclick="addNewHobby()">Add Hobby</button>
            </div>
        </div>

        <div id="modal-projects" class="modal">
            <div class="modal-content">
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

        <div id="modal-awards" class="modal">
            <div class="modal-content">
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

        <div id="modal-experience" class="modal">
            <div class="modal-content">
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

        <div id="modal-certificates" class="modal">
            <div class="modal-content">
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

        <div id="modal-socialmedia" class="modal">
            <div class="modal-content">
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
                $('.modal .btn-save').on('click', function(e) {
                    e.preventDefault(); // Prevent default form submission

                    var button = $(this);
                    var form = button.closest('form');
                    var formData = new FormData(form[0]);
                    var modalId = form.closest('.modal').attr('id');

                    // console.log('Modal ID:', modalId);
                    // console.log('Form data:', formData);

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
                                $('.section.bio p').text(response.user.bio);
                                $('.section.background p').text(response.user.background);
                                $('.section.email p').text(response.user.email);
                                $('.profile .email').text(response.user.email);

                                const skillsContainer = $('.section.skills .oval-container');
                                skillsContainer.empty();
                                response.skills.forEach(function(skill) {
                                    skillsContainer.append('<span class="oval">' + skill + '</span>');
                                });

                                const hobbiesContainer = $('.section.hobbies .oval-container');
                                hobbiesContainer.empty();
                                response.hobbies.forEach(function(hobby) {
                                    hobbiesContainer.append('<span class="oval">' + hobby + '</span>');
                                });

                                const projectsUl = $('.section.projects ul');
                                projectsUl.empty();
                                response.projects.forEach(function(project) {
                                    projectsUl.append('<li>' + project.project_name + ' - ' + project.description + '</li>');
                                });

                                const awardsUl = $('.section .section-header:contains("Awards")').next('ul');
                                awardsUl.empty();
                                response.awards.forEach(function(award) {
                                    awardsUl.append('<li>' + award.award_name + ' (' + award.year + ')</li>');
                                });

                                $('.section .section-header:contains("Experience")').next('p').text(response.user.years_experience + ' Years of Experience');

                                const certUl = $('.section .section-header:contains("Certificates")').next('ul');
                                certUl.empty();
                                response.certificates.forEach(function(cert) {
                                    certUl.append('<li>' + cert.certificate_name + ' by ' + cert.issuer + '</li>');
                                });

                                const socialUl = $('.section .section-header:contains("Social Media")').next('ul');
                                socialUl.empty();
                                response.social_media.forEach(function(social) {
                                    socialUl.append('<li><a href="' + social.url + '" target="_blank">' + social.platform + '</a></li>');
                                });

                                const socialLinks = $('.social-links');
                                socialLinks.empty();
                                response.social_media.forEach(function(social) {
                                    var icon = '';
                                    if (social.platform == 'Facebook') icon = 'facebook.png';
                                    else if (social.platform == 'Instagram') icon = 'instagram.png';
                                    else if (social.platform == 'X') icon = 'twitter.png';
                                    socialLinks.append('<a href="' + social.url + '" target="_blank"><img src="icons/' + icon + '" alt="' + social.platform + '"></a>');
                                });

                                // Close modal
                                $('#' + modalId).fadeOut();

                                // Re-enable submit button
                                form.find('button[type="submit"]').prop('disabled', false).text('Save');
                            } else {
                                // Show error notification
                                showNotification(response.message, 'error');

                                // Re-enable submit button
                                form.find('button[type="submit"]').prop('disabled', false).text('Save');
                            }
                        },
                        error: function(xhr, status, error) {
                            // Show error notification
                            showNotification('Error updating profile. Please try again.', 'error');

                            // Re-enable submit button
                            form.find('button[type="submit"]').prop('disabled', false).text('Save');
                        }
                    });
                });
            });
        </script>


    </div>

</body>
</html>
