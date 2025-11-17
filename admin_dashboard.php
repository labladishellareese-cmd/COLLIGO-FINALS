<?php
// 1. INCLUDE THE DATABASE CONNECTION AND START SESSION
require_once 'db_connect.php';

// 2. SECURITY CHECK
// Check if user is logged in and is an admin.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    // If not, redirect to login page
    header("Location: login.php");
    exit;
}

// 3. GET ADMIN'S NAME FROM SESSION
$admin_name = htmlspecialchars($_SESSION['full_name']);

// --- 4. FETCH ALL DASHBOARD DATA ---

// -- STATS CARDS --
// Get total teachers (approved)
$teacher_count_result = $conn->query("SELECT COUNT(user_id) as count FROM users WHERE role = 'teacher' AND is_approved = 1");
$teacher_count = $teacher_count_result->fetch_assoc()['count'];

// Get total students
$student_count_result = $conn->query("SELECT COUNT(user_id) as count FROM users WHERE role = 'student'");
$student_count = $student_count_result->fetch_assoc()['count'];

// Get total classes
$class_count_result = $conn->query("SELECT COUNT(class_id) as count FROM classes");
$class_count = $class_count_result->fetch_assoc()['count'];

// -- ATTENDANCE STATS --
$today_date = date("Y-m-d");
$missing_count = 0;
$attendance_percent = 0;

if ($class_count > 0) {
    // Find how many classes have submitted attendance today
    $attended_sql = "SELECT COUNT(DISTINCT class_id) as count FROM attendance_records WHERE attendance_date = ?";
    $stmt_attended = $conn->prepare($attended_sql);
    $stmt_attended->bind_param("s", $today_date);
    $stmt_attended->execute();
    $attended_count = $stmt_attended->get_result()->fetch_assoc()['count'];
    
    $missing_count = $class_count - $attended_count;
    $attendance_percent = round(($attended_count / $class_count) * 100);
}

// -- "MISSING SUBMISSIONS" TABLE --
$missing_submissions = [];
// Find classes that do NOT have an attendance record for today
$missing_sql = "
    SELECT c.class_id, c.grade_level, c.section, c.subject, u.first_name, u.last_name, u.user_id as teacher_id
    FROM classes c
    JOIN users u ON c.teacher_id = u.user_id
    WHERE c.class_id NOT IN (
        SELECT DISTINCT a.class_id FROM attendance_records a WHERE a.attendance_date = ?
    )
";
$stmt_missing = $conn->prepare($missing_sql);
$stmt_missing->bind_param("s", $today_date);
$stmt_missing->execute();
$missing_result = $stmt_missing->get_result();
while($row = $missing_result->fetch_assoc()) {
    $missing_submissions[] = $row;
}

// -- "PENDING TEACHER APPROVALS" TABLE --
$pending_teachers = [];
$pending_sql = "SELECT user_id, first_name, last_name, email FROM users WHERE role = 'teacher' AND is_approved = 0";
$pending_result = $conn->query($pending_sql);
while($row = $pending_result->fetch_assoc()) {
    $pending_teachers[] = $row;
}

// -- "STUDENT CLASS CHANGE REQUESTS" TABLE --
$pending_requests = [];
$requests_sql = "
    SELECT cr.request_id, cr.new_grade_level, cr.new_section, 
           u.first_name, u.last_name, u.grade_level as current_grade, u.section as current_section
    FROM change_requests cr
    JOIN users u ON cr.student_id = u.user_id
    WHERE cr.status = 'pending'
    ORDER BY cr.created_at DESC
";
$requests_result = $conn->query($requests_sql);
while($row = $requests_result->fetch_assoc()) {
    $pending_requests[] = $row;
}

