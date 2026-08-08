<?php
$f = 'C:/sites/metalka/extension/mytax/catalog/model/checkout/mytax.php';
$c = file_get_contents($f);

// Удаляем комментарии для проверки ИСПОЛНЯЕМОГО кода
$clean = preg_replace('~//[^\n]*|/\*.*?\*/~s', '', $c);

echo "=== Проверка v3.0.0 (исполняемый код, без комментариев) ===\n\n";
echo "1. proc_open: " . (stripos($clean, 'proc_open') !== false ? "NET - есть!" : "OK - нет") . "\n";
echo "2. mytax-cli: " . (stripos($clean, 'mytax-cli') !== false ? "NET - есть!" : "OK - нет") . "\n";
echo "3. node вызов: " . (preg_match('/["\x27]node["\x27]/', $clean) ? "NET - есть!" : "OK - нет") . "\n";
echo "4. fnsAuth (авторизация ФНС): " . (strpos($c, 'fnsAuth') !== false ? "OK" : "NET") . "\n";
echo "5. fnsApiCall (создание чека): " . (strpos($c, 'fnsApiCall') !== false ? "OK" : "NET") . "\n";
echo "6. fnsRequest (cURL): " . (strpos($c, 'fnsRequest') !== false ? "OK" : "NET") . "\n";
echo "7. lknpd.nalog.ru API: " . (strpos($c, 'lknpd.nalog.ru') !== false ? "OK" : "NET") . "\n";
echo "8. createDeviceId: " . (strpos($c, 'createDeviceId') !== false ? "OK" : "NET") . "\n";

$hasNode = stripos($clean, 'proc_open') !== false || stripos($clean, 'mytax-cli') !== false || preg_match('/["\x27]node["\x27]/', $clean);
echo "\nИТОГ: " . ($hasNode ? "ИСПОЛЬЗУЕТ Node.js!" : "ЧИСТЫЙ PHP v3.0.0 БЕЗ Node.js") . "\n";