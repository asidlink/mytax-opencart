<?php
$zipPath = 'G:/DOWNLOAD/mytax.ocmod.zip';

echo "=== Анализ рабочего архива от ChatGPT: $zipPath ===\n\n";

$z = new ZipArchive();
if ($z->open($zipPath) !== true) {
    // Пробуем другие пути
    foreach (['G:/DOWNLOAD/mytax.ocmod.zip', 'G:\\DOWNLOAD\\mytax.ocmod.zip', 'G:/DOWNLOAD/mytax.ocmod.zip'] as $p) {
        if (file_exists($p)) { $zipPath = $p; break; }
    }
    $z->open($zipPath);
}

if ($z->open($zipPath) !== true) {
    die("Не удалось открыть архив: $zipPath\n");
}

echo "Всего файлов: {$z->numFiles}\n\n";
echo "Содержимое:\n";
for ($i = 0; $i < $z->numFiles; $i++) {
    $name = $z->getNameIndex($i);
    $stat = $z->statIndex($i);
    echo "  [$stat['size'] bytes] $name\n";
}

echo "\n=== install.json ===\n";
echo $z->getFromName('install.json') . "\n";

echo "\n=== install.php ===\n";
$install = $z->getFromName('install.php');
if ($install) echo $install . "\n";
else echo "(нет)\n";

$z->close();