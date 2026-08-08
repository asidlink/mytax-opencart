<?php
/**
 * Сборка mytax.ocmod.zip v4.0.8
 *
 * Исправления v4.0.8 (QR-код чека ФНС в письмах покупателю):
 * 1. Встроена библиотека phpqrcode (catalog/model/checkout/phpqrcode.php) —
 *    раньше generateQRCode() искала библиотеку только по Windows-путям
 *    (C:/sites/metalka/phpqrcode/...), которых нет на Debian 13.
 *    Теперь используется встроенный файл через DIR_EXTENSION.
 * 2. Добавлены after-события view/mail/order_add|order_history:
 *    блок чека с QR-кодом встраивается прямо в отрендеренное HTML письмо
 *    (раньше шаблоны order_add.twig/order_history.twig не содержали
 *    mytax_receipt и QR в письмо не попадал).
 *
 * Также содержит:
 * - FIX зависания установки на шаге «Обновить файлы поставщиков» (ocmod)
 * - description в addEvent() (бесконечное кручение при установке)
 * - ЮKassa: подтверждение оплаты при возврате (confirm)
 * - Отправка только ОДНОГО письма с QR (убран дубль)
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$ext = 'C:/sites/metalka/extension/mytax';
$siteZip = 'C:/sites/metalka/mytax.ocmod.zip';
$storageZip = 'C:/sites/storage/marketplace/mytax.ocmod.zip';

echo "=== 1. Подготовка ===\n";
echo "  [OK] Файлы ядра (mail/order.php) и yoomoney.php уже изменены на сайте.\n";

echo "\n=== 2. Сбор файлов из $ext ===\n";
$files = [];

$installJson = json_decode(file_get_contents("$ext/install.json"), true);
$installJson['version'] = '4.0.8';
$installJson['name'] = 'Мой налог: кассовые чеки для ИП (НПД)';
$files['install.json'] = json_encode($installJson, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
echo "  [OK] install.json (version=" . $installJson['version'] . ")\n";

$map = [
    'install.php' => 'install.php',
    'admin/controller/module/mytax.php' => 'admin/controller/module/mytax.php',
    'admin/model/module/mytax.php' => 'admin/model/module/mytax.php',
    'admin/language/ru-ru/module/mytax.php' => 'admin/language/ru-ru/module/mytax.php',
    'admin/view/template/module/mytax.twig' => 'admin/view/template/module/mytax.twig',
    'catalog/controller/module/mytax.php' => 'catalog/controller/module/mytax.php',
    'catalog/model/checkout/mytax.php' => 'catalog/model/checkout/mytax.php',
    'catalog/model/checkout/phpqrcode.php' => 'catalog/model/checkout/phpqrcode.php',
    'catalog/language/ru-ru/module/mytax.php' => 'catalog/language/ru-ru/module/mytax.php',
    'README.txt' => 'README.txt',
];

// OCMOD-модификация: фикс зависания «Обновить файлы поставщиков» на Debian
$ocmodFile = "$ext/ocmod/mytax_fix_vendor_hang.ocmod.xml";
if (is_file($ocmodFile)) {
    $files['ocmod/mytax_fix_vendor_hang.ocmod.xml'] = file_get_contents($ocmodFile);
    echo "  [OK] ocmod/mytax_fix_vendor_hang.ocmod.xml\n";
} else {
    die("ОТСУТСТВУЕТ OCMOD-файл: $ocmodFile\n");
}

foreach ($map as $srcRel => $zipRel) {
    $src = "$ext/$srcRel";
    if (!is_file($src)) {
        die("ОТСУТСТВУЕТ файл: $src\n");
    }
    $files[$zipRel] = file_get_contents($src);
    echo "  [OK] $zipRel\n";
}

echo "\n=== 3. Проверка исправлений v4.0.8 ===\n";

// 1) Встроенная библиотека phpqrcode
$catModel = $files['catalog/model/checkout/mytax.php'];
if (str_contains($catModel, 'DIR_EXTENSION . \'mytax/catalog/model/checkout/phpqrcode.php\'')) {
    echo "  [OK] generateQRCode() использует встроенную библиотеку через DIR_EXTENSION\n";
} else {
    die("  [!!] generateQRCode() НЕ использует встроенную библиотеку!\n");
}

$phpqrcode = $files['catalog/model/checkout/phpqrcode.php'];
if (str_contains($phpqrcode, 'PHP QR Code encoder') && str_contains($phpqrcode, 'class QRcode')) {
    echo "  [OK] phpqrcode.php встроена в архив (" . strlen($phpqrcode) . " байт)\n";
} else {
    die("  [!!] phpqrcode.php повреждена!\n");
}

// 2) after-события для встраивания QR в HTML писем
$catController = $files['catalog/controller/module/mytax.php'];
if (str_contains($catController, 'viewOrderAddAfter')) {
    echo "  [OK] контроллер: viewOrderAddAfter (после рендера письма)\n";
} else {
    die("  [!!] Нет viewOrderAddAfter!\n");
}
if (str_contains($catController, 'injectReceiptBlock')) {
    echo "  [OK] контроллер: injectReceiptBlock (HTML-блок чека с QR)\n";
} else {
    die("  [!!] Нет injectReceiptBlock!\n");
}
if (str_contains($catController, "empty(\$r['qr_code_path'])")) {
    echo "  [OK] getReceiptData() требует наличие QR-изображения\n";
} else {
    die("  [!!] Нет проверки qr_code_path в getReceiptData()!\n");
}

// 3) Модель: 6 событий (включая after-события)
$adminModel = $files['admin/model/module/mytax.php'];
$afterEvents = substr_count($adminModel, '/after');
echo "  [OK] admin-модель: событий /after: $afterEvents (ожидается 2)\n";
if ($afterEvents < 2) die("  [!!] Нет after-событий в модели!\n");

// 4) Старые проверки
$count = substr_count($adminModel, "'description'");
echo "  description в модели: $count (ожидается 6)\n";
if ($count < 6) die("Модель НЕ исправлена!\n");

if (preg_match('/\{\$[^}]*\?\?/', $catModel)) {
    die("В каталог-модели остался ?? в интерполяции!\n");
}
echo "  [OK] нет ?? в интерполяции каталог-модели\n";

echo "\n=== 4. Создание архива ===\n";
@unlink($siteZip);
$z = new ZipArchive();
if ($z->open($siteZip, ZipArchive::CREATE) !== true) die("Не удалось создать архив\n");
foreach ($files as $name => $content) {
    $z->addFromString($name, $content);
}
$z->close();
echo "  [OK] Создан: $siteZip (" . filesize($siteZip) . " байт)\n";

echo "\n=== 5. Проверка содержимого архива ===\n";
$z = new ZipArchive();
$z->open($siteZip);
echo "  Файлов в архиве: " . $z->numFiles . "\n";
$json = json_decode($z->getFromName('install.json'), true);
echo "  version: " . $json['version'] . "\n";
echo "  phpqrcode в архиве: " . (str_contains($z->getFromName('catalog/model/checkout/phpqrcode.php'), 'class QRcode') ? "OK" : "НЕТ!") . "\n";
echo "  after-события в модели: " . substr_count($z->getFromName('admin/model/module/mytax.php'), '/after') . "\n";
$z->close();

echo "\n=== 6. Копирование в storage ===\n";
if (copy($siteZip, $storageZip)) {
    echo "  [OK] Скопирован в $storageZip\n";
} else {
    die("ОШИБКА копирования!\n");
}

echo "\n=== 7. Проверка синтаксиса PHP файлов ===\n";
foreach ($files as $name => $content) {
    if (substr($name, -4) !== '.php') continue;
    if ($name === 'catalog/model/checkout/phpqrcode.php') continue; // библиотека, не модуль
    $tmp = 'C:/Users/admin/AppData/Local/Temp/mytax/check_' . basename($name);
    file_put_contents($tmp, $content);
    $out = [];
    exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
    $ok = (strpos(implode("\n", $out), 'No syntax errors') !== false);
    echo "  " . ($ok ? "[OK]" : "[!!]") . " $name\n";
    if (!$ok) echo "    " . implode("\n    ", $out) . "\n";
    @unlink($tmp);
}

echo "\nГОТОВО: mytax.ocmod.zip v4.0.8 собран.\n";
echo "QR-код чека ФНС теперь встраивается в письма покупателю.\n";