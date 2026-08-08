<?php
$site = 'C:/sites/metalka';
$adm = 'dmt';
$tM = 'C:/Users/admin/AppData/Local/Temp/mytax_zip_build';
$tP = 'C:/Users/admin/AppData/Local/Temp/zip_final';
$m = new mysqli('localhost', 'root', '', 'metalka');
$p = 'oc_';

// Очищаем ВСЕ записи mytax
$m->query("DELETE FROM {$p}extension WHERE code='mytax'");
$m->query("DELETE FROM {$p}extension_install WHERE code='mytax'");
$m->query("DELETE FROM {$p}extension_path WHERE path LIKE '%mytax%'");
$m->query("DELETE FROM {$p}module WHERE code='mytax'");
$m->query("DELETE FROM {$p}event WHERE code LIKE 'mytax%'");
$m->query("DELETE FROM {$p}setting WHERE code LIKE '%mytax%'");

// oc_extension с полем extension='mytax' (КЛЮЧЕВОЙ МОМЕНТ!)
$m->query("INSERT INTO {$p}extension SET `extension`='mytax', `type`='module', `code`='mytax'");
$extId = $m->insert_id;
echo "oc_extension id=$extId extension=mytax\n";

$m->query("INSERT INTO {$p}extension_install SET extension_id=$extId, extension_download_id=0, code='mytax', name='Мой налог', version='2.0.1', author='MyTax-Service', status=1, date_added=NOW()");
$instId = $m->insert_id;

// Правильные пути (БЕЗ mytax/ в начале!)
$paths = [
    'extension/mytax/admin/controller/module/mytax.php',
    'extension/mytax/admin/model/module/mytax.php',
    'extension/mytax/admin/language/ru-ru/module/mytax.php',
    'extension/mytax/admin/view/template/module/mytax.twig',
    'extension/mytax/catalog/controller/module/mytax.php',
    'extension/mytax/catalog/model/checkout/mytax.php',
    'extension/mytax/catalog/language/ru-ru/module/mytax.php',
];
foreach ($paths as $p2) $m->query("INSERT INTO {$p}extension_path SET extension_install_id=$instId, path='$p2'");
echo "extension_path: ".count($paths)." записей\n";

$m->query("INSERT INTO {$p}module SET name='Мой налог: кассовые чеки для ИП (НПД)', code='mytax', setting=''");
echo "module id=" . $m->insert_id . "\n";

$m->query("INSERT INTO {$p}event SET code='mytax_order_history', `trigger`='catalog/model/checkout/order.addHistory/before', action='extension/mytax/module/mytax.orderHistory', status=1, sort_order=1");
$m->query("INSERT INTO {$p}event SET code='mytax_mail_order_history', `trigger`='catalog/view/mail/order_history/before', action='extension/mytax/module/mytax.viewOrderHistory', status=1, sort_order=1");
$m->query("INSERT INTO {$p}event SET code='mytax_mail_order_add', `trigger`='catalog/view/mail/order_add/before', action='extension/mytax/module/mytax.viewOrderAdd', status=1, sort_order=1");
echo "event: 3 записи\n";

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
echo "mytax_receipts created\n";
$m->close();

// Файлы
$ctrl = '<?php namespace Opencart\Catalog\Controller\Extension\Mytax\Module;
class Mytax extends \Opencart\System\Engine\Controller {
    public function index(array $args): void {}
    public function orderHistory(string &$route, array &$args): void {
        $this->load->model("extension/mytax/checkout/mytax");
        $this->load->model("checkout/order");
        $order_info = $this->model_checkout_order->getOrder($args[0]);
        if ($order_info) $this->model_extension_mytax_checkout_mytax->createReceipt($args[0], $order_info["email"]);
    }
    public function viewOrderHistory(string &$route, array &$args): void {
        $this->load->model("extension/mytax/checkout/mytax");
        $oid = $args["order_id"] ?? 0;
        if ($oid) { $r = $this->model_extension_mytax_checkout_mytax->getReceiptByOrderId($oid); if ($r) $args["mytax"] = $r; }
    }
    public function viewOrderAdd(string &$route, array &$args): void {
        $this->load->model("extension/mytax/checkout/mytax");
        $oid = $args["order_id"] ?? 0;
        if ($oid) { $r = $this->model_extension_mytax_checkout_mytax->getReceiptByOrderId($oid); if ($r) $args["mytax"] = $r; }
    }
}';

