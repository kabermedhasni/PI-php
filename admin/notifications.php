<?php
session_start();
require_once '../core/db.php';
require_once '../core/auth_helper.php';

restore_session_from_cookie($pdo);

// Vérification si l'utilisateur est connecté et a le rôle d'administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Journaliser la tentative d'accès non autorisé
    if (isset($_SESSION['user_id'])) {
        error_log("Tentative d'accès non autorisé à notifications.php par l'utilisateur ID: " . $_SESSION['user_id']);
    } else {
        error_log("Tentative d'accès non autorisé à notifications.php (pas de session)");
    }
    
    // Redirection vers la page de connexion
    header("Location: ../auth.php");
    exit;
}

// Get filter parameter if exists
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Récupérer les cours annulés ou reportés
try {
    // Base query for all notifications: include per-professor flags and subgroup info
    $query = "
        SELECT t.id, s.name as subject, s2.name as subject2, t.day, t.time_slot, t.is_canceled, t.is_reschedule, t.is_split, t.split_type,
               t.subgroup, t.subgroup1, t.subgroup2,
               t.professor1_canceled, t.professor1_rescheduled, t.professor2_canceled, t.professor2_rescheduled,
               u1.name as professor_name, u2.name as professor2_name, y.name as year, g.name as group_name, t.room, t.room2, t.professor_id, t.professor2_id
        FROM timetables t
        LEFT JOIN users u1 ON t.professor_id = u1.id
        LEFT JOIN users u2 ON t.professor2_id = u2.id
        JOIN years y ON t.year_id = y.id
        JOIN `groups` g ON t.group_id = g.id
        JOIN subjects s ON t.subject_id = s.id
        LEFT JOIN subjects s2 ON t.subject2_id = s2.id
        WHERE ";

    if ($filter === 'canceled') {
        $query .= "(t.is_canceled = 1 OR t.professor1_canceled = 1 OR t.professor2_canceled = 1) ";
    } elseif ($filter === 'rescheduled') {
        $query .= "(t.is_reschedule = 1 OR t.professor1_rescheduled = 1 OR t.professor2_rescheduled = 1) ";
    } else {
        $query .= "(t.is_canceled = 1 OR t.is_reschedule = 1 OR t.professor1_canceled = 1 OR t.professor1_rescheduled = 1 OR t.professor2_canceled = 1 OR t.professor2_rescheduled = 1) ";
    }

    $query .= "ORDER BY t.year_id ASC, t.group_id ASC, t.day ASC, t.time_slot ASC";

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
    
    // Calcul des compteurs (annulé, report, mixte) par ligne d'emploi du temps
    $canceledOnly = 0;    // lignes avec annulation uniquement
    $rescheduledOnly = 0; // lignes avec report uniquement
    $mixedCount = 0;      // lignes avec annulation + report (mixtes)

    foreach ($notifications as $n) {
        $isSplitSameTime = !empty($n['is_split']) && (int)$n['is_split'] === 1
            && !empty($n['split_type']) && $n['split_type'] === 'same_time'
            && !empty($n['professor2_id']);

        if ($isSplitSameTime) {
            $p1_cancel = !empty($n['professor1_canceled']) && (int)$n['professor1_canceled'] === 1;
            $p2_cancel = !empty($n['professor2_canceled']) && (int)$n['professor2_canceled'] === 1;
            $p1_report = !empty($n['professor1_rescheduled']) && (int)$n['professor1_rescheduled'] === 1;
            $p2_report = !empty($n['professor2_rescheduled']) && (int)$n['professor2_rescheduled'] === 1;

            $anyCancel = $p1_cancel || $p2_cancel;
            $anyReport = $p1_report || $p2_report;

            if ($anyCancel && $anyReport) {
                $mixedCount++;
            } elseif ($anyCancel) {
                $canceledOnly++;
            } elseif ($anyReport) {
                $rescheduledOnly++;
            }
        } else {
            if (!empty($n['is_canceled']) && (int)$n['is_canceled'] === 1) {
                $canceledOnly++;
            } elseif (!empty($n['is_reschedule']) && (int)$n['is_reschedule'] === 1) {
                $rescheduledOnly++;
            }
        }
    }

    // Compteurs affichés dans les pastilles:
    // - Annulés = annulés_only + mixtes
    // - Report = report_only + mixtes
    // - Mixed = mixtes
    // - Tous = annulés_only + report_only + mixtes
    $pillCanceledCount = $canceledOnly + $mixedCount;
    $pillRescheduledCount = $rescheduledOnly + $mixedCount;
    $totalCount = $canceledOnly + $rescheduledOnly + $mixedCount;
    
} catch (PDOException $e) {
    error_log("Erreur de base de données dans notifications.php: " . $e->getMessage());
    $notifications = [];
    $canceledCount = 0;
    $rescheduledCount = 0;
    $mixedCount = 0;
    $totalCount = 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Cours Annulés ou Reportés</title>
    <link rel="icon" href="../assets/images/logo-supnum.png" />
    <link rel="stylesheet" href="../assets/css/pages/notifications.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <div class="header-container">
            <div class="header-content">
                <h1 class="header-title">Notifications</h1>
                <a href="../admin/index.php" class="back-button">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Retour au Tableau de Bord
                </a>
            </div>
        </div>
    </header>
    
    <main>
        <div class="content-card">
            <h2 class="content-title">Cours Annulés ou Reportés</h2>
            
            <!-- Filter Pills -->
            <div class="filter-container">
                <a href="?filter=all" class="filter-pill <?php echo $filter === 'all' ? 'active-all' : 'inactive'; ?>">
                    Tous (<?php echo $totalCount; ?>)
                </a>
                <a href="?filter=canceled" class="filter-pill <?php echo $filter === 'canceled' ? 'active-canceled' : 'inactive'; ?>">
                    <span class="status-dot status-dot-red"></span>
                    Cours annulés (<?php echo $pillCanceledCount; ?>)
                </a>
                <a href="?filter=rescheduled" class="filter-pill <?php echo $filter === 'rescheduled' ? 'active-rescheduled' : 'inactive'; ?>">
                    <span class="status-dot status-dot-blue"></span>
                    Demandes de report (<?php echo $pillRescheduledCount; ?>)
                </a>
                <a href="?filter=mixed" class="filter-pill <?php echo $filter === 'mixed' ? 'active-mixed' : 'inactive'; ?>">
                    <span class="status-dot status-dot-gray"></span>
                    Annulation + Report (<?php echo $mixedCount; ?>)
                </a>
            </div>
            
            <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <h3>Aucune notification</h3>
                <p>
                    <?php 
                        if ($filter === 'canceled') {
                            echo "Il n'y a actuellement aucun cours annulé";
                        } elseif ($filter === 'rescheduled') {
                            echo "Il n'y a actuellement aucune demande de report";
                        } elseif ($filter === 'mixed') {
                            echo "Il n'y a actuellement aucun cours annulé et reporté";
                        } else {
                            echo "Il n'y a actuellement aucun cours annulé ou reporté";
                        }
                    ?>
                </p>
            </div>
            <?php else: ?>
            <div class="notification-grid">
                <?php 
                // Normalize notifications into per-card entries so we can handle
                // per-professor actions on split same_time classes.
                $displayNotifications = [];

                foreach ($notifications as $notification) {
                    $isSplitSameTime = !empty($notification['is_split']) && (int)$notification['is_split'] === 1
                        && !empty($notification['split_type']) && $notification['split_type'] === 'same_time'
                        && !empty($notification['professor2_id']);

                    if ($isSplitSameTime) {
                        // Determine actions per professor
                        $prof1Action = null;
                        if (!empty($notification['professor1_canceled']) && $notification['professor1_canceled'] == 1) {
                            $prof1Action = 'cancel';
                        } elseif (!empty($notification['professor1_rescheduled']) && $notification['professor1_rescheduled'] == 1) {
                            $prof1Action = 'reschedule';
                        }

                        $prof2Action = null;
                        if (!empty($notification['professor2_canceled']) && $notification['professor2_canceled'] == 1) {
                            $prof2Action = 'cancel';
                        } elseif (!empty($notification['professor2_rescheduled']) && $notification['professor2_rescheduled'] == 1) {
                            $prof2Action = 'reschedule';
                        }

                        // If neither professor has taken an action, skip
                        if ($prof1Action === null && $prof2Action === null) {
                            continue;
                        }

                        // Case 1 & 2: only one professor acted -> one notification
                        // Cas 3: les deux agissent avec des actions différentes -> une carte grise (mixed)
                        if ($prof1Action !== null && $prof2Action === null) {
                            $card = $notification;
                            $card['subject'] = $notification['subject'];
                            $card['room'] = $notification['room'];
                            $card['is_canceled'] = ($prof1Action === 'cancel') ? 1 : 0;
                            $card['is_reschedule'] = ($prof1Action === 'reschedule') ? 1 : 0;
                            $card['professor2_canceled'] = 0;
                            $card['professor2_rescheduled'] = 0;
                            $card['both_canceled'] = 0;
                            $card['both_rescheduled'] = 0;

                            if ($filter === 'all' || ($filter === 'canceled' && $card['is_canceled'] == 1) || ($filter === 'rescheduled' && $card['is_reschedule'] == 1)) {
                                $displayNotifications[] = $card;
                            }
                        } elseif ($prof1Action === null && $prof2Action !== null) {
                            $card = $notification;
                            // Use subject2 and room2 for the second professor to avoid subject/TD mismatch
                            $card['subject'] = !empty($notification['subject2']) ? $notification['subject2'] : $notification['subject'];
                            $card['room'] = !empty($notification['room2']) ? $notification['room2'] : $notification['room'];
                            $card['is_canceled'] = ($prof2Action === 'cancel') ? 1 : 0;
                            $card['is_reschedule'] = ($prof2Action === 'reschedule') ? 1 : 0;
                            $card['professor1_canceled'] = 0;
                            $card['professor1_rescheduled'] = 0;
                            $card['both_canceled'] = 0;
                            $card['both_rescheduled'] = 0;

                            if ($filter === 'all' || ($filter === 'canceled' && $card['is_canceled'] == 1) || ($filter === 'rescheduled' && $card['is_reschedule'] == 1)) {
                                $displayNotifications[] = $card;
                            }
                        } elseif ($prof1Action !== null && $prof2Action !== null && $prof1Action !== $prof2Action) {
                            // Cas mixte : un professeur annule et l'autre reporte -> une seule carte grise
                            $card = $notification;
                            $card['subject'] = $notification['subject'];
                            $card['room'] = $notification['room'];
                            // Pour le style, on marque à la fois annulation et report
                            $card['is_canceled'] = 1;
                            $card['is_reschedule'] = 1;
                            $card['is_mixed'] = 1;
                            $card['both_canceled'] = 0;
                            $card['both_rescheduled'] = 0;

                            if ($filter === 'all' || $filter === 'mixed') {
                                $displayNotifications[] = $card;
                            }
                        } else {
                            // Both professors took the same action -> one notification mentioning both
                            $card = $notification;
                            $action = $prof1Action; // same as prof2Action
                            $card['is_canceled'] = ($action === 'cancel') ? 1 : 0;
                            $card['is_reschedule'] = ($action === 'reschedule') ? 1 : 0;
                            $card['both_canceled'] = ($action === 'cancel') ? 1 : 0;
                            $card['both_rescheduled'] = ($action === 'reschedule') ? 1 : 0;

                            if ($filter === 'all' || ($filter === 'canceled' && $card['is_canceled'] == 1) || ($filter === 'rescheduled' && $card['is_reschedule'] == 1)) {
                                $displayNotifications[] = $card;
                            }
                        }
                    } else {
                        // Non-split or not same_time: use top-level flags as before
                        if ($notification['is_canceled'] == 1) {
                            $card = $notification;
                            $card['is_reschedule'] = 0;
                            $card['both_canceled'] = 0;
                            $card['both_rescheduled'] = 0;
                            if ($filter === 'all' || $filter === 'canceled') {
                                $displayNotifications[] = $card;
                            }
                        }
                        if ($notification['is_reschedule'] == 1) {
                            $card = $notification;
                            $card['is_canceled'] = 0;
                            $card['both_canceled'] = 0;
                            $card['both_rescheduled'] = 0;
                            if ($filter === 'all' || $filter === 'rescheduled') {
                                $displayNotifications[] = $card;
                            }
                        }
                    }
                }

                // Display each notification
                foreach ($displayNotifications as $notification): 
                    // Determine card styling based on status
                    $isMixedCard = !empty($notification['is_mixed']) && $notification['is_mixed'];

                    if ($isMixedCard) {
                        // Cas mixte : carte grise + libellé Annulation + Report
                        $cardColor = 'gray';
                        $iconColor = 'gray';
                        $status = 'Annulation + Report';
                        $statusColor = 'gray';
                    } elseif ($notification['is_reschedule'] == 1) {
                        $cardColor = 'blue';
                        $iconColor = 'blue';
                        $status = 'Report';
                        $statusColor = 'blue';
                    } elseif ($notification['is_canceled'] == 1) {
                        $cardColor = 'red';
                        $iconColor = 'red';
                        $status = 'Annulé';
                        $statusColor = 'red';
                    } else {
                        $cardColor = 'gray';
                        $iconColor = 'gray';
                        $status = 'Info';
                        $statusColor = 'gray';
                    }

                    $redirectUrl = "timetable_management.php?year=" . urlencode($notification['year']) . "&group=" . urlencode($notification['group_name']);
                ?>
                    <?php $inlineStyle = $isMixedCard ? "style=\"background-color: rgb(229, 231, 235); border-color: #ccc;\"" : ""; ?>
                    <a href="<?php echo $redirectUrl; ?>" class="notification-card <?php echo $cardColor; ?>" <?php echo $inlineStyle; ?> >
                        <?php
                            // Build subject line and subgroup info for split classes
                            $subjectLine = $notification['subject'];
                            $subgroupLabel = '';

                            // When both professors cancel or both postpone on a same-time split,
                            // OR when mixed, show both subjects if they are different.
                            $isSameTimeSplit = !empty($notification['is_split']) && (int)$notification['is_split'] === 1
                                && !empty($notification['split_type']) && $notification['split_type'] === 'same_time';
                            if ($isSameTimeSplit && !empty($notification['subject2']) && $notification['subject2'] !== $notification['subject']) {
                                if (!empty($notification['both_canceled']) || !empty($notification['both_rescheduled']) || !empty($notification['is_mixed'])) {
                                    $subjectLine = $notification['subject'] . ' / ' . $notification['subject2'];
                                }
                            }

                            if (!empty($notification['is_split']) && (int)$notification['is_split'] === 1) {
                                // if same_time split and per-slot flags used, show subgroup(s) next to subject for affected slot(s)
                                if (!empty($notification['split_type']) && $notification['split_type'] === 'same_time') {
                                    $parts = [];
                                    if (!empty($notification['professor1_canceled']) && $notification['professor1_canceled'] == 1) {
                                        if (!empty($notification['subgroup1'])) $parts[] = $notification['subgroup1'];
                                        else $parts[] = '(sous-groupe 1)';
                                    }
                                    if (!empty($notification['professor2_canceled']) && $notification['professor2_canceled'] == 1) {
                                        if (!empty($notification['subgroup2'])) $parts[] = $notification['subgroup2'];
                                        else $parts[] = '(sous-groupe 2)';
                                    }
                                    if (!empty($notification['professor1_rescheduled']) && $notification['professor1_rescheduled'] == 1) {
                                        if (!empty($notification['subgroup1'])) $parts[] = $notification['subgroup1'];
                                        else $parts[] = '(sous-groupe 1)';
                                    }
                                    if (!empty($notification['professor2_rescheduled']) && $notification['professor2_rescheduled'] == 1) {
                                        if (!empty($notification['subgroup2'])) $parts[] = $notification['subgroup2'];
                                        else $parts[] = '(sous-groupe 2)';
                                    }
                                    if (!empty($parts)) {
                                        $subgroupLabel = ' - ' . implode(' / ', array_unique($parts));
                                    }
                                } else {
                                    // single-group split: show generic subgroup if provided
                                    if (!empty($notification['subgroup'])) {
                                        $subgroupLabel = ' - ' . $notification['subgroup'];
                                    }
                                }
                            }
                        ?>
                        <h3 class="notification-title"><?php echo htmlspecialchars($subjectLine . $subgroupLabel); ?></h3>
                        <div class="notification-details">
                            <div class="detail-row <?php echo $iconColor; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <span>
                                    <?php
                                        // Determine professor names involved
                                        $profList = [];
                                        // Non-split top-level actor
                                        if (empty($notification['is_split']) || (int)$notification['is_split'] === 0) {
                                            if (!empty($notification['is_canceled']) || !empty($notification['is_reschedule'])) {
                                                if (!empty($notification['professor_name'])) $profList[] = $notification['professor_name'];
                                            }
                                        } else {
                                            // split: collect per-slot actors
                                            if (!empty($notification['professor1_canceled']) && $notification['professor1_canceled'] == 1) {
                                                if (!empty($notification['professor_name'])) $profList[] = $notification['professor_name'];
                                            }
                                            if (!empty($notification['professor2_canceled']) && $notification['professor2_canceled'] == 1) {
                                                if (!empty($notification['professor2_name'])) $profList[] = $notification['professor2_name'];
                                            }
                                            if (!empty($notification['professor1_rescheduled']) && $notification['professor1_rescheduled'] == 1) {
                                                if (!empty($notification['professor_name'])) $profList[] = $notification['professor_name'];
                                            }
                                            if (!empty($notification['professor2_rescheduled']) && $notification['professor2_rescheduled'] == 1) {
                                                if (!empty($notification['professor2_name'])) $profList[] = $notification['professor2_name'];
                                            }
                                        }
                                        echo htmlspecialchars(implode(', ', array_unique($profList)));
                                    ?>
                                </span>
                            </div>
                            <div class="detail-row <?php echo $iconColor; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                <span><?php echo htmlspecialchars($notification['room']); ?></span>
                            </div>
                            <div class="detail-row <?php echo $iconColor; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0" />
                                </svg>
                                <span><?php echo htmlspecialchars($notification['year'] . ' - ' . $notification['group_name']); ?></span>
                            </div>
                            <div class="detail-row <?php echo $iconColor; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>
                                    <?php
                                        // Use the stored day and time_slot values directly for history
                                        $dayVal = $notification['day'] ?? '';
                                        $timeVal = $notification['time_slot'] ?? '';
                                        if (!empty($dayVal) || !empty($timeVal)) {
                                            echo htmlspecialchars(trim($dayVal . ($timeVal ? ', ' . $timeVal : '')));
                                        } else {
                                            echo "Horaire non spécifié";
                                        }
                                    ?>
                                </span>
                            </div>
                            <?php
                                // Show detailed per-professor actor lines
                                $canceledActors = [];
                                $rescheduledActors = [];
                                if (!empty($notification['professor1_canceled']) && $notification['professor1_canceled'] == 1) {
                                    if (!empty($notification['professor_name'])) $canceledActors[] = $notification['professor_name'];
                                }
                                if (!empty($notification['professor2_canceled']) && $notification['professor2_canceled'] == 1) {
                                    if (!empty($notification['professor2_name'])) $canceledActors[] = $notification['professor2_name'];
                                }
                                if (!empty($notification['professor1_rescheduled']) && $notification['professor1_rescheduled'] == 1) {
                                    if (!empty($notification['professor_name'])) $rescheduledActors[] = $notification['professor_name'];
                                }
                                if (!empty($notification['professor2_rescheduled']) && $notification['professor2_rescheduled'] == 1) {
                                    if (!empty($notification['professor2_name'])) $rescheduledActors[] = $notification['professor2_name'];
                                }
                            ?>
                            <?php if (!empty($canceledActors)): ?>
                            <div class="detail-row <?php echo $iconColor; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                <span>
                                    <?php
                                        $names = htmlspecialchars(implode(', ', array_unique($canceledActors)));
                                        if (!empty($notification['both_canceled']) && $notification['both_canceled']) {
                                            echo 'Annulé par les deux professeurs: ' . $names;
                                        } else {
                                            echo 'Annulé par: ' . $names;
                                        }
                                    ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($rescheduledActors)): ?>
                            <div class="detail-row <?php echo $iconColor; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="20" rx="2" ry="2"></rect>
                                    <line x1="3" y1="9" x2="21" y2="9"></line><line x1="6"  y1="2" x2="6"  y2="6"></line><line x1="10" y1="2" x2="10" y2="6"></line><line x1="14" y1="2" x2="14" y2="6"></line><line x1="18" y1="2" x2="18" y2="6"></line><g transform="translate(12 16) scale(0.42) translate(-12 -12)" fill="none" stroke="currentColor" stroke-width="3"><path d="M4,13 C4,17.4183 7.58172,21 12,21 C16.4183,21 20,17.4183 20,13 C20,8.58172 16.4183,5 12,5 C10.4407,5 8.98566,5.44609 7.75543,6.21762" /><path d="M9.2384,1.89795 L7.49856,5.83917 C7.27552,6.34441 7.50429,6.9348 8.00954,7.15784 L11.9508,8.89768" /></g>
                                </svg>
                                <span>
                                    <?php
                                        $names = htmlspecialchars(implode(', ', array_unique($rescheduledActors)));
                                        if (!empty($notification['both_rescheduled']) && $notification['both_rescheduled']) {
                                            echo 'Report demandé par les deux professeurs: ' . $names;
                                        } else {
                                            echo 'Report demandé par: ' . $names;
                                        }
                                    ?>
                                </span>
                            </div>
                            <?php endif; ?>

                            <!-- Add status badge to clearly show notification type -->
                            <span class="status-badge <?php echo $statusColor; ?>">
                                <?php echo $status; ?>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html> 