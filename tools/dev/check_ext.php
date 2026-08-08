<?php
echo "=== extension/mytax directory structure ===\n";
$ext = 'C:\sites\metalka\extension\mytax';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ext));
foreach ($it as $f) {
    if ($f->isFile()) {
        echo str_replace('C:\sites\metalka\\', '', $f->getPathname()) . "\n";
    }
}