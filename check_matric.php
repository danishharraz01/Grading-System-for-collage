<?php
/**
 * AJAX endpoint used by the student form to check matric number uniqueness
 * while typing. The server re-checks on save, so this is only a convenience.
 */
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!session_is_active()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$matric  = strtoupper(trim((string)($_GET['matric'] ?? '')));
$exclude = (int)($_GET['exclude'] ?? 0);

if (!preg_match('/^[A-Z0-9\/-]{3,20}$/', $matric)) {
    echo json_encode(['available' => null]);
    exit;
}

echo json_encode(['available' => !matric_exists($matric, $exclude ?: null)]);
