<?php
$m = new mysqli('localhost', 'root', '', 'metalka');

echo "=== oc_mytax_receipts (последние) ===\n";
$r = $m->query("SELECT receipt_id, order_id, status, error_message, fns_receipt_id, date_added FROM oc_mytax_receipts ORDER BY receipt_id DESC LIMIT 10");
while ($row = $r->fetch_assoc()) {
    echo "  id={$row['receipt_id']} order={$row['order_id']} status={$row['status']} date={$row['date_added']}\n";
    if ($row['error_message']) echo "    ERROR: " . substr($row['error_message'], 0, 300) . "\n";
    if ($row['fns_receipt_id']) echo "    receipt={$row['fns_receipt_id']}\n";
}

echo "\n=== Настройки module_mytax ===\n";
$r = $m->query("SELECT `key`, `value` FROM oc_setting WHERE `key` LIKE 'module_mytax%'");
while ($row = $r->fetch_assoc()) {
    $v = $row['value'];
    if (strpos($row['key'], 'password') !== false) $v = '***';
    echo "  {$row['key']} = " . substr($v, 0, 50) . "\n";
}

echo "\n=== События mytax ===\n";
$r = $m->query("SELECT code, `trigger`, action, status FROM oc_event WHERE code LIKE 'mytax%'");
while ($row = $r->fetch_assoc()) {
    echo "  {$row['code']} | {$row['trigger']} | {$row['action']} | status={$row['status']}\n";
}

echo "\n=== oc_extension ===\n";
$r = $m->query("SELECT extension_id, `extension`, type, code FROM oc_extension WHERE code='mytax'");
while ($row = $r->fetch_assoc()) {
    echo "  id={$row['extension_id']} ext={$row['extension']} type={$row['type']}\n";
}

$m->close();