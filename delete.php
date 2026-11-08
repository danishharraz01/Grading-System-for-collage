<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

if (!is_post()) {
    redirect('index.php');
}
verify_csrf();

$id = (int)($_POST['id'] ?? 0);

$st = db()->prepare('SELECT student_name, matric_no FROM students WHERE id = :id');
$st->execute([':id' => $id]);
$student = $st->fetch();

if ($student) {
    db()->prepare('DELETE FROM students WHERE id = :id')->execute([':id' => $id]);
    flash('success', 'Deleted ' . $student['student_name'] . ' (' . $student['matric_no'] . ').');
} else {
    flash('error', 'That student record was not found. It may already have been deleted.');
}
redirect('index.php');
