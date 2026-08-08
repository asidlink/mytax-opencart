<?php
$ext = 'C:\sites\metalka\extension\mytax';

// Catalog files (уже скопированы из fix_ext.php, но проверим)
$catalog = array(
    'C:\sites\metalka\catalog\controller\extension\mytax\payment\mytax.php' => $ext . '\catalog\controller\payment\mytax.php',
    'C:\sites\metalka\catalog\language\ru-ru\extension\mytax\module\mytax.php' => $ext . '\catalog\language\ru-ru\module\mytax.php',
    'C:\sites\metalka\catalog\model\extension\mytax\checkout\mytax.php' => $ext . '\catalog\model\checkout\mytax.php',
);

foreach ($catalog as $src => $dst) {
    if (!file_exists($src)) { echo "MISS SRC: $src\n"; continue; }
    $d = dirname($dst);
    if (!is_dir($d)) { mkdir($d, 0777, true); }
    copy($src, $dst);
    echo "OK: " . str_replace($ext . '\\', '', $dst) . "\n";
}

echo "\n=== Итоговая структура extension/mytax ===\n";
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ext));
foreach ($it as $f) {
    if ($f->isFile()) {
        echo str_replace($ext . '\\', '', $f->getPathname()) . "\n";
    }
}

echo "\n=== Все модули, которые найдёт OpenCart ===\n";
$r = glob('C:\sites\metalka\extension\*\admin\controller\module\*.php');
foreach ($r as $f) {
    echo "  " . str_replace('C:\sites\metalka\extension\\', '', $f) . "\n";
}

echo "\n=== Готово! ===\n";
echo "Обновите страницу Admin > Extensions > Extensions > Modules\n";
echo "Модуль 'Мой налог (НПД)' должен появиться в списке.\n";
echo "Нажмите Install, затем Edit для настройки.\n";