<?php
// 1. INCLUDE THE DATABASE CONNECTION AND START SESSION
require_once 'db_connect.php';

// 2. SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// 3. GET ADMIN'S NAME
$admin_name = htmlspecialchars($_SESSION['full_name']);

// 4. GET PAGE PARAMETERS
$report_date = $_GET['reportDate'] ?? date('Y-m-d');
$active_tab = $_GET['tab'] ?? 'integrity';

// --- 5. DATA FOR TAB 1: MISSING SUBMISSIONS ---
$missing_submissions = [];
$missing_sql = "
    SELECT c.class_id, c.grade_level, c.section, c.subject, u.first_name, u.last_name, u.user_id as teacher_id
    FROM classes c
    JOIN users u ON c.teacher_id = u.user_id
    WHERE c.class_id NOT IN (
        SELECT DISTINCT a.class_id FROM attendance_records a WHERE a.attendance_date = ?
    )
";
$stmt_missing = $conn->prepare($missing_sql);
$stmt_missing->bind_param("s", $report_date);
$stmt_missing->execute();
$missing_result = $stmt_missing->get_result();
while($row = $missing_result->fetch_assoc()) {
    $missing_submissions[] = $row;
}
$stmt_missing->close();

// --- 6. DATA FOR TAB 2: SUMMARY REPORTS ---

// -- Get options for filters
$grade_options = [];
$section_options = [];
$enum_query = $conn->query("SHOW COLUMNS FROM users WHERE Field IN ('grade_level', 'section')");
while ($row = $enum_query->fetch_assoc()) {
    preg_match("/^enum\(\'(.*)\'\)$/", $row['Type'], $matches);
    $enum_values = explode("','", $matches[1]);
    if ($row['Field'] == 'grade_level') {
        $grade_options = $enum_values;
    } else {
        $section_options = $enum_values;
    }
}
$teacher_options = [];
$teacher_sql = "SELECT user_id, first_name, last_name FROM users WHERE role = 'teacher' AND is_approved = 1 ORDER BY first_name";
$teacher_result = $conn->query($teacher_sql);
while($row = $teacher_result->fetch_assoc()) {
    $teacher_options[] = $row;
}

// -- Get filters for summary
$filter_grade = $_GET['grade'] ?? 'all';
$filter_section = $_GET['section'] ?? 'all';
$filter_teacher = $_GET['teacher_id'] ?? 'all';
$filter_subject = $_GET['subject'] ?? '';

// -- Build dynamic WHERE clause for summary
$where_clause = "WHERE r.attendance_date BETWEEN DATE_FORMAT(NOW(), '%Y-%m-01') AND NOW()"; // Default: This Month
$params = [];
$types = '';

if ($filter_grade != 'all') {
    $where_clause .= " AND c.grade_level = ?";
    $params[] = $filter_grade;
    $types .= 's';
}
if ($filter_section != 'all') {
    $where_clause .= " AND c.section = ?";
    $params[] = $filter_section;
    $types .= 's';
}
if ($filter_teacher != 'all') {
    $where_clause .= " AND c.teacher_id = ?";
    $params[] = $filter_teacher;
    $types .= 'i';
}
if (!empty($filter_subject)) {
    $like_subject = "%" . $filter_subject . "%";
    $where_clause .= " AND c.subject LIKE ?";
    $params[] = $like_subject;
    $types .= 's';
}

// -- Get data for Summary Stats Cards
$summary_stats = [
    'attendance' => 0, 'absences' => 0, 'lates' => 0, 'below_80' => 0
];
$stats_sql = "
    SELECT 
        (COUNT(CASE WHEN r.status IN ('present', 'late') THEN 1 END) / COUNT(r.record_id)) * 100 as attendance,
        COUNT(CASE WHEN r.status = 'absent' THEN 1 END) as absences,
        COUNT(CASE WHEN r.status = 'late' THEN 1 END) as lates
    FROM attendance_records r
    JOIN classes c ON r.class_id = c.class_id
    $where_clause
