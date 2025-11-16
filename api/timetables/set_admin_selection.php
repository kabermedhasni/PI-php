<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$year = isset($_POST['year']) ? trim($_POST['year']) : null;
$group = isset($_POST['group']) ? trim($_POST['group']) : null;

if ($year === null || $group === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Year and group are required']);
    exit;
}

$_SESSION['admin_current_year'] = $year;
$_SESSION['admin_current_group'] = $group;

echo json_encode(['success' => true]);
