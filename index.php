<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$f        = student_filters($_GET);
$students = fetch_students($f);

// Summary over the whole class (not just the filtered rows).
$summary = db()->query(
    "SELECT COUNT(*) AS n, AVG(total) AS avg_total, MAX(total) AS hi, MIN(total) AS lo,
            SUM(grade <> 'F') AS passed
     FROM students"
)->fetch();
$count    = (int)$summary['n'];
$passRate = $count ? round($summary['passed'] / $count * 100, 1) : 0;

$dist = array_fill_keys(array_keys(GRADE_SCALE), 0);
foreach (db()->query('SELECT grade, COUNT(*) AS c FROM students GROUP BY grade') as $row) {
    if (isset($dist[$row['grade']])) $dist[$row['grade']] = (int)$row['c'];
}
$maxBar = max(1, max($dist));

$filtering = $f['q'] !== '' || $f['grade'] !== '';

/** Build a URL keeping the current filters, with some values overridden. */
function list_url(array $f, array $over = [], string $page = 'index.php'): string
{
    $params = array_merge($f, $over);
    $params = array_filter($params, fn($v) => $v !== '');
    return $page . ($params ? '?' . http_build_query($params) : '');
}

/** Sortable column header link. */
function sort_th(array $f, string $key, string $label, string $class = ''): string
{
    $active = $f['sort'] === $key;
    $dir    = $active && $f['dir'] === 'asc' ? 'desc' : 'asc';
    $arrow  = $active ? ($f['dir'] === 'asc' ? ' ▲' : ' ▼') : '';
    $aria   = $active ? ' aria-sort="' . ($f['dir'] === 'asc' ? 'ascending' : 'descending') . '"' : '';
    return '<th class="' . e($class) . '"' . $aria . '><a href="' . e(list_url($f, ['sort' => $key, 'dir' => $dir])) . '">'
        . e($label) . $arrow . '</a></th>';
}

$pageTitle = 'Results';
$activeNav = 'results';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Student Results</h1>
        <p class="muted">Test 1 (20%) + Test 2 (30%) + Final Examination (50%) = Total (100%)</p>
    </div>
    <div class="actions no-print">
        <a class="btn btn-primary" href="student_form.php">+ Add Student</a>
        <a class="btn" href="<?= e(list_url($f, [], 'export.php')) ?>">Export CSV</a>
        <button type="button" class="btn" data-print>Print</button>
    </div>
</div>

<section class="cards" aria-label="Summary">
    <div class="card stat">
        <span class="stat-label">Students</span>
        <span class="stat-value"><?= $count ?></span>
    </div>
    <div class="card stat">
        <span class="stat-label">Average</span>
        <span class="stat-value"><?= $count ? e(number_format((float)$summary['avg_total'], 1)) : '–' ?></span>
    </div>
    <div class="card stat">
        <span class="stat-label">Highest</span>
        <span class="stat-value"><?= $count ? e(fmt_mark($summary['hi'])) : '–' ?></span>
    </div>
    <div class="card stat">
        <span class="stat-label">Lowest</span>
        <span class="stat-value"><?= $count ? e(fmt_mark($summary['lo'])) : '–' ?></span>
    </div>
    <div class="card stat">
        <span class="stat-label">Pass rate</span>
        <span class="stat-value"><?= $count ? e($passRate) . '%' : '–' ?></span>
        <span class="stat-note"><?= (int)$summary['passed'] ?> of <?= $count ?> passed (D and above)</span>
    </div>
</section>

