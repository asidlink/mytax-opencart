<?php
$site = 'C:/sites/metalka';

$files = [
    $site . '/extension/mytax/catalog/controller/module/mytax.php',
    $site . '/catalog/controller/extension/mytax/module/mytax.php',
];

foreach ($files as $f) {
    echo "=== " . str_replace($site . '/', '', $f) . " ===\n";
    if (!file_exists($f)) {
        echo "  ФАЙЛ НЕ НАЙДЕН!\n\n";
        continue;
    }
    $c = file_get_contents($f);
    
    // Проверка 1: есть ли order_status_id > 0
    $hasStatusCheck = strpos($c, 'order_status_id"] > 0') !== false;
    echo "  1. Проверка оплаты (order_status_id > 0): " . ($hasStatusCheck ? "✅ ЕСТЬ" : "❌ НЕТУ!") . "\n";
    
    // Проверка 2: viewSuccess
    $hasViewSuccess = strpos($c, 'viewSuccess') !== false;
    echo "  2. Обработчик страницы успеха (viewSuccess): " . ($hasViewSuccess ? "✅ ЕСТЬ" : "❌ НЕТУ!") . "\n";
    
    // Проверка 3: qr_link
    $hasQrLink = strpos($c, 'qr_link') !== false;
    echo "  3. QR-ссылка (qr_link): " . ($hasQrLink ? "✅ ЕСТЬ" : "❌ НЕТУ!") . "\n";
    
    // Проверка 4: mytax_receipt
    $hasReceipt = strpos($c, 'mytax_receipt') !== false;
    echo "  4. Данные чека (mytax_receipt): " . ($hasReceipt ? "✅ ЕСТЬ" : "❌ НЕТУ!") . "\n";
    
    echo "\n";
}

// Проверка модели на защиту от повторных чеков
$model = $site . '/extension/mytax/catalog/model/checkout/mytax.php';
echo "=== extension/mytax/catalog/model/checkout/mytax.php ===\n";
if (file_exists($model)) {
    $c = file_get_contents($model);
    $hasDedup = strpos($c, "existing['status'] === 'completed'") !== false;
    echo "  1. Защита от повторного чека: " . ($hasDedup ? "✅ ЕСТЬ" : "❌ НЕТУ!") . "\n";
    $hasQR = strpos($c, 'generateQRCode') !== false;
    echo "  2. Генерация QR-кода: " . ($hasQR ? "✅ ЕСТЬ" : "❌ НЕТУ!") . "\n";
} else {
    echo "  ФАЙЛ НЕ НАЙДЕН!\n";
}

// Проверка событий в БД
$m = new mysqli('localhost', 'root', '', 'metalka');
echo "\n=== События mytax в БД ===\n";
$r = $m->query("SELECT code, `trigger`, action, status FROM oc_event WHERE code LIKE 'mytax%'");
while ($row = $r->fetch_assoc()) {
    echo "  {$row['code']}: {$row['trigger']} -> {$row['action']} (status={$row['status']})\n";
}

// Проверка oc_extension
echo "\n=== oc_extension mytax ===\n";
$r = $m->query("SELECT extension_id, `extension`, type, code FROM oc_extension WHERE code='mytax'");
while ($row = $r->fetch_assoc()) {
    echo "  id={$row['extension_id']} ext={$row['extension']} type={$row['type']}\n";
}
$m->close();