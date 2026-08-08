<?php
/**
 * Полная установка mytax через CLI без входа в админку
 * 1. Полная очистка БД
 * 2. Добавление записей в БД вручную (как делает установщик)
 * 3. Копирование файлов в правильные каталоги
 */

$site = 'C:/sites/metalka';
$admin = 'dmt'; // имя папки админа

// ===== 1. Очистка БД =====
echo "=== 1. ОЧИСТКА БД ===\n";
$m = new mysqli('localhost', 'root', '', 'metalka');
$p = 'oc_';

$m->query("DELETE FROM {$p}extension WHERE code = 'mytax'");
$m->query("DELETE FROM {$p}extension_install WHERE code = 'mytax'");
$m->query("DELETE FROM {$p}extension_path WHERE path LIKE '%mytax%'");
$m->query("DELETE FROM {$p}module WHERE code = 'mytax'");
$m->query("DELETE FROM {$p}event WHERE code LIKE 'mytax%'");
$m->query("DELETE FROM {$p}setting WHERE code LIKE '%mytax%'");
$m->query("DROP TABLE IF EXISTS {$p}mytax_receipts");
echo "  [OK] БД очищена\n";

// ===== 2. Создание записей в БД (как Extension Installer) =====
echo "\n=== 2. ДОБАВЛЕНИЕ ЗАПИСЕЙ В БД ===\n";

// oc_extension (главная запись)
$m->query("INSERT INTO {$p}extension SET `extension_id` = 0, `type` = 'module', `code` = 'mytax'");
$extId = $m->insert_id;
echo "  [OK] oc_extension: id=$extId type=module code=mytax\n";

// oc_extension_install
$m->query("INSERT INTO {$p}extension_install SET `extension_id` = $extId, `extension_download_id` = 0, `code` = 'mytax', `name` = 'Мой налог: кассовые чеки для ИП (НПД)', `version` = '2.0.1', `author` = 'MyTax-Service', `link` = 'https://github.com/Ga1maz/fns-receipt-service', `status` = 1, `date_added` = NOW(), `date_modified` = NOW()");
$instId = $m->insert_id;
echo "  [OK] oc_extension_install: id=$instId\n";

// oc_extension_path (путь к файлам в extension/)
$paths = [
    'extension/mytax/admin/controller/module/mytax.php',
    'extension/mytax/admin/model/module/mytax.php',
    'extension/mytax/admin/language/ru-ru/module/mytax.php',
    'extension/mytax/admin/view/template/module/mytax.twig',
    'extension/mytax/catalog/controller/module/mytax.php',
    'extension/mytax/catalog/model/checkout/mytax.php',
    'extension/mytax/catalog/language/ru-ru/module/mytax.php',
];
foreach ($paths as $path) {
    $m->query("INSERT INTO {$p}extension_path SET `extension_install_id` = $instId, `path` = '$path', `date_added` = NOW()");
}
echo "  [OK] oc_extension_path: " . count($paths) . " записей\n";

// oc_event
$events = [
    ['mytax_order_history', 'catalog/model/checkout/order.addHistory/before', 'extension/mytax/module/mytax.orderHistory', 1],
    ['mytax_mail_order_history', 'catalog/view/mail/order_history/before', 'extension/mytax/module/mytax.viewOrderHistory', 1],
    ['mytax_mail_order_add', 'catalog/view/mail/order_add/before', 'extension/mytax/module/mytax.viewOrderAdd', 1],
];
foreach ($events as $e) {
    $m->query("INSERT INTO {$p}event SET `code` = '{$e[0]}', `description` = '', `trigger` = '{$e[1]}', `action` = '{$e[2]}', `status` = {$e[3]}, `sort_order` = 1");
}
echo "  [OK] oc_event: " . count($events) . " записей\n";

// Создаём таблицу
$m->query("CREATE TABLE IF NOT EXISTS {$p}mytax_receipts (
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
echo "  [OK] oc_mytax_receipts: таблица создана\n";

$m->close();

// ===== 3. Копирование файлов =====
echo "\n=== 3. КОПИРОВАНИЕ ФАЙЛОВ ===\n";

// Удалим старые файлы mytax везде
function delTree($dir) {
    if (!is_dir($dir)) return;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $f) $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
    @rmdir($dir);
}

