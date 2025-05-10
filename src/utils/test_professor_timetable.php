<?php
/**
 * Professor Timetable Testing Utility
 * 
 * This script helps check if professors' timetables are properly filtered and displayed.
 * It should be run by administrators for debugging purposes.
 */

session_start();
require_once '../includes/db.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access. This tool is only available to administrators.");
}

// Check if professor_id is provided
$professor_id = isset($_GET['professor_id']) ? intval($_GET['professor_id']) : null;

// If not, show a dropdown menu to select a professor
if (!$professor_id) {
    echo '<!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Test Emploi du Temps Professeur</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; }
            h1, h2 { color: #333; }
            select, button { padding: 8px 12px; margin: 10px 0; }
            .note { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0; }
            table { border-collapse: collapse; width: 100%; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            th { background-color: #f2f2f2; }
            .back { display: inline-block; margin-bottom: 20px; color: #0066cc; text-decoration: none; }
            .back:hover { text-decoration: underline; }
        </style>
    </head>
    <body>
        <a href="../admin/index.php" class="back">« Retour au Tableau de Bord</a>
        <h1>Test Emploi du Temps Professeur</h1>
        <p>Sélectionnez un professeur pour voir les cours qui lui sont assignés et tester la vue emploi du temps.</p>
        
        <form method="get" action="">
            <select name="professor_id" required>
                <option value="">Sélectionner un professeur</option>';
                
    try {
        $stmt = $pdo->query("SELECT id, name, email FROM users WHERE role = 'professor' ORDER BY name");
        $professors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($professors as $prof) {
            echo '<option value="'.$prof['id'].'">'.$prof['name'].' ('.$prof['email'].')</option>';
        }
    } catch (PDOException $e) {
        echo '<option>Erreur: '.$e->getMessage().'</option>';
    }
    
    echo '</select>
            <button type="submit">Tester</button>
        </form>
        
        <div class="note">
            <strong>Note:</strong> Cet outil sert à vérifier que les professeurs voient uniquement leurs propres cours.
            Pour visualiser réellement la vue professeur, utilisez le lien "Debug Vue Professeur" dans le tableau de bord admin.
        </div>
    </body>
    </html>';
    exit;
}

// If professor_id is provided, search for their courses
try {
    // First get professor info
    $profStmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ? AND role = 'professor'");
    $profStmt->execute([$professor_id]);
    $professor = $profStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$professor) {
        die("Professeur non trouvé!");
    }
    
    // Find all timetable files
    $timetable_dir = '../timetable_data';
    $files = glob($timetable_dir . '/timetable_*_published.json');
    
    // Store found courses
    $courses = [];
    
    foreach ($files as $file) {
        if (file_exists($file)) {
            // Extract year and group from filename
            $filename = basename($file);
            preg_match('/timetable_(.+)_(.+)_published\.json/', $filename, $matches);
            
            if (count($matches) === 3) {
                $year = $matches[1];
                $group = $matches[2];
                
                $json = file_get_contents($file);
                $data = json_decode($json, true);
                
                if ($data && isset($data['data'])) {
                    foreach ($data['data'] as $day => $times) {
                        foreach ($times as $time => $course) {
                            if ($course && isset($course['professor_id']) && $course['professor_id'] == $professor_id) {
                                // Add year and group to the course
                                $course['year'] = $year;
                                $course['group'] = $group;
                                $course['day'] = $day;
                                $course['time'] = $time;
                                $courses[] = $course;
                            }
                        }
                    }
                }
            }
        }
    }
    
    // Display results
    echo '<!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Cours pour ' . htmlspecialchars($professor['name']) . '</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; }
            h1, h2 { color: #333; }
            select, button { padding: 8px 12px; margin: 10px 0; }
            .note { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0; }
            .success { background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0; }
            .error { background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0; }
            table { border-collapse: collapse; width: 100%; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            th { background-color: #f2f2f2; }
            .back { display: inline-block; margin-bottom: 20px; color: #0066cc; text-decoration: none; }
            .back:hover { text-decoration: underline; }
            .actions { margin-top: 20px; }
            .button { display: inline-block; padding: 10px 15px; background: #0066cc; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px; }
            .button:hover { background: #0052a3; }
        </style>
    </head>
    <body>
        <a href="?clear=1" class="back">« Choisir un autre professeur</a>
        <h1>Cours assignés à ' . htmlspecialchars($professor['name']) . '</h1>
        <p><strong>Email:</strong> ' . htmlspecialchars($professor['email']) . ' | <strong>ID:</strong> ' . $professor_id . '</p>';
    
    if (empty($courses)) {
        echo '<div class="error">
            <strong>Aucun cours trouvé!</strong> Ce professeur n\'a pas de cours assignés dans les emplois du temps publiés.
            Assurez-vous que des emplois du temps ont été publiés et que ce professeur a des cours attribués.
        </div>';
    } else {
        echo '<div class="success">
            <strong>' . count($courses) . ' cours trouvés</strong> pour ce professeur.
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Jour</th>
                    <th>Heure</th>
                    <th>Matière</th>
                    <th>Salle</th>
                    <th>Année</th>
                    <th>Groupe</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($courses as $course) {
            echo '<tr>
                <td>' . htmlspecialchars($course['day']) . '</td>
                <td>' . htmlspecialchars($course['time']) . '</td>
                <td>' . htmlspecialchars($course['subject']) . '</td>
                <td>' . htmlspecialchars($course['room']) . '</td>
                <td>' . htmlspecialchars($course['year']) . '</td>
                <td>' . htmlspecialchars($course['group']) . '</td>
            </tr>';
        }
        
        echo '</tbody>
        </table>';
    }
    
    echo '<div class="actions">
        <a href="../views/timetable_view.php?professor_id=' . $professor_id . '" class="button">Voir l\'emploi du temps de ce professeur</a>
    </div>';
    
    // Add a test section with API call
    echo '<h2>Test d\'appel API</h2>
    <p>Résultat de l\'appel à l\'API get_timetable.php avec professor_id=' . $professor_id . ':</p>
    <div id="api-result" style="background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6; overflow: auto; max-height: 400px;">
        <pre>Chargement des données...</pre>
    </div>
    
    <script>
        // Fetch the API result
        fetch("../api/get_timetable.php?professor_id=' . $professor_id . '")
            .then(response => response.json())
            .then(data => {
                document.getElementById("api-result").innerHTML = "<pre>" + JSON.stringify(data, null, 4) + "</pre>";
            })
            .catch(error => {
                document.getElementById("api-result").innerHTML = "<pre>Erreur: " + error + "</pre>";
            });
    </script>
    </body>
    </html>';
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
} 