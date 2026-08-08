<?php
$m = new mysqli('localhost', 'root', '', 'metalka');
if ($m->connect_error) die("Connection failed: " . $m->connect_error . "\n");

$prefix = 'oc_';

echo "=== EXTENSION INSTALL ===\n";
$r = $m->query("SELECT * FROM {$prefix}extension_install WHERE code LIKE '%mytax%'");
while ($row = $r->fetch_assoc()) {
    echo '  id=' . $row['extension_install_id'] . ', code=' . $row['code'] . ', version=' . $row['version'] . "\n";
}
echo "Total: " . $r->num_rows . "\n";

echo "\n=== EXTENSION PATH ===\n";
$r = $m->query("SELECT * FROM {$prefix}extension_path WHERE path LIKE '%mytax%'");
while ($row = $r->fetch_assoc()) {
    echo '  id=' . $row['extension_path_id'] . ', install_id=' . $row['extension_install_id'] . ', path=' . $row['path'] . "\n";
}
echo "Total: " . $r->num_rows . "\n";

echo "\n=== EXTENSION ===\n";
$r = $m->query("SELECT * FROM {$prefix}extension WHERE code = 'mytax'");
while ($row = $r->fetch_assoc()) {
    echo '  id=' . $row['extension_id'] . ', ext=' . ($row['extension'] ?? 'NULL') . ', type=' . $row['type'] . ', code=' . $row['code'] . "\n";
}
echo "Total: " . $r->num_rows . "\n";

echo "\n=== ALL mytax-like records in extension ===\n";
$r = $m->query("SELECT * FROM {$prefix}extension WHERE extension = 'mytax' OR code = 'mytax' OR code LIKE '%mytax%'");
while ($row = $r->fetch_assoc()) {
    echo '  id=' . $row['extension_id'] . ', ext=' . ($row['extension'] ?? 'NULL') . ', type=' . $row['type'] . ', code=' . $row['code'] . "\n";
}
echo "Total: " . $r->num_rows . "\n";

echo "\n=== MODULE ===\n";
$r = $m->query("SELECT * FROM {$prefix}module WHERE code = 'mytax'");
while ($row = $r->fetch_assoc()) {
    echo '  id=' . $row['module_id'] . ', code=' . $row['code'] . "\n";
}
echo "Total: " . $r->num_rows . "\n";

echo "\n=== EVENT ===\n";
$r = $m->query("SELECT * FROM {$prefix}event WHERE code LIKE 'mytax%'");
while ($row = $r->fetch_assoc()) {
    echo '  id=' . $row['event_id'] . ', code=' . $row['code'] . ', trigger=' . $row['trigger'] . "\n";
}
echo "Total: " . $r->num_rows . "\n";

echo "\n=== SETTING ===\n";
$r = $m->query("SELECT * FROM {$prefix}setting WHERE code LIKE '%mytax%' OR `key` LIKE '%mytax%'");
while ($row = $r->fetch_assoc()) {
    echo '  key=' . $row['key'] . ', value=' . substr($row['value'] ?? '', 0, 80) . "\n";
}
echo "Total: " . $r->num_rows . "\n";

echo "\n=== mytax_receipts table ===\n";
$r = $m->query("SHOW TABLES LIKE '{$prefix}mytax_receipts'");
if ($r->num_rows > 0) {
    $r2 = $m->query("SELECT COUNT(*) as cnt FROM {$prefix}mytax_receipts");
    $cnt = $r2->fetch_assoc()['cnt'];
    echo "Table exists, rows: $cnt\n";
} else {
    echo "Table does not exist\n";
}

echo "\n=== Checking Layout modules referencing mytax ===\n";
$r = $m->query("SELECT * FROM {$prefix}module WHERE code = 'mytax' OR code LIKE '%mytax%'");
while ($row = $r->fetch_assoc()) {
    echo '  module_id=' . $row['module_id'] . ', code=' . $row['code'] . ', name=' . ($row['name'] ?? '') . "\n";
    // Check if this module is used in layouts
    $r2 = $m->query("SELECT * FROM {$prefix}module_to_layout WHERE module_id = " . $row['module_id']);
    while ($row2 = $r2->fetch_assoc()) {
        echo '    layout_id=' . $row2['layout_id'] . ', position=' . $row2['position'] . "\n";
    }
}

$m->close();
echo "\nDone.\n";