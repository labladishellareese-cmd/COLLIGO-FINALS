<?php
// 1. INCLUDE THE DATABASE CONNECTION AND START SESSION
require_once 'db_connect.php';

// 2. SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    // If not, redirect to login page
    header("Location: login.php");
    exit;
}

// 3. GET TEACHER'S INFO FROM SESSION
$teacher_id = $_SESSION['user_id'];
$teacher_name = htmlspecialchars($_SESSION['full_name']);
$teacher_role = ucfirst(htmlspecialchars($_SESSION['role'])); // "Teacher"

// 4. GET FILTERS
$filter_class_id = $_GET['class_id'] ?? null;
$filter_date = $_GET['date'] ?? date('Y-m-d'); // Default to today

// 5. FETCH "MY ASSIGNED CLASSES" (for dropdowns and list)
$my_classes = [];
$sql_classes = "
    SELECT c.class_id, c.grade_level, c.section, c.subject, 
           (SELECT COUNT(u.user_id) FROM users u WHERE u.role = 'student' AND u.grade_level = c.grade_level AND u.section = c.section) as students_count
    FROM classes c 
    WHERE c.teacher_id = ? 
    ORDER BY c.subject
";
$stmt_classes = $conn->prepare($sql_classes);
$stmt_classes->bind_param("i", $teacher_id);
$stmt_classes->execute();
$result_classes = $stmt_classes->get_result();
while($row = $result_classes->fetch_assoc()) {
    $my_classes[] = $row;
}
$stmt_classes->close();


// 6. --- FETCH DATA FOR STATS CARDS ---

// Base query for this teacher
$stats_where_sql = "WHERE r.teacher_id = ?";
$stats_params = [$teacher_id];
$stats_types = "i";

// Add class filter if selected
if (!empty($filter_class_id)) {
    $stats_where_sql .= " AND r.class_id = ?";
    $stats_params[] = $filter_class_id;
    $stats_types .= "i";
}

// -- Card 1: Average Class Attendance (All-Time)
$avg_att_sql = "SELECT (COUNT(CASE WHEN status IN ('present', 'late') THEN 1 END) / COUNT(record_id)) * 100 as avg_att FROM attendance_records r $stats_where_sql";
$stmt_avg = $conn->prepare($avg_att_sql);
$stmt_avg->bind_param($stats_types, ...$stats_params);
$stmt_avg->execute();
$avg_attendance = $stmt_avg->get_result()->fetch_assoc()['avg_att'] ?? 0;
$avg_attendance = round($avg_attendance);
$stmt_avg->close();

// -- Card 2 & 3: Stats for selected date
$stats_where_sql_date = $stats_where_sql . " AND r.attendance_date = ?";
$stats_params_date = $stats_params;
$stats_params_date[] = $filter_date;
$stats_types_date = $stats_types . "s";

// -- Card 2: Students Present (Today)
$present_sql = "SELECT COUNT(record_id) as count FROM attendance_records r $stats_where_sql_date AND r.status = 'present'";
$stmt_present = $conn->prepare($present_sql);
$stmt_present->bind_param($stats_types_date, ...$stats_params_date);
$stmt_present->execute();
$present_count = $stmt_present->get_result()->fetch_assoc()['count'] ?? 0;
$stmt_present->close();

// -- Card 2: Total Students (for the selected class, or all teacher's students)
$total_students = 0;
if (!empty($filter_class_id)) {
    // Find the class in our array
    foreach($my_classes as $class) {
        if ($class['class_id'] == $filter_class_id) {
            $total_students = $class['students_count'];
            break;
        }
    }
} else {
    // Sum students from all classes
    foreach($my_classes as $class) {
        $total_students += $class['students_count'];
    }
}

// -- Card 3: Students Late (Today)
$late_sql = "SELECT COUNT(record_id) as count FROM attendance_records r $stats_where_sql_date AND r.status = 'late'";
$stmt_late = $conn->prepare($late_sql);
$stmt_late->bind_param($stats_types_date, ...$stats_params_date);
$stmt_late->execute();
$late_count = $stmt_late->get_result()->fetch_assoc()['count'] ?? 0;
$stmt_late->close();

// -- Card 4: Total Absences (This Month)
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$stats_where_sql_month = $stats_where_sql . " AND r.attendance_date BETWEEN ? AND ?";
$stats_params_month = $stats_params;
$stats_params_month[] = $month_start;
$stats_params_month[] = $month_end;
$stats_types_month = $stats_types . "ss";

