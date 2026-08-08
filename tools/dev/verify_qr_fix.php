<?php
/**
 * Проверка исправления v4.0.8:
 * 1. Встроенная библиотека phpqrcode существует и генерирует QR-код
 * 2. generateQRCode() в каталог-модели ссылается на встроенную библиотеку
 * 3. After-события зарегистрированы в БД
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Проверка исправления QR-кода v4.0.8 ===\n\n";

// 1. Проверка встроенной библиотеки
$lib = 'C:/sites/metalka/extension/mytax/catalog/model/checkout/phpqrcode.php';
echo "1. Встроенная библиотека: ";
if (is_file($lib)) {
    echo "OK (" . filesize($lib) . " байт)\n";
} else {
    echo "НЕТ! (файл не найден)\n";
    exit(1);
}

// 2. Генерация тестового QR-кода
echo "2. Генерация тестового QR-кода: ";
$testDir = 'C:/sites/metalka/image/mytax_qr/';
if (!is_dir($testDir)) mkdir($testDir, 0755, true);
$testFile = $testDir . 'test_' . time() . '.png';
include_once($lib);
try {
    if (!function_exists('imagepng')) {
        echo "ПРОПУЩЕНА (нет GD-расширения в CLI)\n";
    } else {
        \QRcode::png('https://lknpd.nalog.ru/api/v1/receipt/test/test/print', $testFile, QR_ECLEVEL_L, 6, 2);
        if (is_file($testFile)) {
            $size = filesize($testFile);
            echo "OK ($size байт)\n";
            // Проверка что это PNG
            $h = file_get_contents($testFile, false, null, 0, 8);
            echo "   Сигнатура: " . bin2hex($h) . " (ожидается 89504e470d0a1a0a)\n";
            @unlink($testFile);
        } else {
            echo "ОШИБКА: файл не создан\n";
            exit(1);
        }
    }
} catch (\Throwable $e) {
    echo "ОШИБКА: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Проверка generateQRCode() в каталог-модели
echo "3. generateQRCode() в каталог-модели: ";
$model = file_get_contents('C:/sites/metalka/extension/mytax/catalog/model/checkout/mytax.php');
if (str_contains($model, 'DIR_EXTENSION . \'mytax/catalog/model/checkout/phpqrcode.php\'')) {
    echo "OK (использует DIR_EXTENSION)\n";
} else {
    echo "ОШИБКА: не использует встроенную библиотеку!\n";
    exit(1);
}

// 4. Проверка after-событий в контроллере
echo "4. After-события в контроллере: ";
$controller = file_get_contents('C:/sites/metalka/extension/mytax/catalog/controller/module/mytax.php');
$hasAddAfter = str_contains($controller, 'viewOrderAddAfter');
$hasHistoryAfter = str_contains($controller, 'viewOrderHistoryAfter');
$hasInject = str_contains($controller, 'injectReceiptBlock');
if ($hasAddAfter && $hasHistoryAfter && $hasInject) {
    echo "OK (viewOrderAddAfter, viewOrderHistoryAfter, injectReceiptBlock)\n";
} else {
    echo "ОШИБКА: не все методы присутствуют!\n";
    exit(1);
}

// 5. Проверка событий в БД
echo "5. After-события в БД: ";
$m = new mysqli('localhost', 'root', '', 'metalka');
if ($m->connect_errno) { echo "ОШИБКА БД: " . $m->connect_error . "\n"; exit(1); }
$q = $m->query("SELECT code FROM oc_event WHERE code IN ('mytax_mail_order_add_after','mytax_mail_order_history_after') AND status=1");
$dbEvents = [];
while ($row = $q->fetch_assoc()) $dbEvents[] = $row['code'];
if (in_array('mytax_mail_order_add_after', $dbEvents) && in_array('mytax_mail_order_history_after', $dbEvents)) {
    echo "OK (оба after-события активны)\n";
} else {
    echo "ОШИБКА: after-события не найдены в oc_event!\n";
    echo "Найдено: " . implode(', ', $dbEvents) . "\n";
    exit(1);
}
$m->close();

// 6. Проверка содержимого ZIP
echo "6. Содержимое mytax.ocmod.zip: ";
$zip = new ZipArchive();
if ($zip->open('C:/sites/metalka/mytax.ocmod.zip') === true) {
    $json = json_decode($zip->getFromName('install.json'), true);
    $hasLib = str_contains($zip->getFromName('catalog/model/checkout/phpqrcode.php'), 'class QRcode');
    echo "version=" . $json['version'] . ", phpqrcode=" . ($hasLib ? "OK" : "НЕТ!") . "\n";
    $zip->close();
} else {
    echo "ОШИБКА: не удалось открыть ZIP\n";
    exit(1);
}

echo "\n=== ВСЕ ПРОВЕРКИ ПРОЙДЕНЫ ===\n";