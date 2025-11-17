<?php
// 1. INCLUDE THE DATABASE CONNECTION AND START SESSION
require_once 'db_connect.php';

// 2. SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: login.php");
    exit;
}

// 3. GET STUDENT'S INFO FROM SESSION
$student_id = $_SESSION['user_id'];
$student_name = htmlspecialchars($_SESSION['full_name']);
$student_firstname = htmlspecialchars($_SESSION['first_name']);

// 4. GET STUDENT'S GRADE/SECTION (We need this for queries)
$stmt_user = $conn->prepare("SELECT grade_level, section FROM users WHERE user_id = ?");
$stmt_user->bind_param("i", $student_id);
$stmt_user->execute();
$user_result = $stmt_user->get_result();
$student_data = $user_result->fetch_assoc();
$student_grade = $student_data['grade_level'];
$student_section = $student_data['section'];
$student_grade_section = htmlspecialchars($student_grade . ' - ' . $student_section);
$stmt_user->close();

// 5. --- FETCH DATA FOR STATS CARDS (All-time) ---
$stats = ['overall_percent' => 0, 'total_lates' => 0, 'total_absences' => 0];
$sql_stats = "
    SELECT 
        status, COUNT(record_id) as count 
    FROM attendance_records 
    WHERE student_id = ? 
    GROUP BY status
";
$stmt_stats = $conn->prepare($sql_stats);
$stmt_stats->bind_param("i", $student_id);
$stmt_stats->execute();
$stats_result = $stmt_stats->get_result();

$total_present_late = 0;
$total_absent = 0;
while($row = $stats_result->fetch_assoc()) {
    if ($row['status'] == 'present') {
        $total_present_late += $row['count'];
    } elseif ($row['status'] == 'late') {
        $total_present_late += $row['count'];
        $stats['total_lates'] = $row['count'];
    } elseif ($row['status'] == 'absent') {
        $total_absent = $row['count'];
        $stats['total_absences'] = $row['count'];
    }
}
$total_records = $total_present_late + $total_absent;
if ($total_records > 0) {
    $stats['overall_percent'] = round(($total_present_late / $total_records) * 100);
}
$stmt_stats->close();

// 6. --- FETCH "MY CLASSES" TABLE DATA ---
$my_classes = [];
$sql_classes = "
    SELECT c.subject, CONCAT(u.first_name, ' ', u.last_name) as teacher_name
    FROM classes c
    LEFT JOIN users u ON c.teacher_id = u.user_id
    WHERE c.grade_level = ? AND c.section = ?
    ORDER BY c.subject
";
$stmt_classes = $conn->prepare($sql_classes);
$stmt_classes->bind_param("ss", $student_grade, $student_section);
$stmt_classes->execute();
$result_classes = $stmt_classes->get_result();
while($row = $result_classes->fetch_assoc()) {
    $my_classes[] = $row;
}
$stmt_classes->close();

// 7. --- FETCH "RECENT ATTENDANCE" PANEL DATA ---
$recent_attendance = [];
$sql_recent = "
    SELECT ar.status, ar.attendance_date, c.subject
    FROM attendance_records ar
    JOIN classes c ON ar.class_id = c.class_id
    WHERE ar.student_id = ?
    ORDER BY ar.attendance_date DESC
    LIMIT 5
