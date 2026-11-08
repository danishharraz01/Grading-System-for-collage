<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare(
    'SELECT s.*, u.full_name AS entered_by
     FROM students s LEFT JOIN users u ON u.id = s.created_by
     WHERE s.id = :id'
);
$st->execute([':id' => $id]);
$s = $st->fetch();

if (!$s) {
    flash('error', 'That student record was not found.');
    redirect('index.php');
}

$rows = [
    ['Test 1', 'test1'],
    ['Test 2', 'test2'],
    ['Final Examination', 'final_exam'],
];

$pageTitle = 'Result Slip – ' . $s['matric_no'];
$activeNav = 'results';
$bodyClass = 'slip-body';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head no-print">
    <div><h1>Result Slip</h1></div>
    <div class="actions">
        <a class="btn btn-ghost" href="index.php">&larr; Back</a>
        <a class="btn" href="student_form.php?id=<?= (int)$s['id'] ?>">Edit</a>
        <button type="button" class="btn btn-primary" data-print>Print slip</button>
    </div>
</div>

<article class="card slip">
    <header class="slip-head">
        <span class="brand-mark brand-mark-lg">AK</span>
        <div>
            <h2><?= e(COLLEGE_NAME) ?></h2>
            <p class="muted">Student Result Slip</p>
        </div>
    </header>

    <dl class="slip-info">
        <div><dt>Student name</dt><dd><?= e($s['student_name']) ?></dd></div>
        <div><dt>Matric number</dt><dd class="mono"><?= e($s['matric_no']) ?></dd></div>
    </dl>

    <table class="table slip-table">
        <thead>
        <tr><th>Assessment</th><th class="num">Weightage</th><th class="num">Marks obtained</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as [$label, $field]): ?>
            <tr>
                <td><?= e($label) ?></td>
                <td class="num"><?= MARK_LIMITS[$field] ?>%</td>
                <td class="num"><?= e(fmt_mark($s[$field])) ?> / <?= MARK_LIMITS[$field] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
        <tr>
            <th>Total</th>
            <th class="num">100%</th>
            <th class="num"><?= e(fmt_mark($s['total'])) ?> / 100</th>
        </tr>
        </tfoot>
    </table>

    <div class="slip-grade">
        <span>Final grade</span>
        <span class="grade grade-xl grade-<?= e($s['grade']) ?>"><?= e($s['grade']) ?></span>
        <span class="muted"><?= $s['grade'] === 'F' ? 'Fail' : 'Pass' ?></span>
    </div>

    <p class="slip-scale muted small">
        Grading scale:
        <?php
        $parts = [];
        foreach (GRADE_SCALE as $g => [$min, $max]) $parts[] = "$min–$max $g";
        echo e(implode(' · ', $parts));
        ?>
    </p>

    <footer class="slip-foot muted small">
        <span>Issued <?= e(date('j F Y')) ?></span>
        <span>Last updated <?= e(date('j M Y, g:i a', strtotime($s['updated_at']))) ?><?= $s['entered_by'] ? ' · Entered by ' . e($s['entered_by']) : '' ?></span>
    </footer>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
