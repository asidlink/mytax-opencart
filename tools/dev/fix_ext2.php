<?php
$ext = 'C:\sites\metalka\extension\mytax';
$upload = $ext . '\upload';

// Files from upload/dmt/ (since we renamed upload/admin -> upload/dmt) to extension/mytax/admin/
$files = array(
    $upload . '\dmt\controller\extension\mytax\module\mytax.php' => $ext . '\admin\controller\module\mytax.php',
    $upload . '\dmt\language\ru-ru\extension\mytax\module\mytax.php' => $ext . '\admin\language\ru-ru\module\mytax.php',
    $upload . '\dmt\model\extension\mytax\module\mytax.php' => $ext . '\admin\model\module\mytax.php',
    $upload . '\dmt\view\template\extension\mytax\module\mytax.twig' => $ext . '\admin\view\template\module\mytax.twig',
);

foreach ($files as $src => $dst) {
    if (!file_exists($src)) { echo "MISS: $src\n"; continue; }
    $d = dirname($dst);
    if (!is_dir($d)) mkdir($d, 0777, true);
    copy($src, $dst);
    echo "OK: " . str_replace($ext . '\\', '', $dst) . "\n";
}

echo "\nGlob test (should show mytax now):\n";
$r = glob('C:\sites\metalka\extension\*\admin\controller\module\*.php');
foreach ($r as $f) {
    echo "  " . str_replace('C:\sites\metalka\extension\\', '', $f) . "\n";
}