";
$stmt_stats = $conn->prepare($stats_sql);
if (!empty($params)) $stmt_stats->bind_param($types, ...$params);
$stmt_stats->execute();
$stats_result = $stmt_stats->get_result()->fetch_assoc();
if ($stats_result) {
    $summary_stats['attendance'] = round($stats_result['attendance'] ?? 0);
    $summary_stats['absences'] = $stats_result['absences'] ?? 0;
    $summary_stats['lates'] = $stats_result['lates'] ?? 0;
}
$stmt_stats->close();


// -- Get data for Summary Table
$summary_table = [];
$table_sql = "
    SELECT 
        c.grade_level, c.section, c.subject,
        CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
        COUNT(DISTINCT r.student_id) as students,
        (COUNT(CASE WHEN r.status IN ('present', 'late') THEN 1 END) / COUNT(r.record_id)) * 100 as attendance_pct,
        COUNT(CASE WHEN r.status = 'absent' THEN 1 END) as total_absences,
        COUNT(CASE WHEN r.status = 'late' THEN 1 END) as total_lates
    FROM classes c
    JOIN users u ON c.teacher_id = u.user_id
    LEFT JOIN attendance_records r ON c.class_id = r.class_id
    $where_clause
    GROUP BY c.class_id
    ORDER BY c.grade_level, c.section, c.subject
