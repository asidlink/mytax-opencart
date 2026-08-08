<?php
$m = new mysqli('localhost', 'root', '', 'metalka');
if ($m->connect_error) die("Connection failed\n");

echo "=== Fixing oc_extension records ===\n";

// Удаляем неправильную запись с NULL extension (id=88)
$m->query("DELETE FROM `oc_extension` WHERE extension_id = 88");
echo "Deleted extension_id=88 (NULL extension)\n";

// Проверяем запись id=90 (mytax module) — правильная ли она
$r = $m->query("SELECT * FROM `oc_extension` WHERE extension_id = 90");
$row = $r->fetch_assoc();
echo "Extension id=90: extension='{$row['extension']}', type='{$row['type']}', code='{$row['code']}'\n";

// Правильная запись для payment должна быть: extension='mytax', type='payment', code='mytax'
$r = $m->query("SELECT * FROM `oc_extension` WHERE `extension` = 'mytax' AND `type` = 'payment'");
if ($r->num_rows == 0) {
    $m->query("INSERT INTO `oc_extension` (`extension`, `type`, `code`) VALUES ('mytax', 'payment', 'mytax')");
    echo "Added correct payment record\n";
}

echo "\n=== Все записи mytax ===\n";
$r = $m->query("SELECT * FROM `oc_extension` WHERE `extension` = 'mytax'");
while ($row = $r->fetch_assoc()) {
    echo "  id={$row['extension_id']}, extension='{$row['extension']}', type='{$row['type']}', code='{$row['code']}'\n";
}

echo "\n=== Проверка getExtensions() - поиск NULL extension ===\n";
$r = $m->query("SELECT * FROM `oc_extension` WHERE `extension` IS NULL");
echo "NULL extensions remaining: " . $r->num_rows . "\n";

$m->close();
echo "\nDone. Ошибка ucwords() должна исчезнуть.\n";