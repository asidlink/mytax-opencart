<?php
$zipPath = 'G:/DOWNLOAD/mytax.ocmod.zip';
$z = new ZipArchive();
if ($z->open($zipPath) !== true) die("FAIL");

// Извлекаем ключевые файлы для анализа
$filesToShow = [
    'install.json',
    'admin/controller/module/mytax.php',
    'admin/model/module/mytax.php',
    'catalog/controller/module/mytax.php',
    'catalog/model/checkout/mytax.php',
];

foreach ($filesToShow as $f) {
    $content = $z->getFromName($f);
    echo "========== $f ==========\n";
    echo $content ? $content : "(не найден)";
    echo "\n\n";
}

$z->close();