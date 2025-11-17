<?php
require_once '../../core/db.php';

// Check if user is professor or admin
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'professor' && $_SESSION['role'] !== 'admin')) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate input
if (!$data || !isset($data['id']) || !isset($data['status'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required data (id or status)'
    ]);
    exit;
}

// Fetch the timetable entry to verify ownership (handles shared classes)
try {
    $ownerStmt = $pdo->prepare("SELECT * FROM timetables WHERE id = ?");
    $ownerStmt->execute([$data['id']]);
    $timetable = $ownerStmt->fetch(PDO::FETCH_ASSOC);
    if (!$timetable) {
        echo json_encode(['success' => false, 'message' => 'Timetable entry not found']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Ensure the user is one of the assigned professors for this class or an admin
if ($_SESSION['role'] === 'professor') {
    $uid = (int)$_SESSION['user_id'];
    $prof1 = isset($timetable['professor_id']) ? (int)$timetable['professor_id'] : 0;
    $prof2 = isset($timetable['professor2_id']) ? (int)$timetable['professor2_id'] : 0;
    if ($uid !== $prof1 && $uid !== $prof2) {
        echo json_encode([
            'success' => false,
            'message' => 'You can only update your own classes'
        ]);
        exit;
    }
}

// Validate status (must be 'cancel', 'reschedule', or 'reset')
$status = $data['status'];
if ($status !== 'cancel' && $status !== 'reschedule' && $status !== 'reset') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status. Must be "cancel", "reschedule", or "reset"'
    ]);
    exit;
}

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Determine whether this is a split same-time class that uses per-professor flags
    $isSplit = isset($timetable['is_split']) && (int)$timetable['is_split'] === 1;
    $splitType = $timetable['split_type'] ?? null;
    $hasProf2 = isset($timetable['professor2_id']) && !empty($timetable['professor2_id']);
    $usePerProfessorFlags = $isSplit && $splitType === 'same_time' && $hasProf2;

    $actingProfId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    if ($usePerProfessorFlags) {
        // Work with boolean columns: professor1_canceled, professor1_rescheduled, professor2_canceled, professor2_rescheduled
        // Determine whether the acting professor corresponds to professor1 or professor2
        $prof1 = isset($timetable['professor_id']) ? (int)$timetable['professor_id'] : 0;
        $prof2 = isset($timetable['professor2_id']) ? (int)$timetable['professor2_id'] : 0;
        $slot = null; // '1' or '2'
        if ($actingProfId === $prof1) $slot = 1;
        if ($actingProfId === $prof2) $slot = 2;

        if ($slot === null) {
            // shouldn't happen because ownership was checked earlier, but guard
            throw new PDOException('Acting professor does not match any professor slot');
        }

        if ($status === 'cancel') {
            if ($slot === 1) {
                $stmt = $pdo->prepare("UPDATE timetables SET professor1_canceled = 1, professor1_rescheduled = 0 WHERE id = ?");
            } else {
                $stmt = $pdo->prepare("UPDATE timetables SET professor2_canceled = 1, professor2_rescheduled = 0 WHERE id = ?");
            }
            $stmt->execute([$data['id']]);

        } elseif ($status === 'reschedule') {
            if ($slot === 1) {
                $stmt = $pdo->prepare("UPDATE timetables SET professor1_rescheduled = 1, professor1_canceled = 0 WHERE id = ?");
            } else {
                $stmt = $pdo->prepare("UPDATE timetables SET professor2_rescheduled = 1, professor2_canceled = 0 WHERE id = ?");
            }
            $stmt->execute([$data['id']]);

        } else { // reset by admin or professor
            if ($_SESSION['role'] === 'admin') {
                // admin clears both flags for both professors
                $stmt = $pdo->prepare("UPDATE timetables SET professor1_canceled = 0, professor1_rescheduled = 0, professor2_canceled = 0, professor2_rescheduled = 0 WHERE id = ?");
                $stmt->execute([$data['id']]);
            } else {
                // professor reset only affects their slot
                if ($slot === 1) {
                    $stmt = $pdo->prepare("UPDATE timetables SET professor1_canceled = 0, professor1_rescheduled = 0 WHERE id = ?");
                } else {
                    $stmt = $pdo->prepare("UPDATE timetables SET professor2_canceled = 0, professor2_rescheduled = 0 WHERE id = ?");
                }
                $stmt->execute([$data['id']]);
            }
        }

        // Additionally, set top-level is_canceled/is_reschedule to reflect if any professor canceled/rescheduled
        $checkStmt = $pdo->prepare("SELECT professor1_canceled, professor2_canceled, professor1_rescheduled, professor2_rescheduled FROM timetables WHERE id = ?");
        $checkStmt->execute([$data['id']]);
        $flags = $checkStmt->fetch(PDO::FETCH_ASSOC);

        $isCanceledAggregate = (((int)($flags['professor1_canceled'] ?? 0) === 1) || ((int)($flags['professor2_canceled'] ?? 0) === 1)) ? 1 : 0;
        $isRescheduleAggregate = (((int)($flags['professor1_rescheduled'] ?? 0) === 1) || ((int)($flags['professor2_rescheduled'] ?? 0) === 1)) ? 1 : 0;

        $updateAgg = $pdo->prepare("UPDATE timetables SET is_canceled = ?, is_reschedule = ? WHERE id = ?");
        $updateAgg->execute([$isCanceledAggregate, $isRescheduleAggregate, $data['id']]);

    } else {
        // Non split or not same_time: act on top-level flags
        if ($status === 'cancel') {
            $stmt = $pdo->prepare("UPDATE timetables SET is_canceled = 1, is_reschedule = 0 WHERE id = ?");
            $stmt->execute([$data['id']]);
        } elseif ($status === 'reschedule') {
            $stmt = $pdo->prepare("UPDATE timetables SET is_reschedule = 1, is_canceled = 0 WHERE id = ?");
            $stmt->execute([$data['id']]);
        } else { // reset
            if ($_SESSION['role'] === 'admin') {
                $stmt = $pdo->prepare("UPDATE timetables SET is_reschedule = 0, is_canceled = 0 WHERE id = ?");
                $stmt->execute([$data['id']]);
            } else {
                // professor resets: clear top-level only
                $stmt = $pdo->prepare("UPDATE timetables SET is_reschedule = 0, is_canceled = 0 WHERE id = ?");
                $stmt->execute([$data['id']]);
            }
        }
    }

    // Commit transaction
    $pdo->commit();

    // Determine the appropriate success message
    $message = '';
    if ($status === 'cancel') {
        $message = 'Class successfully canceled';
    } else if ($status === 'reschedule') {
        $message = 'Class successfully marked for rescheduling';
    } else { // reset
        $message = 'Class status reset successfully';
    }

    echo json_encode([
        'success' => true,
        'message' => $message
    ]);

} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
