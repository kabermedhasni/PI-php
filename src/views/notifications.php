<?php
session_start();
require_once '../includes/db.php';

// Vérification si l'utilisateur est connecté et a le rôle d'administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Journaliser la tentative d'accès non autorisé
    if (isset($_SESSION['user_id'])) {
        error_log("Tentative d'accès non autorisé à notifications.php par l'utilisateur ID: " . $_SESSION['user_id']);
    } else {
        error_log("Tentative d'accès non autorisé à notifications.php (pas de session)");
    }
    
    // Redirection vers la page de connexion
    header("Location: ../views/login.php");
    exit;
}

// Get filter parameter if exists
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Récupérer les cours annulés ou reportés
try {
    // Base query for all notifications
    $query = "
        SELECT t.id, s.name as subject, t.day, t.time_slot, t.is_canceled, t.is_reschedule, 
            u.name as professor_name, y.name as year, g.name as group_name, t.room, t.professor_id
        FROM timetables t
        JOIN users u ON t.professor_id = u.id
        JOIN years y ON t.year_id = y.id
        JOIN `groups` g ON t.group_id = g.id
        JOIN subjects s ON t.subject_id = s.id
        WHERE ";
        
    if ($filter === 'canceled') {
        $query .= "t.is_canceled = 1 ";
    } elseif ($filter === 'rescheduled') {
        $query .= "t.is_reschedule = 1 ";
    } else {
        $query .= "(t.is_canceled = 1 OR t.is_reschedule = 1) ";
    }
        
    $query .= "ORDER BY t.professor_id ASC, t.day ASC, t.time_slot ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Préparer les données pour l'affichage
    $days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];
    $time_slots = [
        1 => '8h-10h',
        2 => '10h-12h',
        3 => '12h-14h',
        4 => '14h-16h',
        5 => '16h-18h',
        6 => '18h-20h'
    ];
    
    // Compter les notifications par type
    $canceledCount = 0;
    $rescheduledCount = 0;
    
    foreach ($notifications as $notification) {
        if ($notification['is_canceled'] == 1) {
            $canceledCount++;
        } 
        if ($notification['is_reschedule'] == 1) {
            $rescheduledCount++;
        }
    }
    
    // Get total counts (regardless of current filter)
    $totalStmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN is_canceled = 1 THEN 1 ELSE 0 END) as canceled_count,
            SUM(CASE WHEN is_reschedule = 1 THEN 1 ELSE 0 END) as reschedule_count
        FROM timetables
        WHERE is_canceled = 1 OR is_reschedule = 1
    ");
    $totalCounts = $totalStmt->fetch(PDO::FETCH_ASSOC);
    $totalCanceledCount = $totalCounts['canceled_count'] ?? 0;
    $totalRescheduledCount = $totalCounts['reschedule_count'] ?? 0;
    
} catch (PDOException $e) {
    error_log("Erreur de base de données dans notifications.php: " . $e->getMessage());
    $notifications = [];
    $canceledCount = 0;
    $rescheduledCount = 0;
    $totalCanceledCount = 0;
    $totalRescheduledCount = 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Cours Annulés ou Reportés</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: "Outfit", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        .notification-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
        }
        
        .notification-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .filter-pill {
            transition: all 0.2s ease;
        }
        
        .filter-pill:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gradient-to-r from-red-700 to-red-500 text-white shadow-md">
        <div class="container mx-auto px-4 py-6">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold">Notifications</h1>
                <a href="../admin/index.php" class="bg-white/20 hover:bg-white/30 text-white font-medium py-2 px-4 rounded transition duration-300 text-sm flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Retour au Tableau de Bord
                </a>
            </div>
        </div>
    </header>
    
    <main class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Cours Annulés ou Reportés</h2>
            
            <!-- Filter Pills -->
            <div class="flex flex-wrap gap-3 mb-6">
                <a href="?filter=all" class="filter-pill px-4 py-2 rounded-full border <?php echo $filter === 'all' ? 'bg-indigo-600 text-white border-indigo-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'; ?> font-medium text-sm">
                    Tous (<?php echo $totalCanceledCount + $totalRescheduledCount; ?>)
                </a>
                <a href="?filter=canceled" class="filter-pill px-4 py-2 rounded-full border <?php echo $filter === 'canceled' ? 'bg-red-600 text-white border-red-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'; ?> font-medium text-sm">
                    <span class="w-2 h-2 bg-red-500 rounded-full inline-block mr-1"></span>
                    Cours annulés (<?php echo $totalCanceledCount; ?>)
                </a>
                <a href="?filter=rescheduled" class="filter-pill px-4 py-2 rounded-full border <?php echo $filter === 'rescheduled' ? 'bg-blue-600 text-white border-blue-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'; ?> font-medium text-sm">
                    <span class="w-2 h-2 bg-blue-500 rounded-full inline-block mr-1"></span>
                    Demandes de report (<?php echo $totalRescheduledCount; ?>)
                </a>
            </div>
            
            <?php if (empty($notifications)): ?>
            <div class="text-center py-10">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <h3 class="text-xl font-medium text-gray-500">Aucune notification</h3>
                <p class="text-gray-400 mt-2">
                    <?php 
                        if ($filter === 'canceled') {
                            echo "Il n'y a actuellement aucun cours annulé";
                        } else if ($filter === 'rescheduled') {
                            echo "Il n'y a actuellement aucune demande de report";
                        } else {
                            echo "Il n'y a actuellement aucun cours annulé ou reporté";
                        }
                    ?>
                </p>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php 
                // Create separate arrays for each type of notification
                $canceledClasses = [];
                $rescheduledClasses = [];
                
                // Sort notifications by type
                foreach ($notifications as $notification) {
                    if ($filter === 'all') {
                        if ($notification['is_canceled'] == 1) {
                            $canceledClasses[] = $notification;
                        }
                        if ($notification['is_reschedule'] == 1) {
                            $rescheduledClasses[] = $notification;
                        }
                    } else {
                        // For single-type filters, just use the notification as is
                        if (($filter === 'canceled' && $notification['is_canceled'] == 1) || 
                            ($filter === 'rescheduled' && $notification['is_reschedule'] == 1)) {
                            if ($notification['is_canceled'] == 1) {
                                $canceledClasses[] = $notification;
                            } else {
                                $rescheduledClasses[] = $notification;
                            }
                        }
                    }
                }
                
                // Combine the arrays for display
                $displayNotifications = array_merge($rescheduledClasses, $canceledClasses);
                
                // Display each notification
                foreach ($displayNotifications as $notification): 
                    // Determine card styling based on status
                    if ($notification['is_reschedule'] == 1) {
                        $bgColor = 'bg-blue-50';
                        $borderColor = 'border-blue-200';
                        $iconColor = 'text-blue-600';
                        $status = 'Report';
                        $statusColor = 'bg-blue-100 text-blue-800';
                    } else if ($notification['is_canceled'] == 1) {
                        $bgColor = 'bg-red-50';
                        $borderColor = 'border-red-200';
                        $iconColor = 'text-red-600';
                        $status = 'Annulé';
                        $statusColor = 'bg-red-100 text-red-800';
                    }
                    
                    $redirectUrl = "../views/admin_timetable.php?year=" . urlencode($notification['year']) . "&group=" . urlencode($notification['group_name']);
                ?>
                    <a href="<?php echo $redirectUrl; ?>" class="notification-card <?php echo $bgColor; ?> border <?php echo $borderColor; ?> rounded-lg p-4 block">
                        <h3 class="font-semibold text-lg"><?php echo htmlspecialchars($notification['subject']); ?></h3>
                        <div class="mt-2 space-y-1">
                            <div class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?php echo $iconColor; ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <span class="text-sm"><?php echo htmlspecialchars($notification['professor_name']); ?></span>
                            </div>
                            <div class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?php echo $iconColor; ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                <span class="text-sm"><?php echo htmlspecialchars($notification['room']); ?></span>
                            </div>
                            <div class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?php echo $iconColor; ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                <span class="text-sm"><?php echo htmlspecialchars($notification['year'] . ' - ' . $notification['group_name']); ?></span>
                            </div>
                            <div class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 <?php echo $iconColor; ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="text-sm">
                                    <?php 
                                    // Make sure day and time_slot are integers and within valid range
                                    $dayIndex = (int)$notification['day'] - 1;
                                    $timeSlotIndex = (int)$notification['time_slot'];
                                    
                                    if ($dayIndex >= 0 && $dayIndex < count($days) && isset($time_slots[$timeSlotIndex])) {
                                        echo $days[$dayIndex] . ', ' . $time_slots[$timeSlotIndex];
                                    } else {
                                        echo "Horaire non spécifié";
                                    }
                                    ?>
                                </span>
                            </div>
                            <!-- Add status badge to clearly show notification type -->
                            <div class="mt-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $statusColor; ?>">
                                    <?php echo $status; ?>
                                </span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html> 