<?php
/**
 * Page header. Set $pageTitle and (optionally) $activeNav / $bodyClass before including.
 */
$user      = current_user();
$activeNav = $activeNav ?? '';
$bodyClass = $bodyClass ?? '';

$navItems = [
    'results'  => ['index.php', 'Results'],
    'add'      => ['student_form.php', 'Add Student'],
    'users'    => ['users.php', 'Users'],
    'password' => ['change_password.php', 'Change Password'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($pageTitle ?? 'Home') . ' · ' . APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css?v=<?= (int)@filemtime(__DIR__ . '/../assets/style.css') ?>">
</head>
<body class="<?= e($bodyClass) ?>">
<?php if ($user): ?>
<header class="topbar">
    <div class="wrap topbar-inner">
        <a class="brand" href="index.php">
            <span class="brand-name"><?= e(COLLEGE_NAME) ?></span>
            <span class="brand-sub">Grading System</span>
        </a>
        <nav class="nav" aria-label="Main">
            <?php foreach ($navItems as $key => [$href, $label]): ?>
                <a href="<?= e($href) ?>"<?= $activeNav === $key ? ' class="active" aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="userbox">
            <span class="userbox-name" title="Logged in as <?= e($user['username']) ?>"><?= e($user['full_name']) ?></span>
            <form method="post" action="logout.php">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-ghost">Log out</button>
            </form>
        </div>
    </div>
</header>
<?php endif; ?>
<main class="wrap main">
<?php foreach (get_flashes() as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?>" role="status"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
