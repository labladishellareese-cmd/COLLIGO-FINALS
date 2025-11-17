<?php
// 1. INCLUDE THE DATABASE CONNECTION AND START SESSION
require_once 'db_connect.php';

// 2. SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: login.php");
    exit;
}

// 3. GET TEACHER'S INFO FROM SESSION
$teacher_id = $_SESSION['user_id'];
$teacher_name = htmlspecialchars($_SESSION['full_name']);
$teacher_role = "Teacher";

// 4. GET FILTERS FROM URL
$filter_class_id = $_GET['class_id'] ?? null;
$filter_date = $_GET['date'] ?? date('Y-m-d'); // Default to today

// 5. FETCH "MY ASSIGNED CLASSES" (for dropdown)
$my_classes = [];
$sql_classes = "SELECT class_id, grade_level, section, subject FROM classes WHERE teacher_id = ? ORDER BY subject";
$stmt_classes = $conn->prepare($sql_classes);
$stmt_classes->bind_param("i", $teacher_id);
$stmt_classes->execute();
$result_classes = $stmt_classes->get_result();
while($row = $result_classes->fetch_assoc()) {
    $my_classes[] = $row;
}
$stmt_classes->close();

// 6. INITIALIZE DATA VARS
$summary = ['overall_percent' => '--', 'present_days' => 0, 'late_days' => 0, 'absent_days' => 0];
$report_data = [];
$report_title = "Please select a class to view a report.";
$export_link = "#";

