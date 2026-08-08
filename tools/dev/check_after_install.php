<?php
$r = 'C:/sites/metalka';

echo "=== Проверка файлов mytax на диске ===\n\n";

echo "--- extension/mytax/ ---\n";
$p = "$r/extension/mytax";
if (is_dir($p)) {
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($p, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($iter as $f) {
        if ($f->isFile()) echo "  " . str_replace($r . '/', '', $f->getPathname()) . "\n";
    }
} else {
    echo "  НЕ СУЩЕСТВУЕТ!\n";
}

echo "\n--- dmt/ (admin) ---\n";
$dirs = [
    "$r/dmt/controller/extension/module/mytax.php",
    "$r/dmt/model/extension/module/mytax.php",
    "$r/dmt/language/ru-ru/extension/module/mytax.php",
    "$r/dmt/view/template/extension/module/mytax.twig",
    "$r/dmt/controller/extension/mytax",
    "$r/dmt/model/extension/mytax",
    "$r/dmt/language/ru-ru/extension/mytax",
    "$r/dmt/view/template/extension/mytax",
];
foreach ($dirs as $p) {
    if (file_exists($p)) echo "  [EXISTS] " . str_replace($r . '/', '', $p) . "\n";
}

echo "\n--- catalog/ ---\n";
$cfiles = [
    "$r/catalog/controller/extension/module/mytax.php",
    "$r/catalog/controller/extension/mytax/module/mytax.php",
    "$r/catalog/model/extension/mytax/checkout/mytax.php",
    "$r/catalog/language/ru-ru/extension/mytax/module/mytax.php",
];
foreach ($cfiles as $p) {
    if (file_exists($p)) echo "  [EXISTS] " . str_replace($r . '/', '', $p) . "\n";
}

echo "\n--- Состояние БД ---\n";
$m = new mysqli('localhost', 'root', '', 'metalka');
if (!$m->connect_error) {
    $p = 'oc_';
    $tables = ['extension', 'extension_install', 'extension_path', 'module', 'event', 'setting'];
    foreach ($tables as $t) {
        $r2 = $m->query("SELECT COUNT(*) as cnt FROM {$p}{$t} WHERE (code LIKE '%mytax%' OR `key` LIKE '%mytax%') AND 1=1");
        // используем для setting проверку по `key`
        if ($t === 'setting') {
            $r2 = $m->query("SELECT COUNT(*) as cnt FROM {$p}setting WHERE code LIKE '%mytax%'");
        }
        $row = $r2->fetch_assoc();
        echo "  oc_$t: {$row['cnt']} записей\n";
        if ($row['cnt'] > 0) {
            $r3 = $m->query("SELECT * FROM {$p}{$t} WHERE code LIKE '%mytax%' LIMIT 3");
            while ($rw = $r3->fetch_assoc()) {
                echo "    => " . json_encode($rw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            }
        }
    }
}
$m->close();