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
    $student_id = $_POST['student_id'] ?? null;
    $group_id = $_POST['group_id'] ?? null;
    
    error_log("Assign student attempt - Student ID: $student_id, Group ID: $group_id");
    
    if (!$student_id || !$group_id) {
        $error = 'Both student and group must be selected';
        error_log("Assign student error: $error");
    } else {
        try {
            // First check if the student exists and is a student
            $checkStmt = $pdo->prepare("SELECT id, email FROM users WHERE id = ? AND role = 'student'");
            $checkStmt->execute([$student_id]);
            $student = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$student) {
                $error = 'Student not found or is not a student role';
                error_log("Assign student error: $error");
            } else {
                // Check if the group exists
                $groupCheckStmt = $pdo->prepare("SELECT g.id, g.name, y.id as year_id, y.name as year_name 
                    FROM groups g
                    JOIN years y ON g.year_id = y.id
                    WHERE g.id = ?");
                $groupCheckStmt->execute([$group_id]);
                $group = $groupCheckStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$group) {
                    $error = 'Group not found';
                    error_log("Assign student error: $error");
                } else {
                    // Update student's group
                    $stmt = $pdo->prepare("UPDATE users SET group_id = ? WHERE id = ? AND role = 'student'");
                    $result = $stmt->execute([$group_id, $student_id]);
                    
                    if ($result && $stmt->rowCount() > 0) {
                        $success = true;
                        error_log("Student assigned successfully - Student: {$student['email']}, Group: {$group['name']}, Year: {$group['year_name']}");
                    } else {
                        $error = 'Failed to update student. Database did not report any changes.';
                        error_log("Assign student error: $error - " . implode(", ", $stmt->errorInfo()));
                    }
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
            error_log("Assign student database error: " . $e->getMessage());
        }
    }
}

// Get all students
try {
    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE role = 'student' ORDER BY email");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching students: ' . $e->getMessage();
    error_log($error);
    $students = [];
}

// Get all groups with year info
try {
    $stmt = $pdo->prepare("
        SELECT g.id, g.name, y.name as year_name 
        FROM groups g 
        JOIN years y ON g.year_id = y.id 
        ORDER BY y.name, g.name
    ");
    $stmt->execute();
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching groups: ' . $e->getMessage();
    error_log($error);
    $groups = [];
}

// Get students with their assigned groups
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.email, g.name as group_name, y.name as year_name 
        FROM users u
        LEFT JOIN groups g ON u.group_id = g.id
        LEFT JOIN years y ON g.year_id = y.id
        WHERE u.role = 'student'
        ORDER BY u.email
    ");
    $stmt->execute();
    $assignedStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching assigned students: ' . $e->getMessage();
    error_log($error);
    $assignedStudents = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Students to Groups</title>
    <!-- Include Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Assign Students to Groups</h1>
            <a href="../admin.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">Back to Admin</a>
        </div>
        
        <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            Student assigned successfully!
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
                <h2 class="text-xl font-semibold mb-4">Assign Student to Group</h2>
                
                <form method="post" action="">
                    <div class="mb-4">
                        <label for="student_id" class="block text-sm font-medium text-gray-700 mb-1">Select Student</label>
                        <select name="student_id" id="student_id" class="w-full border-gray-300 rounded-md shadow-sm" required>
                            <option value="">-- Select Student --</option>
                            <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student['id']; ?>">
                                <?php echo htmlspecialchars($student['email']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label for="group_id" class="block text-sm font-medium text-gray-700 mb-1">Select Group</label>
                        <select name="group_id" id="group_id" class="w-full border-gray-300 rounded-md shadow-sm" required>
                            <option value="">-- Select Group --</option>
                            <?php foreach ($groups as $group): ?>
                            <option value="<?php echo $group['id']; ?>">
                                <?php echo htmlspecialchars($group['year_name'] . ' - ' . $group['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mt-6">
                        <button type="submit" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700">
                            Assign Student
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Current Assignments -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">Current Assignments</h2>
                
                <?php if (count($assignedStudents) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Student
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Group
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($assignedStudents as $student): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($student['email']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($student['group_name']): ?>
                                    <?php echo htmlspecialchars($student['year_name'] . ' - ' . $student['group_name']); ?>
                                    <?php else: ?>
                                    <span class="text-yellow-500">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-gray-500">No students found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 