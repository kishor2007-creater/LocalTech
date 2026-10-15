<?php
require __DIR__ . '/../includes/bootstrap.php'; require __DIR__ . '/../includes/layout.php'; require_role('admin');
$tab = $_GET['tab'] ?? 'dashboard';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { check_csrf(); $a = $_POST['action'] ?? ''; $id = oid($_POST['id'] ?? '');
    if ($id && $a === 'verify') { $v = $_POST['v'] === '1'; db()->shops->updateOne(['_id' => $id], ['$set' => ['verified' => $v]]); log_event($v ? 'shop_verified' : 'shop_unverified', (string)$id); flash($v ? 'Shop verified and now visible to customers.' : 'Shop hidden from customers.'); }
    elseif ($id && $a === 'block') { $b = $_POST['v'] === '1'; db()->users->updateOne(['_id' => $id, 'role' => ['$ne' => 'admin']], ['$set' => ['status' => $b ? 'blocked' : 'active']]); log_event($b ? 'user_blocked' : 'user_unblocked', (string)$id); flash($b ? 'User blocked.' : 'User unblocked.'); }
    elseif ($id && $a === 'delete_user') { $u = db()->users->findOne(['_id' => $id, 'role' => ['$ne' => 'admin']]);
        if ($u) { foreach (db()->shops->find(['owner_id' => $id]) as $s) { db()->products->deleteMany(['shop_id' => $s['_id']]); } db()->shops->deleteMany(['owner_id' => $id]); db()->users->deleteOne(['_id' => $id]); log_event('user_deleted', $u['email']); flash('User and linked shop data removed.'); } }
    elseif ($id && $a === 'delete_shop') { db()->products->deleteMany(['shop_id' => $id]); db()->shops->deleteOne(['_id' => $id]); log_event('shop_deleted', (string)$id); flash('Shop and its listings removed.'); }
    elseif ($id && $a === 'delete_product') { db()->products->deleteOne(['_id' => $id]); log_event('listing_removed', (string)$id); flash('Listing removed.'); }
    redirect('/admin/?tab=' . $tab);
}
$tabs = ['dashboard' => 'Dashboard', 'shops' => 'Shops', 'users' => 'Users', 'products' => 'Listings', 'reservations' => 'Reservations', 'activity' => 'Activity log'];
function mini_form($action, $id, $label, $cls = '', $extra = '', $confirm = '') {
    return '<form method="post" ' . ($confirm ? 'data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field() . '<input type="hidden" name="action" value="' . $action . '"><input type="hidden" name="id" value="' . $id . '">' . $extra . '<button class="btn sm ' . $cls . '">' . $label . '</button></form>';
}
head('Admin console'); ?>
<h1 class="page">Admin console</h1>
<nav class="tabs"><?php foreach ($tabs as $k => $v): ?><a class="<?= $tab === $k ? 'on' : '' ?>" href="?tab=<?= $k ?>"><?= $v ?></a><?php endforeach; ?></nav>

<?php if ($tab === 'dashboard'):
  $c = fn($col, $f = []) => db()->$col->countDocuments($f);
  $byStatus = db()->reservations->aggregate([['$group' => ['_id' => '$status', 'n' => ['$sum' => 1]]]])->toArray();
  $byCat = db()->products->aggregate([['$group' => ['_id' => '$category', 'n' => ['$sum' => 1]]], ['$sort' => ['n' => -1]]])->toArray();
  $top = db()->reservations->aggregate([['$match' => ['status' => ['$ne' => 'cancelled']]], ['$group' => ['_id' => '$product_name', 'units' => ['$sum' => '$qty']]], ['$sort' => ['units' => -1]], ['$limit' => 5]])->toArray();
  $rev = db()->reservations->aggregate([['$match' => ['status' => 'completed']], ['$group' => ['_id' => null, 't' => ['$sum' => '$total']]]])->toArray()[0]['t'] ?? 0; ?>
  <div class="stats">
    <div class="stat"><b><?= $c('users', ['role' => 'customer']) ?></b><span>customers</span></div>
    <div class="stat"><b><?= $c('shops', ['verified' => true]) ?> / <?= $c('shops') ?></b><span>shops verified</span></div>
    <div class="stat"><b><?= $c('products') ?></b><span>product listings</span></div>
    <div class="stat"><b><?= $c('reservations') ?></b><span>reservations</span></div>
    <div class="stat warn"><b><?= $c('shops', ['verified' => false]) ?></b><span>shops awaiting review</span></div>
    <div class="stat"><b><?= money($rev) ?></b><span>value picked up</span></div></div>
  <div class="two">
    <section class="panel"><h2>Reservations by status</h2><?php $mx = max(array_column($byStatus, 'n') ?: [1]); foreach ($byStatus as $r): ?>
      <div class="bar"><span><?= e($r['_id']) ?></span><i style="width:<?= round($r['n'] / $mx * 100) ?>%" class="b-<?= e($r['_id']) ?>"></i><em><?= $r['n'] ?></em></div><?php endforeach; if (!$byStatus) echo '<p class="muted">No reservations yet.</p>'; ?></section>
    <section class="panel"><h2>Listings by category</h2><?php $mx = max(array_column($byCat, 'n') ?: [1]); foreach ($byCat as $r): ?>
      <div class="bar"><span><?= e($r['_id']) ?></span><i style="width:<?= round($r['n'] / $mx * 100) ?>%"></i><em><?= $r['n'] ?></em></div><?php endforeach; ?></section>
    <section class="panel"><h2>Most reserved products</h2><?php foreach ($top as $r): ?><div class="bar"><span><?= e($r['_id']) ?></span><em><?= $r['units'] ?> units</em></div><?php endforeach; if (!$top) echo '<p class="muted">Nothing reserved yet.</p>'; ?></section>
  </div>

<?php elseif ($tab === 'shops'): $rows = db()->shops->find([], ['sort' => ['verified' => 1, 'created' => -1]])->toArray(); ?>
  <div class="table-wrap"><table><tr><th>Shop</th><th>City</th><th>Phone</th><th>Status</th><th>Products</th><th>Action</th></tr>
  <?php foreach ($rows as $s): ?><tr><td><b><?= e($s['name']) ?></b><br><span class="muted"><?= e($s['address']) ?></span></td><td><?= e($s['city']) ?></td><td><?= e($s['phone']) ?></td>
  <td><?= $s['verified'] ? badge('completed') : badge('pending') ?></td><td><?= db()->products->countDocuments(['shop_id' => $s['_id']]) ?></td>
  <td class="acts"><?= mini_form('verify', $s['_id'], $s['verified'] ? 'Unverify' : 'Verify shop', $s['verified'] ? 'ghost' : '', '<input type="hidden" name="v" value="' . ($s['verified'] ? 0 : 1) . '">') ?>
  <?= mini_form('delete_shop', $s['_id'], 'Delete', 'danger', '', 'Delete this shop and all its listings?') ?></td></tr><?php endforeach; ?></table></div>

<?php elseif ($tab === 'users'): $rows = db()->users->find([], ['sort' => ['created' => -1]])->toArray(); ?>
  <div class="table-wrap"><table><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Action</th></tr>
  <?php foreach ($rows as $u): $bl = ($u['status'] ?? 'active') === 'blocked'; ?><tr><td><?= e($u['name']) ?></td><td><?= e($u['email']) ?></td><td><?= e($u['role']) ?></td><td><?= $bl ? badge('cancelled') : badge('completed') ?></td><td><?= dt($u['created']) ?></td>
  <td class="acts"><?php if ($u['role'] !== 'admin') { echo mini_form('block', $u['_id'], $bl ? 'Unblock' : 'Block', 'ghost', '<input type="hidden" name="v" value="' . ($bl ? 0 : 1) . '">'); echo mini_form('delete_user', $u['_id'], 'Delete', 'danger', '', 'Delete this user and any shop they own?'); } ?></td></tr><?php endforeach; ?></table></div>

<?php elseif ($tab === 'products'):
  $q = trim($_GET['q'] ?? ''); $flt = $q !== '' ? ['name' => new MongoDB\BSON\Regex(preg_quote($q), 'i')] : [];
  $rows = db()->products->find($flt, ['sort' => ['created' => -1], 'limit' => 100])->toArray(); $sn = [];
  foreach (db()->shops->find() as $s) $sn[(string)$s['_id']] = $s['name']; ?>
  <form class="searchbar slim" method="get"><input type="hidden" name="tab" value="products"><input name="q" value="<?= e($q) ?>" placeholder="Search listings"><button class="btn sm">Search</button></form>
  <div class="table-wrap"><table><tr><th>Product</th><th>Shop</th><th>Category</th><th>Price</th><th>Stock</th><th></th></tr>
  <?php foreach ($rows as $p): ?><tr><td><?= e($p['name']) ?></td><td><?= e($sn[(string)$p['shop_id']] ?? '—') ?></td><td><?= e($p['category']) ?></td><td><?= money($p['price']) ?></td><td><?= $p['stock'] ?></td>
  <td><?= mini_form('delete_product', $p['_id'], 'Remove', 'danger', '', 'Remove this listing?') ?></td></tr><?php endforeach; ?></table></div>

<?php elseif ($tab === 'reservations'): $rows = db()->reservations->find([], ['sort' => ['created' => -1], 'limit' => 100])->toArray(); ?>
  <div class="table-wrap"><table><tr><th>Code</th><th>Customer</th><th>Product</th><th>Shop</th><th>Qty</th><th>Total</th><th>Status</th><th>Placed</th></tr>
  <?php foreach ($rows as $r): ?><tr><td><b><?= e($r['code']) ?></b></td><td><?= e($r['customer_name']) ?></td><td><?= e($r['product_name']) ?></td><td><?= e($r['shop_name']) ?></td><td><?= $r['qty'] ?></td><td><?= money($r['total']) ?></td><td><?= badge($r['status']) ?></td><td><?= dt($r['created']) ?></td></tr><?php endforeach; ?></table></div>

<?php else: $rows = db()->logs->find([], ['sort' => ['at' => -1], 'limit' => 100])->toArray(); ?>
  <div class="table-wrap"><table><tr><th>When</th><th>Who</th><th>Role</th><th>Action</th><th>Detail</th></tr>
  <?php foreach ($rows as $l): ?><tr><td><?= dt($l['at']) ?></td><td><?= e($l['by']) ?></td><td><?= e($l['role']) ?></td><td><?= e($l['action']) ?></td><td><?= e($l['detail']) ?></td></tr><?php endforeach; ?></table></div>
<?php endif; foot();
