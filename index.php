<?php
require __DIR__ . '/includes/bootstrap.php'; require __DIR__ . '/includes/layout.php';
$q = trim($_GET['q'] ?? ''); $cat = $_GET['cat'] ?? ''; $city = trim($_GET['city'] ?? ''); $max = (float)($_GET['max'] ?? 0);
$inStock = isset($_GET['stock']); $sort = $_GET['sort'] ?? 'name';
$lat = ($_GET['lat'] ?? '') !== '' ? (float)$_GET['lat'] : null; $lng = ($_GET['lng'] ?? '') !== '' ? (float)$_GET['lng'] : null;

$shops = []; $ids = [];
foreach (db()->shops->find(['verified' => true]) as $s) {
    if ($city && stripos($s['city'], $city) === false) continue;
    $shops[(string)$s['_id']] = $s; $ids[] = $s['_id'];
}
$f = ['shop_id' => ['$in' => $ids]];
if ($q !== '') { $rx = new MongoDB\BSON\Regex(preg_quote($q), 'i'); $f['$or'] = [['name' => $rx], ['description' => $rx], ['category' => $rx]]; }
if ($cat) $f['category'] = $cat;
if ($max > 0) $f['price'] = ['$lte' => $max];
if ($inStock) $f['stock'] = ['$gt' => 0];
$sortMap = ['price_asc' => ['price' => 1], 'price_desc' => ['price' => -1], 'stock' => ['stock' => -1], 'new' => ['created' => -1]];
$items = db()->products->find($f, ['sort' => $sortMap[$sort] ?? ['name' => 1], 'limit' => 60])->toArray();

$min = [];
foreach ($items as &$p) {
    $s = $shops[(string)$p['shop_id']]; $p['shop'] = $s; $p['dist'] = null;
    if ($lat !== null && isset($s['lat'])) $p['dist'] = haversine($lat, $lng, $s['lat'], $s['lng']);
    $k = strtolower($p['name']); if ($p['stock'] > 0) { $min[$k]['p'] = min($min[$k]['p'] ?? INF, $p['price']); $min[$k]['n'] = ($min[$k]['n'] ?? 0) + 1; }
} unset($p);
if ($sort === 'near' && $lat !== null) usort($items, fn($a, $b) => ($a['dist'] ?? 1e9) <=> ($b['dist'] ?? 1e9));
$cats = db()->products->distinct('category');
$stats = ['shops' => count($shops), 'products' => db()->products->countDocuments(['shop_id' => ['$in' => $ids]])];
head('Find electronic parts nearby'); ?>
<section class="hero">
  <div>
    <h1>The part you need is probably<br>two streets away.</h1>
    <p class="lead">Search Arduino boards, ESP32 modules, sensors and ICs across verified local shops. Compare prices, check live stock, and reserve for pickup.</p>
    <form class="searchbar" method="get" id="searchForm">
      <input name="q" value="<?= e($q) ?>" placeholder="Try “ESP32”, “DHT22”, “NE555”…" autofocus aria-label="Search products">
      <input type="hidden" name="lat" id="lat" value="<?= e($lat) ?>"><input type="hidden" name="lng" id="lng" value="<?= e($lng) ?>">
      <button class="btn">Search</button>
    </form>
    <div class="quick"><?php foreach (['Arduino', 'ESP32', 'Raspberry Pi', 'Sensor', 'Servo'] as $t): ?><a href="/?q=<?= urlencode($t) ?>"><?= $t ?></a><?php endforeach; ?></div>
  </div>
  <aside class="hero-stats"><b><?= $stats['shops'] ?></b><span>verified shops</span><b><?= $stats['products'] ?></b><span>listed products</span><button type="button" class="btn ghost" id="locateBtn">Use my location</button></aside>
</section>

<section class="layout">
  <form class="filters" method="get">
    <input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="lat" value="<?= e($lat) ?>"><input type="hidden" name="lng" value="<?= e($lng) ?>">
    <h3>Filters</h3>
    <label>Category<select name="cat"><option value="">All categories</option><?php foreach (array_unique(array_merge(CATEGORIES, $cats)) as $c): ?><option <?= $cat === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></label>
    <label>City<input name="city" value="<?= e($city) ?>" placeholder="e.g. Coimbatore"></label>
    <label>Max price (₹)<input type="number" name="max" min="0" value="<?= $max ?: '' ?>"></label>
    <label>Sort by<select name="sort"><?php foreach (['name' => 'Name', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'stock' => 'Most in stock', 'new' => 'Newest', 'near' => 'Nearest (needs location)'] as $k => $v): ?><option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></label>
    <label class="check"><input type="checkbox" name="stock" <?= $inStock ? 'checked' : '' ?>> In stock only</label>
    <button class="btn block">Apply filters</button><a class="muted" href="/">Reset</a>
  </form>

  <div>
    <p class="muted"><?= count($items) ?> result<?= count($items) === 1 ? '' : 's' ?><?= $q ? ' for “' . e($q) . '”' : '' ?></p>
    <?php if (!$items): ?><div class="empty"><h3>No matching parts yet</h3><p>Try a shorter keyword, remove a filter, or widen the city.</p></div><?php endif; ?>
    <div class="grid">
    <?php foreach ($items as $p): $k = strtolower($p['name']); $best = ($min[$k]['n'] ?? 0) > 1 && $p['stock'] > 0 && $p['price'] == $min[$k]['p'];
          $low = $p['stock'] > 0 && $p['stock'] <= ($p['low_threshold'] ?? 5); ?>
      <article class="card">
        <div class="card-top"><span class="cat"><?= e($p['category']) ?></span><?php if ($best): ?><span class="badge b-best">Best price</span><?php endif; ?></div>
        <h3><?= e($p['name']) ?></h3><p class="desc"><?= e($p['description'] ?? '') ?></p>
        <div class="price"><?= money($p['price']) ?></div>
        <div class="stock <?= $p['stock'] == 0 ? 'out' : ($low ? 'low' : 'ok') ?>"><?= $p['stock'] == 0 ? 'Out of stock' : ($low ? 'Only ' . $p['stock'] . ' left' : $p['stock'] . ' in stock') ?></div>
        <div class="shop"><b><?= e($p['shop']['name']) ?></b><br><span class="muted"><?= e($p['shop']['address'] ?? '') ?>, <?= e($p['shop']['city']) ?><?= $p['dist'] !== null ? ' · ' . number_format($p['dist'], 1) . ' km away' : '' ?></span></div>
        <?php if ($p['stock'] > 0): if ((user()['role'] ?? '') === 'customer'): ?>
          <form class="reserve" method="post" action="/reserve.php"><?= csrf_field() ?><input type="hidden" name="pid" value="<?= $p['_id'] ?>">
            <input type="number" name="qty" value="1" min="1" max="<?= $p['stock'] ?>" aria-label="Quantity"><button class="btn">Reserve</button></form>
        <?php elseif (!user()): ?><a class="btn block" href="/login.php">Log in to reserve</a><?php endif; endif; ?>
      </article>
    <?php endforeach; ?></div>
  </div>
</section>
<?php foot();
