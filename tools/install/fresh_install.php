<?php
$site = 'C:/sites/metalka';
$adm = 'dmt';

echo "=== 1. Полная переустановка ===\n\n";

// ===== ОЧИСТКА =====
$m = new mysqli('localhost', 'root', '', 'metalka');
$p = 'oc_';
$m->query("DELETE FROM {$p}extension WHERE code='mytax'");
$m->query("DELETE FROM {$p}extension_install WHERE code='mytax'");
$m->query("DELETE FROM {$p}extension_path WHERE path LIKE '%mytax%'");
$m->query("DELETE FROM {$p}module WHERE code='mytax'");
$m->query("DELETE FROM {$p}event WHERE code LIKE 'mytax%'");
$m->query("DELETE FROM {$p}setting WHERE code LIKE '%mytax%'");
$m->query("DROP TABLE IF EXISTS {$p}mytax_receipts");

// ===== БД: ДОБАВЛЯЕМ ВСЕ ЗАПИСИ =====
// 1. oc_extension
$m->query("INSERT INTO {$p}extension SET type='module', code='mytax'");
$extId = $m->insert_id;
echo "  [OK] oc_extension id=$extId\n";

// 2. oc_extension_install
$m->query("INSERT INTO {$p}extension_install SET extension_id=$extId, extension_download_id=0, code='mytax', name='Мой налог', version='2.0.1', author='MyTax-Service', status=1, date_added=NOW()");
$instId = $m->insert_id;
echo "  [OK] oc_extension_install id=$instId\n";

// 3. oc_extension_path
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
    $m->query("INSERT INTO {$p}extension_path SET extension_install_id=$instId, path='$path'");
}
echo "  [OK] oc_extension_path: ".count($paths)." записей\n";

// 4. oc_module - КЛЮЧЕВАЯ ЗАПИСЬ! Без неё модуль не показывается
$m->query("INSERT INTO {$p}module SET name='Мой налог', code='mytax', setting=''");
echo "  [OK] oc_module id=" . $m->insert_id . "\n";

// 5. oc_event
$m->query("INSERT INTO {$p}event SET code='mytax_order_history', `trigger`='catalog/model/checkout/order.addHistory/before', action='extension/mytax/module/mytax.orderHistory', status=1, sort_order=1");
$m->query("INSERT INTO {$p}event SET code='mytax_mail_order_history', `trigger`='catalog/view/mail/order_history/before', action='extension/mytax/module/mytax.viewOrderHistory', status=1, sort_order=1");
$m->query("INSERT INTO {$p}event SET code='mytax_mail_order_add', `trigger`='catalog/view/mail/order_add/before', action='extension/mytax/module/mytax.viewOrderAdd', status=1, sort_order=1");
echo "  [OK] oc_event: 3 записи\n";