$absent_sql = "SELECT COUNT(record_id) as count FROM attendance_records r $stats_where_sql_month AND r.status = 'absent'";
$stmt_absent = $conn->prepare($absent_sql);
$stmt_absent->bind_param($stats_types_month, ...$stats_params_month);
$stmt_absent->execute();
$total_absences = $stmt_absent->get_result()->fetch_assoc()['count'] ?? 0;
$stmt_absent->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
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
        /* Custom styling for select to remove default arrow */
        select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
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
        
        <aside class="w-64 bg-white shadow-md flex-shrink-0">
            <div class="p-6">
                <div class="flex items-center justify-center">
                    <span class="text-3xl font-bold text-blue-600 tracking-wider">COLLIGO</span>
                </div>
            </div>

            <div class="px-4 py-2 border-t">
                <p class="text-lg font-semibold text-gray-900">
                    <?php echo $teacher_name; ?>
                </p>
                <p class="text-sm text-gray-500">
                    <?php echo $teacher_role; ?>
                </p>
            </div>

            <nav class="mt-4 px-2">
                <a href="teacher_dashboard.php" class="flex items-center px-4 py-3 bg-blue-100 text-blue-600 font-semibold rounded-lg">
                    <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 8.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6A2.25 2.25 0 0115.75 3.75h2.25A2.25 2.25 0 0120.25 6v2.25a2.25 2.25 0 01-2.25 2.25h-2.25A2.25 2.25 0 0113.5 8.25V6zM13.5 15.75A2.25 2.25 0 0115.75 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                    Dashboard
                </a>
                <a href="teacher_take_attendance.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors duration-200">
                    <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.75h16.5m-16.5 3.75h16.5M3.75 17.25h16.5M4.5 5.25h15a2.25 2.25 0 012.25 2.25v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V7.5a2.25 2.25 0 012.25-2.25z" />
                    </svg>
                    Take Attendance
                </a>
                <a href="teacher_report.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors duration-200">
                    <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" /></svg>
                    Attendance Report
                </a>
                <a href="teacher_notifications.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors duration-200">
                    <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0M3.124 7.5A8.969 8.969 0 015.292 3m13.416 0a8.969 8.969 0 012.168 4.5" />
                    </svg>
                    Notifications
                </a>
            </nav>
        </aside>

        <main class="flex-1 p-6 lg:p-10">
            
            <header class="flex flex-col md:flex-row justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-4 md:mb-0">Insights</h1>
                
                <div class="flex flex-wrap items-center gap-4">
                    <form action="teacher_dashboard.php" method="GET" class="flex flex-wrap items-center gap-4">
                        <div class="relative">
                            <label for="header_class_id" class="sr-only">Select Class</label>
                            <select name="class_id" id="header_class_id" class="appearance-none bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All My Classes</option>
                                <?php foreach ($my_classes as $class): ?>
                                    <option value="<?php echo $class['class_id']; ?>" <?php if ($filter_class_id == $class['class_id']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($class['subject'] . ' (' . $class['grade_level'] . '-' . $class['section'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <svg class="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                        </div>
                        <div class="relative">
                            <label for="header_date" class="sr-only">Select Date</label>
                            <input type="date" name="date" id="header_date" class="bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                   value="<?php echo htmlspecialchars($filter_date); ?>">
                        </div>
                        <button type="submit" class="bg-blue-600 text-white py-2 px-3 rounded-lg text-sm font-semibold">Go</button>
                    </form>

                    <div class="relative" id="profileDropdownContainer">
                        <button id="profileButton" class="flex items-center gap-2 bg-white p-2 rounded-lg border border-gray-300">
                            <img src="https://placehold.co/32x32/E0E7FF/6366F1?text=T" alt="Teacher" class="w-8 h-8 rounded-full">
                            <span class="text-sm font-medium">
                                <?php echo $teacher_name; ?>
                            </span>
                            <svg class="w-4 h-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                        </button>
                        <div id="profileMenu" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl py-1 hidden z-10">
                            <a href="teacher_profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">My Profile</a>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
                
                <div class="bg-white p-5 rounded-xl shadow-md border border-gray-200">
                    <p class="text-sm text-gray-500 mb-1">Average Class Attendance</p>
                    <div class="flex justify-between items-end">
                        <div>
                            <p class="text-4xl font-bold text-gray-900">
                                <?php echo $avg_attendance; ?>%
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl shadow-md border border-gray-200">
                    <p class="text-sm text-gray-500 mb-1">Students Present (<?php echo date("M j", strtotime($filter_date)); ?>)</p>
                    <div class="flex justify-between items-end">
                        <div>
                            <p class="text-4xl font-bold text-gray-900">
                                <?php echo $present_count; ?><span class="text-2xl text-gray-400">/<?php echo $total_students; ?></span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl shadow-md border border-gray-200">
                    <p class="text-sm text-gray-500 mb-1">Students Late (<?php echo date("M j", strtotime($filter_date)); ?>)</p>
                    <div class="flex justify-between items-end">
                        <div>
                            <p class="text-4xl font-bold text-gray-900">
                                <?php echo $late_count; ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl shadow-md border border-gray-200">
                    <p class="text-sm text-gray-500 mb-1">Total Absences (This Month)</p>
                    <div class="flex justify-between items-end">
                        <div>
                            <p class="text-4xl font-bold text-gray-900">
                                <?php echo $total_absences; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6">
                
                <div class="bg-white p-5 rounded-xl shadow-md border border-gray-200">
                    <h2 class="text-xl font-semibold mb-4">My Assigned Classes</h2>
                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        
                        <?php if (empty($my_classes)): ?>
                             <div class="text-center text-gray-500 p-4">
                                You are not assigned to any classes.
                            </div>
                        <?php else: ?>
                            <?php foreach ($my_classes as $class): ?>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($class['subject']); ?></p>
                                <span class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars($class['grade_level'] . ' - ' . $class['section']); ?> | 
                                    <?php echo $class['students_count']; ?> Students
                                </span>
                            </div>
                            <?php endforeach; ?>
                         <?php endif; ?>
                    </div>
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