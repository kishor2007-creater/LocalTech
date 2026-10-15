<?php
require __DIR__ . '/includes/bootstrap.php'; require __DIR__ . '/includes/layout.php'; require_role('customer');
$uid = oid(user()['id']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') { check_csrf(); $rid = oid($_POST['rid'] ?? '');
    $own = $rid ? db()->reservations->findOne(['_id' => $rid, 'customer_id' => $uid]) : null;
    flash($own && cancel_reservation($rid) ? 'Reservation cancelled and stock released.' : 'This reservation can no longer be cancelled.'); redirect('/my.php'); }
$rows = db()->reservations->find(['customer_id' => $uid], ['sort' => ['created' => -1]])->toArray();
head('My reservations'); ?>
<h1 class="page">My reservations</h1>
<?php if (!$rows): ?><div class="empty"><h3>Nothing reserved yet</h3><p>Search for a part and tap Reserve — the shop will hold it for you.</p><a class="btn" href="/">Find parts</a></div><?php else: ?>
<div class="table-wrap"><table><tr><th>Code</th><th>Product</th><th>Shop</th><th>Qty</th><th>Total</th><th>Status</th><th>Placed</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><b><?= e($r['code']) ?></b></td><td><?= e($r['product_name']) ?></td><td><?= e($r['shop_name']) ?></td><td><?= $r['qty'] ?></td><td><?= money($r['total']) ?></td><td><?= badge($r['status']) ?></td><td><?= dt($r['created']) ?></td>
<td><?php if (in_array($r['status'], ['pending', 'confirmed'])): ?><form method="post" data-confirm="Cancel this reservation?"><?= csrf_field() ?><input type="hidden" name="rid" value="<?= $r['_id'] ?>"><button class="btn sm danger">Cancel</button></form><?php endif; ?></td></tr><?php endforeach; ?></table></div><?php endif;
foot();
