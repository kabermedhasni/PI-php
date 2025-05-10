<?php
session_start();
require_once '../includes/db.php';

// Basic error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Not logged in, redirect to login page
    header("Location: login.php");
    exit;
}

// Redirect based on role
if ($_SESSION['role'] === 'professor') {
    // Professors go directly to their timetable
    header("Location: timetable_view.php?role=professor");
    exit;
} elseif ($_SESSION['role'] === 'admin') {
    // Show professor selection for admins
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sélection Professeur</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Sélection Professeur - Mode Debug</h1>
            <a href="../admin/index.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                Retour au Tableau de Bord
            </a>
        </div>
        
        <p class="mb-6 text-gray-600">
            Sélectionnez un professeur pour visualiser son emploi du temps.
        </p>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php
            try {
                $stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'professor' ORDER BY name");
                $professors = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($professors as $prof) {
                    echo '<a href="timetable_view.php?professor_id=' . $prof['id'] . '" ';
                    echo 'class="block p-4 border border-gray-200 rounded hover:bg-gray-50">';
                    echo '<div class="font-medium text-blue-600">' . htmlspecialchars($prof['name']) . '</div>';
                    echo '</a>';
                }
                
                if (empty($professors)) {
                    echo '<p class="col-span-full text-center py-4">Aucun professeur trouvé</p>';
                }
            } catch (PDOException $e) {
                echo '<p class="col-span-full text-red-500">Erreur: ' . $e->getMessage() . '</p>';
            }
            ?>
        </div>
    </div>
</body>
</html>
<?php
} else {
    // Students or other roles
    header("Location: timetable_view.php");
    exit;
}
?> 