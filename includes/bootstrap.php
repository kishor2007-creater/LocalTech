<?php
// Core: session, MongoDB connection, helpers, auth, CSRF, logging
session_start();
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) { http_response_code(500); exit('Run <code>composer install</code> first (see README.md).'); }
require $autoload;
use MongoDB\Client; use MongoDB\BSON\ObjectId; use MongoDB\BSON\UTCDateTime;

define('MONGO_URI', getenv('MONGO_URI') ?: 'mongodb://127.0.0.1:27017');
define('DB_NAME', getenv('MONGO_DB') ?: 'localtech');
date_default_timezone_set('Asia/Kolkata');

function db() {
    static $d;
    return $d ??= (new Client(MONGO_URI, [], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]))->selectDatabase(DB_NAME);
}
function oid($s) { try { return new ObjectId((string)$s); } catch (Throwable $e) { return null; } }
function now() { return new UTCDateTime(); }
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n) { return '₹' . number_format((float)$n, 2); }
function dt($d) { return $d instanceof UTCDateTime ? $d->toDateTime()->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y, H:i') : ''; }
function redirect($u) { header("Location: $u"); exit; }
function user() { return $_SESSION['user'] ?? null; }
function require_role($r) { $u = user(); if (!$u || $u['role'] !== $r) { flash('Please log in with a ' . $r . ' account.'); redirect('/login.php'); } }
function csrf() { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf() . '">'; }
function check_csrf() { if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals(csrf(), $_POST['csrf'] ?? '')) { http_response_code(400); exit('Invalid form token. Go back and try again.'); } }
function flash($m = null) { if ($m !== null) { $_SESSION['flash'] = $m; return null; } $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function log_event($action, $detail = '') {
    $u = user();
    db()->logs->insertOne(['action' => $action, 'detail' => $detail, 'by' => $u['name'] ?? 'guest', 'role' => $u['role'] ?? '-', 'at' => now()]);
}
function haversine($lat1, $lng1, $lat2, $lng2) {
    $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;
    return 2 * 6371 * asin(sqrt($a));
}
// Cancel a reservation and return units to stock (status guard prevents double-restore)
function cancel_reservation($id) {
    $res = db()->reservations->findOneAndUpdate(
        ['_id' => $id, 'status' => ['$in' => ['pending', 'confirmed']]],
        ['$set' => ['status' => 'cancelled', 'updated' => now()]]);
    if ($res) { db()->products->updateOne(['_id' => $res['product_id']], ['$inc' => ['stock' => $res['qty']]]); log_event('reservation_cancelled', $res['code']); return true; }
    return false;
}
function badge($status) { return '<span class="badge b-' . e($status) . '">' . e($status) . '</span>'; }
const CATEGORIES = ['Microcontrollers', 'Single-board computers', 'Sensors', 'ICs', 'Modules', 'Cables & connectors', 'Passive components', 'Tools & accessories', 'Power & batteries'];
