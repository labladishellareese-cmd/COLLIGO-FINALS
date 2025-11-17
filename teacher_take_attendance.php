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
$teacher_role = "Teacher"; // Hardcoded as per your HTML

$submit_message = '';

// 4. --- HANDLE ATTENDANCE SUBMISSION (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['attendance'])) {
    
    $class_id = $_POST['class_id'];
    $attendance_date = $_POST['attendance_date'];
    $attendance_data = $_POST['attendance'];

    // This query will INSERT a new record, or UPDATE the 'status' if a record
    // for that student, class, and date already exists. This prevents errors.
    $sql_insert = "
        INSERT INTO attendance_records (class_id, student_id, teacher_id, attendance_date, status) 
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE status = VALUES(status)
    ";
    
    $stmt_insert = $conn->prepare($sql_insert);
    
    // Loop through each student and save their attendance
    foreach ($attendance_data as $student_id => $status) {
        $stmt_insert->bind_param("iisss", $class_id, $student_id, $teacher_id, $attendance_date, $status);
        $stmt_insert->execute();
    }
    
    $stmt_insert->close();
    $submit_message = "<p class='text-green-600'>Attendance submitted successfully!</p>";
}

// 5. --- HANDLE PAGE LOAD (GET) ---

// Get filter values from URL
$filter_grade = $_GET['grade'] ?? '';
$filter_section = $_GET['section'] ?? '';
$filter_subject = $_GET['subject'] ?? '';

// Fetch teacher's assigned classes for the dropdowns
$my_classes = [];
$sql_classes = "SELECT class_id, grade_level, section, subject FROM classes WHERE teacher_id = ? ORDER BY grade_level, section, subject";
$stmt_classes = $conn->prepare($sql_classes);
$stmt_classes->bind_param("i", $teacher_id);
$stmt_classes->execute();
$result_classes = $stmt_classes->get_result();
while($row = $result_classes->fetch_assoc()) {
    $my_classes[] = $row;
}
$stmt_classes->close();

// Create unique lists for filter dropdowns
$grade_options = array_unique(array_column($my_classes, 'grade_level'));
$section_options = array_unique(array_column($my_classes, 'section'));
$subject_options = array_unique(array_column($my_classes, 'subject'));