";
$stmt_recent = $conn->prepare($sql_recent);
$stmt_recent->bind_param("i", $student_id);
$stmt_recent->execute();
$result_recent = $stmt_recent->get_result();
while($row = $result_recent->fetch_assoc()) {
    $recent_attendance[] = $row;
}
$stmt_recent->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
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
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

    <div class="flex min-h-screen">
        
        <aside class="w-64 bg-white shadow-md flex-shrink-0">
            <div class="p-6">
                <div class="flex items-center justify-center">
                    <span class="text-3xl font-bold text-blue-600 tracking-wider">COLLIGO</span>
                </div>
            </div>

            <div class="px-4 py-2 border-t">
                <p class="text-lg font-semibold text-gray-900">
                    <?php echo $student_name; ?>
                </p>
                <p class="text-sm text-gray-500">
                    <?php echo $student_grade_section; ?>
                </p>
            </div>

            <nav class="mt-4 px-2">
                <a href="student_dashboard.php" class="flex items-center px-4 py-3 bg-blue-100 text-blue-600 font-semibold rounded-lg">
                    <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 8.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6A2.25 2.25 0 0115.75 3.75h2.25A2.25 2.25 0 0120.25 6v2.25a2.25 2.25 0 01-2.25 2.25h-2.25A2.25 2.25 0 0113.5 8.25V6zM13.5 15.75A2.25 2.25 0 0115.75 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                    Dashboard
                </a>
                <a href="student_notifications.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors duration-200"">
                    <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0M3.124 7.5A8.969 8.969 0 015.292 3m13.416 0a8.969 8.969 0 012.168 4.5" />
                    </svg>
                    My Notifications
                </a>
                <a href="student_report.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors duration-200">
                    <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" /></svg>
                    My Attendance
                </a>
                <a href="student_profile.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors duration-200">
                    <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
                    My Profile
                </a>
            </nav>
        </aside>

        <main class="flex-1 p-6 lg:p-10">
            
            <header class="flex flex-col md:flex-row justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-4 md:mb-0">
                    Welcome, <?php echo $student_firstname; ?>
                </h1>
                
                <div class="flex flex-wrap items-center gap-4">
                    <div class="relative" id="profileDropdownContainer">
                        <button id="profileButton" class="flex items-center gap-2 bg-white p-2 rounded-lg border border-gray-300">
                            <img src="https://placehold.co/32x32/E0E7FF/6366F1?text=S" alt="Student" class="w-8 h-8 rounded-full">
                            <span class="text-sm font-medium">
                                <?php echo $student_name; ?>
                            </span>
                            <svg class="w-4 h-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                        </button>
                        <div id="profileMenu" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl py-1 hidden z-10">
                            <a href="student_profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">My Profile</a>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6 mb-8">
                
                <div class="bg-white p-5 rounded-xl shadow-md border border-gray-200">
                    <p class="text-sm text-gray-500 mb-1">Overall Attendance</p>
                    <p class="text-4xl font-bold text-gray-900">
                        <?php echo $stats['overall_percent']; ?>%
                    </p>
                </div>

                <div class="bg-white p-5 rounded-xl shadow-md border border-gray-200">
                    <p class="text-sm text-gray-500 mb-1">Total Lates</p>
                    <p class="text-4xl font-bold text-gray-900">
                        <?php echo $stats['total_lates']; ?>
                    </p>
                </div>

                <div class="bg-white p-5 rounded-xl shadow-md border border-gray-200">
                    <p class="text-sm text-gray-500 mb-1">Total Absences</p>
                    <p class="text-4xl font-bold text-gray-900">
                        <?php echo $stats['total_absences']; ?>
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <div class="lg:col-span-2 bg-white p-5 rounded-xl shadow-md border border-gray-200">
                    <h2 class="text-xl font-semibold mb-4">My Classes</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Subject</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Teacher</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                
                                <?php if (empty($my_classes)): ?>
                                    <tr>
                                        <td colspan="2" class="px-4 py-4 text-center text-gray-500">
                                            You are not assigned to any classes.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($my_classes as $class): ?>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900"><?php echo htmlspecialchars($class['subject']); ?></td>
                                        <td class="px-4 py-3 text-gray-600"><?php echo htmlspecialchars($class['teacher_name']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="lg:col-span-1 bg-white p-5 rounded-xl shadow-md border border-gray-200">
                    <h2 class="text-xl font-semibold mb-4">Recent Attendance</h2>
                     <div class="space-y-3">
                        
                        <?php if (empty($recent_attendance)): ?>
                            <div class="text-center text-gray-500 p-4">
                                No recent attendance records.
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_attendance as $record): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <p class="font-medium"><?php echo htmlspecialchars($record['subject']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo date("F j, Y", strtotime($record['attendance_date'])); ?></p>
                                </div>
                                <?php 
                                    if ($record['status'] == 'present') {
                                        echo '<span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Present</span>';
                                    } elseif ($record['status'] == 'late') {
                                        echo '<span class="px-2 py-1 text-xs font-medium rounded-full bg-orange-100 text-orange-800">Late</span>';
                                    } else {
                                        echo '<span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">Absent</span>';
                                    }
                                ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    </div>
                    <a href="student_report.php" class="mt-4 w-full text-center block bg-gray-100 text-gray-700 hover:bg-gray-200 py-2 rounded-lg text-sm font-medium transition-colors">
                        View Full Report
                    </a>
                </div>

            </div>
            
            <footer class="text-center text-sm text-gray-500 mt-8">
                Copyright © 2025 OMPASSIVE. All rights reserved. Template by <a href="#" class="text-blue-600">OMPASSIVE</a>.
            </footer>
        </main>
    </div>

    <script>
        // --- Profile Dropdown Logic (UI only) ---
        const profileButton = document.getElementById('profileButton');
        const profileMenu = document.getElementById('profileMenu');
        const dropdownContainer = document.getElementById('profileDropdownContainer');

        if (profileButton) {
            profileButton.addEventListener('click', () => {
                profileMenu.classList.toggle('hidden');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', (event) => {
                if (!dropdownContainer.contains(event.target)) {
                    profileMenu.classList.add('hidden');
                }
            });
        }
        
    </script>
</body>
</html>