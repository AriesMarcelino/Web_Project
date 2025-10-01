<?php
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

$userObj = new User();
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Fetch predefined skills and hobbies
$predefined_skills = $userObj->getPredefinedSkills();
$predefined_hobbies = $userObj->getPredefinedHobbies();

// Handle profile update POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile']) && $current_user_id) {
    if ($current_user_id != $_POST['user_id']) {
        echo "Unauthorized update attempt.";
        exit();
    }

    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload_result = $userObj->uploadProfilePicture($current_user_id, $_FILES['profile_picture']);
        if ($upload_result !== true) {
            echo "<p style='color:red;'>Error uploading profile picture: " . htmlspecialchars($upload_result) . "</p>";
        }
    }

    // Sanitize and collect user inputs
    $bio = $_POST['bio'] ?? '';
    $background = $_POST['background'] ?? '';
    $years_experience = intval($_POST['years_experience'] ?? 0);
    $email = $_POST['email'] ?? '';

    // Update main user info
    $userObj->updateUserInfo($current_user_id, $bio, $background, $years_experience, $email);

    // Update pivot tables
    $userObj->updateSkills($current_user_id, $_POST['skills'] ?? []);
    $userObj->updateHobbies($current_user_id, $_POST['hobbies'] ?? []);
    $userObj->updateProjects($current_user_id, $_POST['projects'] ?? [], $_POST['project_descriptions'] ?? []);
    $userObj->updateAwards($current_user_id, $_POST['awards'] ?? [], $_POST['award_years'] ?? []);
    $userObj->updateCertificates($current_user_id, $_POST['certificates'] ?? [], $_POST['certificate_issuers'] ?? []);

    // Build social media array
    $social_media = [];
    $platforms = $_POST['social_media_platforms'] ?? [];
    $urls = $_POST['social_media_urls'] ?? [];
    foreach ($platforms as $index => $platform) {
        $url = $urls[$index] ?? '';
        if (trim($platform) !== '' && trim($url) !== '') {
            $social_media[] = ['platform' => $platform, 'url' => $url];
        }
    }
    $userObj->updateSocialMedia($current_user_id, $social_media);

    // Reload updated user data
    $user = $userObj->getUserByUsername($_SESSION['username']);
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
            // Improved toggle edit function with smooth animation
            window.toggleEdit = function() {
                $('#view-mode, #edit-mode').slideToggle(300);
            };

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

            // Enhanced form validation
            $('#edit-mode form').on('submit', function(e) {
                var form = $(this);
                var isValid = true;

                // Check required fields
                form.find('input[required], textarea[required]').each(function() {
                    if (!$(this).val().trim()) {
                        $(this).addClass('error');
                        isValid = false;
                    } else {
                        $(this).removeClass('error');
                    }
                });

                // Email validation
                var emailField = form.find('input[type="email"]');
                if (emailField.length && emailField.val()) {
                    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(emailField.val())) {
                        emailField.addClass('error');
                        isValid = false;
                    }
                }

                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields correctly.');
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

            $('input[name="profile_picture"]').change(function() {
                var file = this.files[0];
                if (file) {
                    var fileSize = (file.size / 1024 / 1024).toFixed(2);
                    if (fileSize > 5) {
                        alert('File size must be less than 5MB');
                        $(this).val('');
                    }
                }
            });

            // Initialize Select2 for skills
            $('#skills-select').select2({
                placeholder: 'Select skills or type to create new ones...',
                allowClear: true,
                tags: true, // Allow custom tags
                tokenSeparators: [','],
                width: '100%',
                createTag: function (params) {
                    // Allow creation of new tags
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

            // Handle skill selection and creation
            $('#skills-select').on('change', function() {
                var selectedValues = $(this).val() || [];
                updateSkillsDisplay(selectedValues);
            });

            // Function to update skills display
            function updateSkillsDisplay(selectedSkills) {
                var container = $('#skills-display-container');
                container.empty(); // Clear existing tags

                if (selectedSkills && selectedSkills.length > 0) {
                    selectedSkills.forEach(function(skill) {
                        if (skill && skill.trim()) {
                            var skillTag = $('<span class="skill-tag" data-skill="' + skill + '">' +
                                skill + '<span class="remove-skill" onclick="removeSkill(this)">×</span></span>');
                            container.append(skillTag);
                        }
                    });
                }
            }

            // Function to remove skill
            window.removeSkill = function(element) {
                var skillTag = $(element).parent();
                var skillValue = skillTag.data('skill');

                // Remove from Select2
                var currentValues = $('#skills-select').val() || [];
                var newValues = currentValues.filter(function(value) {
                    return value !== skillValue;
                });
                $('#skills-select').val(newValues).trigger('change');

                // Remove the tag with animation
                skillTag.fadeOut(300, function() {
                    $(this).remove();
                });
            };

            // Initialize skills display on page load
            var initialSkills = <?php echo json_encode(array_map('trim', $skills)); ?>;
            if (initialSkills.length > 0) {
                $('#skills-select').val(initialSkills).trigger('change');
            }

            // Initialize Select2 for hobbies
            $('#hobbies-select').select2({
                placeholder: 'Select hobbies or type to create new ones...',
                allowClear: true,
                tags: true, // Allow custom tags
                tokenSeparators: [','],
                width: '100%',
                createTag: function (params) {
                    // Allow creation of new tags
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

            // Handle hobby selection and creation
            $('#hobbies-select').on('change', function() {
                var selectedValues = $(this).val() || [];
                updateHobbiesDisplay(selectedValues);
            });

            // Function to update hobbies display
            function updateHobbiesDisplay(selectedHobbies) {
                var container = $('#hobbies-display-container');
                container.empty(); // Clear existing tags

                if (selectedHobbies && selectedHobbies.length > 0) {
                    selectedHobbies.forEach(function(hobby) {
                        if (hobby && hobby.trim()) {
                            var hobbyTag = $('<span class="skill-tag" data-hobby="' + hobby + '">' +
                                hobby + '<span class="remove-skill" onclick="removeHobby(this)">×</span></span>');
                            container.append(hobbyTag);
                        }
                    });
                }
            }

            // Function to remove hobby
            window.removeHobby = function(element) {
                var hobbyTag = $(element).parent();
                var hobbyValue = hobbyTag.data('hobby');

                // Remove from Select2
                var currentValues = $('#hobbies-select').val() || [];
                var newValues = currentValues.filter(function(value) {
                    return value !== hobbyValue;
                });
                $('#hobbies-select').val(newValues).trigger('change');

                // Remove the tag with animation
                hobbyTag.fadeOut(300, function() {
                    $(this).remove();
                });
            };

            // Initialize hobbies display on page load
            var initialHobbies = <?php echo json_encode(array_map('trim', $hobbies)); ?>;
            if (initialHobbies.length > 0) {
                $('#hobbies-select').val(initialHobbies).trigger('change');
            }
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

    <!-- Layout: Left + Right -->
    <div class="layout">
        <div id="view-mode">
            <!-- Left side -->
            <div class="left-side">
                <?php if ($current_user_id && $current_user_id == $user_id): ?>
                    <button class="btn btn-edit" onclick="toggleEdit()">Edit Profile</button>
                <?php endif; ?>
                <div class="section bio">
                    <h3>Bio</h3>
                    <p><?php echo $user['bio']; ?></p>
                </div>
                <div class="section background">
                    <h3>Background</h3>
                    <p><?php echo $user['background']; ?></p>
                </div>
                <div class="section skills">
                    <h3>Skills</h3>
                    <div class="oval-container">
                        <?php foreach ($skills as $skill): ?>
                            <span class="oval"><?php echo $skill; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="section hobbies">
                    <h3>Hobbies</h3>
                    <div class="oval-container">
                        <?php foreach ($hobbies as $hobby): ?>
                            <span class="oval"><?php echo $hobby; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Right side -->
            <div class="right-side">
                <div class="section">
                    <h3>Projects</h3>
                    <ul>
                        <?php foreach ($projects as $project): ?>
                            <li><?php echo $project['project_name']; ?> - <?php echo $project['description']; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="section">
                    <h3>Awards</h3>
                    <ul>
                        <?php foreach ($awards as $award): ?>
                            <li><?php echo $award['award_name']; ?> (<?php echo $award['year']; ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="section">
                    <h3>Experience</h3>
                    <p><?php echo $user['years_experience']; ?> Years of Experience</p>
                </div>
                <div class="section">
                    <h3>Certificates</h3>
                    <ul>
                        <?php foreach ($certificates as $cert): ?>
                            <li><?php echo $cert['certificate_name']; ?> by <?php echo $cert['issuer']; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="section">
                    <h3>Social Media</h3>
                    <ul>
                        <?php foreach ($social_media as $social): ?>
                            <li><a href="<?php echo $social['url']; ?>" target="_blank"><?php echo $social['platform']; ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div id="edit-mode" style="display:none;">
            <form method="POST" action="profile.php" enctype="multipart/form-data">
                <input type="hidden" name="update_profile" value="1">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">

                <div class="left-side">
                    <div class="section profile-pic">
                        <h3>Profile Picture</h3>
                        <input type="file" name="profile_picture" accept="image/*">
                    </div>
                    <div class="section bio">
                        <h3>Bio</h3>
                        <textarea name="bio" rows="4"><?php echo htmlspecialchars($user['bio']); ?></textarea>
                    </div>
                    <div class="section background">
                        <h3>Background</h3>
                        <textarea name="background" rows="4"><?php echo htmlspecialchars($user['background']); ?></textarea>
                    </div>
                    <div class="section experience">
                        <h3>Years of Experience</h3>
                        <input type="number" name="years_experience" value="<?php echo $user['years_experience']; ?>" min="0">
                    </div>
                    <div class="section email">
                        <h3>Email</h3>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                    </div>
                    <div class="section skills">
                        <h3>Skills</h3>
                        <div class="skills-selection-container">
                            <div class="skills-select-wrapper">
                                <select id="skills-select" name="skills[]" multiple="multiple" class="skills-select">
                                    <?php
                                    // Get current user's skills
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
                            <div id="selected-skills-display" class="selected-skills-display">
                                <h4>Selected Skills:</h4>
                                <div id="skills-display-container" class="skills-display-container">
                                    <?php foreach ($skills as $skill): ?>
                                        <span class="skill-tag" data-skill="<?php echo htmlspecialchars($skill); ?>">
                                            <?php echo htmlspecialchars($skill); ?>
                                            <span class="remove-skill" onclick="removeSkill(this)">×</span>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="section hobbies">
                        <h3>Hobbies</h3>
                        <div class="skills-selection-container">
                            <div class="skills-select-wrapper">
                                <select id="hobbies-select" name="hobbies[]" multiple="multiple" class="skills-select">
                                    <?php
                                    // Get current user's hobbies
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
                            <div id="selected-hobbies-display" class="selected-skills-display">
                                <h4>Selected Hobbies:</h4>
                                <div id="hobbies-display-container" class="skills-display-container">
                                    <?php foreach ($hobbies as $hobby): ?>
                                        <span class="skill-tag" data-hobby="<?php echo htmlspecialchars($hobby); ?>">
                                            <?php echo htmlspecialchars($hobby); ?>
                                            <span class="remove-skill" onclick="removeHobby(this)">×</span>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="right-side">
                    <div class="section projects">
                        <h3>Projects</h3>
                        <div id="projects-container">
                            <?php foreach ($projects as $project): ?>
                                <input type="text" name="projects[]" value="<?php echo htmlspecialchars($project['project_name']); ?>" placeholder="Project Name">
                                <input type="text" name="project_descriptions[]" value="<?php echo htmlspecialchars($project['description']); ?>" placeholder="Description">
                            <?php endforeach; ?>
                        </div>
                        <button type="button" onclick="addInput('projects-container', [{name: 'projects[]', placeholder: 'Project Name'}, {name: 'project_descriptions[]', placeholder: 'Description'}])">Add Project</button>
                    </div>
                    <div class="section awards">
                        <h3>Awards</h3>
                        <div id="awards-container">
                            <?php foreach ($awards as $award): ?>
                                <input type="text" name="awards[]" value="<?php echo htmlspecialchars($award['award_name']); ?>" placeholder="Award Name">
                                <input type="number" name="award_years[]" value="<?php echo htmlspecialchars($award['year']); ?>" placeholder="Year">
                            <?php endforeach; ?>
                        </div>
                        <button type="button" onclick="addInput('awards-container', [{name: 'awards[]', placeholder: 'Award Name'}, {name: 'award_years[]', placeholder: 'Year'}])">Add Award</button>
                    </div>
                    <div class="section certificates">
                        <h3>Certificates</h3>
                        <div id="certificates-container">
                            <?php foreach ($certificates as $cert): ?>
                                <input type="text" name="certificates[]" value="<?php echo htmlspecialchars($cert['certificate_name']); ?>" placeholder="Certificate Name">
                                <input type="text" name="certificate_issuers[]" value="<?php echo htmlspecialchars($cert['issuer']); ?>" placeholder="Issuer">
                            <?php endforeach; ?>
                        </div>
                        <button type="button" onclick="addInput('certificates-container', [{name: 'certificates[]', placeholder: 'Certificate Name'}, {name: 'certificate_issuers[]', placeholder: 'Issuer'}])">Add Certificate</button>
                    </div>
                    <div class="section social_media">
                        <h3>Social Media</h3>
                        <div id="social_media-container">
                            <?php foreach ($social_media as $social): ?>
                                <input type="text" name="social_media_platforms[]" value="<?php echo htmlspecialchars($social['platform']); ?>" placeholder="Platform">
                                <input type="text" name="social_media_urls[]" value="<?php echo htmlspecialchars($social['url']); ?>" placeholder="URL">
                            <?php endforeach; ?>
                        </div>
                        <button type="button" onclick="addInput('social_media-container',[{name: 'social_media_platforms[]', placeholder: 'Platform'}, {name: 'social_media_urls[]', placeholder: 'URL'}])">Add Social Media</button>
                    </div>
                </div>

                <div style="clear: both;"></div>
                <button type="submit" class="btn btn-save">Save Changes</button>
                <button type="button" class="btn btn-cancel" onclick="toggleEdit()">Cancel</button>
            </form>
        </div>
    </div>

</body>
</html>
