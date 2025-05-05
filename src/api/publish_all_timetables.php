<?php
// Allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Data directory
$dir = '../timetable_data';
if (!is_dir($dir)) {
    echo json_encode(['success' => false, 'message' => 'Data directory not found']);
    exit;
}

// Get all timetable files
$timetable_files = glob($dir . '/timetable_*_*.json');
$published_files = [];
$failed_files = [];
$timetable_count = 0;

// Filter out already published files
$admin_files = array_filter($timetable_files, function($file) {
    return strpos($file, '_published.json') === false;
});

// Publish each timetable
foreach ($admin_files as $file) {
    // Extract year and group from filename
    $filename = basename($file);
    preg_match('/timetable_(.+)_(.+)\.json/', $filename, $matches);
    
    if (count($matches) !== 3) {
        $failed_files[] = $filename;
        continue;
    }
    
    $year = $matches[1];
    $group = $matches[2];
    
    // Read the timetable data
    $timetable_data = file_get_contents($file);
    $data = json_decode($timetable_data, true);
    
    if (!$data) {
        $failed_files[] = $filename;
        continue;
    }
    
    // Create the published version
    $published_data = $data;
    $published_data['is_published'] = true;
    $published_data['has_draft_changes'] = false;
    $published_data['publish_date'] = date('Y-m-d H:i:s');
    $published_data['last_modified'] = date('Y-m-d H:i:s');
    
    // Save to published file
    $published_filename = $dir . '/timetable_' . $year . '_' . $group . '_published.json';
    $published_result = file_put_contents($published_filename, json_encode($published_data));
    
    // Also update the admin version
    $admin_result = file_put_contents($file, json_encode($published_data));
    
    if ($published_result !== false && $admin_result !== false) {
        $published_files[] = $year . '-' . $group;
        $timetable_count++;
    } else {
        $failed_files[] = $filename;
    }
}

// Return result
if ($timetable_count > 0) {
    echo json_encode([
        'success' => true,
        'message' => $timetable_count . ' timetable(s) published successfully',
        'published' => $published_files,
        'failed' => $failed_files
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No timetables were published',
        'failed' => $failed_files
    ]);
} 