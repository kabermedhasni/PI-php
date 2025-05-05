<?php
session_start();

// Redirect to timetable view for professors
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'professor') {
    header("Location: timetable_view.php?role=professor");
    exit;
} else {
    // Not logged in or not a professor, redirect to login
    header("Location: login.php?error=invalid_access");
    exit;
}
?> 