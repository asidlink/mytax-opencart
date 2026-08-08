<?php
$f = 'C:/sites/metalka/extension/mytax/catalog/model/checkout/mytax.php';
if (!file_exists($f)) { echo "ФАЙЛ НЕ НАЙДЕН\n"; exit; }
$c = file_get_contents($f);

echo "=== Проверка модели v3.0.0 (чистый PHP) ===\n\n";

// 1. Без Node.js
$hasNode = (stripos($c, 'mytax-cli') !== false || stripos($c, 'proc_open') !== false || stripos($c, '"node"') !== false || stripos($c, "'node'") !== false);
echo "1. Без Node.js (нет mytax-cli/proc_open/node): " . ($hasNode ? "❌ ЕСТЬ Node!" : "✅ ЧИСТЫЙ PHP") . "\n";

// 2. fnsAuth
echo "2. fnsAuth (авторизация ФНС): " . (strpos($c, 'fnsAuth') !== false ? "✅" : "❌") . "\n";

// 3. fnsApiCall
echo "3. fnsApiCall (создание чека): " . (strpos($c, 'fnsApiCall') !== false ? "✅" : "❌") . "\n";

// 4. fnsRequest (cURL)
echo "4. fnsRequest (cURL): " . (strpos($c, 'fnsRequest') !== false ? "✅" : "❌") . "\n";

// 5. API URL
echo "5. lknpd.nalog.ru API: " . (strpos($c, 'lknpd.nalog.ru') !== false ? "✅" : "❌") . "\n";

// 6. createDeviceId
echo "6. createDeviceId: " . (strpos($c, 'createDeviceId') !== false ? "✅" : "❌") . "\n";

// 7. Проверка контроллера (ждёт чек перед письмом)
$ctrl = 'C:/sites/metalka/extension/mytax/catalog/controller/module/mytax.php';
$cc = file_get_contents($ctrl);
echo "\n=== Контроллер ===\n";
echo "7. Создание чека в письме (viewOrder* createReceipt): " . ((strpos($cc, 'viewOrderHistory') !== false && strpos($cc, 'createReceipt(') !== false) ? "✅" : "❌") . "\n";
echo "8. Проверка оплаты (order_status_id > 0): " . (strpos($cc, 'order_status_id"] > 0') !== false ? "✅" : "❌") . "\n";
echo "9. QR-link: " . (strpos($cc, 'qr_link') !== false ? "✅" : "❌") . "\n";