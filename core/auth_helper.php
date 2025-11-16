<?php
if (!defined('REMEMBER_ME_SECRET')) {
    define('REMEMBER_ME_SECRET', '017133b07323584decddd947b6e69a25c8f37058e2519733aca65dbe55a91594');
}

function create_remember_me_cookie(array $user)
{
    if (!isset($user['id']) || !isset($user['password'])) {
        return;
    }

    $userId = (string)$user['id'];
    $signature = hash_hmac('sha256', $userId . '|' . $user['password'], REMEMBER_ME_SECRET);
    $value = $userId . ':' . $signature;

    setcookie('remember_me', $value, [
        'expires' => time() + 60 * 60 * 24 * 30,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clear_remember_me_cookie()
{
    if (isset($_COOKIE['remember_me'])) {
        setcookie('remember_me', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

function restore_session_from_cookie(PDO $pdo)
{
    if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
        return;
    }

    if (empty($_COOKIE['remember_me'])) {
        return;
    }

    $parts = explode(':', $_COOKIE['remember_me'], 2);
    if (count($parts) !== 2) {
        return;
    }

    $userId = $parts[0];
    $signature = $parts[1];

    try {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return;
    }

    if (!$user) {
        return;
    }

    $expected = hash_hmac('sha256', $user['id'] . '|' . $user['password'], REMEMBER_ME_SECRET);
    if (!hash_equals($expected, $signature)) {
        return;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];

    if ($user['role'] === 'student' && !empty($user['group_id'])) {
        try {
            $groupStmt = $pdo->prepare('SELECT g.name AS group_name, y.name AS year_name FROM `groups` g JOIN `years` y ON g.year_id = y.id WHERE g.id = ?');
            $groupStmt->execute([$user['group_id']]);
            $groupInfo = $groupStmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $groupInfo = false;
        }

        if ($groupInfo) {
            $_SESSION['group_id'] = $groupInfo['group_name'];
            $_SESSION['year_id'] = $groupInfo['year_name'];
        } else {
            $_SESSION['group_id'] = null;
            $_SESSION['year_id'] = null;
        }
    } else {
        $_SESSION['group_id'] = null;
        $_SESSION['year_id'] = null;
    }
}