// 7. --- IF A CLASS IS SELECTED, FETCH DATA ---
if (!empty($filter_class_id)) {
    
    // --- A. GET DATA FOR STATS CARDS (All-time stats for this class) ---
    $sql_stats = "
        SELECT status, COUNT(record_id) as count 
        FROM attendance_records 
        WHERE class_id = ? AND teacher_id = ?
        GROUP BY status
    ";
    $stmt_stats = $conn->prepare($sql_stats);
    $stmt_stats->bind_param("ii", $filter_class_id, $teacher_id);
    $stmt_stats->execute();
    $result_stats = $stmt_stats->get_result();
    
    $total_records = 0;
    while($row = $result_stats->fetch_assoc()) {
        if ($row['status'] == 'present') $summary['present_days'] = $row['count'];
        if ($row['status'] == 'late') $summary['late_days'] = $row['count'];
        if ($row['status'] == 'absent') $summary['absent_days'] = $row['count'];
    }
    $total_present_late = $summary['present_days'] + $summary['late_days'];
    $total_records = $total_present_late + $summary['absent_days'];
    
    if ($total_records > 0) {
        $summary['overall_percent'] = round(($total_present_late / $total_records) * 100);
    } else {
        $summary['overall_percent'] = 0; // No records yet
    }
    $stmt_stats->close();
    
    // --- B. GET DATA FOR MAIN TABLE (Specific date) ---
    
    // First, get the grade/section for the selected class
    $stmt_class_info = $conn->prepare("SELECT grade_level, section, subject FROM classes WHERE class_id = ?");
    $stmt_class_info->bind_param("i", $filter_class_id);
    $stmt_class_info->execute();
    $class_info = $stmt_class_info->get_result()->fetch_assoc();
    $report_title = "Report for " . htmlspecialchars($class_info['subject']) . " (" . htmlspecialchars(date("F j, Y", strtotime($filter_date))) . ")";
    $export_link = "export_report.php?class_id=$filter_class_id&date=$filter_date";

    // Now, get all students in that grade/section and LEFT JOIN their attendance for the selected date
    $sql_table = "
        SELECT 
            u.first_name, u.last_name, u.email,
            ar.status, ar.created_at
        FROM users u
        LEFT JOIN attendance_records ar 
            ON u.user_id = ar.student_id 
            AND ar.class_id = ? 
            AND ar.attendance_date = ?
        WHERE 
            u.role = 'student' 
            AND u.grade_level = ? 
            AND u.section = ?
        ORDER BY 
            u.last_name, u.first_name
    ";
    $stmt_table = $conn->prepare($sql_table);
    $stmt_table->bind_param("isss", $filter_class_id, $filter_date, $class_info['grade_level'], $class_info['section']);
    $stmt_table->execute();
    $result_table = $stmt_table->get_result();
    while($row = $result_table->fetch_assoc()) {
        $report_data[] = $row;
    }
    $stmt_table->close();
    $stmt_class_info->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report</title>
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
                <a href="teacher_dashboard.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors duration-200">
                    <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 8.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6A2.25 2.25 0 0115.75 3.75h2.25A2.25 2.25 0 0120.25 6v2.25a2.25 2.25 0 01-2.25 2.25h-2.25A2.25 2.25 0 0113.5 8.25V6zM13.5 15.75A2.25 2.25 0 0115.75 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                    Dashboard
                </a>
                <a href="teacher_take_attendance.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors duration-200">
                    <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.75h16.5m-16.5 3.75h16.5M3.75 17.25h16.5M4.5 5.25h15a2.25 2.25 0 012.25 2.25v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V7.5a2.25 2.25 0 012.25-2.25z" />
                    </svg>
                    Take Attendance
                </a>
                <a href="teacher_report.php" class="flex items-center px-4 py-3 bg-blue-100 text-blue-600 font-semibold rounded-lg">
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
                <h1 class="text-3xl font-bold text-gray-900 mb-4 md:mb-0">Attendance Report</h1>
                
                <div class="flex flex-wrap items-center gap-4">
                    <form action="teacher_report.php" method="GET" class="flex flex-wrap items-center gap-4">
                        <div class="relative">
                            <label for="header_class_id" class="sr-only">Select Class</label>
                            <select name="class_id" id="header_class_id" class="appearance-none bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Class</option>
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
                    <p class="text-sm text-gray-500 mb-1">Overall Attendance</p>
                    <p class="text-4xl font-bold text-gray-900">
                        <?php echo $summary['overall_percent']; ?>%
                    </p>
                </div>
                <div class="bg-white p-5 rounded-xl shadow-md border border-gray-200">
                    <p class="text-sm text-gray-500 mb-1">Total Present Days</p>
                    <p class="text-4xl font-bold text-gray-900">
                        <?php echo $summary['present_days']; ?>
                    </p>
                </div>
                <div class="bg-white p-5 rounded-xl shadow-md border border-gray-200">
                    <p class="text-sm text-gray-500 mb-1">Total Lates</p>
                    <p class="text-4xl font-bold text-gray-900">
                        <?php echo $summary['late_days']; ?>
                    </p>
                </div>
                <div class="bg-white p-5 rounded-xl shadow-md border border-gray-200">
                    <p class="text-sm text-gray-500 mb-1">Total Absences</p>
                    <p class="text-4xl font-bold text-gray-900">
                        <?php echo $summary['absent_days']; ?>
                    </p>
                </div>
            </div>

            <div class="bg-white p-5 rounded-xl shadow-md border border-gray-200">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">
                        <?php echo $report_title; ?>
                    </h2>
                    <a href="<?php echo $export_link; ?>" class="flex items-center gap-2 text-sm bg-blue-50 hover:bg-blue-100 text-blue-600 font-medium py-2 px-3 rounded-lg">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                        Export as CSV
                    </a>
                </div>

                <div class="border rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Student Name</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Time In</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            
                            <?php if (empty($report_data)): ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-4 text-center text-gray-500">
                                        No student data found for this class and date.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($report_data as $student): ?>
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap font-medium text-gray-900"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <?php 
                                            $status = $student['status'] ?? 'Absent'; // Default to Absent if NULL (LEFT JOIN)
                                            if ($status == 'present') {
                                                echo '<span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Present</span>';
                                            } else if ($status == 'late') {
                                                echo '<span class="px-2 py-1 text-xs font-medium rounded-full bg-orange-100 text-orange-800">Late</span>';
                                            } else {
                                                echo '<span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">Absent</span>';
                                            }
                                        ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-600">
                                        <?php echo $student['created_at'] ? date("g:i A", strtotime($student['created_at'])) : 'N/A'; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4 flex items-center gap-6 text-sm">
                    <h3 class="font-medium">Legend:</h3>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Present</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-orange-100 text-orange-800">Late</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">Absent</span>
                    </div>
                </div>
            </div>
            
            <footer class="text-center text-sm text-gray-500 mt-8">
                Copyright © 2025 OMPASSIVE. All rights reserved. Template by <a href="#" class="text-blue-600">OMPASSIVE</a>.
            </footer>
        </main>
    </div>

    <script>
        // --- Profile Dropdown Logic ---
        const profileButton = document.getElementById('profileButton');
        const profileMenu = document.getElementById('profileMenu');
        const dropdownContainer = document.getElementById('profileDropdownContainer');

        if (profileButton) {
            profileButton.addEventListener('click', () => {
                profileMenu.classList.toggle('hidden');
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', (event) => {
            if (profileButton && dropdownContainer && !dropdownContainer.contains(event.target)) {
                profileMenu.classList.add('hidden');
            }
        });
    </script>
</body>
</html>