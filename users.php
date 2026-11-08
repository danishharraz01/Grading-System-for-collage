<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$values = ['username' => '', 'full_name' => ''];
$errors = [];

if (is_post()) {
    verify_csrf();

    $username = trim((string)($_POST['username'] ?? ''));
    $fullName = trim(preg_replace('/\s+/u', ' ', (string)($_POST['full_name'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm_password'] ?? '');
    $values   = ['username' => $username, 'full_name' => $fullName];

    if ($username === '') {
        $errors['username'] = 'Username is required.';
    } elseif (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $username)) {
        $errors['username'] = 'Username must be 3-30 characters: letters, digits, _ or .';
    } else {
        $st = db()->prepare('SELECT 1 FROM users WHERE username = :u');
        $st->execute([':u' => $username]);
        if ($st->fetchColumn()) $errors['username'] = 'That username is already taken.';
    }

    if ($fullName === '') {
        $errors['full_name'] = 'Full name is required.';
    } elseif (mb_strlen($fullName) > 100) {
        $errors['full_name'] = 'Full name must be 100 characters or fewer.';
    }

    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (strlen($password) > 72) {
        $errors['password'] = 'Password must be 72 characters or fewer.';
    }
    if ($confirm !== $password) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (!$errors) {
        try {
            db()->prepare('INSERT INTO users (username, full_name, password) VALUES (:u, :n, :p)')
                ->execute([':u' => $username, ':n' => $fullName, ':p' => password_hash($password, PASSWORD_DEFAULT)]);
            flash('success', 'User "' . $username . '" was created.');
            redirect('users.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() !== '23000') throw $ex;
            $errors['username'] = 'That username is already taken.';
        }
    }
}

$users = db()->query(
    'SELECT u.id, u.username, u.full_name, u.created_at, COUNT(s.id) AS entries
     FROM users u LEFT JOIN students s ON s.created_by = u.id
     GROUP BY u.id, u.username, u.full_name, u.created_at
     ORDER BY u.created_at, u.id'
)->fetchAll();

function err(array $errors, string $f): string
{
    return '<p class="field-error" id="' . e($f) . '-error"' . (isset($errors[$f]) ? '>' . e($errors[$f]) : ' hidden>') . '</p>';
}

$pageTitle = 'Users';
$activeNav = 'users';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Users</h1>
        <p class="muted">Staff accounts that can log in and manage results.</p>
    </div>
</div>

<div class="grid-2 grid-2-wide">
    <section class="card">
        <h2 class="card-title">All users (<?= count($users) ?>)</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Username</th><th>Full name</th><th class="num">Results entered</th><th>Created</th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="mono"><?= e($u['username']) ?></td>
                        <td><?= e($u['full_name']) ?></td>
                        <td class="num"><?= (int)$u['entries'] ?></td>
                        <td class="muted"><?= e(date('j M Y', strtotime($u['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2 class="card-title">Add user</h2>
        <form method="post" action="users.php" data-validate novalidate>
            <?= csrf_field() ?>
            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?= e($values['username']) ?>" required
                       maxlength="30" data-pattern="[A-Za-z0-9_.]{3,30}" autocomplete="off"
                       data-pattern-msg="Username must be 3-30 characters: letters, digits, _ or ."
                       aria-describedby="username-error"<?= isset($errors['username']) ? ' aria-invalid="true"' : '' ?>>
                <?= err($errors, 'username') ?>
            </div>
            <div class="field">
                <label for="full_name">Full name</label>
                <input type="text" id="full_name" name="full_name" value="<?= e($values['full_name']) ?>" required
                       maxlength="100" autocomplete="off"
                       aria-describedby="full_name-error"<?= isset($errors['full_name']) ? ' aria-invalid="true"' : '' ?>>
                <?= err($errors, 'full_name') ?>
            </div>
            <div class="field">
                <label for="password">Password <span class="muted">(min 8 characters)</span></label>
                <input type="password" id="password" name="password" required minlength="8" maxlength="72"
                       autocomplete="new-password"
                       aria-describedby="password-error"<?= isset($errors['password']) ? ' aria-invalid="true"' : '' ?>>
                <?= err($errors, 'password') ?>
            </div>
            <div class="field">
                <label for="confirm_password">Confirm password</label>
                <input type="password" id="confirm_password" name="confirm_password" required data-match="password"
                       autocomplete="new-password"
                       aria-describedby="confirm_password-error"<?= isset($errors['confirm_password']) ? ' aria-invalid="true"' : '' ?>>
                <?= err($errors, 'confirm_password') ?>
            </div>
            <button type="submit" class="btn btn-primary">Create user</button>
        </form>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
