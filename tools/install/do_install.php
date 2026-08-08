<?php
// Quick force install - чистая установка
$site = 'C:/sites/metalka';
$tM = 'C:/Users/admin/AppData/Local/Temp/mytax_zip_build';
$tP = 'C:/Users/admin/AppData/Local/Temp/zip_final';

// 1. Сборка архива с правильными путями OC4
$zip = new ZipArchive();
@unlink('C:/sites/metalka/mytax.ocmod.zip');
if ($zip->open('C:/sites/metalka/mytax.ocmod.zip', ZipArchive::CREATE) !== true) die("FAIL");

$zip->addFromString('install.json', json_encode([
    'code' => 'mytax',
    'name' => 'Мой налог: кассовые чеки для ИП (НПД)',
    'version' => '2.0.1',
    'author' => 'MyTax-Service'
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// OC4 paths: extension/{code}/
$zip->addFromString('extension/mytax/admin/controller/module/mytax.php', file_get_contents("$tM/admin/controller/module/mytax.php"));
$zip->addFromString('extension/mytax/admin/model/module/mytax.php', file_get_contents("$tM/admin/model/module/mytax.php"));
$zip->addFromString('extension/mytax/admin/language/ru-ru/module/mytax.php', file_get_contents("$tM/admin/language/ru-ru/module/mytax.php"));
$zip->addFromString('extension/mytax/admin/view/template/module/mytax.twig', file_get_contents("$tM/admin/view/template/module/mytax.twig"));
$zip->addFromString('extension/mytax/catalog/controller/module/mytax.php', '<?php
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
}');
$zip->addFromString('extension/mytax/catalog/model/checkout/mytax.php', file_get_contents("$tP/catalog/model/checkout/mytax.php"));
$zip->addFromString('extension/mytax/catalog/language/ru-ru/module/mytax.php', file_get_contents("$tM/catalog/language/ru-ru/module/mytax.php"));
$zip->addFromString('install.php', '<?php function install(){} function uninstall(){}');

$zip->close();
echo "  [OK] Архив: mytax.ocmod.zip (".$zip->numFiles." файлов)\n";

// 2. Очистка БД + force install
$m = new mysqli('localhost', 'root', '', 'metalka');
$p = 'oc_';

$m->query("DELETE FROM {$p}extension WHERE code='mytax'");
$m->query("DELETE FROM {$p}extension_install WHERE code='mytax'");
$m->query("DELETE FROM {$p}extension_path WHERE path LIKE '%mytax%'");
$m->query("DELETE FROM {$p}module WHERE code='mytax'");
$m->query("DELETE FROM {$p}event WHERE code LIKE 'mytax%'");
$m->query("DELETE FROM {$p}setting WHERE code LIKE '%mytax%'");
$m->query("DROP TABLE IF EXISTS {$p}mytax_receipts");

$m->query("INSERT INTO {$p}extension SET type='module', code='mytax'");
$extId = $m->insert_id;
echo "  [OK] oc_extension id=$extId\n";

$m->query("INSERT INTO {$p}extension_install SET extension_id=$extId, extension_download_id=0, code='mytax', name='Мой налог: кассовые чеки для ИП (НПД)', version='2.0.1', author='MyTax-Service', status=1, date_added=NOW()");
$instId = $m->insert_id;
echo "  [OK] oc_extension_install id=$instId\n";

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

$m->query("INSERT INTO {$p}event SET code='mytax_order_history', `trigger`='catalog/model/checkout/order.addHistory/before', action='extension/mytax/module/mytax.orderHistory', status=1, sort_order=1");
$m->query("INSERT INTO {$p}event SET code='mytax_mail_order_history', `trigger`='catalog/view/mail/order_history/before', action='extension/mytax/module/mytax.viewOrderHistory', status=1, sort_order=1");
$m->query("INSERT INTO {$p}event SET code='mytax_mail_order_add', `trigger`='catalog/view/mail/order_add/before', action='extension/mytax/module/mytax.viewOrderAdd', status=1, sort_order=1");
echo "  [OK] oc_event: 3 записи\n";

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
echo "  [OK] oc_mytax_receipts\n";
$m->close();

// 3. Копирование файлов в extension/mytax/
function rrmdir($dir) {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
    @rmdir($dir);
}
rrmdir("$site/extension/mytax");

@mkdir("$site/extension/mytax/admin/controller/module", 0777, true);
copy("$tM/admin/controller/module/mytax.php", "$site/extension/mytax/admin/controller/module/mytax.php");
echo "  [OK] extension/mytax/admin/controller/module/mytax.php\n";

@mkdir("$site/extension/mytax/admin/model/module", 0777, true);
copy("$tM/admin/model/module/mytax.php", "$site/extension/mytax/admin/model/module/mytax.php");
echo "  [OK] extension/mytax/admin/model/module/mytax.php\n";

@mkdir("$site/extension/mytax/admin/language/ru-ru/module", 0777, true);
copy("$tM/admin/language/ru-ru/module/mytax.php", "$site/extension/mytax/admin/language/ru-ru/module/mytax.php");
echo "  [OK] extension/mytax/admin/language/ru-ru/module/mytax.php\n";

@mkdir("$site/extension/mytax/admin/view/template/module", 0777, true);
copy("$tM/admin/view/template/module/mytax.twig", "$site/extension/mytax/admin/view/template/module/mytax.twig");
echo "  [OK] extension/mytax/admin/view/template/module/mytax.twig\n";

@mkdir("$site/extension/mytax/catalog/controller/module", 0777, true);
file_put_contents("$site/extension/mytax/catalog/controller/module/mytax.php", '<?php
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
}');
echo "  [OK] extension/mytax/catalog/controller/module/mytax.php\n";

@mkdir("$site/extension/mytax/catalog/model/checkout", 0777, true);
copy("$tP/catalog/model/checkout/mytax.php", "$site/extension/mytax/catalog/model/checkout/mytax.php");
echo "  [OK] extension/mytax/catalog/model/checkout/mytax.php\n";

@mkdir("$site/extension/mytax/catalog/language/ru-ru/module", 0777, true);
copy("$tM/catalog/language/ru-ru/module/mytax.php", "$site/extension/mytax/catalog/language/ru-ru/module/mytax.php");
echo "  [OK] extension/mytax/catalog/language/ru-ru/module/mytax.php\n";

echo "\n✅ ГОТОВО! Зайдите в админку: Расширения → Модули\n";
echo "   Там должен появиться Мой налог\n";