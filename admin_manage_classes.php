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
$modal_message = ''; // For success/error in the modal

// 4. --- HANDLE FORM SUBMISSION (CREATE NEW CLASS) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $grade_level = $_POST['grade'];
    $section = $_POST['section'];
    $teacher_id = $_POST['teacher_id'];
    $subject = $_POST['subject'];

    // Check for duplicates
    $check_sql = "SELECT class_id FROM classes WHERE grade_level = ? AND section = ? AND subject = ?";
    $stmt_check = $conn->prepare($check_sql);
    $stmt_check->bind_param("sss", $grade_level, $section, $subject);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        $modal_message = "<p class='text-red-600'>Error: This class (Grade, Section, and Subject) already exists.</p>";
    } else {
        // No duplicate, insert new class
        $insert_sql = "INSERT INTO classes (grade_level, section, teacher_id, subject) VALUES (?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($insert_sql);
        $stmt_insert->bind_param("ssis", $grade_level, $section, $teacher_id, $subject);
        
        if ($stmt_insert->execute()) {
            $modal_message = "<p class='text-green-600'>Success! New class assignment created.</p>";
        } else {
            $modal_message = "<p class='text-red-600'>Error: Could not create assignment.</p>";
        }
        $stmt_insert->close();
    }
    $stmt_check->close();
}

// 5. --- FETCH DATA FOR THE PAGE (TABLE AND FILTERS) ---

// -- Get Grade and Section options from the ENUMs
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

// -- Get Teacher options
$teacher_options = [];
$teacher_sql = "SELECT user_id, first_name, last_name FROM users WHERE role = 'teacher' AND is_approved = 1 ORDER BY first_name";
$teacher_result = $conn->query($teacher_sql);
while($row = $teacher_result->fetch_assoc()) {
    $teacher_options[] = $row;
}

// -- Get Class list for the main table (with filters)
$filter_grade = $_GET['grade'] ?? 'all';
$filter_section = $_GET['section'] ?? 'all';
$filter_teacher = $_GET['teacher'] ?? 'all';
$filter_subject = $_GET['subject'] ?? '';

$sql_classes = "
    SELECT c.class_id, c.grade_level, c.section, c.subject, u.first_name, u.last_name 
    FROM classes c
    LEFT JOIN users u ON c.teacher_id = u.user_id
    WHERE 1=1
";

$params = [];
$types = '';

if ($filter_grade != 'all') {
    $sql_classes .= " AND c.grade_level = ?";
    $params[] = $filter_grade;
    $types .= 's';
}
if ($filter_section != 'all') {
    $sql_classes .= " AND c.section = ?";
    $params[] = $filter_section;
    $types .= 's';
}
if ($filter_teacher != 'all') {
    $sql_classes .= " AND c.teacher_id = ?";
    $params[] = $filter_teacher;
    $types .= 'i';
}
if (!empty($filter_subject)) {
    $like_subject = "%" . $filter_subject . "%";
    $sql_classes .= " AND c.subject LIKE ?";
    $params[] = $like_subject;
    $types .= 's';
}

$sql_classes .= " ORDER BY c.grade_level, c.section, c.subject";
$stmt_classes = $conn->prepare($sql_classes);
if (!empty($params)) {
    $stmt_classes->bind_param($types, ...$params);
}
$stmt_classes->execute();
$result_classes = $stmt_classes->get_result();
$assignments = [];
while($row = $result_classes->fetch_assoc()) {
    $assignments[] = $row;
}

