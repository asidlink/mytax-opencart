<?php
/**
 * Обновление событий mytax в локальной БД (добавление after-событий для QR в письмах).
 */
$m = new mysqli('localhost', 'root', '', 'metalka');
if ($m->connect_errno) die("ОШИБКА БД: " . $m->connect_error . "\n");
$m->set_charset('utf8mb4');

$events = [
    ['code'  => 'mytax_mail_order_add_after',
     'descr' => 'Встраивание блока чека с QR-кодом в письмо о новом заказе',
     'trigger' => 'catalog/view/mail/order_add/after',
     'action'  => 'extension/mytax/module/mytax.viewOrderAddAfter'],
    ['code'  => 'mytax_mail_order_history_after',
     'descr' => 'Встраивание блока чека с QR-кодом в письмо об изменении статуса',
     'trigger' => 'catalog/view/mail/order_history/after',
     'action'  => 'extension/mytax/module/mytax.viewOrderHistoryAfter'],
];

foreach ($events as $ev) {
    $m->query("DELETE FROM oc_event WHERE code = '" . $m->real_escape_string($ev['code']) . "'");
    $m->query("INSERT INTO oc_event (`code`, `description`, `trigger`, `action`, `status`, `sort_order`)
        VALUES ('" . $m->real_escape_string($ev['code']) . "',
                '" . $m->real_escape_string($ev['descr']) . "',
                '" . $m->real_escape_string($ev['trigger']) . "',
                '" . $m->real_escape_string($ev['action']) . "',
                1, 1)");
    echo "[OK] Событие: " . $ev['code'] . "\n";
}

// Проверка
$r = $m->query("SELECT code, `trigger`, action FROM oc_event WHERE code LIKE 'mytax%' ORDER BY code");
echo "\nСобытия mytax в БД:\n";
while ($row = $r->fetch_assoc()) {
    echo "  - {$row['code']}: {$row['trigger']} -> {$row['action']}\n";
}
$m->close();
echo "\nГОТОВО\n";