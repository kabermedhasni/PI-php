<?php
session_start();

// Redirect to timetable view for students
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    // If group_id exists and is in numeric format (e.g., 12 for Year 1 Group 2)
    if (isset($_SESSION['group_id']) && is_numeric($_SESSION['group_id']) && strlen($_SESSION['group_id']) >= 2) {
        $groupNumeric = $_SESSION['group_id'];
        $year = substr($groupNumeric, 0, 1);
        $group = substr($groupNumeric, 1, 1);
        
        // Convert to the format expected by timetable_view.php and timetable data files
        switch($year) {
            case '1':
                $yearName = "First Year";
                break;
            case '2':
                $yearName = "Second Year";
                break;
            case '3':
                $yearName = "Third Year";
                break;
            default:
                $yearName = "First Year";
        }
        
        $groupName = "G" . $group;
        
        error_log("Student redirect: Using groupID $groupNumeric as year=$yearName, group=$groupName");
        header("Location: timetable_view.php?role=student&year=$yearName&group=$groupName");
        exit;
    } else {
        // Fallback for legacy format or missing group_id
        // Default to "First Year" instead of "Y1" format
        $year_id = $_SESSION['year_id'] ?? 'First Year';
        $group_id = $_SESSION['group_id'] ?? 'G1';
        
        error_log("Student redirect (legacy): year=$year_id, group=$group_id");
        header("Location: timetable_view.php?role=student&year=$year_id&group=$group_id");
        exit;
    }
} else {
    // Not logged in or not a student, redirect to login
    header("Location: login.php?error=invalid_access");
    exit;
}
?>
