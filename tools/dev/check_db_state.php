<?php
$m = new mysqli('localhost', 'root', '', 'metalka');
if ($m->connect_error) die("Connection failed\n");
$p = 'oc_';

echo "=== oc_extension WHERE code LIKE '%mytax%' ===\n";
$r = $m->query("SELECT extension_id, type, code FROM {$p}extension WHERE code LIKE '%mytax%'");
while ($row = $r->fetch_assoc()) {
    echo "  id={$row['extension_id']} type={$row['type']} code={$row['code']}\n";
}

echo "\n=== oc_extension_install WHERE code LIKE '%mytax%' ===\n";
$r = $m->query("SELECT extension_install_id, code FROM {$p}extension_install WHERE code LIKE '%mytax%'");
while ($row = $r->fetch_assoc()) {
    echo "  id={$row['extension_install_id']} code={$row['code']}\n";
}

echo "\n=== oc_extension_path WHERE path LIKE '%mytax%' ===\n";
$r = $m->query("SELECT extension_path_id, path FROM {$p}extension_path WHERE path LIKE '%mytax%'");
while ($row = $r->fetch_assoc()) {
    echo "  id={$row['extension_path_id']} path={$row['path']}\n";
}

echo "\n=== oc_module WHERE code LIKE '%mytax%' ===\n";
$r = $m->query("SELECT module_id, name, code FROM {$p}module WHERE code LIKE '%mytax%'");
while ($row = $r->fetch_assoc()) {
    echo "  id={$row['module_id']} name={$row['name']} code={$row['code']}\n";
}

echo "\n=== oc_event WHERE code LIKE 'mytax\_%' ===\n";
$r = $m->query("SELECT event_id, code, trigger, action FROM {$p}event WHERE code LIKE 'mytax\_%'");
while ($row = $r->fetch_assoc()) {
    echo "  id={$row['event_id']} code={$row['code']} trigger={$row['trigger']} action={$row['action']}\n";
}

echo "\n=== oc_setting WHERE code LIKE '%mytax%' ===\n";
$r = $m->query("SELECT setting_id, code, `key`, `value` FROM {$p}setting WHERE code LIKE '%mytax%'");
while ($row = $r->fetch_assoc()) {
    $val = substr($row['value'], 0, 50);
    echo "  id={$row['setting_id']} code={$row['code']} key={$row['key']} value={$val}\n";
}

echo "\n=== Check ALL types in oc_extension for mytax ===\n";
$r = $m->query("SELECT DISTINCT type FROM {$p}extension WHERE code LIKE '%mytax%' ORDER BY type");
while ($row = $r->fetch_assoc()) {
    echo "  type={$row['type']}\n";
}

$m->close();