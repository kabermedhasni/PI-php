<?php
session_start();
require_once '../includes/db.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Handle form submission
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $professor_id = $_POST['professor_id'] ?? null;
    $subject_ids = $_POST['subject_ids'] ?? [];
    
    error_log("Assign subjects attempt - Professor ID: $professor_id, Subject IDs: " . implode(',', $subject_ids));
    
    if (!$professor_id) {
        $error = 'Professor must be selected';
        error_log("Assign subjects error: $error");
    } elseif (empty($subject_ids)) {
        $error = 'At least one subject must be selected';
        error_log("Assign subjects error: $error");
    } else {
        try {
            // Verify professor exists
            $checkStmt = $pdo->prepare("SELECT id, email FROM users WHERE id = ? AND role = 'professor'");
            $checkStmt->execute([$professor_id]);
            $professor = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$professor) {
                $error = 'Professor not found or does not have professor role';
                error_log("Assign subjects error: $error");
            } else {
                // Start transaction
                $pdo->beginTransaction();
                
                // First, remove existing assignments for this professor
                $stmt = $pdo->prepare("DELETE FROM professor_subjects WHERE professor_id = ?");
                $deleteResult = $stmt->execute([$professor_id]);
                
                if (!$deleteResult) {
                    throw new PDOException("Failed to delete existing assignments: " . implode(", ", $stmt->errorInfo()));
                }
                
                // Then add new assignments
                $stmt = $pdo->prepare("INSERT INTO professor_subjects (professor_id, subject_id) VALUES (?, ?)");
                $assignedSubjects = [];
                
                foreach ($subject_ids as $subject_id) {
                    // Verify subject exists
                    $subjectCheckStmt = $pdo->prepare("SELECT id, name FROM subjects WHERE id = ?");
                    $subjectCheckStmt->execute([$subject_id]);
                    $subject = $subjectCheckStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$subject) {
                        throw new PDOException("Subject with ID $subject_id not found");
                    }
                    
                    $insertResult = $stmt->execute([$professor_id, $subject_id]);
                    
                    if (!$insertResult) {
                        throw new PDOException("Failed to assign subject $subject_id: " . implode(", ", $stmt->errorInfo()));
                    }
                    
                    $assignedSubjects[] = $subject['name'];
                }
                
                // Commit transaction
                $pdo->commit();
                $success = true;
                error_log("Subjects assigned successfully - Professor: {$professor['email']}, Subjects: " . implode(', ', $assignedSubjects));
            }
        } catch (PDOException $e) {
            // Rollback transaction on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            $error = 'Database error: ' . $e->getMessage();
            error_log("Assign subjects database error: " . $e->getMessage());
        }
    }
}

// Get all professors
try {
    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE role = 'professor' ORDER BY email");
    $stmt->execute();
    $professors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching professors: ' . $e->getMessage();
    error_log($error);
    $professors = [];
}

// Get all subjects
try {
    $stmt = $pdo->prepare("SELECT id, name, code, color FROM subjects ORDER BY name");
    $stmt->execute();
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching subjects: ' . $e->getMessage();
    error_log($error);
    $subjects = [];
}

// Get current assignments
$assignments = [];
try {
    $stmt = $pdo->prepare("
        SELECT ps.professor_id, u.email as professor_name, 
               GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ') as subjects
        FROM professor_subjects ps
        JOIN users u ON ps.professor_id = u.id
        JOIN subjects s ON ps.subject_id = s.id
        GROUP BY ps.professor_id
        ORDER BY u.email
    ");
    $stmt->execute();
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching assignments: ' . $e->getMessage();
    error_log($error);
}

// Get subjects for a specific professor if requested
$professorSubjects = [];
if (isset($_GET['professor_id']) && $_GET['professor_id']) {
    try {
        $stmt = $pdo->prepare("
            SELECT subject_id 
            FROM professor_subjects 
            WHERE professor_id = ?
        ");
        $stmt->execute([$_GET['professor_id']]);
        $professorSubjects = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $error = 'Error fetching professor subjects: ' . $e->getMessage();
        error_log($error);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Subjects to Professors</title>
    <!-- Include Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Assign Subjects to Professors</h1>
            <a href="../admin.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">Back to Admin</a>
        </div>
        
        <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            Subjects assigned successfully!
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Assignment Form -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">Assign Subjects to Professor</h2>
                
                <form method="post" action="">
                    <div class="mb-4">
                        <label for="professor_id" class="block text-sm font-medium text-gray-700 mb-1">Select Professor</label>
                        <select name="professor_id" id="professor_id" class="w-full border-gray-300 rounded-md shadow-sm" required onchange="this.form.action='?professor_id='+this.value; this.form.submit();">
                            <option value="">-- Select Professor --</option>
                            <?php foreach ($professors as $professor): ?>
                            <option value="<?php echo $professor['id']; ?>" <?php echo (isset($_GET['professor_id']) && $_GET['professor_id'] == $professor['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($professor['email']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if (isset($_GET['professor_id']) && $_GET['professor_id']): ?>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Assign Subjects</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-60 overflow-y-auto p-2 border border-gray-200 rounded">
                            <?php foreach ($subjects as $subject): ?>
                            <div class="flex items-center">
                                <input type="checkbox" name="subject_ids[]" id="subject_<?php echo $subject['id']; ?>" 
                                       value="<?php echo $subject['id']; ?>" class="h-4 w-4 text-indigo-600 rounded"
                                       <?php echo in_array($subject['id'], $professorSubjects) ? 'checked' : ''; ?>>
                                <label for="subject_<?php echo $subject['id']; ?>" class="ml-2 text-sm text-gray-700">
                                    <span class="inline-block w-3 h-3 rounded-full mr-1" style="background-color: <?php echo htmlspecialchars($subject['color']); ?>"></span>
                                    <?php echo htmlspecialchars($subject['name']); ?> (<?php echo htmlspecialchars($subject['code']); ?>)
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <button type="submit" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700">
                            Save Assignments
                        </button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Current Assignments -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">Current Assignments</h2>
                
                <?php if (count($assignments) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Professor
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Assigned Subjects
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($assignments as $assignment): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($assignment['professor_name']); ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?php echo htmlspecialchars($assignment['subjects']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <a href="?professor_id=<?php echo $assignment['professor_id']; ?>" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-gray-500">No assignments found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 