$stmt_classes->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Class Assignments</title>
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
                <a href="admin_manage_classes.php" class="flex items-center px-4 py-3 bg-blue-100 text-blue-600 font-semibold rounded-lg">
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
                <h1 class="text-3xl font-bold text-gray-900 mb-4 md:mb-0">Manage Class Assignments</h1>
                
                <div class="flex items-center gap-4">
                    <button id="openModalBtn" class="flex items-center gap-2 bg-blue-600 text-white py-2 px-4 rounded-lg font-semibold shadow-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-75 transition duration-300">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        New Assignment
                    </button>
                    
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

            <div class="bg-white p-4 rounded-xl shadow-md border border-gray-200 mb-6">
                <form action="admin_manage_classes.php" method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div class="relative">
                        <label for="filterGrade" class="block text-sm font-medium text-gray-700 mb-1">Grade</label>
                        <select id="filterGrade" name="grade" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
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
                        <label for="filterSection" class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                        <select id="filterSection" name="section" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
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
                        <label for="filterTeacher" class="block text-sm font-medium text-gray-700 mb-1">Teacher</label>
                        <select id="filterTeacher" name="teacher" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
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
                        <label for="filterSubject" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                        <input type="text" id="filterSubject" name="subject" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               placeholder="Search subject..."
                               value="<?php echo htmlspecialchars($filter_subject); ?>">
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md font-semibold hover:bg-blue-700">
                            Search
                        </button>
                        <a href="admin_manage_classes.php" class="w-full text-center bg-gray-200 text-gray-700 py-2 px-4 rounded-md font-semibold hover:bg-gray-300">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <div class="bg-white p-5 rounded-xl shadow-md border border-gray-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Grade</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Section</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Subject</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Assigned Teacher</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="classTableBody">
                            
                            <?php if (empty($assignments)): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-4 text-center text-gray-500">
                                        No class assignments found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($assignments as $assignment): ?>
                                <tr id="class-<?php echo $assignment['class_id']; ?>" class="class-row">
                                    <td class="px-4 py-3 whitespace-nowrap font-medium text-gray-700" data-column="grade"><?php echo htmlspecialchars($assignment['grade_level']); ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap font-medium text-gray-700" data-column="section"><?php echo htmlspecialchars($assignment['section']); ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap font-medium text-gray-900" data-column="subject"><?php echo htmlspecialchars($assignment['subject']); ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-600" data-column="teacher">
                                        <div class="flex items-center gap-2">
                                            <img src="https://placehold.co/32x32/E0E7FF/6366F1?text=T" class="w-8 h-8 rounded-full">
                                            <?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap space-x-2">
                                        <a href="admin_delete_class.php?id=<?php echo $assignment['class_id']; ?>" class="text-red-600 hover:text-red-800 font-medium">Remove</a>
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

    <div id="addClassModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center hidden z-50">
        <div class="relative mx-auto p-5 border w-full max-w-lg shadow-lg rounded-lg bg-white">
            <form id="addClassForm" action="admin_manage_classes.php" method="POST">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">New Class Assignment</h3>
                <div class="space-y-4">
                    
                    <div class="relative">
                        <label for="gradeSelect" class="block text-sm font-medium text-gray-700 mb-1">Grade Level</label>
                        <select id="gradeSelect" name="grade" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="" disabled selected>Select Grade...</option>
                            <?php foreach ($grade_options as $option): ?>
                                <option value="<?php echo $option; ?>"><?php echo htmlspecialchars($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="absolute inset-y-0 right-0 top-6 flex items-center px-3 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                        </div>
                    </div>

                    <div class="relative">
                        <label for="sectionSelect" class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                        <select id="sectionSelect" name="section" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="" disabled selected>Select Section...</option>
                             <?php foreach ($section_options as $option): ?>
                                <option value="<?php echo $option; ?>"><?php echo htmlspecialchars($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="absolute inset-y-0 right-0 top-6 flex items-center px-3 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                        </div>
                    </div>

                    <div class="relative">
                        <label for="teacherSelect" class="block text-sm font-medium text-gray-700 mb-1">Teacher</label>
                        <select id="teacherSelect" name="teacher_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="" disabled selected>Select Teacher...</option>
                            <?php foreach ($teacher_options as $teacher): ?>
                                <option value="<?php echo $teacher['user_id']; ?>">
                                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="absolute inset-y-0 right-0 top-6 flex items-center px-3 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                        </div>
                    </div>

                    <div>
                        <label for="subjectInput" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                        <input type="text" id="subjectInput" name="subject" placeholder="e.g., History 101, Algebra II" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                </div>
                
                <div id="modalMessage" class="text-sm text-center mt-4">
                    <?php echo $modal_message; ?>
                </div>

                <div class="items-center px-4 py-3 gap-3 flex justify-end mt-4 border-t">
                    <button id="cancelAddBtn" type="button" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 font-medium">
                        Cancel
                    </button>
                    <button id="confirmAddBtn" type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-medium">
                        Create Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center hidden z-50">
        <div class="relative mx-auto p-5 border w-full max-w-md shadow-lg rounded-lg bg-white">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                    <svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                </div>
                <h3 class="text-lg leading-6 font-medium text-gray-900 mt-3">Remove Assignment</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500">
                        Are you sure you want to remove this assignment? This action cannot be undone.
                    </p>
                </div>
                <div class="items-center px-4 py-3 gap-3 flex justify-center">
                    <button id="cancelDelete" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 font-medium">
                        Cancel
                    </button>
                    <a id="confirmDeleteLink" href="admin_delete_class.php?id=..." class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 font-medium">
                        Remove
                    </a>
                </div>
            </div>
        </div>
    </div>


    <script>
        // --- Profile Dropdown Logic (UI) ---
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

        // --- Add Class Modal Logic (UI) ---
        const addClassModal = document.getElementById('addClassModal');
        const openModalBtn = document.getElementById('openModalBtn');
        const cancelAddBtn = document.getElementById('cancelAddBtn');
        const addClassForm = document.getElementById('addClassForm');
        
        openModalBtn.addEventListener('click', () => {
            addClassModal.classList.remove('hidden');
            addClassForm.reset();
            document.getElementById('modalMessage').innerHTML = ''; // Clear message on open
        });

        cancelAddBtn.addEventListener('click', () => {
            addClassModal.classList.add('hidden');
        });

        // --- MODIFICATION: Show modal if PHP has a message ---
        <?php if (!empty($modal_message)): ?>
        addClassModal.classList.remove('hidden');
        <?php endif; ?>
        
        // --- Delete Modal Logic (UI) ---
        // Your original JS is kept, but I've corrected the link-finding
        const deleteModal = document.getElementById('deleteModal');
        const cancelDeleteBtn = document.getElementById('cancelDelete');
        const confirmDeleteLink = document.getElementById('confirmDeleteLink');
        const classTableBody = document.getElementById('classTableBody');
        
        if (classTableBody) {
            classTableBody.addEventListener('click', (event) => {
                const deleteButton = event.target.closest('a[href^="admin_delete_class.php"]'); // Find the delete link
                if (deleteButton) {
                    event.preventDefault(); // Stop the link from navigating immediately
                    
                    // Set the correct delete link
                    confirmDeleteLink.href = deleteButton.href;
                    
                    deleteModal.classList.remove('hidden');
                }
            });
        }

        if (cancelDeleteBtn) {
            cancelDeleteBtn.addEventListener('click', () => {
                deleteModal.classList.add('hidden');
            });
        }
        
        // --- All simulation and filter JS removed. ---

    </script>
</body>
</html>