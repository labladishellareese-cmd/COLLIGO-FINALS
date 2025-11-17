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

// 4. GET STUDENT'S GRADE/SECTION (for sidebar and queries)
$stmt_user = $conn->prepare("SELECT grade_level, section FROM users WHERE user_id = ?");
$stmt_user->bind_param("i", $student_id);
$stmt_user->execute();
$user_result = $stmt_user->get_result();
$student_data = $user_result->fetch_assoc();
$student_grade = $student_data['grade_level'];
$student_section = $student_data['section'];
$student_grade_section = htmlspecialchars($student_grade . ' - ' . $student_section);
$stmt_user->close();

// 5. GET FILTERS FROM URL
$filter_date = $_GET['date'] ?? date('Y-m-d'); // Default to today
$report_title = "Attendance Report for " . date("F j, Y", strtotime($filter_date));

// 6. --- FETCH DATA FOR STATS CARDS (All-time stats for this student) ---
$summary = ['overall_percent' => 0, 'present_days' => 0, 'late_days' => 0, 'absent_days' => 0];
$sql_stats = "
    SELECT status, COUNT(record_id) as count 
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
        $summary['present_days'] = $row['count'];
    } elseif ($row['status'] == 'late') {
        $total_present_late += $row['count'];
        $summary['late_days'] = $row['count'];
    } elseif ($row['status'] == 'absent') {
        $total_absent = $row['count'];
        $summary['absent_days'] = $row['count'];
    }
}
$total_records = $total_present_late + $total_absent;
if ($total_records > 0) {
    $summary['overall_percent'] = round(($total_present_late / $total_records) * 100);
}
$stmt_stats->close();

// 7. --- FETCH DATA FOR MAIN REPORT TABLE (for selected date) ---
// This query gets all classes for the student and LEFT JOINS their attendance
// This ensures we show "Absent" for classes they missed
$report_data = [];
$sql_report = "
    SELECT 
        c.subject, 
        CONCAT(u.first_name, ' ', u.last_name) as teacher_name, 
        ar.status, 
        ar.created_at
    FROM classes c
    JOIN users u ON c.teacher_id = u.user_id
    LEFT JOIN attendance_records ar 
        ON c.class_id = ar.class_id 
        AND ar.student_id = ? 
        AND ar.attendance_date = ?
    WHERE 
        c.grade_level = ? AND c.section = ?
    ORDER BY 
        c.subject
";
$stmt_report = $conn->prepare($sql_report);
$stmt_report->bind_param("isss", $student_id, $filter_date, $student_grade, $student_section);
$stmt_report->execute();
$result_report = $stmt_report->get_result();
while($row = $result_report->fetch_assoc()) {
    $report_data[] = $row;
}
$stmt_report->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance Report</title>
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
                    <?php echo $student_name; ?>
                </p>
                <p class="text-sm text-gray-500">
                    <?php echo $student_grade_section; ?>
                </p>
            </div>

            <nav class="mt-4 px-2">
                <a href="student_dashboard.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors duration-200">
                    <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 8.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6A2.25 2.25 0 0115.75 3.75h2.25A2.25 2.25 0 0120.25 6v2.25a2.25 2.25 0 01-2.25 2.25h-2.25A2.25 2.25 0 0113.5 8.25V6zM13.5 15.75A2.25 2.25 0 0115.75 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                    Dashboard
                </a>
                <a href="student_notifications.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors duration-200">
                    <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0M3.124 7.5A8.969 8.969 0 015.292 3m13.416 0a8.969 8.969 0 012.168 4.5" />
                    </svg>
                    My Notifications
                </a>
                <a href="student_report.php" class="flex items-center px-4 py-3 bg-blue-100 text-blue-600 font-semibold rounded-lg">
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
                <h1 class="text-3xl font-bold text-gray-900 mb-4 md:mb-0">My Attendance Report</h1>
                
                <div class="flex flex-wrap items-center gap-4">
                    <form action="student_report.php" method="GET" class="flex flex-wrap items-center gap-2">
                        <label for="filter_date" class="text-sm font-medium text-gray-700">Select Date</label>
                        <input type="date" name="date" id="filter_date" class="bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               value="<?php echo htmlspecialchars($filter_date); ?>">
                        <button type="submit" class="bg-blue-600 text-white py-2 px-3 rounded-lg text-sm font-semibold">Go</button>
                    </form>

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
                <h2 class="text-xl font-semibold mb-4">
                    <?php echo $report_title; ?>
                </h2>

                <div class="border rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Time In</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            
                            <?php if (empty($report_data)): ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-4 text-center text-gray-500">
                                        No attendance records found for this date.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($report_data as $record): ?>
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($record['subject']); ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($record['teacher_name']); ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <?php 
                                            $status = $record['status'] ?? 'Absent'; // Default NULL to Absent
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
                                        <?php echo $record['created_at'] ? date("g:i A", strtotime($record['created_at'])) : 'N/A'; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                        </tbody>
                    </table>
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