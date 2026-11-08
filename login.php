<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    if (session_is_active()) {
        redirect('index.php');
    }
    // Stale (timed-out) session: clear it before showing the form.
    logout_user();
    session_start();
}

$error    = '';
$username = '';

if (is_post()) {
    verify_csrf();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter both your username and password.';
    } else {
        $st = db()->prepare('SELECT id, username, full_name, password FROM users WHERE username = :u LIMIT 1');
        $st->execute([':u' => $username]);
        $user = $st->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                $up = db()->prepare('UPDATE users SET password = :p WHERE id = :id');
                $up->execute([':p' => password_hash($password, PASSWORD_DEFAULT), ':id' => $user['id']]);
            }
            login_user($user);
            flash('success', 'Welcome back, ' . $user['full_name'] . '.');
            redirect('index.php');
        }
        // Same message whether the username or the password was wrong.
        $error = 'Invalid username or password.';
    }
}

$pageTitle = 'Log in';
$bodyClass = 'auth-page';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-card card">
    <div class="auth-head">
        <span class="brand-mark brand-mark-lg">AK</span>
        <h1><?= e(COLLEGE_NAME) ?></h1>
        <p class="muted">Grading System &middot; Staff login</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="login.php" data-validate novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?= e($username) ?>"
                   required maxlength="50" autocomplete="username" autofocus>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Log in</button>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