// Fetch students IF all filters are set
$students = [];
$selected_class_id = null;
if (!empty($filter_grade) && !empty($filter_section) && !empty($filter_subject)) {
    // Find the class_id that matches the filters
    foreach ($my_classes as $class) {
        if ($class['grade_level'] == $filter_grade && $class['section'] == $filter_section && $class['subject'] == $filter_subject) {
            $selected_class_id = $class['class_id'];
            break;
        }
    }

    if ($selected_class_id) {
        // Found the class, now get the students for that grade/section
        $sql_students = "
            SELECT user_id, first_name, last_name 
            FROM users 
            WHERE role = 'student' AND grade_level = ? AND section = ? 
            ORDER BY last_name, first_name
        ";
        $stmt_students = $conn->prepare($sql_students);
        $stmt_students->bind_param("ss", $filter_grade, $filter_section);
        $stmt_students->execute();
        $result_students = $stmt_students->get_result();
        while($row = $result_students->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt_students->close();
    }
    // If $selected_class_id is null, it means teacher selected a combo they aren't assigned. $students remains empty.
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Attendance</title>
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
                <a href="teacher_take_attendance.php" class="flex items-center px-4 py-3 bg-blue-100 text-blue-600 font-semibold rounded-lg">
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
                <h1 class="text-3xl font-bold text-gray-900 mb-4 md:mb-0">Take Attendance</h1>
                
                <div class="flex flex-wrap items-center gap-4">
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

            <div class="bg-white p-5 rounded-xl shadow-md border border-gray-200 max-w-4xl mx-auto">
                
                <form id="filterForm" action="teacher_take_attendance.php" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4 p-4 bg-gray-50 rounded-lg">
                    <div class="relative">
                        <label for="att_grade" class="block text-xs font-medium text-gray-600 mb-1">Grade</label>
                        <select name="grade" id="att_grade" class="appearance-none w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Grade...</option>
                            <?php foreach ($grade_options as $option): ?>
                                <option value="<?php echo $option; ?>" <?php if ($filter_grade == $option) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <svg class="w-4 h-4 text-gray-400 absolute right-3 top-7 -translate-y-1/2 pointer-events-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                    </div>
                    <div class="relative">
                        <label for="att_section" class="block text-xs font-medium text-gray-600 mb-1">Section</label>
                        <select name="section" id="att_section" class="appearance-none w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Section...</option>
                            <?php foreach ($section_options as $option): ?>
                                <option value="<?php echo $option; ?>" <?php if ($filter_section == $option) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <svg class="w-4 h-4 text-gray-400 absolute right-3 top-7 -translate-y-1/2 pointer-events-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                    </div>
                    <div class="relative">
                        <label for="att_subject" class="block text-xs font-medium text-gray-600 mb-1">Subject</label>
                        <select name="subject" id="att_subject" class="appearance-none w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Subject...</option>
                            <?php foreach ($subject_options as $option): ?>
                                <option value="<?php echo $option; ?>" <?php if ($filter_subject == $option) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <svg class="w-4 h-4 text-gray-400 absolute right-3 top-7 -translate-y-1/2 pointer-events-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-600 text-white py-2 px-3 rounded-lg text-sm font-semibold">
                            Load Students
                        </button>
                    </div>
                </form>

                <?php if (empty($students) || !$selected_class_id): ?>
                    <div class="text-center text-gray-500 p-4">
                        Please select a valid grade, section, and subject to begin.
                    </div>
                    <div id="submitMessage" class="text-center mt-3 text-sm">
                        <?php echo $submit_message; ?>
                    </div>
                <?php else: ?>
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold">Today's Attendance</h2>
                        <button id="markAllPresent" class="text-sm bg-blue-50 hover:bg-blue-100 text-blue-600 font-medium py-2 px-3 rounded-lg">Mark All Present</button>
                    </div>
                    <div class="overflow-y-auto">
                        <form id="attendanceForm" action="teacher_take_attendance.php" method="POST">
                            
                            <input type="hidden" name="class_id" value="<?php echo htmlspecialchars($selected_class_id); ?>">
                            <input type="hidden" name="attendance_date" value="<?php echo date('Y-m-d'); ?>">
                            
                            <?php foreach ($students as $student): ?>
                            <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <img src="https://placehold.co/40x40/E0E7FF/6366F1?text=<?php echo substr($student['first_name'], 0, 1); ?>" alt="Student" class="w-10 h-10 rounded-full">
                                    <span class="font-medium"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
                                </div>
                                <div class="flex gap-4" data-student-id="<?php echo $student['user_id']; ?>">
                                    <label class="flex items-center gap-1 cursor-pointer">
                                        <input type="radio" name="attendance[<?php echo $student['user_id']; ?>]" value="present" class="w-4 h-4 text-blue-600"> Present
                                    </label>
                                    <label class="flex items-center gap-1 cursor-pointer">
                                        <input type="radio" name="attendance[<?php echo $student['user_id']; ?>]" value="late" class="w-4 h-4 text-orange-500"> Late
                                    </label>
                                    <label class="flex items-center gap-1 cursor-pointer">
                                        <input type="radio" name="attendance[<?php echo $student['user_id']; ?>]" value="absent" class="w-4 h-4 text-red-600"> Absent
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <div class="mt-6">
                                <button type="submit" id="submitButton" class="w-full bg-green-600 text-white py-3 px-4 rounded-lg font-semibold text-center shadow-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-75 transition duration-300">
                                    Submit Attendance
                                </button>
                                <div id="submitMessage" class="text-center mt-3 text-sm">
                                    <?php echo $submit_message; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

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

        // --- Attendance Form Logic (UI only) ---
        const markAllPresentButton = document.getElementById('markAllPresent');
        const attendanceForm = document.getElementById('attendanceForm');

        // Mark All Present
        if (markAllPresentButton) {
            markAllPresentButton.addEventListener('click', () => {
                const presentRadios = attendanceForm.querySelectorAll('input[value="present"]');
                presentRadios.forEach(radio => {
                    radio.checked = true;
                });
            });
        }
    </script>
</body>
</html>