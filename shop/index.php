<?php
require __DIR__ . '/../includes/bootstrap.php'; require __DIR__ . '/../includes/layout.php'; require_role('shop');
$shop = db()->shops->findOne(['owner_id' => oid(user()['id'])]);
if (!$shop) { flash('No shop is linked to this account.'); redirect('/'); }
$sid = $shop['_id']; $tab = $_GET['tab'] ?? 'overview';

if ($_SERVER['REQUEST_METHOD'] === 'POST') { check_csrf(); $a = $_POST['action'] ?? '';
    if ($a === 'save_product') {
        $d = ['name' => trim($_POST['name']), 'category' => $_POST['category'], 'description' => trim($_POST['description'] ?? ''),
              'price' => max(0, (float)$_POST['price']), 'stock' => max(0, (int)$_POST['stock']), 'low_threshold' => max(0, (int)($_POST['low_threshold'] ?? 5))];
        if ($d['name'] === '') { flash('Product name is required.'); redirect('/shop/?tab=inventory'); }
        if ($pid = oid($_POST['pid'] ?? '')) { db()->products->updateOne(['_id' => $pid, 'shop_id' => $sid], ['$set' => $d + ['updated' => now()]]); flash('Product updated.'); }
        else { db()->products->insertOne($d + ['shop_id' => $sid, 'created' => now(), 'updated' => now()]); flash('Product added to your inventory.'); }
        log_event('product_saved', $d['name']);
    } elseif ($a === 'quick' && ($pid = oid($_POST['pid'] ?? ''))) {
        db()->products->updateOne(['_id' => $pid, 'shop_id' => $sid], ['$set' => ['price' => max(0, (float)$_POST['price']), 'stock' => max(0, (int)$_POST['stock']), 'updated' => now()]]); flash('Price and stock updated.');
    } elseif ($a === 'delete' && ($pid = oid($_POST['pid'] ?? ''))) {
        db()->products->deleteOne(['_id' => $pid, 'shop_id' => $sid]); log_event('product_deleted', (string)$pid); flash('Product deleted.');
    } elseif ($a === 'status' && ($rid = oid($_POST['rid'] ?? ''))) {
        $to = $_POST['to'] ?? ''; $r = db()->reservations->findOne(['_id' => $rid, 'shop_id' => $sid]);
        if ($r && $to === 'cancelled') flash(cancel_reservation($rid) ? 'Reservation cancelled; stock returned.' : 'Cannot cancel this reservation.');
        elseif ($r && (($to === 'confirmed' && $r['status'] === 'pending') || ($to === 'completed' && $r['status'] === 'confirmed'))) {
            db()->reservations->updateOne(['_id' => $rid], ['$set' => ['status' => $to, 'updated' => now()]]); log_event('reservation_' . $to, $r['code']); flash('Reservation marked ' . $to . '.'); }
    }
    redirect('/shop/?tab=' . ($a === 'status' ? 'reservations' : $tab));
}
$lowQ = ['shop_id' => $sid, '$expr' => ['$lte' => ['$stock', '$low_threshold']]];
head('Shop dashboard'); ?>
<h1 class="page"><?= e($shop['name']) ?> <?= $shop['verified'] ? '<span class="badge b-completed">Verified</span>' : '<span class="badge b-pending">Awaiting verification</span>' ?></h1>
<?php if (!$shop['verified']): ?><p class="notice">Your shop is hidden from customers until an administrator verifies it. You can prepare your inventory in the meantime.</p><?php endif; ?>
<nav class="tabs"><?php foreach (['overview' => 'Overview', 'inventory' => 'Inventory', 'reservations' => 'Reservations'] as $k => $v): ?><a class="<?= $tab === $k ? 'on' : '' ?>" href="?tab=<?= $k ?>"><?= $v ?></a><?php endforeach; ?></nav>

<?php if ($tab === 'overview'):
  $val = db()->products->aggregate([['$match' => ['shop_id' => $sid]], ['$group' => ['_id' => null, 'v' => ['$sum' => ['$multiply' => ['$price', '$stock']]]]]])->toArray()[0]['v'] ?? 0;
  $low = db()->products->find($lowQ, ['sort' => ['stock' => 1]])->toArray(); ?>
  <div class="stats">
    <div class="stat"><b><?= db()->products->countDocuments(['shop_id' => $sid]) ?></b><span>products listed</span></div>
    <div class="stat warn"><b><?= count($low) ?></b><span>low or out of stock</span></div>
    <div class="stat"><b><?= db()->reservations->countDocuments(['shop_id' => $sid, 'status' => 'pending']) ?></b><span>pending reservations</span></div>
    <div class="stat"><b><?= money($val) ?></b><span>inventory value</span></div></div>
  <h2>Needs restocking</h2>
  <?php if (!$low): ?><div class="empty"><p>Every product is above its low-stock threshold.</p></div><?php else: ?>
  <div class="table-wrap"><table><tr><th>Product</th><th>Stock</th><th>Alert at</th><th>Restock</th></tr>
  <?php foreach ($low as $p): ?><tr><td><?= e($p['name']) ?></td><td><span class="stock <?= $p['stock'] ? 'low' : 'out' ?>"><?= $p['stock'] ?></span></td><td><?= $p['low_threshold'] ?></td>
  <td><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="quick"><input type="hidden" name="pid" value="<?= $p['_id'] ?>"><input type="hidden" name="price" value="<?= $p['price'] ?>"><input type="number" name="stock" value="<?= $p['stock'] + 20 ?>" min="0"><button class="btn sm">Set stock</button></form></td></tr><?php endforeach; ?></table></div><?php endif; ?>

