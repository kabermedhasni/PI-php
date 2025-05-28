<?php
session_start();
require_once '../includes/db.php';

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
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md overflow-hidden">
        <div class="w-full bg-gradient-to-tr from-purple-800 to-purple-600 p-6">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold text-white">Sélection Professeur - Mode Debug</h1>
                <a href="../admin/index.php" class="bg-white/20 hover:bg-white/30 text-white font-medium py-2 px-4 rounded transition duration-300 text-sm flex items-center border border-white/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Retour au Tableau de Bord
                </a>
            </div>
        </div>
        <div class="p-6">
        
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