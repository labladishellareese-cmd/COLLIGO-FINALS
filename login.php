<?php
// 1. INCLUDE THE DATABASE CONNECTION
require_once 'db_connect.php';

// 2. DEFINE A VARIABLE FOR ERRORS
$error_message = '';

// 3. CHECK IF THE FORM WAS SUBMITTED
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 4. GET THE FORM DATA
    $email = $_POST['email'];
    $password = $_POST['password'];

    // 5. PREPARE THE SQL QUERY TO FIND THE USER
    // We use prepared statements to prevent SQL injection
    $stmt = $conn->prepare("SELECT user_id, email, password, role, first_name, last_name, is_approved FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    // 6. CHECK IF A USER WAS FOUND
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        // 7. VERIFY THE PASSWORD
        // We use password_verify() to check against the hashed password
        if (password_verify($password, $user['password'])) {
            
            // 8. CHECK IF THE USER IS APPROVED (for teachers)
            if ($user['role'] == 'teacher' && $user['is_approved'] == 0) {
                $error_message = "Your account is pending admin approval.";
            } else {
                // 9. PASSWORD IS CORRECT! START THE SESSION.
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];

                // 10. REDIRECT BASED ON ROLE (*** THIS IS THE CORRECTED PART ***)
                if ($user['role'] == 'admin') {
                    header("Location: admin_dashboard.php");
                } elseif ($user['role'] == 'teacher') {
                    header("Location: teacher_dashboard.php");
                } else {
                    header("Location: student_dashboard.php");
                }
                exit(); // Always exit after a header redirect
            }
        } else {
            // Invalid password
            $error_message = "Invalid email or password.";
        }
    } else {
        // No user found with that email
        $error_message = "Invalid email or password.";
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* Apply Inter font as the default sans-serif font */
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100">

    <div class="min-h-screen flex">
        
        <div class="w-full lg:w-2/5 bg-sky-50 flex items-center justify-center p-8 sm:p-12">
            <div class="w-full max-w-md">
                
                <h1 class="text-4xl font-bold text-gray-900 mb-2">Login</h1>
                <h2 class="text-xl font-semibold text-gray-700 mb-2">Welcome Back</h2>
                <p class="text-sm text-gray-600 mb-8">Please enter your Attendance credentials.</p>

                <form id="loginForm" action="login.php" method="POST">
                    <div class="mb-4">
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email ID</label>
                        <input type="email" id="email" name="email" placeholder="Enter Email ID" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
                    </div>

                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <input type="password" id="password" name="password" placeholder="Password" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
                    </div>

                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center">
                            <input id="remember-me" name="remember-me" type="checkbox"
                                   class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <label for="remember-me" class="ml-2 block text-sm text-gray-900">Remember me</label>
                        </div>
                        
                        <a href="forgot_password.php" class="text-sm text-blue-600 hover:underline">Forgot Password?</a>
                    </div>

                    <button type="submit" id="loginButton"
                            class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg font-semibold text-center shadow-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-75 transition duration-300">
                        LOGIN
                    </button>

                    <div id="message" class="text-center mt-4 text-sm font-medium">
                        <?php if(!empty($error_message)) { echo "<p class='text-red-600'>$error_message</p>"; } ?>
                    </div>
                </form>

                <div class="text-center mt-8">
                    <p class="text-sm text-gray-600">
                        Don't you have account? 
                        
                        <a href="register_page.php" class="font-medium text-blue-600 hover:underline">Register here</a>
                    </p>
                </div>
            </div>
        </div>

        <div class="hidden lg:flex w-3/5 bg-gradient-to-br from-blue-400 to-sky-600 items-center justify-center p-12 relative overflow-hidden">
            
            <div class="absolute inset-0 w-full h-full opacity-10" style="background-image: linear-gradient(45deg, rgba(255,255,255,0.1) 25%, transparent 25%, transparent 50%, rgba(255,255,255,0.1) 50%, rgba(255,255,255,0.1) 75%, transparent 75%, transparent); background-size: 50px 50px;"></div>

            <div class="z-10 bg-blue-500 bg-opacity-80 backdrop-blur-sm p-12 xl:p-16 rounded-3xl shadow-2xl text-white text-center flex flex-col items-center max-w-lg">
                <div class="text-7xl font-bold mb-6 tracking-wider" aria-label="Colligo">
                    COLLIGO
                </div>
                
                <p class="text-xl italic font-light">
                    "Attendance is the first step to success, be present to win."
                </p>
            </div>
        </div>
    </div>
    
</body>
</html>