<?php
require __DIR__ . '/includes/bootstrap.php'; require_role('customer'); check_csrf();
$pid = oid($_POST['pid'] ?? ''); $qty = max(1, (int)($_POST['qty'] ?? 1));
$prod = $pid ? db()->products->findOne(['_id' => $pid]) : null;
if (!$prod) { flash('That product no longer exists.'); redirect('/'); }
// Atomic stock decrement: succeeds only if enough units remain (no overselling)
$r = db()->products->updateOne(['_id' => $pid, 'stock' => ['$gte' => $qty]], ['$inc' => ['stock' => -$qty]]);
if ($r->getModifiedCount() !== 1) { flash('Sorry, not enough stock left for that quantity.'); redirect('/'); }
$shop = db()->shops->findOne(['_id' => $prod['shop_id']]);
$code = 'LT' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
db()->reservations->insertOne([
    'code' => $code, 'product_id' => $pid, 'shop_id' => $prod['shop_id'], 'customer_id' => oid(user()['id']), 'customer_name' => user()['name'],
    'product_name' => $prod['name'], 'unit_price' => $prod['price'], 'shop_name' => $shop['name'], 'qty' => $qty, 'total' => $qty * $prod['price'],
    'status' => 'pending', 'created' => now(), 'updated' => now()]);
log_event('reservation_created', "$code · {$prod['name']} × $qty");
flash("Reserved! Show code $code at {$shop['name']} to collect.");
redirect('/my.php');
