<?php
session_start();
include "db.php";
include "classes.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create'])) {
        $username = $conn->real_escape_string($_POST['username']);
        $password = $_POST['password'];
        $email = $conn->real_escape_string($_POST['email']);
        $sql = "INSERT INTO users (username, password, email) VALUES ('$username', '$password', '$email')";
        $conn->query($sql);
    } elseif (isset($_POST['update'])) {
        $id = (int)$_POST['id'];
        $username = $conn->real_escape_string($_POST['username']);
        $email = $conn->real_escape_string($_POST['email']);
        $sql = "UPDATE users SET username='$username', email='$email' WHERE id=$id";
        $conn->query($sql);
    } elseif (isset($_POST['delete'])) {
        $id = (int)$_POST['id'];
        $sql = "UPDATE users SET deleted_at=NOW() WHERE id=$id";
        $conn->query($sql);
    }
}

// Fetch users
$sql = "SELECT * FROM users WHERE deleted_at IS NULL ORDER BY id";
$result = $conn->query($sql);
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <header>
        <button class="menu-toggle">☰</button>
        <h1>Admin Dashboard</h1>
        <a href="logout.php" class="logout-btn">Logout</a>
    </header>

    <aside class="sidebar">
        <ul>
            <li><a href="admin_dashboard.php">Dashboard</a></li>
            <li><a href="admin_users.php">Users</a></li>
            <li><a href="admin_settings.php">Settings</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <h2>Dashboard Overview</h2>

        <div class="dashboard-cards">
            <div class="card">
                <h3>Total Users</h3>
                <p><?php echo count($users); ?></p>
            </div>
            <div class="card">
                <h3>Active Users</h3>
                <p><?php echo count(array_filter($users, function($u) { return $u['is_admin'] == 0; })); ?></p>
            </div>
            <div class="card">
                <h3>Admins</h3>
                <p><?php echo count(array_filter($users, function($u) { return $u['is_admin'] == 1; })); ?></p>
            </div>
        </div>

        <h3>Create User</h3>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="email" name="email" placeholder="Email">
            <button type="submit" name="create">Create</button>
        </form>

        <h3>Users</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo $user['username']; ?></td>
                    <td><?php echo $user['email']; ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                            <input type="text" name="username" value="<?php echo $user['username']; ?>" required>
                            <input type="email" name="email" value="<?php echo $user['email']; ?>">
                            <button type="submit" name="update">Update</button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                            <button type="submit" name="delete" onclick="return confirm('Delete?')">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.querySelector('.menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');

            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            });

            const currentPage = window.location.pathname.split('/').pop();
            const links = document.querySelectorAll('.sidebar a');
            links.forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.parentElement.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
