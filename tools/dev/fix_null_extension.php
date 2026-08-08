<?php
$m = new mysqli('localhost', 'root', '', 'metalka');
if ($m->connect_error) die("Connection failed\n");

echo "=== Checking for NULL extensions ===\n";
$r = $m->query('SELECT * FROM `oc_extension` WHERE `extension` IS NULL OR `extension` = "" OR `extension` = 0');
echo "Found: " . $r->num_rows . "\n";
while ($row = $r->fetch_assoc()) {
    echo "  extension_id={$row['extension_id']}, type={$row['type']}, extension='" . var_export($row['extension'], true) . "'\n";
    // Delete bad records
    // $m->query("DELETE FROM `oc_extension` WHERE extension_id = {$row['extension_id']}");
    // echo "  Deleted\n";
}

// Also check what getExtensions returns
echo "\n=== All extensions ===\n";
$r = $m->query('SELECT * FROM `oc_extension` ORDER BY type, extension');
while ($row = $r->fetch_assoc()) {
    $ext = $row['extension'] ?? 'NULL';
    echo "  id={$row['extension_id']}, type={$row['type']}, ext={$ext}\n";
}

$m->close();