// Close the connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* Apply Inter font */
        body {
            font-family: 'Inter', sans-serif;
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        /* Fix for date picker icon */
        input[type="date"]::-webkit-calendar-picker-indicator {
            cursor: pointer;
            opacity: 0.6;
        }
        input[type="date"]::-webkit-calendar-picker-indicator:hover {
            opacity: 1;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

    <div class="flex min-h-screen">
        
        <aside class="w-64 bg-white shadow-lg flex-shrink-0">
            <div class="p-6">
                <div class="flex items-center justify-center">
                    <span class="text-3xl font-bold text-blue-600 tracking-wider">COLLIGO</span>
                </div>
            </div>

            <div class="px-4 py-2 border-t">
                <p class="text-lg font-semibold text-gray-900">
                    <?php echo $admin_name; ?>
                </p>
                <p class="text-sm text-gray-500">System Administrator</p>
            </div>

            <nav class="mt-4 px-2 space-y-1">
                <a href="admin_dashboard.php" class="flex items-center px-4 py-3 bg-blue-100 text-blue-600 font-semibold rounded-lg">
                    <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 8.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6A2.25 2.25 0 0115.75 3.75h2.25A2.25 2.25 0 0120.25 6v2.25a2.25 2.25 0 01-2.25 2.25h-2.25A2.25 2.25 0 0113.5 8.25V6zM13.5 15.75A2.25 2.25 0 0115.75 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                    Dashboard
                </a>
                <a href="admin_manage_teachers.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors duration-200">
                    <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
                    Manage Teachers
                </a>
                <a href="admin_manage_students.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors duration-200">
                    <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.75 0 11-6.75 0 3.375 3.75 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.375 12.375 0 0110.5 21c-2.372 0-4.556-.64-6.397-1.766z" /></svg>
                    Manage Students
                </a>
                <a href="admin_manage_classes.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors duration-200">
                    <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18c-2.305 0-4.408.867-6 2.292m0-14.25v14.25" /></svg>
                    Manage Classes
                </a>
                <a href="admin_global_reports.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors duration-200">
                    <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" /></svg>
                    Global Reports
                </a>
            </nav>
        </aside>

        <main class="flex-1 p-6 lg:p-10">
            
            <header class="flex flex-col md:flex-row justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-4 md:mb-0">Admin Dashboard</h1>
                
                <div class="flex items-center gap-4">
                    <div class="relative">
                        <input type="search" placeholder="Search..." class="bg-white border border-gray-300 rounded-lg pl-10 pr-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                    </div>

                    <div class="relative" id="profileDropdownContainer">
                        <button id="profileButton" class="flex items-center gap-2 bg-white p-2 rounded-lg border border-gray-300">
                            <img src="https://placehold.co/32x32/6366F1/E0E7FF?text=A" alt="Admin User" class="w-8 h-8 rounded-full">
                            <span class="text-sm font-medium">
                                <?php echo $admin_name; ?>
                            </span>
                            <svg class="w-4 h-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                        </button>
                        <div id="profileMenu" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl py-1 hidden z-10">
                            <a href="admin_profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">My Profile</a>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
                
                <a href="admin_manage_teachers.php" class="bg-white p-5 rounded-xl shadow-md border border-gray-200 flex items-center gap-4 hover:shadow-lg hover:border-blue-500 transition-all">
                    <div class="p-3 bg-blue-100 rounded-full">
                        <svg class="w-6 h-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Total Teachers</p>
                        <p class="text-3xl font-bold text-gray-900">
                            <?php echo $teacher_count; ?>
                        </p>
                    </div>
                </a>

                <a href="admin_manage_students.php" class="bg-white p-5 rounded-xl shadow-md border border-gray-200 flex items-center gap-4 hover:shadow-lg hover:border-green-500 transition-all">
                    <div class="p-3 bg-green-100 rounded-full">
                         <svg class="w-6 h-6 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m-4.682 2.72a3 3 0 01-4.682-2.72 9.094 9.094 0 013.741-.479m0 0a9.094 9.094 0 00-3.741-.479m0 0c-1.313 0-2.57.21-3.741.479m6.44-4.596a3.75 3.75 0 10-5.714 0m5.714 0a3 3 0 10-5.714 0" /></svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Total Students</p>
                        <p class="text-3xl font-bold text-gray-900">
                            <?php echo $student_count; ?>
                        </p>
                    </div>
                </a>

                <a href="admin_manage_classes.php" class="bg-white p-5 rounded-xl shadow-md border border-gray-200 flex items-center gap-4 hover:shadow-lg hover:border-indigo-500 transition-all">
                    <div class="p-3 bg-indigo-100 rounded-full">
                        <svg class="w-6 h-6 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18c-2.305 0-4.408.867-6 2.292m0-14.25v14.25" /></svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Total Classes</p>
                        <p class="text-3xl font-bold text-gray-900">
                            <?php echo $class_count; ?>
                        </p>
                    </div>
                </a>

                <div class="bg-white p-5 rounded-xl shadow-md border border-gray-200 flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm text-gray-500">Attendance (Today)</p>
                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-800">
                            <?php echo $missing_count; ?> Missing
                        </span>
                    </div>
                    <p class="text-4xl font-bold text-gray-900 mb-3">
                        <?php echo $attendance_percent; ?>%
                    </p>
                    <a href="admin_global_reports.php" class="w-full text-center bg-gray-100 text-gray-700 hover:bg-gray-200 py-2 rounded-lg text-sm font-medium transition-colors">
                        View Missing Reports
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <div class="lg:col-span-2 space-y-6">

                    <div class="bg-white p-5 rounded-xl shadow-md border border-gray-200">
                        <h2 class="text-xl font-semibold mb-4 text-red-600">Missing Submissions (Today)</h2>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Teacher</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Class</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="missingSubmissionsList">
                                    
                                    <?php if (empty($missing_submissions)): ?>
                                        <tr>
                                            <td colspan="3" class="px-4 py-4 text-center text-gray-500">
                                                No missing submissions for today. Great job!
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($missing_submissions as $sub): ?>
                                        <tr>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-3">
                                                    <img src="https://placehold.co/40x40/E0E7FF/6366F1?text=<?php echo substr($sub['first_name'], 0, 1); ?>" alt="User" class="w-10 h-10 rounded-full">
                                                    <span class="font-medium"><?php echo htmlspecialchars($sub['first_name'] . ' ' . $sub['last_name']); ?></span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-gray-600"><?php echo htmlspecialchars($sub['grade_level'] . ' ' . $sub['section'] . ' - ' . $sub['subject']); ?></td>
                                            <td class="px-4 py-3">
                                                <a href="send_reminder.php?class_id=<?php echo $sub['class_id']; ?>&teacher_id=<?php echo $sub['teacher_id']; ?>" class="send-reminder-btn text-sm bg-blue-50 hover:bg-blue-100 text-blue-600 font-medium py-2 px-3 rounded-lg">
                                                    Send Reminder
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-xl shadow-md border border-gray-200">
                        <h2 class="text-xl font-semibold mb-4 text-blue-600">Pending Teacher Approvals</h2>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">User</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Email</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Role</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="pendingUsersList">
                                    
                                    <?php if (empty($pending_teachers)): ?>
                                        <tr>
                                            <td colspan="4" class="px-4 py-4 text-center text-gray-500">
                                                No pending teacher approvals.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($pending_teachers as $teacher): ?>
                                        <tr id="pending-<?php echo $teacher['user_id']; ?>">
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="flex items-center gap-3">
                                                    <img src="https://placehold.co/40x40/E0E7FF/6366F1?text=<?php echo substr($teacher['first_name'], 0, 1); ?>" alt="User" class="w-10 h-10 rounded-full">
                                                    <span class="font-medium"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($teacher['email']); ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">Teacher</span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap space-x-2">
                                                <a href="admin_approve_user.php?user_id=<?php echo $teacher['user_id']; ?>" class="text-sm bg-green-50 hover:bg-green-100 text-green-700 font-medium py-2 px-3 rounded-lg">Approve</a>
                                                <a href="admin_deny_user.php?user_id=<?php echo $teacher['user_id']; ?>" class="text-sm bg-red-50 hover:bg-red-100 text-red-700 font-medium py-2 px-3 rounded-lg">Deny</a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-1">
                    <div class="bg-white p-5 rounded-xl shadow-md border border-gray-200">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold text-orange-600">Student Requests</h2>
                        </div>
                        <div class="space-y-4 max-h-[60vh] overflow-y-auto">
                            
                            <?php if (empty($pending_requests)): ?>
                                <div class="text-center text-sm text-gray-500 py-4">
                                    No pending class change requests.
                                </div>
                            <?php else: ?>
                                <?php foreach ($pending_requests as $req): ?>
                                <div class="border-b pb-3">
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?></span>
                                    </div>
                                    <p class="text-sm text-gray-500">
                                        From: <span class="font-medium text-gray-700"><?php echo htmlspecialchars($req['current_grade'] . ' - ' . $req['current_section']); ?></span>
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        To: <span class="font-medium text-blue-600"><?php echo htmlspecialchars($req['new_grade_level'] . ' - ' . $req['new_section']); ?></span>
                                    </p>
                                    <div class="flex gap-2 mt-3">
                                        <a href="admin_approve_change.php?request_id=<?php echo $req['request_id']; ?>" class="flex-1 text-center text-sm bg-green-50 hover:bg-green-100 text-green-700 font-medium py-2 px-3 rounded-lg">Approve</a>
                                        <a href="admin_deny_change.php?request_id=<?php echo $req['request_id']; ?>" class="flex-1 text-center text-sm bg-red-50 hover:bg-red-100 text-red-700 font-medium py-2 px-3 rounded-lg">Deny</a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>

            </div>
            
            <footer class="text-center text-sm text-gray-500 mt-8">
                Copyright © 2025 OMPASSIVE. All rights reserved. Template by <a href="#" class="text-blue-600">OMPASSIVE</a>.
            </footer>
        </main>
    </div>

    <script>
        // --- Profile Dropdown Logic (UI Only) ---
        const profileButton = document.getElementById('profileButton');
        const profileMenu = document.getElementById('profileMenu');
        const dropdownContainer = document.getElementById('profileDropdownContainer');

        if (profileButton) {
            profileButton.addEventListener('click', () => {
                profileMenu.classList.toggle('hidden');
            });
            document.addEventListener('click', (event) => {
                if (!dropdownContainer.contains(event.target)) {
                    profileMenu.classList.add('hidden');
                }
            });
        }
        
        // --- All simulation JS has been removed ---
        
    </script>
</body>
</html>