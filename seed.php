<?php
// Run once:  php seed.php   — creates indexes + demo data (safe to re-run; it resets the demo DB)
if (PHP_SAPI !== 'cli') exit('Run from the command line: php seed.php');
require __DIR__ . '/includes/bootstrap.php';
foreach (['users', 'shops', 'products', 'reservations', 'logs'] as $c) db()->$c->drop();
db()->users->createIndex(['email' => 1], ['unique' => true]);
db()->shops->createIndex(['verified' => 1, 'city' => 1]); db()->shops->createIndex(['owner_id' => 1]);
db()->products->createIndex(['name' => 'text', 'description' => 'text', 'category' => 'text']);
db()->products->createIndex(['shop_id' => 1, 'category' => 1]); db()->products->createIndex(['price' => 1]); db()->products->createIndex(['stock' => 1]);
db()->reservations->createIndex(['customer_id' => 1, 'created' => -1]); db()->reservations->createIndex(['shop_id' => 1, 'status' => 1]);
db()->logs->createIndex(['at' => -1]);

$mk = fn($n, $e, $r, $s = 'active') => db()->users->insertOne(['name' => $n, 'email' => $e, 'password' => password_hash($r === 'admin' ? 'admin123' : 'pass123', PASSWORD_DEFAULT), 'role' => $r, 'status' => $s, 'created' => now()])->getInsertedId();
$mk('Administrator', 'admin@localtech.com', 'admin'); $mk('Priya Customer', 'customer@localtech.com', 'customer'); $mk('Arun (student)', 'arun@localtech.com', 'customer');
$shops = [
  ['Gandhipuram Electronics', 'shop1@localtech.com', '12 Cross Cut Rd', 'Coimbatore', '+91 98400 11111', 11.0183, 76.9725, true],
  ['RS Puram Maker Hub', 'shop2@localtech.com', '45 DB Road, RS Puram', 'Coimbatore', '+91 98400 22222', 11.0067, 76.9500, true],
  ['Peelamedu Circuits', 'shop3@localtech.com', '7 Avinashi Rd', 'Coimbatore', '+91 98400 33333', 11.0258, 77.0270, true],
  ['New Volt Components', 'shop4@localtech.com', '3 Race Course', 'Coimbatore', '+91 98400 44444', 11.0000, 76.9660, false]];
$sid = [];
foreach ($shops as $s) { $o = $mk($s[0] . ' Owner', $s[1], 'shop'); $sid[] = db()->shops->insertOne(['owner_id' => $o, 'name' => $s[0], 'address' => $s[2], 'city' => $s[3], 'phone' => $s[4], 'lat' => $s[5], 'lng' => $s[6], 'verified' => $s[7], 'created' => now()])->getInsertedId(); }
// name, category, description, price per shop (0 = not stocked), stock per shop
$catalog = [
  ['Arduino Uno R3', 'Microcontrollers', 'ATmega328P board with USB cable', [550, 520, 580, 0], [25, 4, 10, 8]],
  ['Arduino Nano', 'Microcontrollers', 'Compact ATmega328P board', [320, 0, 299, 310], [15, 0, 30, 5]],
  ['ESP32 DevKit V1', 'Microcontrollers', 'Dual-core WiFi + Bluetooth module, 30 pin', [480, 450, 495, 440], [12, 3, 0, 20]],
  ['ESP8266 NodeMCU', 'Microcontrollers', 'WiFi microcontroller with CP2102', [290, 275, 0, 0], [18, 22, 0, 0]],
  ['Raspberry Pi 4 Model B 4GB', 'Single-board computers', 'Quad-core 1.5GHz SBC', [5600, 5450, 0, 0], [3, 2, 0, 0]],
  ['Raspberry Pi Pico', 'Microcontrollers', 'RP2040 microcontroller board', [380, 360, 399, 0], [9, 14, 6, 0]],
  ['DHT22 Temperature & Humidity Sensor', 'Sensors', 'Digital sensor, ±0.5°C accuracy', [220, 240, 210, 0], [14, 6, 2, 0]],
  ['HC-SR04 Ultrasonic Sensor', 'Sensors', 'Distance sensor 2cm–400cm', [65, 60, 70, 55], [40, 25, 18, 12]],
  ['MPU6050 Gyro + Accelerometer', 'Sensors', '6-axis IMU module (I2C)', [150, 0, 145, 0], [7, 0, 11, 0]],
  ['NE555 Timer IC', 'ICs', 'DIP-8 precision timer', [12, 10, 15, 0], [100, 150, 60, 0]],
  ['L293D Motor Driver IC', 'ICs', 'Dual H-bridge motor driver, DIP-16', [45, 42, 0, 0], [30, 8, 0, 0]],
  ['SG90 Micro Servo', 'Modules', '9g servo motor, 180°', [120, 110, 125, 105], [22, 3, 15, 30]],
  ['Relay Module 5V 1-Channel', 'Modules', 'Opto-isolated relay board', [55, 60, 52, 0], [35, 20, 0, 0]],
  ['Jumper Wires (40pcs M-M)', 'Cables & connectors', '20cm male-to-male dupont wires', [60, 55, 65, 50], [50, 40, 22, 60]],
  ['Breadboard 830 points', 'Tools & accessories', 'Solderless breadboard', [95, 90, 100, 85], [20, 2, 16, 10]],
  ['18650 Li-ion Battery 2600mAh', 'Power & batteries', 'Rechargeable cell', [180, 175, 0, 170], [12, 9, 0, 4]],
  ['Micro USB Cable 1m', 'Cables & connectors', 'Data + charge cable', [60, 0, 55, 0], [25, 0, 40, 0]]];
foreach ($catalog as [$n, $c, $d, $pr, $st]) foreach ($sid as $i => $id) if ($pr[$i] > 0)
    db()->products->insertOne(['shop_id' => $id, 'name' => $n, 'category' => $c, 'description' => $d, 'price' => (float)$pr[$i], 'stock' => $st[$i], 'low_threshold' => 5, 'created' => now(), 'updated' => now()]);
echo "LocalTech demo data ready.\n  Admin     admin@localtech.com / admin123\n  Shop      shop1@localtech.com / pass123  (shop2, shop3 verified; shop4 awaiting)\n  Customer  customer@localtech.com / pass123\n";
