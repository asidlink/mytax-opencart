<?php
$dir = 'C:/TEST/MyTax-Service/node_modules/lknpd-nalog-api';
if (!is_dir($dir)) { echo "Папки нет\n"; exit; }
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isFile() && in_array($f->getExtension(), ['js', 'mjs', 'json', 'ts'])) {
        echo str_replace($dir . '/', '', $f->getPathname()) . ' [' . $f->getSize() . "]\n";
    }
}