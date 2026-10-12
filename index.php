<?php
// Main entry point router
require_once __DIR__ . '/includes/helpers.php';

if (is_logged_in()) {
    header('Location: ' . base_url('pages/dashboard.php'));
} else {
    header('Location: ' . base_url('pages/login.php'));
}
exit;