// Удаляем старые папки
delTree("$site/extension/mytax");
delTree("$site/$admin/controller/extension/module/mytax");
delTree("$site/$admin/model/extension/module/mytax");
delTree("$site/$admin/language/ru-ru/extension/module/mytax");
delTree("$site/$admin/view/template/extension/module/mytax");
delTree("$site/catalog/controller/extension/module/mytax");
delTree("$site/catalog/model/extension/mytax");
delTree("$site/catalog/language/ru-ru/extension/module/mytax");

// Удаляем старые файлы (от payment)
@unlink("$site/$admin/controller/extension/mytax/payment/mytax.php");
@unlink("$site/$admin/model/extension/mytax/payment/mytax.php");
@unlink("$site/$admin/language/ru-ru/extension/mytax/payment/mytax.php");
@unlink("$site/$admin/view/template/extension/mytax/payment/mytax.twig");

$tM = 'C:/Users/admin/AppData/Local/Temp/mytax_zip_build';

// Файлы в extension/mytax/ (исходники для установщика)
$pairs = [
    "$tM/admin/controller/module/mytax.php" => "$site/extension/mytax/admin/controller/module/mytax.php",
    "$tM/admin/model/module/mytax.php" => "$site/extension/mytax/admin/model/module/mytax.php",
    "$tM/admin/language/ru-ru/module/mytax.php" => "$site/extension/mytax/admin/language/ru-ru/module/mytax.php",
    "$tM/admin/view/template/module/mytax.twig" => "$site/extension/mytax/admin/view/template/module/mytax.twig",
    "$tM/catalog/language/ru-ru/module/mytax.php" => "$site/extension/mytax/catalog/language/ru-ru/module/mytax.php",
];

// Catalog controller из pack.php
$catalogCtrl = '<?php
namespace Opencart\Catalog\Controller\Extension\Mytax\Module;
class Mytax extends \Opencart\System\Engine\Controller {
    public function index(array $args): void {}
    public function orderHistory(string &$route, array &$args): void {
        $this->load->model("extension/mytax/checkout/mytax");
        $order_id = $args[0];
        $this->load->model("checkout/order");
        $order_info = $this->model_checkout_order->getOrder($order_id);
        if ($order_info) $this->model_extension_mytax_checkout_mytax->createReceipt($order_id, $order_info["email"]);
    }
    public function viewOrderHistory(string &$route, array &$args): void {
        $this->load->model("extension/mytax/checkout/mytax");
        $order_id = $args["order_id"] ?? 0;
        if ($order_id) { $r = $this->model_extension_mytax_checkout_mytax->getReceiptByOrderId($order_id); if ($r) $args["mytax"] = $r; }
    }
    public function viewOrderAdd(string &$route, array &$args): void {
        $this->load->model("extension/mytax/checkout/mytax");
        $order_id = $args["order_id"] ?? 0;
        if ($order_id) { $r = $this->model_extension_mytax_checkout_mytax->getReceiptByOrderId($order_id); if ($r) $args["mytax"] = $r; }
    }
}';
$pairs2 = [
    "$tM/admin/controller/module/mytax.php" => "$site/extension/mytax/admin/controller/module/mytax.php",
    "$tM/admin/model/module/mytax.php" => "$site/extension/mytax/admin/model/module/mytax.php",
    "$tM/admin/language/ru-ru/module/mytax.php" => "$site/extension/mytax/admin/language/ru-ru/module/mytax.php",
    "$tM/admin/view/template/module/mytax.twig" => "$site/extension/mytax/admin/view/template/module/mytax.twig",
];

foreach ($pairs2 as $src => $dst) {
    @mkdir(dirname($dst), 0777, true);
    copy($src, $dst);
    echo "  [OK] " . str_replace("$site/", '', $dst) . "\n";
}

