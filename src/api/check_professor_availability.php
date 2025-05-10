<?php
header('Content-Type: application/json');

// Allow requests from your application's domain
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Check if it's an OPTIONS request (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Function to check professor availability
function checkProfessorAvailability($professorId, $day, $time, $currentYear, $currentGroup) {
    $baseDir = "../timetable_data/";
    $files = scandir($baseDir);
    $conflicts = [];
    
    // Get all timetable JSON files
    foreach ($files as $file) {
        // Skip non-timetable files and published versions
        if (!str_starts_with($file, 'timetable_') || str_contains($file, '_published.json')) {
            continue;
        }
        
        // Extract year and group from filename
        preg_match('/timetable_(.+)_(.+)\.json/', $file, $matches);
        if (count($matches) < 3) {
            continue;
        }
        
        $year = $matches[1];
        $group = $matches[2];
        
        // Skip checking the current year/group since we're modifying it
        if ($year === $currentYear && $group === $currentGroup) {
            continue;
        }
        
        // Read the timetable file
        $timetableJson = file_get_contents($baseDir . $file);
        $timetable = json_decode($timetableJson, true);
        
        // Check if this professor is assigned to the same time slot
        if (isset($timetable['data'][$day][$time]) && 
            $timetable['data'][$day][$time] !== null && 
            isset($timetable['data'][$day][$time]['professor_id']) && 
            $timetable['data'][$day][$time]['professor_id'] == $professorId) {
            
            // Found a conflict
            $conflicts[] = [
                'year' => $year,
                'group' => $group,
                'day' => $day,
                'time' => $time,
                'subject' => $timetable['data'][$day][$time]['subject'] ?? 'Unknown subject',
                'professor' => $timetable['data'][$day][$time]['professor'] ?? 'Unknown professor'
            ];
        }
    }
    
    return $conflicts;
}

// Process request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Check if all required fields are present
    if (!isset($data['professor_id']) || !isset($data['day']) || !isset($data['time']) || !isset($data['year']) || !isset($data['group'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields'
        ]);
        exit;
    }
    
    // Check availability
    $conflicts = checkProfessorAvailability(
        $data['professor_id'],
        $data['day'],
        $data['time'],
        $data['year'],
        $data['group']
    );
    
    // Return response
    echo json_encode([
        'success' => true,
        'available' => empty($conflicts),
        'conflicts' => $conflicts
    ]);
} else {
    // Invalid request method
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?> 