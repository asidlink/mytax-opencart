<?php
/**
 * Финальное удаление всех файлов mytax с диска
 */
$r = 'C:/sites/metalka';

echo "Удаление файлов mytax...\n\n";

// 1. extension/mytax
$p = "$r/extension/mytax";
if (is_dir($p)) {
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($p, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($rii as $f) $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
    @rmdir($p);
    echo is_dir($p) ? "  [FAIL] extension/mytax\n" : "  [OK] extension/mytax\n";
} else {
    echo "  extension/mytax не найден\n";
}

// 2. catalog/controller/extension/mytax
$d = "$r/catalog/controller/extension/mytax";
if (is_dir($d)) {
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($rii as $f) $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
    @rmdir($d);
    echo is_dir($d) ? "  [FAIL] catalog/controller/extension/mytax\n" : "  [OK] catalog/controller/extension/mytax\n";
}

// 3. catalog/language/ru-ru/extension/mytax
$d = "$r/catalog/language/ru-ru/extension/mytax";
if (is_dir($d)) {
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($rii as $f) $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
    @rmdir($d);
    echo is_dir($d) ? "  [FAIL] catalog/language/ru-ru/extension/mytax\n" : "  [OK] catalog/language/ru-ru/extension/mytax\n";
}

// 4. catalog/model/extension/mytax
$d = "$r/catalog/model/extension/mytax";
if (is_dir($d)) {
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($rii as $f) $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
    @rmdir($d);
    echo is_dir($d) ? "  [FAIL] catalog/model/extension/mytax\n" : "  [OK] catalog/model/extension/mytax\n";
}

// 5. admin/controller/extension/module/mytax.php
$files = [
    "$r/admin/controller/extension/module/mytax.php",
    "$r/admin/language/ru-ru/extension/module/mytax.php",
    "$r/admin/model/extension/module/mytax.php",
    "$r/admin/view/template/extension/module/mytax.twig",
    "$r/catalog/controller/extension/module/mytax.php",
    "$r/catalog/language/ru-ru/extension/module/mytax.php",
    "$r/catalog/model/extension/module/mytax.php",
    "$r/catalog/view/template/extension/module/mytax.twig",
    "$r/admin/controller/payment/mytax.php",
    "$r/admin/model/payment/mytax.php",
    "$r/admin/language/ru-ru/payment/mytax.php",
    "$r/admin/view/template/payment/mytax.twig",
    "$r/catalog/controller/payment/mytax.php",
    "$r/catalog/model/checkout/mytax.php",
    "$r/catalog/language/ru-ru/payment/mytax.php",
];
echo "\nУдаление отдельных файлов:\n";
foreach ($files as $f) {
    if (file_exists($f)) {
        @unlink($f);
        echo file_exists($f) ? "  [FAIL] " : "  [OK] ";
        echo str_replace("$r/", '', $f) . "\n";
    }
}

// ФИНАЛЬНАЯ ПРОВЕРКА
echo "\n=== ФИНАЛЬНАЯ ПРОВЕРКА ===\n";
$found = [];
foreach (['admin', 'catalog', 'extension', 'dmt'] as $sec) {
    $root = "$r/$sec";
    if (!is_dir($root)) continue;
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($iter as $f) {
        if ($f->isFile() && stripos($f->getFilename(), 'mytax') !== false) {
            $found[] = str_replace("$r/", '', $f->getPathname());
        }
    }
}

if ($found) {
    echo "⚠️ Остались файлы:\n";
    foreach ($found as $f) echo "  $f\n";
} else {
    echo "✅ Все файлы mytax удалены!\n";
    echo "\nТеперь обновите страницу Расширения → Установщик расширений и Расширения → Модули в админке.\n";
}