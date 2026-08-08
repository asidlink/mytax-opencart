<?php
$zipPath = 'C:/sites/metalka/mytax.ocmod_new.zip';

if (!file_exists($zipPath)) {
    echo "Файл не найден: $zipPath\n";
    exit;
}

$z = new ZipArchive();
if ($z->open($zipPath) !== true) {
    die("Не удалось открыть архив\n");
}

echo "=== Содержимое mytax.ocmod_new.zip ===\n\n";
echo "Всего файлов: {$z->numFiles}\n\n";

for ($i = 0; $i < $z->numFiles; $i++) {
    $name = $z->getNameIndex($i);
    $stat = $z->statIndex($i);
    $size = $stat['size'];
    echo "  [{$size} bytes] {$name}\n";
}

echo "\n=== install.json ===\n";
$json = $z->getFromName('install.json');
echo $json ? $json : "(нет)";

echo "\n\n=== catalog/model/checkout/mytax.php ===\n";
$model = $z->getFromName('catalog/model/checkout/mytax.php');
echo $model ? $model : "(нет)";

echo "\n";
$z->close();