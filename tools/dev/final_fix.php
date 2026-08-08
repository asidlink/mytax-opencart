<?php
$ext = 'C:\sites\metalka\extension\mytax';

// Admin: берем из dmt (куда мы ранее скопировали upload/admin/) и кладем в extension/mytax/admin/ 
// с правильной структурой (без extension/mytax/ в пути)
$srcFiles = array(
    'C:\sites\metalka\dmt\controller\extension\mytax\module\mytax.php' => $ext . '\admin\controller\module\mytax.php',
    'C:\sites\metalka\dmt\language\ru-ru\extension\mytax\module\mytax.php' => $ext . '\admin\language\ru-ru\module\mytax.php',
    'C:\sites\metalka\dmt\model\extension\mytax\module\mytax.php' => $ext . '\admin\model\module\mytax.php',
    'C:\sites\metalka\dmt\view\template\extension\mytax\module\mytax.twig' => $ext . '\admin\view\template\module\mytax.twig',
);

foreach ($srcFiles as $src => $dst) {
    if (!file_exists($src)) { echo "MISS SRC: $src\n"; continue; }
    $d = dirname($dst);
    if (!is_dir($d)) { mkdir($d, 0777, true); echo "Created: $d\n"; }
    copy($src, $dst);
    echo "OK: " . str_replace($ext . '\\', '', $dst) . "\n";
}

echo "\n=== Glob test ===\n";
$r = glob('C:\sites\metalka\extension\*\admin\controller\module\*.php');
foreach ($r as $f) {
    echo "  " . str_replace('C:\sites\metalka\extension\\', '', $f) . "\n";
}