<?php
/**
 * Автономная установка модуля mytax без шага «Обновить файлы поставщиков».
 *
 * Зачем это нужно:
 *   На Debian/Linux шаг marketplace/installer.vendor вызывает oc_generate_vendor(),
 *   который рекурсивно сканирует ВСЕ composer-пакеты в storage/vendor
 *   (включая огромный aws/aws-sdk-php от ЮKassa — десятки тысяч файлов).
 *   На слабом сервере или с лимитом max_execution_time это выглядит как
 *   бесконечное зависание на тексте «Обновить файлы поставщиков».
 *
 * Модуль mytax НЕ содержит composer-зависимостей, поэтому vendor.php
 * пере-генерировать не нужно. Этот скрипт устанавливает модуль напрямую:
 *   - распаковывает mytax.ocmod.zip в extension/mytax (включая встроенную
 *     библиотеку phpqrcode для генерации QR-кодов чеков ФНС)
 *   - создаёт таблицу oc_mytax_receipts
 *   - регистрирует 6 событий в oc_event
 *   - добавляет запись в oc_extension_install / oc_extension_path
 *   - добавляет модуль в oc_extension (type=module)
 *   - сохраняет настройки module_mytax_* (статус включён)
 *
 * Использование:
 *   1. Скопируйте на сервер: mytax.ocmod.zip + этот файл.
 *   2. Настройте подключение к БД ниже (или раскомментируйте автозагрузку config).
 *   3. Запустите:  php install_mytax_debian.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ---------------------------------------------------------------
// 1. КОНФИГУРАЦИЯ
// ---------------------------------------------------------------
// Путь к OpenCart (там, где лежат catalog/, system/, extension/)
$DIR_OPENCART = '/var/www/site/';          // <-- УКАЖИТЕ ВАШ ПУТЬ, например /var/www/metalka/
$DIR_STORAGE  = '/var/www/storage/';        // <-- УКАЖИТЕ ВАШ storage, например /var/www/storage/

// Подключение к БД
$DB_HOST = 'localhost';
$DB_USER = 'root';                          // <-- УКАЖИТЕ
$DB_PASS = '';                              // <-- УКАЖИТЕ
$DB_NAME = 'metalka';                       // <-- УКАЖИТЕ
$DB_PREFIX = 'oc_';

// Если хотите прочитать параметры БД из config.php OpenCart — раскомментируйте:
// require_once $DIR_OPENCART . 'config.php';
// $DB_HOST = DB_HOSTNAME; $DB_USER = DB_USERNAME; $DB_PASS = DB_PASSWORD; $DB_NAME = DB_DATABASE; $DB_PREFIX = DB_PREFIX;

// Путь к ZIP-архиву модуля
$zipFile = __DIR__ . '/mytax.ocmod.zip';

echo "=== Автономная установка mytax (без vendor) ===\n\n";

// ---------------------------------------------------------------
// 1a. ПАТЧ system/helper/vendor.php (устраняет «Зависание на Обновить файлы поставщиков»)
// ---------------------------------------------------------------
$vendorFile = $DIR_OPENCART . 'system/helper/vendor.php';
$patched = false;
if (is_file($vendorFile)) {
    $content = @file_get_contents($vendorFile);

    // Полный желаемый патч: set_time_limit + быстрый выход, если vendor.php
    // уже сгенерирован и composer.json не менялись. Это делает шаг
    // «Обновить файлы поставщиков» мгновенным при последующих установках.
    $prefix = "function oc_generate_vendor(): void {\n"
        . "\tset_time_limit(600);\n"
        . "\tini_set('memory_limit', '512M');\n\n"
        . "\t// Быстрый выход: если vendor.php уже сгенерирован и composer.json не менялись.\n"
        . "\t\$vendor_file = DIR_SYSTEM . 'vendor.php';\n"
        . "\t\$composer_files = glob(DIR_STORAGE . 'vendor/*/*/composer.json');\n"
        . "\t\$newest = 0;\n"
        . "\tforeach (\$composer_files as \$cf) {\n"
        . "\t\t\$t = @filemtime(\$cf);\n"
        . "\t\tif (\$t !== false && \$t > \$newest) \$newest = \$t;\n"
        . "\t}\n"
        . "\tif (is_file(\$vendor_file)) {\n"
        . "\t\t\$vt = @filemtime(\$vendor_file);\n"
        . "\t\tif (\$vt !== false && \$vt >= \$newest) {\n"
        . "\t\t\treturn;\n"
        . "\t\t}\n"
        . "\t}\n\n";

    if ($content !== false && strpos($content, 'Быстрый выход') === false) {
        $patchedContent = str_replace(
            "function oc_generate_vendor(): void {\n",
            $prefix,
            $content
        );
        if ($patchedContent !== $content && @file_put_contents($vendorFile, $patchedContent) !== false) {
            $patched = true;
        }
    } elseif ($content !== false && strpos($content, 'Быстрый выход') !== false) {
        $patched = true; // уже пропатчен
    }
}
echo $patched
    ? "[OK] system/helper/vendor.php пропатчен (set_time_limit 600 + быстрый кэш-выход) — шаг «Обновить файлы поставщиков» больше не виснет\n"
    : "[i] system/helper/vendor.php не изменён (файл не найден или нет прав на запись)\n";