// Catalog controller
@mkdir("$site/extension/mytax/catalog/controller/module", 0777, true);
file_put_contents("$site/extension/mytax/catalog/controller/module/mytax.php", $catalogCtrl);
echo "  [OK] extension/mytax/catalog/controller/module/mytax.php\n";

// Catalog model
$tP = 'C:/Users/admin/AppData/Local/Temp/zip_final';
@mkdir("$site/extension/mytax/catalog/model/checkout", 0777, true);
copy("$tP/catalog/model/checkout/mytax.php", "$site/extension/mytax/catalog/model/checkout/mytax.php");
echo "  [OK] extension/mytax/catalog/model/checkout/mytax.php\n";

// Catalog language
@mkdir("$site/extension/mytax/catalog/language/ru-ru/module", 0777, true);
copy("$tM/catalog/language/ru-ru/module/mytax.php", "$site/extension/mytax/catalog/language/ru-ru/module/mytax.php");
echo "  [OK] extension/mytax/catalog/language/ru-ru/module/mytax.php\n";

// ===== 4. Копирование в dmt/ (admin) =====
echo "\n=== 4. КОПИРОВАНИЕ В dmt/ ===\n";
$adminPairs = [
    "$site/extension/mytax/admin/controller/module/mytax.php" => "$site/$admin/controller/extension/module/mytax.php",
    "$site/extension/mytax/admin/model/module/mytax.php" => "$site/$admin/model/extension/module/mytax.php",
    "$site/extension/mytax/admin/language/ru-ru/module/mytax.php" => "$site/$admin/language/ru-ru/extension/module/mytax.php",
    "$site/extension/mytax/admin/view/template/module/mytax.twig" => "$site/$admin/view/template/extension/module/mytax.twig",
];
foreach ($adminPairs as $src => $dst) {
    @mkdir(dirname($dst), 0777, true);
    if (file_exists($src)) {
        copy($src, $dst);
        echo "  [OK] " . str_replace("$site/", '', $dst) . "\n";
    } else {
        echo "  [FAIL] $src не найден\n";
    }
}

// ===== 5. Копирование в catalog/ =====
echo "\n=== 5. КОПИРОВАНИЕ В catalog/ ===\n";
$catPairs = [
    "$site/extension/mytax/catalog/controller/module/mytax.php" => "$site/catalog/controller/extension/module/mytax.php",
    "$site/extension/mytax/catalog/model/checkout/mytax.php" => "$site/catalog/model/extension/mytax/checkout/mytax.php",
    "$site/extension/mytax/catalog/language/ru-ru/module/mytax.php" => "$site/catalog/language/ru-ru/extension/module/mytax.php",
];
foreach ($catPairs as $src => $dst) {
    @mkdir(dirname($dst), 0777, true);
    if (file_exists($src)) {
        copy($src, $dst);
        echo "  [OK] " . str_replace("$site/", '', $dst) . "\n";
    } else {
        echo "  [FAIL] $src не найден\n";
    }
}

// ===== 6. Проверка =====
echo "\n=== 6. ПРОВЕРКА ===\n";
$checks = [
    "$site/extension/mytax/admin/controller/module/mytax.php",
    "$site/$admin/controller/extension/module/mytax.php",
    "$site/catalog/controller/extension/module/mytax.php",
    "$site/catalog/model/extension/mytax/checkout/mytax.php",
];
$allOk = true;
foreach ($checks as $f) {
    if (file_exists($f)) {
        echo "  [OK] " . str_replace("$site/", '', $f) . "\n";
    } else {
        echo "  [FAIL] " . str_replace("$site/", '', $f) . " - ОТСУТСТВУЕТ\n";
        $allOk = false;
    }
}

if ($allOk) {
    echo "\n✅ ВСЁ УСТАНОВЛЕНО! Зайдите в админку:\n";
    echo "   https://металька.рф:8443/dmt/\n";
    echo "   Расширения → Модули → включите Мой налог\n";
} else {
    echo "\n❌ ЕСТЬ ПРОБЛЕМЫ!\n";
}