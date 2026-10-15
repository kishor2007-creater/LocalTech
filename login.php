<?php
require __DIR__ . '/includes/bootstrap.php'; require __DIR__ . '/includes/layout.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { check_csrf();
    $u = db()->users->findOne(['email' => strtolower(trim($_POST['email'] ?? ''))]);
    if (!$u || !password_verify($_POST['password'] ?? '', $u['password'])) $err = 'Email or password is incorrect.';
    elseif (($u['status'] ?? 'active') === 'blocked') $err = 'This account has been blocked. Contact the administrator.';
    else { session_regenerate_id(true); $_SESSION['user'] = ['id' => (string)$u['_id'], 'name' => $u['name'], 'role' => $u['role']];
        log_event('login'); redirect(['admin' => '/admin/', 'shop' => '/shop/'][$u['role']] ?? '/'); } }
head('Log in'); ?>
<div class="auth"><h1>Welcome back</h1><?php if ($err): ?><p class="error"><?= e($err) ?></p><?php endif; ?>
<form method="post"><?= csrf_field() ?><label>Email<input type="email" name="email" required></label><label>Password<input type="password" name="password" required></label><button class="btn block">Log in</button></form>
<p class="muted">New here? <a href="/register.php">Create an account</a></p></div><?php foot();
