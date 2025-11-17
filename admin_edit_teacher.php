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
$message = '';
$teacher = null;
$teacher_id = $_GET['id'] ?? 0;

// 4. --- HANDLE FORM SUBMISSION (UPDATE DETAILS) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['teacher_id'])) {
    
    $posted_teacher_id = $_POST['teacher_id'];
    $first_name = $_POST['firstName'];
    $last_name = $_POST['lastName'];
    $email = $_POST['email'];
    
    // Update the user's details
    $update_sql = "UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE user_id = ? AND role = 'teacher'";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sssi", $first_name, $last_name, $email, $posted_teacher_id);
    
    if ($stmt->execute()) {
        $message = "<p class='text-green-600'>Teacher details updated successfully.</p>";
    } else {
        $message = "<p class='text-red-600'>Error updating details.</p>";
    }
    $stmt->close();
    
    // We update the $teacher_id to ensure we fetch the fresh data below
    $teacher_id = $posted_teacher_id;
}


// 5. --- FETCH TEACHER DATA TO DISPLAY ---
if (empty($teacher_id)) {
    header("Location: admin_manage_teachers.php"); // No ID, so redirect
    exit;
}

$stmt_fetch = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ? AND role = 'teacher'");
$stmt_fetch->bind_param("i", $teacher_id);
$stmt_fetch->execute();
$result = $stmt_fetch->get_result();

if ($result->num_rows == 1) {
    $teacher = $result->fetch_assoc();
} else {
    // Teacher not found
    header("Location: admin_manage_teachers.php");
    exit;
}

$stmt_fetch->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Teacher</title>
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
                <a href="admin_manage_teachers.php" class="flex items-center px-4 py-3 bg-blue-100 text-blue-600 font-semibold rounded-lg">
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
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Edit Teacher</h1>
                    <p class="text-sm text-gray-500 mt-1">Update the teacher's details, qualifications, and class assignments.</p>
                </div>
                
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

            <div class="bg-white p-6 md:p-8 rounded-xl shadow-md border border-gray-200 max-w-4xl mx-auto">
                
                <form id="detailsForm" action="admin_edit_teacher.php?id=<?php echo $teacher_id; ?>" method="POST">
                    <input type="hidden" name="teacher_id" value="<?php echo $teacher_id; ?>">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Teacher Details</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="firstName" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                            <input type="text" id="firstName" name="firstName" required
                                   value="<?php echo htmlspecialchars($teacher['first_name']); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label for="lastName" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                            <input type="text" id="lastName" name="lastName" required
                                   value="<?php echo htmlspecialchars($teacher['last_name']); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                             <input type="email" id="email" name="email" required
                                   value="<?php echo htmlspecialchars($teacher['email']); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <div class="text-center mt-4 text-sm font-medium">
                        <?php echo $message; ?>
                    </div>
                    <div class="flex justify-end mt-4">
                        <button type="submit" class="bg-blue-600 text-white py-2 px-5 rounded-lg font-semibold shadow-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            Update Details
                        </button>
                    </div>
                </form>

                <hr class="my-8">

                <form id="permissionsForm" action="#" method="POST">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Subject Qualifications (Not Functional)</h2>
                    <div class="space-y-3 border-b pb-6 mb-6">
                        <p class="text-sm text-gray-500">Select the subjects this teacher is qualified to handle.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                            <p class="text-sm text-gray-500 col-span-4">This section is not connected to the database.</p>
                        </div>
                    </div>

                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Assign Grade & Section (Not Functional)</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-500">Select the student groups this teacher will be assigned to.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                            <p class="text-sm text-gray-500 col-span-3">This section is not connected to the database.</p>
                        </div>
                    </div>

                    <div id="formMessage" class="text-center mt-6 text-sm font-medium">
                         </div>

                    <div class="flex items-center justify-end gap-4 mt-6 pt-6 border-t">
                        <a href="admin_manage_teachers.php" id="cancelButton" class="bg-gray-200 text-gray-800 py-2 px-5 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                            Cancel
                        </a>
                        <button type="submit" id="updateButton" class="bg-blue-600 text-white py-2 px-5 rounded-lg font-semibold shadow-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-75 transition duration-300" disabled>
                            Update Permissions
                        </button>
                    </div>
                </form>
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

            // Close dropdown when clicking outside
            document.addEventListener('click', (event) => {
                if (!dropdownContainer.contains(event.target)) {
                    profileMenu.classList.add('hidden');
                }
            });
        }
        
        // --- Your original JS is left intact ---
        // --- Form Population Logic (JavaScript Fallback) ---
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('detailsForm');
            const params = new URLSearchParams(window.location.search);

            const userName = params.get('name');
            const userEmail = params.get('email');

            // Only fill if the value is not already set (by PHP)
            if (userName && !form.firstName.value) {
                const nameParts = userName.split(' ');
                form.firstName.value = nameParts[0] || '';
                form.lastName.value = nameParts.slice(1).join(' ') || '';
            }
            if (userEmail && !form.email.value) {
                form.email.value = userEmail;
            }
        });
    </script>
</body>
</html>