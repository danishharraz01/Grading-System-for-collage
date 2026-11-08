<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$errors = [];

if (is_post()) {
    verify_csrf();

    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    $st = db()->prepare('SELECT password FROM users WHERE id = :id');
    $st->execute([':id' => $user['id']]);
    $hash = $st->fetchColumn();

    if ($current === '') {
        $errors['current_password'] = 'Enter your current password.';
    } elseif (!$hash || !password_verify($current, $hash)) {
        $errors['current_password'] = 'Current password is incorrect.';
    }

    if (strlen($new) < 8) {
        $errors['new_password'] = 'New password must be at least 8 characters.';
    } elseif (strlen($new) > 72) {
        $errors['new_password'] = 'New password must be 72 characters or fewer.';
    } elseif ($current !== '' && hash_equals($current, $new)) {
        $errors['new_password'] = 'New password must be different from the current one.';
    }

    if ($confirm !== $new) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (!$errors) {
        db()->prepare('UPDATE users SET password = :p WHERE id = :id')
            ->execute([':p' => password_hash($new, PASSWORD_DEFAULT), ':id' => $user['id']]);
        session_regenerate_id(true);
        flash('success', 'Your password has been changed.');
        redirect('index.php');
    }
}

function pw_field(array $errors, string $name, string $label, string $autocomplete, string $extra = ''): string
{
    $err = $errors[$name] ?? '';
    return '<div class="field">'
        . '<label for="' . e($name) . '">' . e($label) . '</label>'
        . '<input type="password" id="' . e($name) . '" name="' . e($name) . '" required autocomplete="' . e($autocomplete) . '"'
        . ' aria-describedby="' . e($name) . '-error"' . ($err ? ' aria-invalid="true"' : '') . $extra . '>'
        . '<p class="field-error" id="' . e($name) . '-error"' . ($err ? '>' . e($err) : ' hidden>') . '</p>'
        . '</div>';
}

$pageTitle = 'Change Password';
$activeNav = 'password';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Change Password</h1>
        <p class="muted">Logged in as <strong><?= e($user['username']) ?></strong></p>
    </div>
</div>

<section class="card narrow">
    <form method="post" action="change_password.php" data-validate novalidate>
        <?= csrf_field() ?>
        <?= pw_field($errors, 'current_password', 'Current password', 'current-password') ?>
        <?= pw_field($errors, 'new_password', 'New password (min 8 characters)', 'new-password', ' minlength="8" maxlength="72"') ?>
        <?= pw_field($errors, 'confirm_password', 'Confirm new password', 'new-password', ' data-match="new_password"') ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Change password</button>
            <a class="btn btn-ghost" href="index.php">Cancel</a>
        </div>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
