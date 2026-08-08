<?php
$m = new mysqli('localhost', 'root', '', 'metalka');
if ($m->connect_error) die("Connection failed\n");
$p = 'oc_';

echo "=== 1. oc_extension WHERE code LIKE '%mytax%' ===\n";
$r = $m->query("SELECT extension_id, type, code FROM {$p}extension WHERE code LIKE '%mytax%'");
while ($row = $r->fetch_assoc()) {
    echo "  id={$row['extension_id']} type={$row['type']} code={$row['code']}\n";
}
if ($r->num_rows == 0) echo "  (empty)\n";

echo "\n=== 2. oc_extension_install WHERE code LIKE '%mytax%' ===\n";
$r = $m->query("SELECT extension_install_id, code FROM {$p}extension_install WHERE code LIKE '%mytax%'");
while ($row = $r->fetch_assoc()) {
    echo "  id={$row['extension_install_id']} code={$row['code']}\n";
}
if ($r->num_rows == 0) echo "  (empty)\n";

echo "\n=== 3. oc_extension_path WHERE path LIKE '%mytax%' ===\n";
$r = $m->query("SELECT extension_path_id, path FROM {$p}extension_path WHERE path LIKE '%mytax%'");
while ($row = $r->fetch_assoc()) {
    echo "  id={$row['extension_path_id']} path={$row['path']}\n";
}
if ($r->num_rows == 0) echo "  (empty)\n";

echo "\n=== 4. oc_module WHERE code = 'mytax' ===\n";
$r = $m->query("SELECT module_id, name, code FROM {$p}module WHERE code = 'mytax'");
while ($row = $r->fetch_assoc()) {
    echo "  id={$row['module_id']} name={$row['name']} code={$row['code']}\n";
}
if ($r->num_rows == 0) echo "  (empty)\n";

echo "\n=== 5. oc_event WHERE code LIKE 'mytax%' ===\n";
$r = $m->query("SELECT event_id, code, `trigger` as trig, `action` as act FROM {$p}event WHERE code LIKE 'mytax%'");
while ($row = $r->fetch_assoc()) {
    echo "  id={$row['event_id']} code={$row['code']} trigger={$row['trig']} action={$row['act']}\n";
}
if ($r->num_rows == 0) echo "  (empty)\n";

echo "\n=== 6. oc_setting WHERE code LIKE '%mytax%' ===\n";
$r = $m->query("SELECT setting_id, code, `key` as k, `value` as v FROM {$p}setting WHERE code LIKE '%mytax%'");
while ($row = $r->fetch_assoc()) {
    $val = substr($row['v'], 0, 80);
    echo "  id={$row['setting_id']} code={$row['code']} key={$row['k']} value={$val}\n";
}
if ($r->num_rows == 0) echo "  (empty)\n";

echo "\n=== 7. oc_setting WHERE `key` LIKE '%mytax%' ===\n";
$r = $m->query("SELECT setting_id, code, `key` as k FROM {$p}setting WHERE `key` LIKE '%mytax%'");
while ($row = $r->fetch_assoc()) {
    echo "  id={$row['setting_id']} code={$row['code']} key={$row['k']}\n";
}
if ($r->num_rows == 0) echo "  (empty)\n";

echo "\n=== 8. oc_layout_module WHERE code = 'mytax' ===\n";
$r = $m->query("SELECT layout_module_id, layout_id, code, position FROM {$p}layout_module WHERE code = 'mytax'");
while ($row = $r->fetch_assoc()) {
    echo "  id={$row['layout_module_id']} layout_id={$row['layout_id']} code={$row['code']} position={$row['position']}\n";
}
if ($r->num_rows == 0) echo "  (empty)\n";

echo "\n=== 9. oc_modification WHERE code LIKE '%mytax%' ===\n";
$r = $m->query("SELECT modification_id, name, code FROM {$p}modification WHERE code LIKE '%mytax%'");
while ($row = $r->fetch_assoc()) {
    echo "  id={$row['modification_id']} name={$row['name']} code={$row['code']}\n";
}
if ($r->num_rows == 0) echo "  (empty)\n";

echo "\n=== 10. Extension folders on disk ===\n";
$dirs = [
    'C:/sites/metalka/extension/mytax',
    'C:/sites/metalka/admin/controller/extension/module/mytax.php',
    'C:/sites/metalka/admin/language/ru-ru/extension/module/mytax.php',
    'C:/sites/metalka/admin/model/extension/module/mytax.php',
    'C:/sites/metalka/admin/view/template/extension/module/mytax.twig',
    'C:/sites/metalka/catalog/controller/extension/module/mytax.php',
    'C:/sites/metalka/catalog/language/ru-ru/extension/module/mytax.php',
    'C:/sites/metalka/catalog/model/extension/module/mytax.php',
    'C:/sites/metalka/catalog/view/template/extension/module/mytax.twig',
    'C:/sites/metalka/admin/controller/payment/mytax.php',
    'C:/sites/metalka/admin/model/payment/mytax.php',
    'C:/sites/metalka/admin/language/ru-ru/payment/mytax.php',
    'C:/sites/metalka/admin/view/template/payment/mytax.twig',
    'C:/sites/metalka/catalog/controller/payment/mytax.php',
    'C:/sites/metalka/catalog/model/checkout/mytax.php',
    'C:/sites/metalka/catalog/language/ru-ru/payment/mytax.php',
];
foreach ($dirs as $path) {
    $exists = file_exists($path) ? 'EXISTS' : 'not found';
    $display = str_replace('C:/sites/metalka/', '', $path);
    if ($exists === 'EXISTS') {
        echo "  [EXISTS] $display\n";
    }
}

echo "\n=== 11. Any files containing 'mytax' in admin/controller/ ===\n";
foreach (['admin', 'catalog', 'extension'] as $section) {
    $root = "C:/sites/metalka/$section";
    if (!is_dir($root)) continue;
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($iter as $f) {
        if ($f->isFile() && stripos($f->getFilename(), 'mytax') !== false) {
            echo "  [FILE] $section/" . str_replace($root . '/', '', $f->getPathname()) . "\n";
        }
    }
}

$m->close();
?>