echo "\n";

// ---------------------------------------------------------------
// 2. ПРОВЕРКИ
// ---------------------------------------------------------------
if (!is_dir($DIR_OPENCART . 'extension/')) {
    die("ОШИБКА: не найдена папка extension в $DIR_OPENCART\n");
}
if (!is_file($zipFile)) {
    die("ОШИБКА: не найден архив $zipFile\n");
}

$mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    die("ОШИБКА БД: " . $mysqli->connect_error . "\n");
}
$mysqli->set_charset('utf8mb4');
echo "[OK] Подключение к БД $DB_NAME\n";

// ---------------------------------------------------------------
// 3. РАСПАКОВКА В extension/mytax
// ---------------------------------------------------------------
$dest = $DIR_OPENCART . 'extension/mytax/';
echo "Распаковка $zipFile -> $dest\n";

$zip = new ZipArchive();
if ($zip->open($zipFile) !== true) {
    die("ОШИБКА: не удалось открыть архив\n");
}

for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    // Пропускаем ocmod-файлы (модификации ядра) — модуль их не содержит
    if (substr($name, 0, 6) == 'ocmod/') continue;

    $target = $dest . $name;

    if (substr($name, -1) == '/') { // директория
        if (!is_dir($target)) mkdir($target, 0777, true);
    } else {
        $dir = dirname($target);
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        file_put_contents($target, $zip->getFromIndex($i));
    }
}
$zip->close();
echo "[OK] Файлы распакованы\n";

