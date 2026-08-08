<?php
$zipPath = 'c:/sites/metalka/extension/yoomoney';

echo "=== Структура extension/yoomoney ===\n";
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($zipPath, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($iter as $f) {
    if ($f->isFile()) {
        echo "  " . str_replace('C:/sites/metalka/extension/yoomoney/', '', $f->getPathname()) . "\n";
    }
}

echo "\n=== Содержимое install.json (если есть) ===\n";
if (file_exists("$zipPath/install.json")) {
    echo file_get_contents("$zipPath/install.json") . "\n";
}

echo "\n=== Содержимое install.php (если есть) ===\n";
if (file_exists("$zipPath/install.php")) {
    $content = file_get_contents("$zipPath/install.php");
    echo substr($content, 0, 500) . "\n";
}

// Также проверим, есть ли ZIP-архив yoomoney
$zipFile = 'c:/sites/metalka/yoomoney.ocmod.zip';
if (file_exists($zipFile)) {
    echo "\n=== Содержимое ZIP-архива yoomoney ===\n";
    $z = new ZipArchive();
    if ($z->open($zipFile) === true) {
        for ($i = 0; $i < $z->numFiles; $i++) {
            echo "  " . $z->getNameIndex($i) . "\n";
        }
        $z->close();
    }
} else {
    echo "\nZIP-файл yoomoney не найден\n";
    
    // Ищем любой .ocmod.zip в папке sites
    foreach (glob('c:/sites/metalka/*.ocmod.zip') as $f) {
        echo "\n=== Найден архив: " . basename($f) . " ===\n";
        $z = new ZipArchive();
        if ($z->open($f) === true) {
            for ($i = 0; $i < $z->numFiles; $i++) {
                echo "  " . $z->getNameIndex($i) . "\n";
            }
            $z->close();
        }
    }
}