<?php elseif ($tab === 'inventory'):
  $edit = ($eid = oid($_GET['edit'] ?? '')) ? db()->products->findOne(['_id' => $eid, 'shop_id' => $sid]) : null;
  $q = trim($_GET['q'] ?? ''); $flt = ['shop_id' => $sid]; if ($q !== '') $flt['name'] = new MongoDB\BSON\Regex(preg_quote($q), 'i');
  $items = db()->products->find($flt, ['sort' => ['name' => 1]])->toArray(); ?>
  <section class="panel"><h2><?= $edit ? 'Edit product' : 'Add a product' ?></h2>
  <form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="save_product"><input type="hidden" name="pid" value="<?= $edit['_id'] ?? '' ?>">
    <label>Name<input name="name" required value="<?= e($edit['name'] ?? '') ?>" placeholder="ESP32 DevKit V1"></label>
    <label>Category<select name="category"><?php foreach (CATEGORIES as $c): ?><option <?= ($edit['category'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></label>
    <label>Price (₹)<input type="number" step="0.01" min="0" name="price" required value="<?= e($edit['price'] ?? '') ?>"></label>
    <label>Stock<input type="number" min="0" name="stock" required value="<?= e($edit['stock'] ?? '') ?>"></label>
    <label>Low-stock alert at<input type="number" min="0" name="low_threshold" value="<?= e($edit['low_threshold'] ?? 5) ?>"></label>
    <label class="wide">Description<input name="description" value="<?= e($edit['description'] ?? '') ?>"></label>
    <div><button class="btn"><?= $edit ? 'Save changes' : 'Add product' ?></button> <?php if ($edit): ?><a class="muted" href="?tab=inventory">Cancel</a><?php endif; ?></div></form></section>
  <form class="searchbar slim" method="get"><input type="hidden" name="tab" value="inventory"><input name="q" value="<?= e($q) ?>" placeholder="Search your inventory"><button class="btn sm">Search</button></form>
  <?php if (!$items): ?><div class="empty"><h3>No products yet</h3><p>Add your first product above so nearby customers can find it.</p></div><?php else: ?>
  <div class="table-wrap"><table><tr><th>Product</th><th>Category</th><th>Price (₹)</th><th>Stock</th><th></th><th></th></tr>
  <?php foreach ($items as $p): ?><tr>
    <td><form id="q<?= $p['_id'] ?>" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="quick"><input type="hidden" name="pid" value="<?= $p['_id'] ?>"></form><b><?= e($p['name']) ?></b><?= $p['stock'] <= $p['low_threshold'] ? ' <span class="badge b-pending">low</span>' : '' ?></td><td><?= e($p['category']) ?></td>
    <td><input class="mini" form="q<?= $p['_id'] ?>" type="number" step="0.01" name="price" value="<?= $p['price'] ?>"></td><td><input class="mini" form="q<?= $p['_id'] ?>" type="number" name="stock" value="<?= $p['stock'] ?>"></td>
    <td><button class="btn sm" form="q<?= $p['_id'] ?>">Update</button></td>
    <td class="acts"><a class="btn sm ghost" href="?tab=inventory&edit=<?= $p['_id'] ?>">Edit</a>
    <form method="post" data-confirm="Delete this product?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="pid" value="<?= $p['_id'] ?>"><button class="btn sm danger">Delete</button></form></td></tr><?php endforeach; ?></table></div><?php endif; ?>

<?php else:
  $st = $_GET['st'] ?? ''; $flt = ['shop_id' => $sid]; if ($st) $flt['status'] = $st;
  $rows = db()->reservations->find($flt, ['sort' => ['created' => -1]])->toArray(); ?>
  <div class="chips"><?php foreach (['' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $k => $v): ?><a class="<?= $st === $k ? 'on' : '' ?>" href="?tab=reservations&st=<?= $k ?>"><?= $v ?></a><?php endforeach; ?></div>
  <?php if (!$rows): ?><div class="empty"><h3>No reservations here</h3><p>New reservations from customers will appear in this list.</p></div><?php else: ?>
  <div class="table-wrap"><table><tr><th>Code</th><th>Customer</th><th>Product</th><th>Qty</th><th>Total</th><th>Status</th><th>Placed</th><th>Action</th></tr>
  <?php foreach ($rows as $r): ?><tr><td><b><?= e($r['code']) ?></b></td><td><?= e($r['customer_name']) ?></td><td><?= e($r['product_name']) ?></td><td><?= $r['qty'] ?></td><td><?= money($r['total']) ?></td><td><?= badge($r['status']) ?></td><td><?= dt($r['created']) ?></td>
  <td class="acts"><?php foreach (['pending' => ['confirmed', 'Confirm'], 'confirmed' => ['completed', 'Handed over']] as $from => [$to, $lbl]) if ($r['status'] === $from): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="rid" value="<?= $r['_id'] ?>"><input type="hidden" name="to" value="<?= $to ?>"><button class="btn sm"><?= $lbl ?></button></form><?php endif;
    if (in_array($r['status'], ['pending', 'confirmed'])): ?><form method="post" data-confirm="Cancel and release stock?"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="rid" value="<?= $r['_id'] ?>"><input type="hidden" name="to" value="cancelled"><button class="btn sm danger">Cancel</button></form><?php endif; ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
<?php endif; foot();
