<?php
// Allow cross-origin requests (if needed)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Log the received data for debugging
error_log("save_timetable.php received data: " . substr($json, 0, 200) . "...");

// Validate input
if (!$data || !isset($data['year']) || !isset($data['group']) || !isset($data['data'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data received']);
    exit;
}

// Create data directory if it doesn't exist
$dir = '../timetable_data';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

// Define file paths - ensure we're using the exact year and group names
$year = $data['year'];
$group = $data['group'];
$published_file = $dir . '/timetable_' . $year . '_' . $group . '_published.json';
$admin_file = $dir . '/timetable_' . $year . '_' . $group . '.json';

// Check if a published version exists
$has_published_version = file_exists($published_file);

// Initialize the draft changes flag
$has_draft_changes = false;

// If published version exists, check if current data differs from published
if ($has_published_version) {
    $published_json = file_get_contents($published_file);
    $published_data = json_decode($published_json, true);
    
    // Compare the timetable data (ignore metadata like timestamps)
    if ($published_data && isset($published_data['data'])) {
        // Check if the data is different
        $has_draft_changes = json_encode($data['data']) !== json_encode($published_data['data']);
        error_log("save_timetable.php draft changes detected: " . ($has_draft_changes ? "YES" : "NO"));
    } else {
        // Can't compare, assume there are draft changes
        $has_draft_changes = true;
    }
}

// Create the admin-only version of the data (unpublished)
$admin_data = [
    'year' => $data['year'],
    'group' => $data['group'],
    'data' => $data['data'],
    'is_published' => false, // This is just a flag for the UI
    'has_draft_changes' => $has_draft_changes,
    'last_modified' => date('Y-m-d H:i:s')
];

// Save the admin draft version (never publish)
$result = file_put_contents($admin_file, json_encode($admin_data));

if ($result !== false) {
    $response = [
        'success' => true, 
        'message' => 'Timetable saved successfully',
        'is_published' => $has_published_version,
        'has_draft_changes' => $has_draft_changes,
        'file_path' => $admin_file
    ];
    echo json_encode($response);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save timetable']);
} 