<section class="grid-2">
    <div class="card">
        <h2 class="card-title">Grade distribution</h2>
        <div class="chart" role="img" aria-label="Grade distribution: <?= e(implode(', ', array_map(fn($g, $c) => "$g $c", array_keys($dist), $dist))) ?>">
            <?php foreach ($dist as $g => $c): ?>
                <div class="chart-row">
                    <span class="chart-label grade grade-<?= e($g) ?>"><?= e($g) ?></span>
                    <div class="chart-track">
                        <div class="chart-bar bar-<?= e($g) ?>" style="width: <?= round($c / $maxBar * 100, 1) ?>%"></div>
                    </div>
                    <span class="chart-value">
                        <?= $c ?><?= $count ? ' <small>(' . round($c / $count * 100) . '%)</small>' : '' ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card">
        <h2 class="card-title">Grading scale</h2>
        <table class="table table-compact">
            <thead><tr><th>Total marks</th><th>Grade</th></tr></thead>
            <tbody>
            <?php foreach (GRADE_SCALE as $g => [$min, $max]): ?>
                <tr><td><?= $min ?> – <?= $max ?></td><td><span class="grade grade-<?= e($g) ?>"><?= e($g) ?></span></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <form class="filters no-print" method="get" action="index.php" role="search">
        <input type="hidden" name="sort" value="<?= e($f['sort']) ?>">
        <input type="hidden" name="dir" value="<?= e($f['dir']) ?>">
        <div class="field field-grow">
            <label for="q">Search</label>
            <input type="search" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="Name or matric number" maxlength="100">
        </div>
        <div class="field">
            <label for="grade">Grade</label>
            <select id="grade" name="grade">
                <option value="">All grades</option>
                <?php foreach (array_keys(GRADE_SCALE) as $g): ?>
                    <option value="<?= e($g) ?>"<?= $f['grade'] === $g ? ' selected' : '' ?>><?= e($g) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filters-buttons">
            <button type="submit" class="btn btn-primary">Apply</button>
            <?php if ($filtering): ?>
                <a class="btn btn-ghost" href="index.php">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <p class="muted list-count">
        Showing <?= count($students) ?> of <?= $count ?> student<?= $count === 1 ? '' : 's' ?>
        <?= $filtering ? '(filtered)' : '' ?>
    </p>

    <?php if (!$students): ?>
        <div class="empty">
            <?php if ($filtering): ?>
                No students match your search. <a href="index.php">Clear filters</a>
            <?php else: ?>
                No results yet. <a href="student_form.php">Add the first student</a>.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th class="num">#</th>
                    <?= sort_th($f, 'name', 'Student Name') ?>
                    <?= sort_th($f, 'matric', 'Matric No') ?>
                    <?= sort_th($f, 'test1', 'Test 1 /20', 'num') ?>
                    <?= sort_th($f, 'test2', 'Test 2 /30', 'num') ?>
                    <?= sort_th($f, 'final', 'Final /50', 'num') ?>
                    <?= sort_th($f, 'total', 'Total /100', 'num') ?>
                    <?= sort_th($f, 'grade', 'Grade', 'center') ?>
                    <th class="no-print right">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $i => $s): ?>
                    <tr>
                        <td class="num muted"><?= $i + 1 ?></td>
                        <td><?= e($s['student_name']) ?></td>
                        <td class="mono"><?= e($s['matric_no']) ?></td>
                        <td class="num"><?= e(fmt_mark($s['test1'])) ?></td>
                        <td class="num"><?= e(fmt_mark($s['test2'])) ?></td>
                        <td class="num"><?= e(fmt_mark($s['final_exam'])) ?></td>
                        <td class="num strong"><?= e(fmt_mark($s['total'])) ?></td>
                        <td class="center"><span class="grade grade-<?= e($s['grade']) ?>"><?= e($s['grade']) ?></span></td>
                        <td class="no-print right row-actions">
                            <a class="btn btn-sm" href="slip.php?id=<?= (int)$s['id'] ?>">Slip</a>
                            <a class="btn btn-sm" href="student_form.php?id=<?= (int)$s['id'] ?>">Edit</a>
                            <form method="post" action="delete.php"
                                  data-confirm="Delete the result for <?= e($s['student_name']) ?> (<?= e($s['matric_no']) ?>)? This cannot be undone.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
