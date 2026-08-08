<?php
$site = 'C:/sites/metalka';
$adm = 'dmt';
$tM = 'C:/Users/admin/AppData/Local/Temp/mytax_zip_build';
$tP = 'C:/Users/admin/AppData/Local/Temp/zip_final';

// ОТДЕЛЬНО: исправление ошибки ucwords(null)
// В oc_extension должно быть поле extension, а не code
$m = new mysqli('localhost', 'root', '', 'metalka');
$p = 'oc_';

// Проверяем структуру
$cols = $m->query("SHOW COLUMNS FROM {$p}extension");
echo "=== Поля oc_extension ===\n";
while ($c = $cols->fetch_assoc()) {
    echo "  {$c['Field']} ({$c['Type']})\n";
}

// Смотрим YooMoney как эталон
echo "\n=== YooMoney extension row ===\n";
$r = $m->query("SELECT * FROM {$p}extension WHERE code='yoomoney'");
if ($r->num_rows > 0) {
    $row = $r->fetch_assoc();
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}

// Смотрим нашу запись mytax
echo "\n=== MyTax extension row ===\n";
$r = $m->query("SELECT * FROM {$p}extension WHERE code='mytax'");
if ($r->num_rows > 0) {
    $row = $r->fetch_assoc();
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    
    // Исправляем: extension должен быть равен code если он NULL
    if ($row['extension'] === null) {
        $m->query("UPDATE {$p}extension SET `extension`='mytax' WHERE code='mytax'");
        echo "  [FIXED] extension=null -> extension='mytax'\n";
    }
}

// Остальные наши записи
echo "\n=== Все extension строки mytax ===\n";
$r = $m->query("SELECT * FROM {$p}extension WHERE code LIKE '%mytax%' OR `extension` LIKE '%mytax%'");
while ($row = $r->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}

$m->close();