@mkdir("$site/extension/mytax/admin/controller/module", 0777, true);
copy("$tM/admin/controller/module/mytax.php", "$site/extension/mytax/admin/controller/module/mytax.php");
@mkdir("$site/extension/mytax/admin/model/module", 0777, true);
copy("$tM/admin/model/module/mytax.php", "$site/extension/mytax/admin/model/module/mytax.php");
@mkdir("$site/extension/mytax/admin/language/ru-ru/module", 0777, true);
copy("$tM/admin/language/ru-ru/module/mytax.php", "$site/extension/mytax/admin/language/ru-ru/module/mytax.php");
@mkdir("$site/extension/mytax/admin/view/template/module", 0777, true);
copy("$tM/admin/view/template/module/mytax.twig", "$site/extension/mytax/admin/view/template/module/mytax.twig");
@mkdir("$site/extension/mytax/catalog/controller/module", 0777, true);
file_put_contents("$site/extension/mytax/catalog/controller/module/mytax.php", $ctrl);
@mkdir("$site/extension/mytax/catalog/model/checkout", 0777, true);
copy("$tP/catalog/model/checkout/mytax.php", "$site/extension/mytax/catalog/model/checkout/mytax.php");
@mkdir("$site/extension/mytax/catalog/language/ru-ru/module", 0777, true);
copy("$tM/catalog/language/ru-ru/module/mytax.php", "$site/extension/mytax/catalog/language/ru-ru/module/mytax.php");

// dmt
@mkdir("$site/$adm/controller/extension/mytax/module", 0777, true);
copy("$tM/admin/controller/module/mytax.php", "$site/$adm/controller/extension/mytax/module/mytax.php");
@mkdir("$site/$adm/model/extension/mytax/module", 0777, true);
copy("$tM/admin/model/module/mytax.php", "$site/$adm/model/extension/mytax/module/mytax.php");
@mkdir("$site/$adm/language/ru-ru/extension/mytax/module", 0777, true);
copy("$tM/admin/language/ru-ru/module/mytax.php", "$site/$adm/language/ru-ru/extension/mytax/module/mytax.php");
@mkdir("$site/$adm/view/template/extension/mytax/module", 0777, true);
copy("$tM/admin/view/template/module/mytax.twig", "$site/$adm/view/template/extension/mytax/module/mytax.twig");
@mkdir("$site/catalog/controller/extension/mytax/module", 0777, true);
file_put_contents("$site/catalog/controller/extension/mytax/module/mytax.php", $ctrl);
@mkdir("$site/catalog/model/extension/mytax/checkout", 0777, true);
copy("$tP/catalog/model/checkout/mytax.php", "$site/catalog/model/extension/mytax/checkout/mytax.php");
@mkdir("$site/catalog/language/ru-ru/extension/mytax/module", 0777, true);
copy("$tM/catalog/language/ru-ru/module/mytax.php", "$site/catalog/language/ru-ru/extension/mytax/module/mytax.php");

// Проверка
echo "\n=== VERIFY ===\n";
$m = new mysqli('localhost', 'root', '', 'metalka');
$r = $m->query("SELECT extension_id, `extension`, type, code FROM {$p}extension WHERE code='mytax'");
if ($row = $r->fetch_assoc()) echo "oc_extension: id={$row['extension_id']} extension='{$row['extension']}' type='{$row['type']}'\n";
$m->close();

$files = [
    "$adm/controller/extension/mytax/module/mytax.php",
    "$adm/model/extension/mytax/module/mytax.php",
    "$adm/language/ru-ru/extension/mytax/module/mytax.php",
    "$adm/view/template/extension/mytax/module/mytax.twig",
    "catalog/controller/extension/mytax/module/mytax.php",
    "catalog/model/extension/mytax/checkout/mytax.php",
    "catalog/language/ru-ru/extension/mytax/module/mytax.php",
    "extension/mytax/admin/controller/module/mytax.php",
];
foreach ($files as $f) echo (file_exists("$site/$f")?"OK":"MISS").": $f\n";