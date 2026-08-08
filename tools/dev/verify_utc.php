<?php
$f = 'C:/sites/metalka/extension/mytax/catalog/model/checkout/mytax.php';
$c = file_get_contents($f);

echo "=== Проверка v3.0.1 (UTC-фикс) ===\n";
echo "1. DateTimeZone('UTC'): " . (strpos($c, "DateTimeZone('UTC')") !== false || strpos($c, 'DateTimeZone("UTC")') !== false ? "OK - есть" : "НЕТУ!") . "\n";
echo "2. Без Node (proc_open/mytax-cli в исполняемом коде): ";
$clean = preg_replace('~//[^\n]*|/\*.*?\*/~s', '', $c);
echo (stripos($clean, 'proc_open') === false && stripos($clean, 'mytax-cli') === false ? "OK - чистый PHP" : "НЕТУ!") . "\n";
echo "3. fnsAuth (авторизация): " . (strpos($c, 'fnsAuth') !== false ? "OK" : "НЕТУ!") . "\n";
echo "4. fnsApiCall (чека): " . (strpos($c, 'fnsApiCall') !== false ? "OK" : "НЕТУ!") . "\n";

echo "\n=== Версия в install.json (zip) ===\n";
$zip = new ZipArchive();
if ($zip->open('C:/sites/metalka/mytax.ocmod.zip') === true) {
    echo $zip->getFromName('install.json') . "\n";
    $zip->close();
}