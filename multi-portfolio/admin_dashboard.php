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
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="font-sans m-0 p-0 bg-gray-100">
    <div class="hidden fixed inset-0 flex items-center justify-center bg-black bg-opacity-80 text-white p-5 rounded z-50" id="loading">
        <div>Processing...</div>
    </div>
    <header class="bg-gray-800 text-white p-4 flex justify-between items-center sticky top-0 z-10">
        <h1 class="flex-1 text-center">Admin Dashboard</h1>
        <a href="logout.php" class="text-white bg-gray-600 px-3 py-1 rounded transition hover:bg-gray-800">Logout</a>
    </header>
    <aside class="w-64 bg-gradient-to-b from-slate-800 to-slate-900 h-screen fixed left-0 top-16 shadow-xl md:block hidden">
        <nav class="flex flex-col py-6">
            <div class="px-6 mb-8">
                <h2 class="text-white text-lg font-semibold tracking-wide">Admin Panel</h2>
                <p class="text-slate-400 text-sm mt-1">Management Tools</p>
            </div>

            <div class="space-y-2 px-4">
                <a href="admin_dashboard.php" class="group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'bg-slate-700 text-white shadow-lg' : 'text-slate-300 hover:bg-slate-700 hover:text-white'; ?>">
                    <svg class="w-5 h-5 mr-3 transition-colors duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'text-blue-400' : 'text-slate-400 group-hover:text-blue-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v2H8V5z"></path>
                    </svg>
                    Dashboard
                </a>

                <a href="admin_user_management.php" class="group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'admin_user_management.php' ? 'bg-slate-700 text-white shadow-lg' : 'text-slate-300 hover:bg-slate-700 hover:text-white'; ?>">
                    <svg class="w-5 h-5 mr-3 transition-colors duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'admin_user_management.php' ? 'text-green-400' : 'text-slate-400 group-hover:text-green-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                    </svg>
                    User Management
                </a>

                <a href="admin_skills.php" class="group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'admin_skills.php' ? 'bg-slate-700 text-white shadow-lg' : 'text-slate-300 hover:bg-slate-700 hover:text-white'; ?>">
                    <svg class="w-5 h-5 mr-3 transition-colors duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'admin_skills.php' ? 'text-purple-400' : 'text-slate-400 group-hover:text-purple-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                    Skills Management
                </a>

                <a href="admin_hobbies.php" class="group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'admin_hobbies.php' ? 'bg-slate-700 text-white shadow-lg' : 'text-slate-300 hover:bg-slate-700 hover:text-white'; ?>">
                    <svg class="w-5 h-5 mr-3 transition-colors duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'admin_hobbies.php' ? 'text-yellow-400' : 'text-slate-400 group-hover:text-yellow-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1.586a1 1 0 01.707.293l.707.707A1 1 0 0012.414 11H15m-3-3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Hobbies Management
                </a>

                <a href="admin_manage_users.php" class="group flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'admin_manage_users.php' ? 'bg-slate-700 text-white shadow-lg' : 'text-slate-300 hover:bg-slate-700 hover:text-white'; ?>">
                    <svg class="w-5 h-5 mr-3 transition-colors duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'admin_manage_users.php' ? 'text-indigo-400' : 'text-slate-400 group-hover:text-indigo-400'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Per-User Skills & Hobbies
                </a>
            </div>

            <div class="mt-8 px-4">
                <div class="border-t border-slate-700 pt-4">
                    <div class="flex items-center px-4 py-2 text-xs text-slate-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Admin Version 1.0
                    </div>
                </div>
            </div>
        </nav>
    </aside>

    <main class="md:ml-64 p-5 bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-800 mb-2">Dashboard Overview</h2>
            <p class="text-gray-600">Welcome back! Here's what's happening with your portfolio system.</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium">Total Users</p>
                        <p class="text-white text-3xl font-bold"><?php echo count($users); ?></p>
                    </div>
                    <div class="bg-blue-400 bg-opacity-30 p-3 rounded-full">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-r from-green-500 to-green-600 p-6 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium">Active Users</p>
                        <p class="text-white text-3xl font-bold"><?php echo count(array_filter($users, function($u) { return $u['is_admin'] == 0; })); ?></p>
                    </div>
                    <div class="bg-green-400 bg-opacity-30 p-3 rounded-full">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-r from-purple-500 to-purple-600 p-6 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-sm font-medium">Total Skills</p>
                        <p class="text-white text-3xl font-bold">
                            <?php
                            $skills_count = $conn->query("SELECT COUNT(*) as count FROM skills")->fetch_assoc()['count'];
                            echo $skills_count;
                            ?>
                        </p>
                    </div>
                    <div class="bg-purple-400 bg-opacity-30 p-3 rounded-full">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-r from-orange-500 to-orange-600 p-6 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-100 text-sm font-medium">Total Hobbies</p>
                        <p class="text-white text-3xl font-bold">
                            <?php
                            $hobbies_count = $conn->query("SELECT COUNT(*) as count FROM hobbies")->fetch_assoc()['count'];
                            echo $hobbies_count;
                            ?>
                        </p>
                    </div>
                    <div class="bg-orange-400 bg-opacity-30 p-3 rounded-full">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1.586a1 1 0 01.707.293l.707.707A1 1 0 0012.414 11H13m-3 3.5a.5.5 0 11-1 0 .5.5 0 011 0zm6 0a.5.5 0 11-1 0 .5.5 0 011 0zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="bg-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-semibold text-gray-800">Top 5 Most Common Skills</h3>
                    <div class="bg-blue-100 p-2 rounded-full">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                </div>
                <div class="h-80">
                    <canvas id="skillsChart" class="w-full h-full"></canvas>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-semibold text-gray-800">Top 5 Most Common Hobbies</h3>
                    <div class="bg-green-100 p-2 rounded-full">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1.586a1 1 0 01.707.293l.707.707A1 1 0 0012.414 11H13m-3 3.5a.5.5 0 11-1 0 .5.5 0 011 0zm6 0a.5.5 0 11-1 0 .5.5 0 011 0zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="h-80">
                    <canvas id="hobbiesChart" class="w-full h-full"></canvas>
                </div>
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