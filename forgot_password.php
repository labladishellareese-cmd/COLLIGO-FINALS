<?php
// 1. INCLUDE THE DATABASE CONNECTION
require_once 'db_connect.php';

$message = '';
$is_success = false;

// 2. CHECK IF THE FORM WAS SUBMITTED
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST['email'];

    // 3. CHECK IF THIS EMAIL EXISTS IN OUR USERS TABLE
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        // --- EMAIL EXISTS, PROCEED WITH TOKEN GENERATION ---

        // 4. Delete any old tokens for this email
        $stmt_delete = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt_delete->bind_param("s", $email);
        $stmt_delete->execute();
        $stmt_delete->close();

        // 5. Generate a secure token
        $token = bin2hex(random_bytes(32));
        
        // 6. Set an expiration time (e.g., 1 hour from now)
        $expires_at = date("Y-m-d H:i:s", time() + 3600);

        // 7. Store the new token in the database
        $stmt_insert = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt_insert->bind_param("sss", $email, $token, $expires_at);
        $stmt_insert->execute();
        
        // --- SIMULATE SENDING THE EMAIL ---
        // On a real server, you would use a mail library (like PHPMailer) here
        // $reset_link = "http://yourwebsite.com/reset_password.php?token=" . $token;
        // $mail_body = "Click here to reset your password: " . $reset_link;
        // mail($email, "Password Reset Link", $mail_body);
        
        $stmt_insert->close();
    }
    
    // 8. Show a generic success message
    // We show this message whether the email existed or not.
    // This is a security feature to prevent hackers from guessing valid emails.
    $message = "If an account with that email exists, a password reset link has been sent.";
    $is_success = true;

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
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
                
                <h1 class="text-4xl font-bold text-gray-900 mb-2">Forgot Password</h1>
                <p class="text-sm text-gray-600 mb-8">Enter your email address and we'll send you a link to reset your password.</p>

                <form id="resetForm" action="forgot_password.php" method="POST">
                    <div class="mb-4">
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email ID</label>
                        <input type="email" id="email" name="email" placeholder="Enter your registered email" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
                    </div>

                    <button type="submit" id="resetButton"
                            class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg font-semibold text-center shadow-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-75 transition duration-300">
                        Send Reset Link
                    </button>

                    <div id="message" class="text-center mt-4 text-sm font-medium">
                        <?php 
                        if (!empty($message)) {
                            $text_color = $is_success ? 'text-green-600' : 'text-red-600';
                            echo "<p class='$text_color'>$message</p>";
                        }
                        ?>
                    </div>
                </form>

                <div class="text-center mt-8">
                    <p class="text-sm text-gray-600">
                        Remember your password? 
                        <a href="login.php" class="font-medium text-blue-600 hover:underline">Back to Login</a>
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