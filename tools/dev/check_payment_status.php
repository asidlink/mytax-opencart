<?php
$m = new mysqli('localhost', 'root', '', 'metalka');
$p = 'oc_';

// 1. Настройки Ю-Кассы (какой статус ставится при успешной оплате)
echo "=== Настройки Ю-Кассы (payment_yoomoney) ===\n";
$r = $m->query("SELECT `key`, `value` FROM {$p}setting WHERE code='payment_yoomoney' AND (`key` LIKE '%status%' OR `key` LIKE '%order%')");
while ($row = $r->fetch_assoc()) {
    echo "  {$row['key']} = {$row['value']}\n";
}

// 2. Все статусы заказов
echo "\n=== Статусы заказов ===\n";
$r = $m->query("SELECT order_status_id, name FROM {$p}order_status WHERE language_id=1 ORDER BY order_status_id");
while ($row = $r->fetch_assoc()) {
    echo "  id={$row['order_status_id']} name={$row['name']}\n";
}

// 3. Последние заказы с их статусами
echo "\n=== Последние заказы ===\n";
$r = $m->query("SELECT order_id, order_status_id, payment_code, payment_method FROM {$p}order ORDER BY order_id DESC LIMIT 10");
while ($row = $r->fetch_assoc()) {
    echo "  id={$row['order_id']} status={$row['order_status_id']} pay_code={$row['payment_code']} method={$row['payment_method']}\n";
}

// 4. История статусов заказов (что менялось)
echo "\n=== История последних заказов ===\n";
$r = $m->query("SELECT oh.order_id, oh.order_status_id, oh.notify, oh.date_added, os.name FROM {$p}order_history oh LEFT JOIN {$p}order_status os ON oh.order_status_id=os.order_status_id AND os.language_id=1 WHERE oh.order_id IN (SELECT order_id FROM {$p}order ORDER BY order_id DESC LIMIT 5) ORDER BY oh.order_id DESC, oh.date_added DESC LIMIT 20");
while ($row = $r->fetch_assoc()) {
    echo "  order={$row['order_id']} status={$row['order_status_id']} ({$row['name']}) notify={$row['notify']} date={$row['date_added']}\n";
}

$m->close();