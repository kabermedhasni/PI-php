<?php
// Allow cross-origin requests (if needed)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Get parameters from query parameters
$year = isset($_GET['year']) ? $_GET['year'] : null;
$group = isset($_GET['group']) ? $_GET['group'] : null;
$admin = isset($_GET['admin']) && $_GET['admin'] === 'true';

// Check if we have the required parameters
if (!$year || !$group) {
    echo json_encode(['success' => false, 'message' => 'Year and group parameters are required']);
    exit;
}

// Define data directory
$dir = '../timetable_data';

// Define file paths
$published_file = $dir . '/timetable_' . $year . '_' . $group . '_published.json';
$admin_file = $dir . '/timetable_' . $year . '_' . $group . '.json';

// For admins, try to load the admin draft file first
if ($admin) {
    if (file_exists($admin_file)) {
        // Admin version exists
        $json = file_get_contents($admin_file);
        $data = json_decode($json, true);
        
        // Check if published version also exists
        $has_published = file_exists($published_file);
        
        // Update flags
        $data['is_published'] = $has_published;
        
        // If published version exists, determine if there are draft changes
        if ($has_published && !isset($data['has_draft_changes'])) {
            // Compare data with published version if the flag isn't already set
            $published_json = file_get_contents($published_file);
            $published_data = json_decode($published_json, true);
            
            if ($published_data && isset($published_data['data']) && isset($data['data'])) {
                $has_draft_changes = json_encode($data['data']) !== json_encode($published_data['data']);
                $data['has_draft_changes'] = $has_draft_changes;
            } else {
                $data['has_draft_changes'] = true; // Cannot compare, assume changes
            }
        }
        
        echo json_encode($data);
    } elseif (file_exists($published_file)) {
        // Only published version exists
        $json = file_get_contents($published_file);
        $data = json_decode($json, true);
        $data['is_published'] = true;
        $data['has_draft_changes'] = false; // No draft changes if using published version
        echo json_encode($data);
    } else {
        // No data found
        echo json_encode([
            'success' => false, 
            'message' => 'No timetable found for ' . $year . '-' . $group
        ]);
    }
} else {
    // For students and professors, only show published timetables
    if (file_exists($published_file)) {
        $json = file_get_contents($published_file);
        $data = json_decode($json, true);
        echo json_encode($data);
    } else {
        // No published data found
        echo json_encode([
            'success' => false, 
            'message' => 'No published timetable found for ' . $year . '-' . $group
        ]);
    }
} 