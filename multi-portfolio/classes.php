<?php
// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); 
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');
// Custom error handler function
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $error_message = "[Error $errno] $errstr in $errfile on line $errline";
    error_log($error_message);
    // Optionally display a generic message to users
    echo "An internal error occurred. Please try again later.";
    return true; // Prevent PHP internal error handler
}
set_error_handler("customErrorHandler");
class Database {
    private $conn;
    public function __construct() {
        try {
            $this->conn = new mysqli("localhost", "root", "", "portfolio_db");
            if ($this->conn->connect_error) {
                throw new Exception("Connection failed: " . $this->conn->connect_error);
            }
        } catch (Exception $e) {
            error_log("Database connection error in Database class: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }
    public function getConnection() {
        return $this->conn;
    }
}
class User {
    protected $db;
    private $id;    

    public function __construct() {
        $this->db = new Database();
    }
    // Update main user info: bio, background, years_experience, email
    public function updateUserInfo($user_id, $bio, $background, $years_experience, $email) {
        try {
            $conn = $this->db->getConnection();
            $sql = "UPDATE users SET bio = ?, background = ?, years_experience = ?, email = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssisi", $bio, $background, $years_experience, $email, $user_id);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error in updateUserInfo: " . $e->getMessage());
            return false;
        }
    }
    // Helper function to delete all entries from a pivot table for a user
    private function deleteFromTable($table, $user_id) {
        try {
            $conn = $this->db->getConnection();
            $sql = "DELETE FROM $table WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error in deleteFromTable: " . $e->getMessage());
            return false;
        }
    }
    // Update skills pivot table
    public function updateSkills($user_id, $skills) {
        try {
            // Clear existing skills for the user
            $this->deleteFromTable('skill_user', $user_id);
            if (empty($skills)) return true;

            $conn = $this->db->getConnection();

            foreach ($skills as $skill_name) {
                $skill_name = trim(($skill_name));
                if ($skill_name === '') continue;

                // Check if skill already exists
                $checkSkill = $conn->prepare("SELECT id FROM skills WHERE skill_name = ?");
                $checkSkill->bind_param("s", $skill_name);
                $checkSkill->execute();
                $result = $checkSkill->get_result();
                $skill = $result->fetch_assoc();

                if ($skill) {
                    $skill_id = $skill['id'];
                } else {

                    $insertSkill = $conn->prepare("INSERT INTO skills (skill_name) VALUES (?)");
                    $insertSkill->bind_param("s", $skill_name);
                    $insertSkill->execute();
                    $skill_id = $conn->insert_id;
                }
                // Insert into pivot table
                $insertPivot = $conn->prepare("INSERT INTO skill_user (user_id, skill_id) VALUES (?, ?)");
                $insertPivot->bind_param("ii", $user_id, $skill_id);
                $insertPivot->execute();
            }
            return true;
        } catch (Exception $e) {
            error_log("Error in updateSkills: " . $e->getMessage());
            return false;
        }
    }
    // Update hobbies pivot table
    public function updateHobbies($user_id, $hobbies) {
    // Clear existing hobbies for the user
    $this->deleteFromTable('hobby_user', $user_id);

    if (empty($hobbies)) return true;

    $conn = $this->db->getConnection();

    foreach ($hobbies as $hobby_name) {
        $hobby_name = trim(($hobby_name));
        if ($hobby_name === '') continue;

        // Check if hobby already exists
        $checkHobby = $conn->prepare("SELECT id FROM hobbies WHERE hobby_name = ?");
        $checkHobby->bind_param("s", $hobby_name);
        $checkHobby->execute();
        $result = $checkHobby->get_result();
        $hobby = $result->fetch_assoc();

        if ($hobby) {
            $hobby_id = $hobby['id'];
        } else {
            // Insert new hobby
            $insertHobby = $conn->prepare("INSERT INTO hobbies (hobby_name) VALUES (?)");
            $insertHobby->bind_param("s", $hobby_name);
            $insertHobby->execute();
            $hobby_id = $conn->insert_id;
        }

        // Insert into pivot table
        $insertPivot = $conn->prepare("INSERT INTO hobby_user (user_id, hobby_id) VALUES (?, ?)");
        $insertPivot->bind_param("ii", $user_id, $hobby_id);
        $insertPivot->execute();
    }

    return true;
}
    // Update projects pivot table
    public function updateProjects($user_id, $projects, $descriptions = []) {
    // Clear existing project associations for the user
    $this->deleteFromTable('project_user', $user_id);

    if (empty($projects)) return true;

    $conn = $this->db->getConnection();

    foreach ($projects as $index => $project_name) {
        $project_name = trim(($project_name));
        if ($project_name === '') continue;

        $description = trim($descriptions[$index] ?? '');

        // Check if project already exists
        $checkProject = $conn->prepare("SELECT id FROM projects WHERE project_name = ? AND description = ?");
        $checkProject->bind_param("ss", $project_name, $description);
        $checkProject->execute();
        $result = $checkProject->get_result();
        $project = $result->fetch_assoc();

        if ($project) {
            $project_id = $project['id'];
        } else {
            // Insert new project
            $insertProject = $conn->prepare("INSERT INTO projects (project_name, description) VALUES (?, ?)");
            $insertProject->bind_param("ss", $project_name, $description);
            $insertProject->execute();
            $project_id = $conn->insert_id;
        }
        // Insert into pivot table
        $insertPivot = $conn->prepare("INSERT INTO project_user (user_id, project_id) VALUES (?, ?)");
        $insertPivot->bind_param("ii", $user_id, $project_id);
        $insertPivot->execute();
    }

    return true;
    }
    // Update awards pivot table
   public function updateAwards($user_id, $awards, $years = []) {
    // Clear existing award associations for the user
    $this->deleteFromTable('award_user', $user_id);

    if (empty($awards)) return true;

    $conn = $this->db->getConnection();

    foreach ($awards as $index => $award_name) {
        
        $award_name = trim($award_name);
        if ($award_name === '') continue;

        $year = intval($years[$index] ?? 0);

        // Check if award already exists
        $checkAward = $conn->prepare("SELECT id FROM awards WHERE award_name = ? AND year = ?");
        $checkAward->bind_param("si", $award_name, $year);
        $checkAward->execute();
        $result = $checkAward->get_result();
        $award = $result->fetch_assoc();

        if ($award) {
            $award_id = $award['id'];
        } else {
            // Insert new award
            $insertAward = $conn->prepare("INSERT INTO awards (award_name, year) VALUES (?, ?)");
            $insertAward->bind_param("si", $award_name, $year);
            $insertAward->execute();
            $award_id = $conn->insert_id;
        }

        // Insert into pivot table
        $insertPivot = $conn->prepare("INSERT INTO award_user (user_id, award_id) VALUES (?, ?)");
        $insertPivot->bind_param("ii", $user_id, $award_id);
        $insertPivot->execute();
    }

    return true;
}
    // Update certificates pivot table
    public function updateCertificates($user_id, $certificates, $issuers = []) {
    // Clear existing certificate associations for the user
    $this->deleteFromTable('certificate_user', $user_id);

    if (empty($certificates)) return true;

    $conn = $this->db->getConnection();

    foreach ($certificates as $index => $certificate_name) {
        $certificate_name = trim($certificate_name);
        if ($certificate_name === '') continue;

        $issuer = trim($issuers[$index] ?? '');

        // Check if certificate already exists
        $checkCertificate = $conn->prepare("SELECT id FROM certificates WHERE certificate_name = ? AND issuer = ?");
        $checkCertificate->bind_param("ss", $certificate_name, $issuer);
        $checkCertificate->execute();
        $result = $checkCertificate->get_result();
        $certificate = $result->fetch_assoc();

        if ($certificate) {
            $certificate_id = $certificate['id'];
        } else {
            // Insert new certificate
            $insertCertificate = $conn->prepare("INSERT INTO certificates (certificate_name, issuer) VALUES (?, ?)");
            $insertCertificate->bind_param("ss", $certificate_name, $issuer);
            $insertCertificate->execute();
            $certificate_id = $conn->insert_id;
        }
        // Insert into pivot table
        $insertPivot = $conn->prepare("INSERT INTO certificate_user (user_id, certificate_id) VALUES (?, ?)");
        $insertPivot->bind_param("ii", $user_id, $certificate_id);
        $insertPivot->execute();
    }

    return true;
}

    // Update social media pivot table
    public function updateSocialMedia($user_id, $social_media) {
    // Clear existing social media associations for the user
    $this->deleteFromTable('social_media_user', $user_id);

    if (empty($social_media)) return true;

    $conn = $this->db->getConnection();

    foreach ($social_media as $social) {
        $platform = trim($social['platform'] ?? '');
        $url = trim($social['url'] ?? '');

        if ($platform === '' || $url === '') continue;

        // Check if the social media entry already exists
        $checkSocial = $conn->prepare("SELECT id FROM social_media WHERE platform = ? AND url = ?");
        $checkSocial->bind_param("ss", $platform, $url);
        $checkSocial->execute();
        $result = $checkSocial->get_result();
        $entry = $result->fetch_assoc();

        if ($entry) {
            $social_id = $entry['id'];
        } else {
            // Insert new social media entry
            $insertSocial = $conn->prepare("INSERT INTO social_media (platform, url) VALUES (?, ?)");
            $insertSocial->bind_param("ss", $platform, $url);
            $insertSocial->execute();
            $social_id = $conn->insert_id;
        }

        // Insert into pivot table
        $insertPivot = $conn->prepare("INSERT INTO social_media_user (user_id, social_media_id) VALUES (?, ?)");
        $insertPivot->bind_param("ii", $user_id, $social_id);
        $insertPivot->execute();
    }

    return true;
}
    public function login($username, $password) {
        $conn = $this->db->getConnection();
        $sql = "SELECT * FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            // Check if password is hashed (assuming hashed passwords start with $2y$ or $argon2)
            $is_hashed = preg_match('/^\$2y\$|^\$argon2/', $user['password']);
            if ($is_hashed) {
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    return $user;
                }
            } else {
                // Password is not hashed, verify plain text and rehash
                if ($password === $user['password']) {
                    // Rehash password and update DB
                    $new_hashed = password_hash($password, PASSWORD_DEFAULT);
                    $update_sql = "UPDATE users SET password = ? WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("si", $new_hashed, $user['id']);
                    $update_stmt->execute();

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    return $user;
                }
            }
        }
        return false;
    }

    public function getUserByUsername($username) {
        $conn = $this->db->getConnection();
        $sql = "SELECT * FROM users WHERE username = ? AND deleted_at IS NULL";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function getProfileById($user_id) {
        $conn = $this->db->getConnection();
        $sql = "SELECT * FROM users WHERE id = ? AND deleted_at IS NULL";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function getSkills($user_id) {
        $conn = $this->db->getConnection();
        $sql = "SELECT skills.skill_name 
        FROM `skills` 
        JOIN skill_user 
        ON skills.id = skill_user.skill_id 
        WHERE skill_user.user_id =  ?; ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $skills = [];
        while ($row = $result->fetch_assoc()) {
            $skills[] = $row['skill_name'];
        }
        return $skills;
    }

    public function getHobbies($user_id) {
        $conn = $this->db->getConnection();
        $sql = "SELECT hobbies.hobby_name 
        FROM `hobbies` 
        JOIN hobby_user 
        ON hobbies.id = hobby_user.hobby_id 
        WHERE hobby_user.user_id =  ?; ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $hobbies = [];
        while ($row = $result->fetch_assoc()) {
            $hobbies[] = $row['hobby_name'];
        }
        return $hobbies;
    }

    public function getProjects($user_id) {
        $conn = $this->db->getConnection();
    
        $sql = "SELECT projects.project_name, projects.description 
            FROM projects
            JOIN project_user ON projects.id = project_user.project_id 
            WHERE project_user.user_id = ?";
    
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    
        $result = $stmt->get_result();
        $projects = [];
    
        while ($row = $result->fetch_assoc()) {
            $projects[] = $row;
     }
    
    return $projects;
    }   

    public function getAwards($user_id) {
    $conn = $this->db->getConnection();

    $sql = "SELECT awards.award_name, awards.year 
            FROM awards 
            JOIN award_user ON awards.id = award_user.award_id 
            WHERE award_user.user_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $awards = [];

    while ($row = $result->fetch_assoc()) {
        $awards[] = $row;
    }

    return $awards;
}

    public function getCertificates($user_id) {
    $conn = $this->db->getConnection();

    $sql = "SELECT certificates.certificate_name, certificates.issuer 
            FROM certificates 
            JOIN certificate_user ON certificates.id = certificate_user.certificate_id 
            WHERE certificate_user.user_id = ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();

            $result = $stmt->get_result();
            $certificates = [];

            while ($row = $result->fetch_assoc()) {
        // Format display: capitalize each word
            $row['certificate_name'] = ucwords($row['certificate_name']);
            $row['issuer'] = ucwords($row['issuer']);
            $certificates[] = $row;
    }
    return $certificates;
}

    public function getSocialMedia($user_id) {
    $conn = $this->db->getConnection();

    $sql = "SELECT social_media.platform, social_media.url 
            FROM social_media 
            JOIN social_media_user ON social_media.id = social_media_user.social_media_id 
            WHERE social_media_user.user_id = ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();

            $result = $stmt->get_result();
            $socials = [];

            while ($row = $result->fetch_assoc()) {
        // Format display: capitalize platform name
            $row['platform'] = ucwords($row['platform']);
            $socials[] = $row;
    }

    return $socials;
}

     public function follow($follower_id, $following_id) {
        $conn = $this->db->getConnection();
        $sql = "INSERT INTO user_followers (follower_id, following_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $follower_id, $following_id);
        return $stmt->execute();
    }

    public function unfollow($follower_id, $following_id) {
        $conn = $this->db->getConnection();
        $sql = "DELETE FROM user_followers WHERE follower_id = ? AND following_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $follower_id, $following_id);
        return $stmt->execute();
    }

    public function isFollowing($follower_id, $following_id) {
    $conn = $this->db->getConnection();
    $sql = "SELECT COUNT(*) as count FROM user_followers WHERE follower_id = ? AND following_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $follower_id, $following_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'] > 0;
}

    public function getFollowersCount($id): int {
    $conn = $this->db->getConnection();
    $sql = "SELECT COUNT(*) as count FROM user_followers WHERE following_id = ? AND follower_id != ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id, $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

    public function getFollowingCount($id) {
    $conn = $this->db->getConnection();
    $sql = "SELECT COUNT(*) as count FROM user_followers WHERE follower_id = ? AND following_id != ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id, $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

    public function getFollowersList($user_id) {
        $conn = $this->db->getConnection();
        $sql = "SELECT u.id, u.username, u.profile_pic FROM users u JOIN user_followers uf ON u.id = uf.follower_id WHERE uf.following_id = ? AND u.id != ? AND u.deleted_at IS NULL ORDER BY uf.created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $followers = [];
        while ($row = $result->fetch_assoc()) {
            $followers[] = $row;
        }
        return $followers;
    }

    public function getFollowingList($user_id) {
        $conn = $this->db->getConnection();
        $sql = "SELECT u.id, u.username, u.profile_pic FROM users u JOIN user_followers uf ON u.id = uf.following_id WHERE uf.follower_id = ? AND u.id != ? AND u.deleted_at IS NULL ORDER BY uf.created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $following = [];
        while ($row = $result->fetch_assoc()) {
            $following[] = $row;
        }
        return $following;
    }

    public function uploadProfilePicture($user_id, $file) {
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return "Upload error: " . $file['error'];
        }

        // Check file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowed_types)) {
            return "Invalid file type. Only JPG, PNG, and GIF are allowed.";
        }

        // Check file size (5MB max)
        $max_size = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $max_size) {
            return "File too large. Maximum size is 5MB.";
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $unique_name = uniqid('profile_', true) . '.' . $extension;
        $target_path = 'uploads/' . $unique_name;

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            // Update database
            $conn = $this->db->getConnection();
            $sql = "UPDATE users SET profile_pic = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $unique_name, $user_id);
            if ($stmt->execute()) {
                return true; // Success
            } else {
                // If DB update fails, remove the uploaded file
                unlink($target_path);
                return "Database update failed.";
            }
        } else {
            return "Failed to move uploaded file.";
        }
    }
}
class Admin extends User {
    public function login($username, $password) {
        $conn = $this->db->getConnection();
        $sql = "SELECT * FROM admin WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            // Verify password hash
            if (password_verify($password, $user['password'])) {
                // Only start session if not already started
                if (session_status() == PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                return $user;
            }
        }
        return false;
    }
    public function getAllUsers() {
        $conn = $this->db->getConnection();
        $sql = "SELECT * FROM users WHERE deleted_at IS NULL ORDER BY id";
        $result = $conn->query($sql);
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        return $users;
    }

    public function createUser($username, $password, $email) {
        $conn = $this->db->getConnection();
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, password, email) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $username, $hashed_password, $email);
        return $stmt->execute();
    }

    public function updateUser($id, $username, $email) {
        $conn = $this->db->getConnection();
        $sql = "UPDATE users SET username = ?, email = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $username, $email, $id);
        return $stmt->execute();
    }

    public function deleteUser($id) {
        $conn = $this->db->getConnection();
        $sql = "UPDATE users SET deleted_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
}