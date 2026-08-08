<?php
function delDir($d) {
    if (!is_dir($d)) return;
    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($d, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($rii as $f) {
        $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
    }
    @rmdir($d);
}

$r = 'C:/sites/metalka';

$dirs = [
    "$r/dmt/controller/extension/mytax",
    "$r/dmt/language/ru-ru/extension/mytax",
    "$r/dmt/model/extension/mytax",
    "$r/dmt/view/template/extension/mytax",
];

foreach ($dirs as $d) {
    delDir($d);
    echo is_dir($d) ? "[FAIL] $d\n" : "[OK] $d\n";
}

echo "\n=== FINAL CHECK ===\n";
$found = false;
foreach (['admin', 'catalog', 'extension', 'dmt'] as $s) {
    $root = "$r/$s";
    if (!is_dir($root)) continue;
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($iter as $f) {
        if ($f->isFile() && stripos($f->getFilename(), 'mytax') !== false) {
            echo "REMAINING: " . str_replace("$r/", '', $f->getPathname()) . "\n";
            $found = true;
        }
    }
}
if (!$found) echo "ALL CLEAN! Все файлы mytax удалены.\n";