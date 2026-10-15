<?php
function head($title = 'LocalTech') { $u = user(); $f = flash(); ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> · LocalTech</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@500;700;800&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css"></head><body>
<header class="nav"><div class="nav-in">
  <a class="logo" href="/"><svg width="26" height="26" viewBox="0 0 26 26" aria-hidden="true"><rect x="1" y="1" width="24" height="24" rx="6" fill="#ffb400"/><path d="M6 13h5l2-5 3 10 2-5h2" fill="none" stroke="#101820" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>LocalTech</a>
  <nav class="links"><a href="/">Find parts</a>
  <?php if (!$u): ?><a href="/login.php">Log in</a><a class="btn sm" href="/register.php">Create account</a>
  <?php else: ?>
    <?php if ($u['role'] === 'customer'): ?><a href="/my.php">My reservations</a><?php endif; ?>
    <?php if ($u['role'] === 'shop'): ?><a href="/shop/">Shop dashboard</a><?php endif; ?>
    <?php if ($u['role'] === 'admin'): ?><a href="/admin/">Admin console</a><?php endif; ?>
    <span class="chip"><?= e($u['name']) ?></span><a href="/logout.php">Log out</a>
  <?php endif; ?></nav></div></header>
<?php if ($f): ?><div class="toast" role="status"><?= e($f) ?></div><?php endif; ?>
<main class="wrap">
<?php }
function foot() { ?></main><footer class="foot">LocalTech · Find it nearby. Reserve it before it's gone.</footer><script src="/assets/app.js"></script></body></html><?php }
