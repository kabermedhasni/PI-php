<?php
// Start the session
session_start();
require_once 'auth_helper.php';

clear_remember_me_cookie();

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: ../auth.php");
exit;
?>