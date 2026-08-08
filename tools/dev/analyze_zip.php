<?php
$zipPath = 'C:/sites/metalka/mytax.ocmod.zip';

echo "Анализ архива: $zipPath\n\n";

$z = new ZipArchive();
if ($z->open($zipPath) !== true) {
    die("Failed to open zip\n");
}

echo "Всего файлов: " . $z->numFiles . "\n\n";
echo "Содержимое:\n";
for ($i = 0; $i < $z->numFiles; $i++) {
    $name = $z->getNameIndex($i);
    $stat = $z->statIndex($i);
    $size = $stat['size'];
    $comp = $stat['comp_method'] == 0 ? 'DIR' : 'FILE';
    echo "  [$comp] $name ($size bytes)\n";
}

// Проверим install.json
$installJson = $z->getFromName('install.json');
if ($installJson) {
    echo "\n=== install.json ===\n";
    echo $installJson . "\n";
} else {
    echo "\ninstall.json НЕ НАЙДЕН в корне архива!\n";
    // Поищем install.json в любой папке
    for ($i = 0; $i < $z->numFiles; $i++) {
        $name = $z->getNameIndex($i);
        if (basename($name) === 'install.json') {
            echo "Найден install.json по пути: $name\n";
            echo "Содержимое:\n" . $z->getFromName($name) . "\n";
        }
    }
}

$z->close();

echo "\n=== Текущее состояние БД после установки ===\n";
$m = new mysqli('localhost', 'root', '', 'metalka');
if ($m->connect_error) die("Connection failed\n");
$p = 'oc_';

$tables = ['extension', 'extension_install', 'extension_path', 'module', 'event', 'setting'];
foreach ($tables as $t) {
    $r = $m->query("SELECT COUNT(*) as cnt FROM {$p}{$t} WHERE code LIKE '%mytax%' OR `key` LIKE '%mytax%'");
    $row = $r->fetch_assoc();
    $cnt = $row['cnt'];
    if ($cnt > 0) {
        echo "  oc_$t: $cnt записей (проблема!)\n";
        $r2 = $m->query("SELECT * FROM {$p}{$t} WHERE code LIKE '%mytax%' OR `key` LIKE '%mytax%' LIMIT 5");
        while ($row2 = $r2->fetch_assoc()) {
            echo "    => " . json_encode($row2, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        }
    } else {
        echo "  oc_$t: 0 записей (OK)\n";
    }
}

// Проверим файлы после установки
echo "\n=== Файлы на диске после установки ===\n";
foreach (['admin', 'catalog', 'extension'] as $sec) {
    $root = "C:/sites/metalka/$sec";
    if (!is_dir($root)) continue;
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($iter as $f) {
        if ($f->isFile() && stripos($f->getFilename(), 'mytax') !== false) {
            echo "  [FILE] $sec/" . str_replace("$root/", '', $f->getPathname()) . "\n";
        }
    }
}

$m->close();