";
$stmt_table = $conn->prepare($table_sql);
if (!empty($params)) $stmt_table->bind_param($types, ...$params);
$stmt_table->execute();
$table_result = $stmt_table->get_result();
while($row = $table_result->fetch_assoc()) {
    $summary_table[] = $row;
    if (round($row['attendance_pct']) < 80) {
        $summary_stats['below_80']++;
    }
}
$stmt_table->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management</title>
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
        /* Style for active tab */
        .tab-btn-active {
            border-bottom-color: #2563EB; /* border-blue-600 */
            color: #2563EB; /* text-blue-600 */
            font-weight: 600;
        }
         /* Custom styling for select to remove default arrow */
        select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
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
                <a href="admin_dashboard.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors duration-200">
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
                <a href="admin_global_reports.php" class="flex items-center px-4 py-3 bg-blue-100 text-blue-600 font-semibold rounded-lg">
                    <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" /></svg>
                    Global Reports
                </a>
            </nav>
        </aside>

        <main class="flex-1 p-6 lg:p-10">
            
            <header class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
                <h1 class="text-3xl font-bold text-gray-900">Attendance Management & Reports</h1>
                
                <div class="flex items-center gap-4">
                    <div class="relative" id="profileDropdownContainer">
                        <button id="profileButton" class="flex items-center gap-2 bg-white p-2 rounded-lg border border-gray-300">
                            <img src="https://placehold.co/32x32/6366F1/E0E7FF?text=A" alt="Admin User" class="w-8 h-8 rounded-full">
                            <span class="text-sm font-medium">
                                <?php echo $admin_name; ?>
                            </span>
                            <svg class="w-4 h-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                        </button>
                        <div id="profileMenu" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl py-1 hidden z-20">
                            <a href="admin_profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">My Profile</a>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <form action="admin_global_reports.php" method="GET" class="mb-6 bg-white p-4 rounded-xl shadow-md border border-gray-200 flex items-center gap-4">
                <label for="reportDate" class="block text-sm font-medium text-gray-700">Select Date:</label>
                <input type="date" id="reportDate" name="reportDate" class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500" 
                       value="<?php echo htmlspecialchars($report_date); ?>">
                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-md font-semibold hover:bg-blue-700">
                    Go
                </button>
            </form>

            <div class="border-b border-gray-200 mb-6">
                <nav class="-mb-px flex gap-6" aria-label="Tabs">
                    <button id="tab-btn-integrity" class="tab-btn tab-btn-active whitespace-nowrap py-4 px-1 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        Missing Submissions
                    </button>
                    <button id="tab-btn-summary" class="tab-btn whitespace-nowrap py-4 px-1 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        Summary Reports
                    </button>
                </nav>
            </div>

            <div>
                <div id="tab-panel-integrity" class="tab-panel">
                    <div class="bg-white p-5 rounded-xl shadow-md border border-gray-200">
                        <h2 class="text-xl font-semibold mb-4 text-red-600">
                            Missing Submissions for <?php echo date("F j, Y", strtotime($report_date)); ?>
                        </h2>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Teacher</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Grade</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Section</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Subject</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Status</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    
                                    <?php if (empty($missing_submissions)): ?>
                                        <tr>
                                            <td colspan="6" class="px-4 py-4 text-center text-gray-500">
                                                No missing submissions found.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($missing_submissions as $sub): ?>
                                        <tr>
                                            <td class="px-4 py-3 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($sub['first_name'] . ' ' . $sub['last_name']); ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($sub['grade_level']); ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($sub['section']); ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($sub['subject']); ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap"><span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">Missing</span></td>
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
                </div>

                <div id="tab-panel-summary" class="tab-panel hidden">
                    
                    <form action="admin_global_reports.php" method="GET" class="bg-white p-4 rounded-xl shadow-md border border-gray-200 mb-6">
                        <input type="hidden" name="tab" value="summary">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Filter Summary</h3>
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                            
                            <div class="relative">
                                <label for="summaryFilterGrade" class="block text-sm font-medium text-gray-700 mb-1">Grade</label>
                                <select id="summaryFilterGrade" name="grade" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="all">All Grades</option>
                                    <?php foreach ($grade_options as $option): ?>
                                        <option value="<?php echo $option; ?>" <?php if ($filter_grade == $option) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($option); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute inset-y-0 right-0 top-6 flex items-center px-3 pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                </div>
                            </div>
                            
                            <div class="relative">
                                <label for="summaryFilterSection" class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                                <select id="summaryFilterSection" name="section" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="all">All Sections</option>
                                    <?php foreach ($section_options as $option): ?>
                                        <option value="<?php echo $option; ?>" <?php if ($filter_section == $option) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($option); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute inset-y-0 right-0 top-6 flex items-center px-3 pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                </div>
                            </div>
                            
                            <div class="relative">
                                <label for="summaryFilterTeacher" class="block text-sm font-medium text-gray-700 mb-1">Teacher</label>
                                <select id="summaryFilterTeacher" name="teacher_id" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="all">All Teachers</option>
                                    <?php foreach ($teacher_options as $teacher): ?>
                                        <option value="<?php echo $teacher['user_id']; ?>" <?php if ($filter_teacher == $teacher['user_id']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute inset-y-0 right-0 top-6 flex items-center px-3 pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                </div>
                            </div>
                            
                            <div class="relative">
                                <label for="summaryFilterSubject" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                                <input type="text" id="summaryFilterSubject" name="subject" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                       placeholder="Search subject..."
                                       value="<?php echo htmlspecialchars($filter_subject); ?>">
                            </div>
                            <div class="flex items-end gap-2">
                                <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md font-semibold hover:bg-blue-700">
                                    Filter
                                </button>
                                <a href="admin_global_reports.php?tab=summary" class="w-full text-center bg-gray-200 text-gray-700 py-2 px-4 rounded-md font-semibold hover:bg-gray-300">
                                    Reset
                                </a>
                            </div>
                        </div>
                    </form>

                    <div id="summaryStats" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white p-5 rounded-xl shadow-md border border-gray-200">
                            <p class="text-sm text-gray-500 mb-1">Attendance</p>
                            <p id="stat-attendance" class="text-4xl font-bold text-gray-900">
                                <?php echo $summary_stats['attendance']; ?>%
                            </p>
                        </div>
                        <div class="bg-white p-5 rounded-xl shadow-md border border-gray-200">
                            <p class="text-sm text-gray-500 mb-1">Total Absences</p>
                            <p id="stat-absences" class="text-4xl font-bold text-gray-900">
                                <?php echo $summary_stats['absences']; ?>
                            </p>
                        </div>
                        <div class="bg-white p-5 rounded-xl shadow-md border border-gray-200">
                            <p class="text-sm text-gray-500 mb-1">Total Lates</p>
                            <p id="stat-lates" class="text-4xl font-bold text-gray-900">
                                <?php echo $summary_stats['lates']; ?>
                            </p>
                        </div>
                        <div class="bg-white p-5 rounded-xl shadow-md border border-gray-200">
                            <p class="text-sm text-gray-500 mb-1">Classes Below 80%</p>
                            <p id="stat-below-80" class="text-4xl font-bold text-gray-900">
                                <?php echo $summary_stats['below_80']; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="bg-white p-5 rounded-xl shadow-md border border-gray-200">
                        <h2 class="text-xl font-semibold mb-4">Summary by Class (This Month)</h2>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Grade</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Section</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Subject</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Teacher</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Students</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Attendance %</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Absences</th>
                                        <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Lates</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="summaryTableBody">
                                    
                                    <?php if (empty($summary_table)): ?>
                                        <tr>
                                            <td colspan="8" class="px-4 py-4 text-center text-gray-500">
                                                No summary data found for the selected filters.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($summary_table as $row): ?>
                                        <tr class="summary-row">
                                            <td class="px-4 py-3 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($row['grade_level']); ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($row['section']); ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($row['subject']); ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($row['teacher_name']); ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-gray-600"><?php echo $row['students']; ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap font-medium <?php echo (round($row['attendance_pct']) < 80) ? 'text-red-600' : 'text-green-600'; ?>">
                                                <?php echo round($row['attendance_pct']); ?>%
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-gray-600"><?php echo $row['total_absences']; ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-gray-600"><?php echo $row['total_lates']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                </tbody>
                            </table>
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
        // --- Profile Dropdown Logic ---
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

        // --- RE-ADDED Tab Switching Logic (UI Only) ---
        const tabButtons = document.querySelectorAll('.tab-btn');
        const tabPanels = document.querySelectorAll('.tab-panel');
        
        // Check for a tab in the URL query parameters
        const urlParams = new URLSearchParams(window.location.search);
        // MODIFIED: Read the PHP-set active tab, default to 'integrity'
        const activeTab = "<?php echo $active_tab; ?>";

        tabButtons.forEach(tab => {
            const tabId = tab.id.replace('btn-', '');
            const targetPanelId = tab.id.replace('btn', 'panel');
            const targetPanel = document.getElementById(targetPanelId);

            if (tabId === activeTab) {
                // Activate this tab
                tab.classList.add('tab-btn-active');
                if(targetPanel) targetPanel.classList.remove('hidden');
            } else {
                // Deactivate this tab
                tab.classList.remove('tab-btn-active');
                if(targetPanel) targetPanel.classList.add('hidden');
            }
            
            // Add click listener to switch tabs
            tab.addEventListener('click', (e) => {
                e.preventDefault(); // Stop form submission
                const newTabId = tab.id.replace('btn-', '');

                // Deactivate all
                tabButtons.forEach(t => t.classList.remove('tab-btn-active'));
                tabPanels.forEach(p => p.classList.add('hidden'));

                // Activate clicked
                tab.classList.add('tab-btn-active');
                if (targetPanel) {
                    targetPanel.classList.remove('hidden');
                }
                
                // Update URL parameter without reloading (for bookmarking)
                const newUrl = new URL(window.location);
                newUrl.searchParams.set('tab', newTabId);
                // We also keep the date filter
                const reportDate = document.getElementById('reportDate').value;
                if (reportDate) {
                    newUrl.searchParams.set('reportDate', reportDate);
                }
                history.pushState({}, '', newUrl);
            });
        });
        
        // --- All other simulation JS removed. ---

    </script>
</body>
</html>