<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$id      = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;

$values = ['student_name' => '', 'matric_no' => '', 'test1' => '', 'test2' => '', 'final_exam' => ''];

if ($editing) {
    $st = db()->prepare('SELECT * FROM students WHERE id = :id');
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    if (!$row) {
        flash('error', 'That student record no longer exists.');
        redirect('index.php');
    }
    $values = [
        'student_name' => $row['student_name'],
        'matric_no'    => $row['matric_no'],
        'test1'        => fmt_mark($row['test1']),
        'test2'        => fmt_mark($row['test2']),
        'final_exam'   => fmt_mark($row['final_exam']),
    ];
}

$errors = [];

if (is_post()) {
    verify_csrf();

    // Keep what the user typed so the form can be redisplayed on error.
    foreach (array_keys($values) as $k) {
        $values[$k] = trim((string)($_POST[$k] ?? ''));
    }

    [$data, $errors] = validate_student($_POST, $editing ? $id : null);

    if (!$errors) {
        $params = [
            ':name'  => $data['student_name'],
            ':matric'=> $data['matric_no'],
            ':t1'    => $data['test1'],
            ':t2'    => $data['test2'],
            ':fe'    => $data['final_exam'],
            ':total' => $data['total'],
            ':grade' => $data['grade'],
        ];
        try {
            if ($editing) {
                $sql = 'UPDATE students SET student_name = :name, matric_no = :matric, test1 = :t1, test2 = :t2,
                               final_exam = :fe, total = :total, grade = :grade
                        WHERE id = :id';
                $params[':id'] = $id;
            } else {
                $sql = 'INSERT INTO students (student_name, matric_no, test1, test2, final_exam, total, grade, created_by)
                        VALUES (:name, :matric, :t1, :t2, :fe, :total, :grade, :uid)';
                $params[':uid'] = $user['id'];
            }
            db()->prepare($sql)->execute($params);

            flash('success', sprintf(
                '%s %s (%s): total %s, grade %s.',
                $editing ? 'Updated' : 'Saved',
                $data['student_name'],
                $data['matric_no'],
                fmt_mark($data['total']),
                $data['grade']
            ));
            redirect(isset($_POST['add_another']) && !$editing ? 'student_form.php' : 'index.php');
        } catch (PDOException $ex) {
            // 23000 = integrity constraint (e.g. two people saved the same matric at once).
            if ($ex->getCode() === '23000') {
                $errors['matric_no'] = 'This matric number is already registered.';
            } else {
                throw $ex;
            }
        }
    }
}

/** Render error text + aria attributes for a field. */
function field_error(array $errors, string $field): string
{
    return isset($errors[$field])
        ? '<p class="field-error" id="' . e($field) . '-error">' . e($errors[$field]) . '</p>'
        : '<p class="field-error" id="' . e($field) . '-error" hidden></p>';
}
function invalid_attr(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' aria-invalid="true"' : '';
}

$pageTitle = $editing ? 'Edit Student' : 'Add Student';
$activeNav = $editing ? 'results' : 'add';
$formAction = 'student_form.php' . ($editing ? '?id=' . $id : '');
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1><?= e($pageTitle) ?></h1>
        <p class="muted">All fields are required. Total and grade are calculated automatically.</p>
    </div>
    <div class="actions"><a class="btn btn-ghost" href="index.php">&larr; Back to results</a></div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-error" role="alert">Please fix the highlighted field<?= count($errors) > 1 ? 's' : '' ?> below.</div>
<?php endif; ?>

<div class="grid-form">
    <form class="card" method="post" action="<?= e($formAction) ?>" id="student-form"
          data-validate data-student-form data-exclude-id="<?= $editing ? $id : 0 ?>" novalidate>
        <?= csrf_field() ?>

        <h2 class="card-title">Student details</h2>
        <div class="field">
            <label for="student_name">Student name</label>
            <input type="text" id="student_name" name="student_name" value="<?= e($values['student_name']) ?>"
                   required maxlength="100" autocomplete="off"
                   data-pattern="[\p{L}][\p{L}\p{M} .'\/\-]*" data-pattern-msg="Name may only contain letters, spaces and . ' / -"
                   aria-describedby="student_name-error"<?= invalid_attr($errors, 'student_name') ?>>
            <?= field_error($errors, 'student_name') ?>
        </div>
        <div class="field">
            <label for="matric_no">Matric number</label>
            <input type="text" id="matric_no" name="matric_no" value="<?= e($values['matric_no']) ?>"
                   required maxlength="20" autocomplete="off" class="mono upper" placeholder="e.g. AK2024001"
                   data-pattern="[A-Za-z0-9\/\-]{3,20}" data-pattern-msg="Matric number must be 3-20 characters: letters, digits, / or -."
                   aria-describedby="matric_no-error"<?= invalid_attr($errors, 'matric_no') ?>>
            <?= field_error($errors, 'matric_no') ?>
        </div>

        <h2 class="card-title">Marks</h2>
        <div class="marks-grid">
            <?php foreach (MARK_LIMITS as $field => $max): ?>
                <div class="field">
                    <label for="<?= e($field) ?>"><?= e(MARK_LABELS[$field]) ?> <span class="field-hint">out of <?= $max ?> &middot; <?= $max ?>% of total</span></label>
                    <input type="number" id="<?= e($field) ?>" name="<?= e($field) ?>" value="<?= e($values[$field]) ?>"
                           required min="0" max="<?= $max ?>" step="0.01" inputmode="decimal" data-mark
                           data-label="<?= e(MARK_LABELS[$field]) ?>"
                           aria-describedby="<?= e($field) ?>-error"<?= invalid_attr($errors, $field) ?>>
                    <?= field_error($errors, $field) ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $editing ? 'Save changes' : 'Save result' ?></button>
            <?php if (!$editing): ?>
                <button type="submit" class="btn" name="add_another" value="1">Save &amp; add another</button>
            <?php endif; ?>
            <a class="btn btn-ghost" href="index.php">Cancel</a>
        </div>
    </form>

    <aside class="card preview" aria-live="polite">
        <h2 class="card-title">Running total</h2>
        <div class="preview-total">
            <span class="preview-number" id="preview-total">0</span><span class="muted"> / 100</span>
        </div>
        <div class="meter"><div class="meter-bar" id="preview-bar"></div></div>
        <div class="preview-grade">
            Grade <span class="grade grade-lg" id="preview-grade">–</span>
        </div>
        <p class="muted small" id="preview-note">Enter all three marks to see the final grade.</p>
        <table class="table table-compact">
            <tbody>
            <?php foreach (GRADE_SCALE as $g => [$min, $max]): ?>
                <tr data-scale="<?= e($g) ?>"><td><?= $min ?> – <?= $max ?></td><td><span class="grade grade-<?= e($g) ?>"><?= e($g) ?></span></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
