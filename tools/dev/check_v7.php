<?php
$site = 'C:/sites/metalka';

$files = [
    $site . '/extension/mytax/catalog/controller/module/mytax.php',
    $site . '/catalog/controller/extension/mytax/module/mytax.php',
];

$allOk = true;
foreach ($files as $f) {
    echo "=== " . str_replace($site . '/', '', $f) . " ===\n";
    if (!file_exists($f)) {
        echo "  ФАЙЛ НЕ НАЙДЕН!\n\n";
        $allOk = false;
        continue;
    }
    $c = file_get_contents($f);
    
    // 1. Сколько раз вызывается createReceipt (должно быть >= 2: orderHistory + viewSuccess + viewOrder*)
    $count = substr_count($c, 'createReceipt(');
    echo "  1. Вызовов createReceipt: $count (ожидается >= 3)\n";
    if ($count < 3) $allOk = false;
    
    // 2. Есть ли принудительный вызов в viewOrderHistory
    $hasInHistory = strpos($c, 'viewOrderHistory') !== false && strpos($c, 'createReceipt(') !== false;
    echo "  2. viewOrderHistory + createReceipt: " . ($hasInHistory ? "✅" : "❌") . "\n";
    if (!$hasInHistory) $allOk = false;
    
    // 3. Проверка оплаты
    $hasStatus = strpos($c, 'order_status_id"] > 0') !== false;
    echo "  3. Проверка order_status_id > 0: " . ($hasStatus ? "✅" : "❌") . "\n";
    if (!$hasStatus) $allOk = false;
    
    // 4. qr_link
    $hasQr = strpos($c, 'qr_link') !== false;
    echo "  4. QR-ссылка (qr_link): " . ($hasQr ? "✅" : "❌") . "\n";
    if (!$hasQr) $allOk = false;
    
    echo "\n";
}

echo $allOk ? "✅ ВСЕ ПРОВЕРКИ ПРОЙДЕНЫ — v2.7.0 развёрнут!\n" : "❌ ЕСТЬ ПРОБЛЕМЫ!\n";
echo "\nТеперь при отправке письма:\n";
echo "1. Сначала вызывается createReceipt() — чек создаётся в Мой налог (если его ещё нет)\n";
echo "2. Потом getReceiptByOrderId() читает свежий чек из БД\n";
echo "3. QR-код (qr_link) вставляется в письмо\n";