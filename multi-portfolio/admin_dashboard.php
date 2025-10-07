<?php
// Start session only if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Include database connection
include "db.php";

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

// Handle AJAX request for chart data
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if ($isAjax && isset($_GET['action']) && $_GET['action'] === 'get_chart_data') {
    // Fetch top 5 most common skills
    $skills_sql = "SELECT skills.skill_name, COUNT(skill_user.user_id) as count
                   FROM skills
                   JOIN skill_user ON skills.id = skill_user.skill_id
                   GROUP BY skills.id, skills.skill_name
                   ORDER BY count DESC
                   LIMIT 5";
    $skills_result = $conn->query($skills_sql);
    $top_skills = [];
    while ($row = $skills_result->fetch_assoc()) {
        $top_skills[] = $row;
    }

    // Fetch top 5 most common hobbies
    $hobbies_sql = "SELECT hobbies.hobby_name, COUNT(hobby_user.user_id) as count
                    FROM hobbies
                    JOIN hobby_user ON hobbies.id = hobby_user.hobby_id
                    GROUP BY hobbies.id, hobbies.hobby_name
                    ORDER BY count DESC
                    LIMIT 5";
    $hobbies_result = $conn->query($hobbies_sql);
    $top_hobbies = [];
    while ($row = $hobbies_result->fetch_assoc()) {
        $top_hobbies[] = $row;
    }

    // Return data as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'skills' => $top_skills,
        'hobbies' => $top_hobbies
    ]);
    exit();
}

    // Fetch users for display
    $sql = "SELECT * FROM users WHERE deleted_at IS NULL ORDER BY id";
    $result = $conn->query($sql);
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

        // // Fetch top 5 most common skills
        // $skills_sql = "SELECT skills.skill_name, COUNT(skill_user.user_id) as count
        //             FROM skills
        //             JOIN skill_user ON skills.id = skill_user.skill_id
        //             GROUP BY skills.id, skills.skill_name
        //             ORDER BY count DESC
        //             LIMIT 5";
        // $skills_result = $conn->query($skills_sql);
        // $top_skills = [];
        // while ($row = $skills_result->fetch_assoc()) {
        //     $top_skills[] = $row;
        // }

        // // Fetch top 5 most common hobbies
        // $hobbies_sql = "SELECT hobbies.hobby_name, COUNT(hobby_user.user_id) as count
        //                 FROM hobbies
        //                 JOIN hobby_user ON hobbies.id = hobby_user.hobby_id
        //                 GROUP BY hobbies.id, hobbies.hobby_name
        //                 ORDER BY count DESC
        //                 LIMIT 5";
        // $hobbies_result = $conn->query($hobbies_sql);
        // $top_hobbies = [];
        // while ($row = $hobbies_result->fetch_assoc()) {
        //     $top_hobbies[] = $row;
        // }
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="admin.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .loading {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 20px;
            border-radius: 5px;
            z-index: 1000;
        }
        .charts-wrapper {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-top: 30px;
        }
        .chart-container {
            flex: 1;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 48%;
        }
        .chart-container h3 {
            margin-bottom: 20px;
            color: #333;
            text-align: center;
        }
        #skillsChart, #hobbiesChart {
            max-width: 100%;
            height: auto !important;
        }
        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .nav-link {
            display: block;
            padding: 10px 15px;
            text-decoration: none;
            color: #333;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }
        .nav-link:hover {
            background-color: #f0f0f0;
        }
        .nav-link.active {
            background-color: #e0e0e0;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="loading" id="loading">
        <div>Processing...</div>
    </div>
    <header>
        <h1>Admin Dashboard</h1>
        <a href="logout.php" class="logout-btn">Logout</a>
    </header>
    <aside class="sidebar">
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
            <a href="admin_user_management.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_user_management.php' ? 'active' : ''; ?>">User Management</a>
            <a href="admin_skills.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_skills.php' ? 'active' : ''; ?>">Skills Management</a>
            <a href="admin_hobbies.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_hobbies.php' ? 'active' : ''; ?>">Hobbies Management</a>
            <a href="admin_manage_users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_manage_users.php' ? 'active' : ''; ?>">Per-User Skills & Hobbies</a>
        </nav>
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
        </div>

        <div class="charts-wrapper">
            <div class="chart-container">
                <h3>Top 5 Most Common Skills</h3>
                <canvas id="skillsChart" width="400" height="200"></canvas>
            </div>
            <div class="chart-container">
                <h3>Top 5 Most Common Hobbies</h3>
                <canvas id="hobbiesChart" width="400" height="200"></canvas>
            </div>
        </div>


    </main>

    <script>
        $(document).ready(function() {
            // Chart.js bar chart for top skills and hobbies using AJAX
            const skillsCtx = document.getElementById('skillsChart').getContext('2d');
            const hobbiesCtx = document.getElementById('hobbiesChart').getContext('2d');

            let skillsChart;
            let hobbiesChart;

            function fetchChartData() {
                $.ajax({
                    url: 'admin_dashboard.php',
                    method: 'GET',
                    data: { action: 'get_chart_data' },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const skillLabels = response.skills.map(skill => skill.skill_name);
                            const skillData = response.skills.map(skill => skill.count);
                            const skillColors = ['rgba(54, 162, 235, 0.6)', 'rgba(75, 192, 192, 0.6)', 'rgba(153, 102, 255, 0.6)', 'rgba(255, 159, 64, 0.6)', 'rgba(255, 99, 132, 0.6)'];
                            const skillBorderColors = ['rgba(54, 162, 235, 1)', 'rgba(75, 192, 192, 1)', 'rgba(153, 102, 255, 1)', 'rgba(255, 159, 64, 1)', 'rgba(255, 99, 132, 1)'];

                            const hobbyLabels = response.hobbies.map(hobby => hobby.hobby_name);
                            const hobbyData = response.hobbies.map(hobby => hobby.count);
                            const hobbyColors = ['rgba(255, 206, 86, 0.6)', 'rgba(54, 162, 235, 0.6)', 'rgba(75, 192, 192, 0.6)', 'rgba(153, 102, 255, 0.6)', 'rgba(255, 159, 64, 0.6)'];
                            const hobbyBorderColors = ['rgba(255, 206, 86, 1)', 'rgba(54, 162, 235, 1)', 'rgba(75, 192, 192, 1)', 'rgba(153, 102, 255, 1)', 'rgba(255, 159, 64, 1)'];

                            if (skillsChart) {
                                skillsChart.destroy();
                            }
                            if (hobbiesChart) {
                                hobbiesChart.destroy();
                            }

                            skillsChart = new Chart(skillsCtx, {
                                type: 'bar',
                                data: {
                                    labels: skillLabels,
                                    datasets: [{
                                        label: 'Count',
                                        data: skillData,
                                        backgroundColor: skillColors,
                                        borderColor: skillBorderColors,
                                        borderWidth: 2
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            ticks: {
                                                stepSize: 1
                                            }
                                        }
                                    },
                                    plugins: {
                                        legend: {
                                            display: false
                                        }
                                    }
                                }
                            });

                            hobbiesChart = new Chart(hobbiesCtx, {
                                type: 'bar',
                                data: {
                                    labels: hobbyLabels,
                                    datasets: [{
                                        label: 'Count',
                                        data: hobbyData,
                                        backgroundColor: hobbyColors,
                                        borderColor: hobbyBorderColors,
                                        borderWidth: 2
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            ticks: {
                                                stepSize: 1
                                            }
                                        }
                                    },
                                    plugins: {
                                        legend: {
                                            display: false
                                        }
                                    }
                                }
                            });
                        } else {
                            alert('Failed to load chart data: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error fetching chart data.');
                    }
                });
            }

            fetchChartData();
        });
    </script>
</body>
</html>