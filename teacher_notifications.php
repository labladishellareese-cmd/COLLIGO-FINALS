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
$teacher_role = ucfirst(htmlspecialchars($_SESSION['role'])); // "Teacher"

// 4. FETCH NOTIFICATIONS
$notifications = [];
$sql = "SELECT notification_id, title, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();
$conn->close();

// 5. HELPER FUNCTION to calculate "time ago"
function time_ago($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $w = floor($diff->d / 7);
    $d = $diff->d - ($w * 7);

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($k === 'w') {
            $val = $w;
        } elseif ($k === 'd') {
            $val = $d;
        } else {
            $val = $diff->$k;
        }
        if ($val) {
            $v = $val . ' ' . $v . ($val > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* Apply Inter font */
        body {
            font-family: 'Inter', sans-serif;
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
                <a href="teacher_report.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors duration-200">
                    <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" /></svg>
                    Attendance Report
                </a>
                <a href="teacher_notifications.php" class="flex items-center px-4 py-3 bg-blue-100 text-blue-600 font-semibold rounded-lg">
                    <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0M3.124 7.5A8.969 8.969 0 015.292 3m13.416 0a8.969 8.969 0 012.168 4.5" />
                    </svg>
                    Notifications
                </a>
            </nav>
        </aside>

        <main class="flex-1 p-6 lg:p-10">
            
            <header class="flex flex-col md:flex-row justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-4 md:mb-0">Notifications</h1>
                
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
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">Your Inbox</h2>
                    <a href="teacher_mark_read.php?all=true" class="text-sm font-medium text-blue-600 hover:underline">Mark All as Read</a>
                </div>

                <div class="divide-y divide-gray-200">
                    
                    <?php if (empty($notifications)): ?>
                        <div class="p-4 text-center text-gray-500">
                            You have no notifications.
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                        <div class="flex items-start gap-4 p-4 <?php if(!$notification['is_read']) echo 'bg-blue-50'; ?>">
                            <div class="flex-shrink-0">
                                <span class="flex items-center justify-center h-10 w-10 rounded-full bg-blue-100">
                                    <svg class="h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                                    </svg>
                                </span>
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between items-center">
                                    <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($notification['title']); ?></p>
                                    <span class="text-xs text-gray-500"><?php echo htmlspecialchars(time_ago($notification['created_at'])); ?></span>
                                </div>
                                <p class="mt-1 text-sm text-gray-600"><?php echo htmlspecialchars($notification['message']); ?></p>
                                <?php if(!$notification['is_read']): ?>
                                <a href="teacher_mark_read.php?id=<?php echo $notification['notification_id']; ?>" class="text-sm font-medium text-blue-600 hover:underline mt-2 inline-block">Mark as Read</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                
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