// 6. oc_mytax_receipts
$m->query("CREATE TABLE IF NOT EXISTS {$p}mytax_receipts (
    receipt_id int(11) NOT NULL AUTO_INCREMENT,
    order_id int(11) NOT NULL,
    email varchar(96) NOT NULL,
    fns_receipt_id varchar(255) DEFAULT NULL,
    print_link varchar(500) DEFAULT NULL,
    qr_code_path varchar(255) DEFAULT NULL,
    amount decimal(15,4) NOT NULL DEFAULT 0.0000,
    status varchar(50) NOT NULL DEFAULT 'pending',
    error_message text DEFAULT NULL,
    date_added datetime NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (receipt_id),
    UNIQUE KEY order_id (order_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
echo "  [OK] oc_mytax_receipts создана\n";

$m->close();

// ===== ФАЙЛЫ =====
echo "\n=== 2. КОПИРОВАНИЕ ФАЙЛОВ ===\n";

$tM = 'C:/Users/admin/AppData/Local/Temp/mytax_zip_build';
$tP = 'C:/Users/admin/AppData/Local/Temp/zip_final';

// Удаляем старые файлы mytax
function rrmdir($dir) {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
    @rmdir($dir);
}
rrmdir("$site/extension/mytax");

// Копируем файлы в extension/mytax (основное расположение OC4)
$files = [
    "$tM/admin/controller/module/mytax.php" => "$site/extension/mytax/admin/controller/module/mytax.php",
    "$tM/admin/model/module/mytax.php" => "$site/extension/mytax/admin/model/module/mytax.php",
    "$tM/admin/language/ru-ru/module/mytax.php" => "$site/extension/mytax/admin/language/ru-ru/module/mytax.php",
    "$tM/admin/view/template/module/mytax.twig" => "$site/extension/mytax/admin/view/template/module/mytax.twig",
];
foreach ($files as $src => $dst) {
    @mkdir(dirname($dst), 0777, true);
    copy($src, $dst);
    echo "  [OK] " . str_replace("$site/", '', $dst) . "\n";
}

// Catalog files
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

@mkdir("$site/extension/mytax/catalog/controller/module", 0777, true);
file_put_contents("$site/extension/mytax/catalog/controller/module/mytax.php", $catalogCtrl);
echo "  [OK] extension/mytax/catalog/controller/module/mytax.php\n";

@mkdir("$site/extension/mytax/catalog/model/checkout", 0777, true);
copy("$tP/catalog/model/checkout/mytax.php", "$site/extension/mytax/catalog/model/checkout/mytax.php");
echo "  [OK] extension/mytax/catalog/model/checkout/mytax.php\n";

@mkdir("$site/extension/mytax/catalog/language/ru-ru/module", 0777, true);
copy("$tM/catalog/language/ru-ru/module/mytax.php", "$site/extension/mytax/catalog/language/ru-ru/module/mytax.php");
echo "  [OK] extension/mytax/catalog/language/ru-ru/module/mytax.php\n";

// Копируем admin файлы в dmt/ (чтобы OpenCart видел их)
echo "\n=== 3. КОПИРОВАНИЕ В dmt/ И catalog/ ===\n";
$pairs = [
    "$site/extension/mytax/admin/controller/module/mytax.php" => "$site/$adm/controller/extension/module/mytax.php",
    "$site/extension/mytax/admin/model/module/mytax.php" => "$site/$adm/model/extension/module/mytax.php",
    "$site/extension/mytax/admin/language/ru-ru/module/mytax.php" => "$site/$adm/language/ru-ru/extension/module/mytax.php",
    "$site/extension/mytax/admin/view/template/module/mytax.twig" => "$site/$adm/view/template/extension/module/mytax.twig",
    "$site/extension/mytax/catalog/controller/module/mytax.php" => "$site/catalog/controller/extension/module/mytax.php",
    "$site/extension/mytax/catalog/model/checkout/mytax.php" => "$site/catalog/model/extension/mytax/checkout/mytax.php",
    "$site/extension/mytax/catalog/language/ru-ru/module/mytax.php" => "$site/catalog/language/ru-ru/extension/module/mytax.php",
];
foreach ($pairs as $src => $dst) {
    if (file_exists($src)) {
        @mkdir(dirname($dst), 0777, true);
        copy($src, $dst);
        echo "  [OK] " . str_replace("$site/", '', $dst) . "\n";
    } else {
        echo "  [FAIL] source: " . str_replace("$site/", '', $src) . " missing\n";
    }
}

echo "\n=== 4. ИТОГОВАЯ ПРОВЕРКА ===\n";
$checks = [
    "$adm/controller/extension/module/mytax.php",
    "$adm/model/extension/module/mytax.php",
    "$adm/language/ru-ru/extension/module/mytax.php",
    "$adm/view/template/extension/module/mytax.twig",
    "catalog/controller/extension/module/mytax.php",
    "catalog/model/extension/mytax/checkout/mytax.php",
    "catalog/language/ru-ru/extension/module/mytax.php",
    "extension/mytax/admin/controller/module/mytax.php",
];
$allOk = true;
foreach ($checks as $f) {
    if (file_exists("$site/$f")) {
        echo "  [OK] $f\n";
    } else {
        echo "  [FAIL] $f - ОТСУТСТВУЕТ\n";
        $allOk = false;
    }
}

if ($allOk) {
    echo "\n✅ УСТАНОВКА ЗАВЕРШЕНА\n";
    echo "   Зайдите в админку: https://металька.рф:8443/dmt/\n";
    echo "   Расширения → Модули → Мой налог должен быть 17-м модулем\n";
} else {
    echo "\n❌ ЕСТЬ ПРОБЛЕМЫ С ФАЙЛАМИ!\n";
}