<?php
$checks = [
    'phpqrcode.php' => 'C:/TEST/MyTax-Service/phpqrcode/phpqrcode.php',
    'qr_dir' => 'C:/sites/metalka/image/mytax_qr/',
];
foreach ($checks as $label => $path) {
    echo "$label: " . (file_exists($path) ? "OK" : "MISS") . " ($path)\n";
}

if (is_dir('C:/TEST/MyTax-Service/phpqrcode')) {
    echo "\nСодержимое phpqrcode:\n";
    foreach (scandir('C:/TEST/MyTax-Service/phpqrcode') as $f) {
        if ($f[0] != '.') echo "  $f\n";
    }
}

if (is_dir('C:/sites/metalka/image/mytax_qr')) {
    echo "\nСодержимое image/mytax_qr:\n";
    foreach (scandir('C:/sites/metalka/image/mytax_qr') as $f) {
        if ($f[0] != '.') echo "  $f\n";
    }
}

// Посмотрим последние чеки в БД
$m = new mysqli('localhost', 'root', '', 'metalka');
$r = $m->query("SELECT receipt_id, order_id, status, qr_code_path, print_link FROM oc_mytax_receipts ORDER BY receipt_id DESC LIMIT 5");
echo "\n=== Последние чеки ===\n";
while ($row = $r->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}
$m->close();