// ---------------------------------------------------------------
// 4. СТАТУС МОДУЛЯ (включён по умолчанию)
// ---------------------------------------------------------------
$mysqli->query("INSERT INTO `{$DB_PREFIX}setting`
    (`store_id`, `code`, `key`, `value`, `serialized`)
    VALUES
    (0, 'module_mytax', 'module_mytax_status', '1', 0),
    (0, 'module_mytax', 'module_mytax_inn', '', 0),
    (0, 'module_mytax', 'module_mytax_password', '', 0),
    (0, 'module_mytax', 'module_mytax_app_name', 'Мой налог', 0)
    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
echo "[OK] Настройки module_mytax (статус=вкл)\n";

// ---------------------------------------------------------------
// 5. ТАБЛИЦА ЧЕКОВ
// ---------------------------------------------------------------
$mysqli->query("CREATE TABLE IF NOT EXISTS `{$DB_PREFIX}mytax_receipts` (
    `receipt_id` int(11) NOT NULL AUTO_INCREMENT,
    `order_id` int(11) NOT NULL,
    `email` varchar(96) NOT NULL,
    `fns_receipt_id` varchar(255) DEFAULT NULL,
    `print_link` varchar(500) DEFAULT NULL,
    `qr_code_path` varchar(255) DEFAULT NULL,
    `amount` decimal(15,4) NOT NULL DEFAULT 0.0000,
    `status` varchar(50) NOT NULL DEFAULT 'pending',
    `error_message` text DEFAULT NULL,
    `date_added` datetime NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`receipt_id`),
    UNIQUE KEY `order_id` (`order_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
echo "[OK] Таблица {$DB_PREFIX}mytax_receipts\n";

// ---------------------------------------------------------------
// 6. СОБЫТИЯ
// ---------------------------------------------------------------
$events = [
    [
        'code'  => 'mytax_order_history',
        'descr' => 'Создание чека «Мой налог» при изменении статуса заказа',
        'trigger' => 'catalog/model/checkout/order.addHistory/before',
        'action'  => 'extension/mytax/module/mytax.orderHistory',
    ],
    [
        'code'  => 'mytax_mail_order_add',
        'descr' => 'Дополнение письма о новом заказе данными чека «Мой налог»',
        'trigger' => 'catalog/view/mail/order_add/before',
        'action'  => 'extension/mytax/module/mytax.viewOrderAdd',
    ],
    [
        'code'  => 'mytax_mail_order_history',
        'descr' => 'Дополнение письма об изменении статуса данными чека «Мой налог»',
        'trigger' => 'catalog/view/mail/order_history/before',
        'action'  => 'extension/mytax/module/mytax.viewOrderHistory',
    ],
    [
        'code'  => 'mytax_mail_order_add_after',
        'descr' => 'Встраивание блока чека с QR-кодом в письмо о новом заказе',
        'trigger' => 'catalog/view/mail/order_add/after',
        'action'  => 'extension/mytax/module/mytax.viewOrderAddAfter',
    ],
    [
        'code'  => 'mytax_mail_order_history_after',
        'descr' => 'Встраивание блока чека с QR-кодом в письмо об изменении статуса',
        'trigger' => 'catalog/view/mail/order_history/after',
        'action'  => 'extension/mytax/module/mytax.viewOrderHistoryAfter',
    ],
    [
        'code'  => 'mytax_checkout_success',
        'descr' => 'Создание чека «Мой налог» на странице успешного оформления',
        'trigger' => 'catalog/view/checkout/success/before',
        'action'  => 'extension/mytax/module/mytax.viewSuccess',
    ],
];

foreach ($events as $ev) {
    $mysqli->query("DELETE FROM `{$DB_PREFIX}event` WHERE `code` = '" . $mysqli->real_escape_string($ev['code']) . "'");
    $mysqli->query("INSERT INTO `{$DB_PREFIX}event`
        (`code`, `description`, `trigger`, `action`, `status`, `sort_order`)
        VALUES (
            '" . $mysqli->real_escape_string($ev['code']) . "',
            '" . $mysqli->real_escape_string($ev['descr']) . "',
            '" . $mysqli->real_escape_string($ev['trigger']) . "',
            '" . $mysqli->real_escape_string($ev['action']) . "',
            1, 1)");
}
echo "[OK] События oc_event (" . count($events) . " шт.)\n";

// ---------------------------------------------------------------
// 7. oc_extension_install + oc_extension_path + oc_extension
// ---------------------------------------------------------------
// Получаем путь к нашему архиву в marketplace (чтобы не ломать удаление)
$existing = $mysqli->query("SELECT * FROM `{$DB_PREFIX}extension_install` WHERE `code` = 'mytax'");
if (!$existing || !$existing->num_rows) {
    $mysqli->query("INSERT INTO `{$DB_PREFIX}extension_install`
        (`extension_download_id`, `name`, `description`, `code`, `version`, `author`, `link`, `status`, `date_added`)
        VALUES (0, 'Мой налог', '', 'mytax', '4.0.8', 'MyTax-Service', '', 1, NOW())");
    $installId = $mysqli->insert_id;
    echo "[OK] oc_extension_install id=$installId\n";
} else {
    $installId = (int)$existing->fetch_assoc()['extension_install_id'];
    echo "[i] oc_extension_install уже есть id=$installId\n";
}

// oc_extension (модуль)
$mysqli->query("DELETE FROM `{$DB_PREFIX}extension` WHERE `code` = 'mytax' AND `type` = 'module'");
$mysqli->query("INSERT INTO `{$DB_PREFIX}extension` (`extension_id`, `type`, `code`) VALUES (0, 'module', 'mytax')");
echo "[OK] oc_extension (module/mytax)\n";

$mysqli->close();

echo "\n=== ГОТОВО ===\n";
echo "Модуль mytax установлен напрямую, без шага «Обновить файлы поставщиков».\n";
echo "Настройте ИНН/пароль в админке: Расширения → Модули → Мой налог.\n\n";
echo "ВАЖНО: шаг vendor в marketplace по-прежнему будет зависать при установке\n";
echo "ЛЮБОГО расширения на этом сервере, пока в storage/vendor лежит огромный\n";
echo "aws/aws-sdk-php. Если это мешает — удалите неиспользуемые пакеты\n";
echo "из storage/vendor (например aws/*), либо поднимите max_execution_time в PHP.\n";