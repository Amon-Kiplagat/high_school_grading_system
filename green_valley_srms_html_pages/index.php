<?php
/**
 * index.php - entry point. Sends users to the dashboard or the login page.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
redirect(is_logged_in() ? 'dashboard.php' : 'login.php');
