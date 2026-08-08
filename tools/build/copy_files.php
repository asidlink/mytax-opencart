<?php
$site = 'C:/sites/metalka';
$adm = 'dmt';

// 1. Проверяем наличие файлов в extension/mytax
echo "=== Checking extension/mytax source files ===\n";
$srcFiles = [
    'extension/mytax/admin/controller/module/mytax.php',
    'extension/mytax/admin/model/module/mytax.php',
    'extension/mytax/admin/language/ru-ru/module/mytax.php',
    'extension/mytax/admin/view/template/module/mytax.twig',
    'extension/mytax/catalog/controller/module/mytax.php',
    'extension/mytax/catalog/model/checkout/mytax.php',
    'extension/mytax/catalog/language/ru-ru/module/mytax.php',
];
foreach ($srcFiles as $f) {
    echo (file_exists("$site/$f") ? '  OK' : '  MISS') . ': ' . $f . "\n";
}

// 2. Копируем admin файлы в dmt/
echo "\n=== Copying admin files to dmt/ ===\n";
$adminPairs = [
    'extension/mytax/admin/controller/module/mytax.php' => "$adm/controller/extension/module/mytax.php",
    'extension/mytax/admin/model/module/mytax.php' => "$adm/model/extension/module/mytax.php",
    'extension/mytax/admin/language/ru-ru/module/mytax.php' => "$adm/language/ru-ru/extension/module/mytax.php",
    'extension/mytax/admin/view/template/module/mytax.twig' => "$adm/view/template/extension/module/mytax.twig",
];
foreach ($adminPairs as $src => $dst) {
    $sp = "$site/$src";
    $dp = "$site/$dst";
    if (file_exists($sp)) {
        @mkdir(dirname($dp), 0777, true);
        copy($sp, $dp);
        echo "  OK: $dst\n";
    } else {
        echo "  FAIL: source $src missing\n";
    }
}

// 3. Копируем catalog файлы
echo "\n=== Copying catalog files ===\n";
$catPairs = [
    'extension/mytax/catalog/controller/module/mytax.php' => 'catalog/controller/extension/module/mytax.php',
    'extension/mytax/catalog/model/checkout/mytax.php' => 'catalog/model/extension/mytax/checkout/mytax.php',
    'extension/mytax/catalog/language/ru-ru/module/mytax.php' => 'catalog/language/ru-ru/extension/module/mytax.php',
];
foreach ($catPairs as $src => $dst) {
    $sp = "$site/$src";
    $dp = "$site/$dst";
    if (file_exists($sp)) {
        @mkdir(dirname($dp), 0777, true);
        copy($sp, $dp);
        echo "  OK: $dst\n";
    } else {
        echo "  FAIL: source $src missing\n";
    }
}

// 4. Финальная проверка
echo "\n=== Final verification ===\n";
$allChecks = [
    "$adm/controller/extension/module/mytax.php",
    "$adm/model/extension/module/mytax.php",
    "$adm/language/ru-ru/extension/module/mytax.php",
    "$adm/view/template/extension/module/mytax.twig",
    'catalog/controller/extension/module/mytax.php',
    'catalog/model/extension/mytax/checkout/mytax.php',
    'catalog/language/ru-ru/extension/module/mytax.php',
    'extension/mytax/admin/controller/module/mytax.php',
];
$allOk = true;
foreach ($allChecks as $f) {
    $exists = file_exists("$site/$f");
    echo ($exists ? '  OK' : '  MISS') . ": $f\n";
    if (!$exists) $allOk = false;
}

if ($allOk) {
    echo "\nALL FILES IN PLACE!\n";
} else {
    echo "\nSOME FILES MISSING!\n";
}