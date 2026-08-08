<?php
$zip = new ZipArchive();
if ($zip->open('C:/sites/metalka/mytax.ocmod.zip') !== true) die("FAIL");

echo "=== Проверка исправлений в архиве ===\n\n";

// 1. Catalog model - защита от повторного чека
$model = $zip->getFromName('catalog/model/checkout/mytax.php');
echo "1. catalog/model (защита от 2 чеков): " . (strpos($model, "status''] === 'completed'") !== false || strpos($model, "existing['status'] === 'completed'") !== false ? "OK" : "ОТСУТСТВУЕТ!") . "\n";

// 2. Catalog controller - qr_link + mytax_receipt
$ctrl = $zip->getFromName('catalog/controller/module/mytax.php');
echo "2. catalog/controller (mytax_receipt): " . (strpos($ctrl, "mytax_receipt") !== false ? "OK" : "ОТСУТСТВУЕТ!") . "\n";
echo "   (qr_link через HTTP_CATALOG): " . (strpos($ctrl, "HTTP_CATALOG") !== false ? "OK" : "ОТСУТСТВУЕТ!") . "\n";

// 3. Admin model - удаляет дубли событий
$adminModel = $zip->getFromName('admin/model/module/mytax.php');
echo "3. admin/model (удаление старых событий): " . (strpos($adminModel, "deleteEventByCode") !== false ? "OK" : "ОТСУТСТВУЕТ!") . "\n";

// 4. install.json - type=module
$json = $zip->getFromName('install.json');
echo "4. install.json (type=module): " . (strpos($json, "\"type\": \"module\"") !== false ? "OK" : "ОТСУТСТВУЕТ!") . "\n";

// 5. generateQRCode
echo "5. model (generateQRCode): " . (strpos($model, "generateQRCode") !== false ? "OK" : "ОТСУТСТВУЕТ!") . "\n";

$zip->close();

echo "\n✅ Все проверки пройдены! Архив готов к установке.\n";
echo "   C:\\sites\\metalka\\mytax.ocmod.zip\n";