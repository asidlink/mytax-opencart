<?php
$ext = 'C:\sites\metalka\extension\mytax';
$upload = $ext . '\upload';

$files = array(
    $upload . '\admin\controller\extension\mytax\module\mytax.php' => $ext . '\admin\controller\module\mytax.php',
    $upload . '\admin\language\ru-ru\extension\mytax\module\mytax.php' => $ext . '\admin\language\ru-ru\module\mytax.php',
    $upload . '\admin\model\extension\mytax\module\mytax.php' => $ext . '\admin\model\module\mytax.php',
    $upload . '\admin\view\template\extension\mytax\module\mytax.twig' => $ext . '\admin\view\template\module\mytax.twig',
    $upload . '\catalog\controller\extension\mytax\payment\mytax.php' => $ext . '\catalog\controller\payment\mytax.php',
    $upload . '\catalog\language\ru-ru\extension\mytax\module\mytax.php' => $ext . '\catalog\language\ru-ru\module\mytax.php',
    $upload . '\catalog\model\extension\mytax\checkout\mytax.php' => $ext . '\catalog\model\checkout\mytax.php',
);

foreach ($files as $src => $dst) {
    if (!file_exists($src)) { echo "MISS: $src\n"; continue; }
    $d = dirname($dst);
    if (!is_dir($d)) mkdir($d, 0777, true);
    copy($src, $dst);
    echo "OK: " . str_replace($ext . '\\', '', $dst) . "\n";
}

echo "\nGlob test:\n";
$r = glob('C:\sites\metalka\extension\*\admin\controller\module\*.php');
foreach ($r as $f) {
    echo "  " . str_replace('C:\sites\metalka\extension\\', '', $f) . "\n";
}