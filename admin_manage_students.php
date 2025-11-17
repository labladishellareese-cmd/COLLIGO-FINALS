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

// 4. PREPARE FILTERS
$grade_filter = $_GET['grade'] ?? 'all';
$section_filter = $_GET['section'] ?? 'all';
$search_filter = $_GET['search'] ?? '';

// 5. FETCH STUDENTS
$sql = "SELECT user_id, first_name, last_name, email, grade_level, section FROM users WHERE role = 'student'";
$params = [];
$types = '';

if ($grade_filter != 'all') {
    $sql .= " AND grade_level = ?";
    $params[] = $grade_filter;
    $types .= 's';
}
if ($section_filter != 'all') {
    $sql .= " AND section = ?";
    $params[] = $section_filter;
    $types .= 's';
}
if (!empty($search_filter)) {
    $like_term = "%" . $search_filter . "%";
    $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $params[] = $like_term;
    $params[] = $like_term;
    $params[] = $like_term;
    $types .= 'sss';
}

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$students = [];
while($row = $result->fetch_assoc()) {
    $students[] = $row;
}

// 6. GET ENUM VALUES FOR DROPDOWNS
// We get this from the database to stay in sync
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

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students</title>
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
                <a href="admin_manage_students.php" class="flex items-center px-4 py-3 bg-blue-100 text-blue-600 font-semibold rounded-lg">
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
                <h1 class="text-3xl font-bold text-gray-900 mb-4 md:mb-0">Manage Students</h1>
                
                <div class="flex items-center gap-4">
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

            <div class="bg-white p-4 rounded-xl shadow-md border border-gray-200 mb-6">
                <form action="admin_manage_students.php" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="relative">
                        <label for="filterGrade" class="block text-sm font-medium text-gray-700 mb-1">Grade</label>
                        <select id="filterGrade" name="grade" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="all">All Grades</option>
                            <?php foreach ($grade_options as $option): ?>
                                <option value="<?php echo $option; ?>" <?php if ($grade_filter == $option) echo 'selected'; ?>>
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
                                <option value="<?php echo $option; ?>" <?php if ($section_filter == $option) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="absolute inset-y-0 right-0 top-6 flex items-center px-3 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                        </div>
                    </div>
                    <div class="relative md:col-span-1">
                        <label for="filterSearch" class="block text-sm font-medium text-gray-700 mb-1">Name / Email</label>
                        <input type="text" id="filterSearch" name="search" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               placeholder="Search..."
                               value="<?php echo htmlspecialchars($search_filter); ?>">
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md font-semibold hover:bg-blue-700">
                            Search
                        </button>
                        <a href="admin_manage_students.php" class="w-full text-center bg-gray-200 text-gray-700 py-2 px-4 rounded-md font-semibold hover:bg-gray-300">
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
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Student</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Email</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Grade</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Section</th>
                                <th scope="col" class="px-4 py-3 text-left font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="studentTableBody">
                            
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-4 text-center text-gray-500">
                                        No students found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $student): ?>
                                <tr id="student-<?php echo $student['user_id']; ?>" class="student-row">
                                    <td class="px-4 py-3 whitespace-nowrap" data-column="name">
                                        <div class="flex items-center gap-3">
                                            <span class="font-medium"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-600" data-column="email">
                                        <?php echo htmlspecialchars($student['email']); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-600" data-column="grade">
                                        <?php echo htmlspecialchars($student['grade_level']); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-600" data-column="section">
                                        <?php echo htmlspecialchars($student['section']); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap space-x-2">
                                        <a href="admin_edit_student.php?id=<?php echo $student['user_id']; ?>" class="text-blue-600 hover:text-blue-800 font-medium">Edit</a>
                                        <a href="admin_delete_user.php?id=<?php echo $student['user_id']; ?>" 
                                           class="text-red-600 hover:text-red-800 font-medium"
                                           onclick="return confirm('Are you sure you want to delete this student? This action cannot be undone.');">
                                           Delete
                                        </a>
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
        
        // --- All simulation and filter JS removed. Form submission will handle filtering. ---

    </script>
</body>
</html>