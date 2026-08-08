<?php
/**
 * Копирование файлов модуля из рабочего сайта в репозиторий.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$src = 'C:/sites/metalka/extension/mytax';
$dst = 'C:/TEST/MyTax-Service/extension/mytax';

$map = [
    'install.json' => 'install.json',
    'install.php' => 'install.php',
    'README.txt' => 'README.txt',
    'admin/controller/module/mytax.php' => 'admin/controller/module/mytax.php',
    'admin/model/module/mytax.php' => 'admin/model/module/mytax.php',
    'admin/language/ru-ru/module/mytax.php' => 'admin/language/ru-ru/module/mytax.php',
    'admin/view/template/module/mytax.twig' => 'admin/view/template/module/mytax.twig',
    'catalog/controller/module/mytax.php' => 'catalog/controller/module/mytax.php',
    'catalog/language/ru-ru/module/mytax.php' => 'catalog/language/ru-ru/module/mytax.php',
    'catalog/model/checkout/mytax.php' => 'catalog/model/checkout/mytax.php',
    'catalog/model/checkout/phpqrcode.php' => 'catalog/model/checkout/phpqrcode.php',
    'ocmod/mytax_fix_vendor_hang.ocmod.xml' => 'ocmod/mytax_fix_vendor_hang.ocmod.xml',
];

foreach ($map as $rel => $target) {
    $s = "$src/$rel";
    $t = "$dst/$target";
    if (!is_file($s)) { echo "[!!] Нет файла: $s\n"; exit(1); }
    @mkdir(dirname($t), 0777, true);
    if (!copy($s, $t)) { echo "[!!] Ошибка копирования: $s -> $t\n"; exit(1); }
    echo "[OK] $rel\n";
}

// Скрипты
$scripts = [
    'C:/TEST/MyTax-Service/install_mytax_debian.php' => 'C:/TEST/MyTax-Service/install_mytax_debian.php',
    'C:/TEST/MyTax-Service/build_v408.php' => 'C:/TEST/MyTax-Service/build_v408.php',
];
foreach ($scripts as $s => $t) {
    if (is_file($s)) { echo "[OK] скрипт: " . basename($t) . "\n"; }
}

// Архив
$zipSrc = 'C:/sites/metalka/mytax.ocmod.zip';
$zipDst = 'C:/TEST/MyTax-Service/releases/mytax.ocmod.zip';
if (is_file($zipSrc)) {
    @mkdir(dirname($zipDst), 0777, true);
    copy($zipSrc, $zipDst);
    echo "[OK] архив: releases/mytax.ocmod.zip (" . filesize($zipDst) . " байт)\n";
} else {
    echo "[!!] Архив не найден: $zipSrc\n";
}

echo "\nГОТОВО\n";