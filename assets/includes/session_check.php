<?php
// assets/includes/session_check.php

if (!defined('SESSION_CHECK_LOADED')) {
    define('SESSION_CHECK_LOADED', true);
}

session_start();

$inactivityLimit = 30 * 60; // 30 minutes

if (!isset($_SESSION['user_id'])) {
    header('Location: /auth.php?action=login');
    exit;
}

if (isset($_SESSION['last_activity'])) {
    $elapsed = time() - $_SESSION['last_activity'];
    if ($elapsed > $inactivityLimit) {
        session_unset();
        session_destroy();
        header('Location: /auth.php?action=login&timeout=1');
        exit;
    }
}

$_SESSION['last_activity'] = time();
?>
