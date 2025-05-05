<?php
// Allow cross-origin requests (if needed)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Log the received data for debugging
error_log("publish_timetable.php received data: " . substr($json, 0, 200) . "...");

// Validate input
if (!$data || !isset($data['year']) || !isset($data['group']) || !isset($data['data'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data received']);
    exit;
}

// Create the published version of the data
$published_data = [
    'year' => $data['year'],
    'group' => $data['group'],
    'data' => $data['data'],
    'is_published' => true, // Explicitly mark as published
    'has_draft_changes' => false, // Reset draft changes flag when publishing
    'publish_date' => date('Y-m-d H:i:s'),
    'last_modified' => date('Y-m-d H:i:s')
];

// Create data directory if it doesn't exist
$dir = '../timetable_data';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

// Define filenames
$published_filename = $dir . '/timetable_' . $data['year'] . '_' . $data['group'] . '_published.json';
$admin_filename = $dir . '/timetable_' . $data['year'] . '_' . $data['group'] . '.json';

// Convert to JSON once
$published_json = json_encode($published_data);

// Save to published file (for students/professors)
$published_result = file_put_contents($published_filename, $published_json);

// Also update the admin version to reflect published status
$admin_result = file_put_contents($admin_filename, $published_json);

error_log("publish_timetable.php published_result: " . ($published_result ? "success" : "failed"));
error_log("publish_timetable.php admin_result: " . ($admin_result ? "success" : "failed"));

if ($published_result !== false) {
    echo json_encode([
        'success' => true, 
        'message' => 'Timetable published successfully',
        'is_published' => true,
        'has_draft_changes' => false
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to publish timetable'
    ]);
} 