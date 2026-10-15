<?php
require __DIR__ . '/includes/bootstrap.php'; require __DIR__ . '/includes/layout.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { check_csrf();
    $name = trim($_POST['name'] ?? ''); $email = strtolower(trim($_POST['email'] ?? '')); $pw = $_POST['password'] ?? ''; $role = ($_POST['role'] ?? '') === 'shop' ? 'shop' : 'customer';
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Enter your name and a valid email address.';
    elseif (strlen($pw) < 6) $err = 'Password must be at least 6 characters.';
    elseif (db()->users->findOne(['email' => $email])) $err = 'An account with this email already exists.';
    elseif ($role === 'shop' && trim($_POST['shop_name'] ?? '') === '') $err = 'Enter your shop name.';
    else {
        $res = db()->users->insertOne(['name' => $name, 'email' => $email, 'password' => password_hash($pw, PASSWORD_DEFAULT), 'role' => $role, 'status' => 'active', 'created' => now()]);
        if ($role === 'shop') db()->shops->insertOne(['owner_id' => $res->getInsertedId(), 'name' => trim($_POST['shop_name']), 'address' => trim($_POST['address'] ?? ''), 'city' => trim($_POST['city'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''), 'lat' => ($_POST['slat'] ?? '') !== '' ? (float)$_POST['slat'] : null, 'lng' => ($_POST['slng'] ?? '') !== '' ? (float)$_POST['slng'] : null, 'verified' => false, 'created' => now()]);
        $_SESSION['user'] = ['id' => (string)$res->getInsertedId(), 'name' => $name, 'role' => $role]; log_event('register', $role);
        flash($role === 'shop' ? 'Shop registered. It becomes visible to customers once the admin verifies it.' : 'Account created. Happy hunting!'); redirect($role === 'shop' ? '/shop/' : '/');
    } }
head('Create account'); ?>
<div class="auth"><h1>Create your account</h1><?php if ($err): ?><p class="error"><?= e($err) ?></p><?php endif; ?>
<form method="post" id="regForm"><?= csrf_field() ?>
<div class="seg"><label><input type="radio" name="role" value="customer" checked> I'm a customer</label><label><input type="radio" name="role" value="shop"> I own a shop</label></div>
<label>Your name<input name="name" required></label><label>Email<input type="email" name="email" required></label><label>Password<input type="password" name="password" minlength="6" required></label>
<fieldset id="shopFields" hidden><legend>Shop details</legend><label>Shop name<input name="shop_name"></label><label>Address<input name="address"></label><label>City<input name="city"></label><label>Phone<input name="phone"></label>
<input type="hidden" name="slat" id="slat"><input type="hidden" name="slng" id="slng"><button type="button" class="btn ghost" id="shopLocate">Pin shop location (optional)</button></fieldset>
<button class="btn block">Create account</button></form><p class="muted">Already registered? <a href="/login.php">Log in</a></p></div><?php foot();
