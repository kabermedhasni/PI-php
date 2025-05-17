<?php
// Allow cross-origin requests (if needed)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Get parameters from query parameters
$year = isset($_GET['year']) ? urldecode($_GET['year']) : null;
$group = isset($_GET['group']) ? urldecode($_GET['group']) : null;
$admin = isset($_GET['admin']) && $_GET['admin'] === 'true';
$professor_id = isset($_GET['professor_id']) ? $_GET['professor_id'] : null;

// Check if we have the required parameters
if (!$professor_id && (!$year || !$group)) {
    echo json_encode(['success' => false, 'message' => 'Either professor_id OR year and group parameters are required']);
    exit;
}

// Define data directory
$dir = '../timetable_data';

// Helper function to get subject name from ID
function getSubjectName($subject_id) {
    try {
        require_once '../includes/db.php';
        global $pdo;
        
        $stmt = $pdo->prepare("SELECT name FROM subjects WHERE id = ?");
        $stmt->execute([$subject_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['name'] : 'Unknown Subject';
    } catch (Exception $e) {
        return 'Subject #' . $subject_id;
    }
}

// Helper function to get professor name from ID
function getProfessorName($professor_id) {
    try {
        require_once '../includes/db.php';
        global $pdo;
        
        $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ? AND role = 'professor'");
        $stmt->execute([$professor_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['name'] : 'Unknown Professor';
    } catch (Exception $e) {
        return 'Professor #' . $professor_id;
    }
}

// Define file paths based on request type
if ($professor_id) {
    // Professor view - we'll need to aggregate data from multiple files
    $timetable_files = glob($dir . '/timetable_*_published.json');
    $all_timetable_data = [];
    
    // Process each file to extract courses for this professor
    foreach ($timetable_files as $file) {
        if (file_exists($file)) {
            $json = file_get_contents($file);
            $data = json_decode($json, true);
            
            if ($data && isset($data['data'])) {
                // Extract year and group from filename
                $filename = basename($file);
                preg_match('/timetable_(.+)_(.+)_published\.json/', $filename, $matches);
                
                if (count($matches) === 3) {
                    $file_year = $matches[1];
                    $file_group = $matches[2];
                    
                    // Scan through each day and time slot
                    foreach ($data['data'] as $day => $times) {
                        foreach ($times as $time => $course) {
                            if ($course && isset($course['professor_id']) && $course['professor_id'] == $professor_id) {
                                // This course belongs to the requested professor
                                // Add year and group information if not already set
                                if (!isset($course['year'])) {
                                    $course['year'] = $file_year;
                                }
                                if (!isset($course['group'])) {
                                    $course['group'] = $file_group;
                                }
                                
                                // Make sure subject and other required fields are included
                                if (!isset($course['subject']) && isset($course['subject_id'])) {
                                    $course['subject'] = getSubjectName($course['subject_id']);
                                }
                                
                                // Make sure professor name is set
                                if (!isset($course['professor']) && isset($course['professor_id'])) {
                                    $course['professor'] = getProfessorName($course['professor_id']);
                                }
                                
                                // Add to the results
                                if (!isset($all_timetable_data[$day])) {
                                    $all_timetable_data[$day] = [];
                                }
                                
                                if (!isset($all_timetable_data[$day][$time])) {
                                    $all_timetable_data[$day][$time] = [];
                                }
                                
                                // Add course to the array for this time slot
                                $all_timetable_data[$day][$time][] = $course;
                            }
                        }
                    }
                }
            }
        }
    }
    
    // Return combined data for the professor
    if (!empty($all_timetable_data)) {
        echo json_encode([
            'success' => true,
            'data' => $all_timetable_data
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No timetable entries found for this professor'
        ]);
    }
} else {
    // Regular year/group request - original logic
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
            
            // Ensure data structure has all required fields for proper rendering
            if (isset($data['data'])) {
                foreach ($data['data'] as $day => $times) {
                    foreach ($times as $time => $course) {
                        if ($course) {
                            // Make sure subject and other required fields are included
                            if (!isset($course['subject']) && isset($course['subject_id'])) {
                                // If we have subject_id but not subject name, try to get it
                                $data['data'][$day][$time]['subject'] = getSubjectName($course['subject_id']);
                            }
                            // Make sure professor name is set
                            if (!isset($course['professor']) && isset($course['professor_id'])) {
                                $data['data'][$day][$time]['professor'] = getProfessorName($course['professor_id']);
                            }
                        }
                    }
                }
            }
            
            echo json_encode($data);
        } else {
            // No published data found
            echo json_encode([
                'success' => false, 
                'message' => 'No published timetable found for ' . $year . '-' . $group
            ]);
        }
    }
} 