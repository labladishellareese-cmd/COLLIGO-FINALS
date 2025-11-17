<?php
// 1. INCLUDE THE DATABASE CONNECTION AND START SESSION
require_once 'db_connect.php';

// 2. SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// 3. GET THE USER ID FROM THE URL
if (isset($_GET['user_id']) && filter_var($_GET['user_id'], FILTER_VALIDATE_INT)) {
    
    $user_id_to_approve = $_GET['user_id'];
    
    // 4. PREPARE AND EXECUTE THE UPDATE QUERY
    $stmt = $conn->prepare("UPDATE users SET is_approved = 1 WHERE user_id = ? AND role = 'teacher'");
    $stmt->bind_param("i", $user_id_to_approve);
    $stmt->execute();
    $stmt->close();
}

// 5. REDIRECT BACK TO THE DASHBOARD
header("Location: admin_dashboard.php");
exit;
?>