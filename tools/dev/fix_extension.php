<?php
/**
 * OpenCart 4 ищет модули по пути:
 *   glob(DIR_EXTENSION . '*/admin/controller/module/*.php')
 * 
 * Это жёстко зашитый путь - НЕ зависит от названия админ-папки сайта!
 * Файлы должны лежать в:
 *   extension/mytax/admin/controller/module/mytax.php
 *   extension/mytax/admin/language/.../mytax.php
 *   extension/mytax/admin/model/.../mytax.php
 *   extension/mytax/admin/view/template/.../mytax.twig
 *   
 * А catalog файлы просто:
 *   extension/mytax/catalog/controller/...
 */

$base = 'C:\sites\metalka';
$ext = $base . '\extension\mytax';
$upload = $ext . '\upload';

echo "=== Step 1: Copy admin files to extension/mytax/admin/ (without upload/ prefix) ===\n\n";

$fileMap = [
    // Admin files: from upload/admin/ to admin/
    $upload . '\admin\controller\extension\mytax\module\mytax.php' => $ext . '\admin\controller\module\mytax.php',
    $upload . '\admin\language\ru-ru\extension\mytax\module\mytax.php' => $ext . '\admin\language\ru-ru\module\mytax.php',
    $upload . '\admin\model\extension\mytax\module\mytax.php' => $ext . '\admin\model\module\mytax.php',
    $upload . '\admin\view\template\extension\mytax\module\mytax.twig' => $ext . '\admin\view\template\module\mytax.twig',
    
    // Catalog files: from upload/catalog/ to catalog/
    $upload . '\catalog\controller\extension\mytax\payment\mytax.php' => $ext . '\catalog\controller\payment\mytax.php',
    $upload . '\catalog\language\ru-ru\extension\mytax\module\mytax.php' => $ext . '\catalog\language\ru-ru\module\mytax.php',
    $upload . '\catalog\model\extension\mytax\checkout\mytax.php' => $ext . '\catalog\model\checkout\mytax.php',
];

foreach ($fileMap as $src => $dst) {
    if (file_exists($src)) {
        $dstDir = dirname($dst);
        if (!is_dir($dstDir)) {
            mkdir($dstDir, 0777, true);
            echo "  Created: $dstDir\n";
        }
        copy($src, $dst);
        echo "  OK: " . str_replace($ext . '\\', '', $dst) . "\n";
    } else {
        echo "  MISSING SRC: $src\n";
    }
}

echo "\n=== Step 2: Verify ===\n";

// Check admin files
echo "\nAdmin controller: " . (file_exists($ext . '\admin\controller\module\mytax.php') ? 'OK' : 'MISSING') . "\n";
echo "Admin model: " . (file_exists($ext . '\admin\model\module\mytax.php') ? 'OK' : 'MISSING') . "\n";
echo "Admin language: " . (file_exists($ext . '\admin\language\ru-ru\module\mytax.php') ? 'OK' : 'MISSING') . "\n";
echo "Admin view: " . (file_exists($ext . '\admin\view\template\module\mytax.twig') ? 'OK' : 'MISSING') . "\n";

echo "\nCatalog controller: " . (file_exists($ext . '\catalog\controller\payment\mytax.php') ? 'OK' : 'MISSING') . "\n";
echo "Catalog model: " . (file_exists($ext . '\catalog\model\checkout\mytax.php') ? 'OK' : 'MISSING') . "\n";
echo "Catalog language: " . (file_exists($ext . '\catalog\language\ru-ru\module\mytax.php') ? 'OK' : 'MISSING') . "\n";

echo "\n=== Step 3: Check glob pattern ===\n";
$globPath = str_replace('/', '\\', $base . '\extension\*\admin\controller\module\*.php');
echo "Looking for: $globPath\n";
$results = glob($globPath);
foreach ($results as $r) {
    echo "  Found: " . str_replace($base . '\extension\\', '', $r) . "\n";
}

echo "\n=== Done! ===\n";
echo "Refresh Admin > Extensions > Extensions > Modules\n";
echo "Module 'Мой налог (НПД)' should now appear\n";