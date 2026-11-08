<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Logout is a POST with a CSRF token so other sites can't log users out.
if (!is_post()) {
    redirect('index.php');
}
verify_csrf();

logout_user();
session_start();
flash('info', 'You have been logged out.');
redirect('login.php');
