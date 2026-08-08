<?php
$site = 'C:/sites/metalka';
$adm = 'dmt';
$tM = 'C:/Users/admin/AppData/Local/Temp/mytax_zip_build';
$tP = 'C:/Users/admin/AppData/Local/Temp/zip_final';

$m = new mysqli('localhost', 'root', '', 'metalka');
$p = 'oc_';

// Очистка
$m->query("DELETE FROM {$p}extension WHERE code='mytax'");
$m->query("DELETE FROM {$p}extension_install WHERE code='mytax'");
$m->query("DELETE FROM {$p}extension_path WHERE path LIKE '%mytax%'");
$m->query("DELETE FROM {$p}module WHERE code='mytax'");
$m->query("DELETE FROM {$p}event WHERE code LIKE 'mytax%'");
$m->query("DELETE FROM {$p}setting WHERE code LIKE '%mytax%'");
$m->query("DROP TABLE IF EXISTS {$p}mytax_receipts");

// Удаление старых файлов
function rrmdir($d) {
    if(!is_dir($d)) return;
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d,RecursiveDirectoryIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as $f) $f->isDir()?@rmdir($f->getRealPath()):@unlink($f->getRealPath());
    @rmdir($d);
}
rrmdir("$site/extension/mytax");
rrmdir("$site/$adm/controller/extension/mytax");
rrmdir("$site/$adm/model/extension/mytax");
rrmdir("$site/$adm/language/ru-ru/extension/mytax");
rrmdir("$site/$adm/view/template/extension/mytax");
rrmdir("$site/catalog/controller/extension/mytax");
rrmdir("$site/catalog/model/extension/mytax");
rrmdir("$site/catalog/language/ru-ru/extension/mytax");

// === КЛЮЧЕВОЕ: INSERT с extension='mytax' (не только code!) ===
$m->query("INSERT INTO {$p}extension SET `extension`='mytax', `type`='module', `code`='mytax'");
$extId = $m->insert_id;
echo "oc_extension id=$extId extension=mytax\n";

$m->query("INSERT INTO {$p}extension_install SET extension_id=$extId, extension_download_id=0, code='mytax', name='Мой налог: кассовые чеки для ИП (НПД)', version='2.0.1', author='MyTax-Service', status=1, date_added=NOW()");
$instId = $m->insert_id;

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

$m->query("INSERT INTO {$p}module SET name='Мой налог: кассовые чеки для ИП (НПД)', code='mytax', setting=''");
echo "oc_module id=" . $m->insert_id . "\n";

$m->query("INSERT INTO {$p}event SET code='mytax_order_history', `trigger`='catalog/model/checkout/order.addHistory/before', action='extension/mytax/module/mytax.orderHistory', status=1, sort_order=1");
$m->query("INSERT INTO {$p}event SET code='mytax_mail_order_history', `trigger`='catalog/view/mail/order_history/before', action='extension/mytax/module/mytax.viewOrderHistory', status=1, sort_order=1");
$m->query("INSERT INTO {$p}event SET code='mytax_mail_order_add', `trigger`='catalog/view/mail/order_add/before', action='extension/mytax/module/mytax.viewOrderAdd', status=1, sort_order=1");
$m->query("CREATE TABLE IF NOT EXISTS {$p}mytax_receipts (receipt_id int(11) NOT NULL AUTO_INCREMENT,order_id int(11) NOT NULL,email varchar(96) NOT NULL,fns_receipt_id varchar(255) DEFAULT NULL,print_link varchar(500) DEFAULT NULL,qr_code_path varchar(255) DEFAULT NULL,amount decimal(15,4) NOT NULL DEFAULT 0.0000,status varchar(50) NOT NULL DEFAULT 'pending',error_message text DEFAULT NULL,date_added datetime NOT NULL DEFAULT current_timestamp(),PRIMARY KEY (receipt_id),UNIQUE KEY order_id (order_id)) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
echo "oc_mytax_receipts created\n";
echo "oc_event x3\n";
$m->close();

// Файлы
$ctrl = '<?php namespace Opencart\Catalog\Controller\Extension\Mytax\Module;
class Mytax extends \Opencart\System\Engine\Controller {
    public function index(array $args): void {}
    public function orderHistory(string &$route, array &$args): void {
        $this->load->model("extension/mytax/checkout/mytax");
        $this->load->model("checkout/order");
        $order_info = $this->model_checkout_order->getOrder($args[0]);
        if($order_info) $this->model_extension_mytax_checkout_mytax->createReceipt($args[0],$order_info["email"]);
    }
    public function viewOrderHistory(string &$route, array &$args): void {
        $this->load->model("extension/mytax/checkout/mytax");
        $oid=$args["order_id"]??0; if($oid){$r=$this->model_extension_mytax_checkout_mytax->getReceiptByOrderId($oid);if($r)$args["mytax"]=$r;}
    }
    public function viewOrderAdd(string &$route, array &$args): void {
        $this->load->model("extension/mytax/checkout/mytax");
        $oid=$args["order_id"]??0; if($oid){$r=$this->model_extension_mytax_checkout_mytax->getReceiptByOrderId($oid);if($r)$args["mytax"]=$r;}
    }
}';

echo "\n=== FILES ===\n";
$pairs = [
    ["$tM/admin/controller/module/mytax.php", "$site/extension/mytax/admin/controller/module/mytax.php"],
    ["$tM/admin/model/module/mytax.php", "$site/extension/mytax/admin/model/module/mytax.php"],
    ["$tM/admin/language/ru-ru/module/mytax.php", "$site/extension/mytax/admin/language/ru-ru/module/mytax.php"],
    ["$tM/admin/view/template/module/mytax.twig", "$site/extension/mytax/admin/view/template/module/mytax.twig"],
    ["$tP/catalog/model/checkout/mytax.php", "$site/extension/mytax/catalog/model/checkout/mytax.php"],
    ["$tM/catalog/language/ru-ru/module/mytax.php", "$site/extension/mytax/catalog/language/ru-ru/module/mytax.php"],
];
foreach ($pairs as $pair) {
    @mkdir(dirname($pair[1]), 0777, true);
    copy($pair[0], $pair[1]);
    echo str_replace("$site/",'',$pair[1])."\n";
}
// catalog controller
@mkdir("$site/extension/mytax/catalog/controller/module", 0777, true);
file_put_contents("$site/extension/mytax/catalog/controller/module/mytax.php", $ctrl);
echo "extension/mytax/catalog/controller/module/mytax.php\n";

// dmt files
$dmtPairs = [
    "$site/extension/mytax/admin/controller/module/mytax.php" => "$site/$adm/controller/extension/mytax/module/mytax.php",
    "$site/extension/mytax/admin/model/module/mytax.php" => "$site/$adm/model/extension/mytax/module/mytax.php",
    "$site/extension/mytax/admin/language/ru-ru/module/mytax.php" => "$site/$adm/language/ru-ru/extension/mytax/module/mytax.php",
    "$site/extension/mytax/admin/view/template/module/mytax.twig" => "$site/$adm/view/template/extension/mytax/module/mytax.twig",
    "$site/extension/mytax/catalog/controller/module/mytax.php" => "$site/catalog/controller/extension/mytax/module/mytax.php",
    "$site/extension/mytax/catalog/model/checkout/mytax.php" => "$site/catalog/model/extension/mytax/checkout/mytax.php",
    "$site/extension/mytax/catalog/language/ru-ru/module/mytax.php" => "$site/catalog/language/ru-ru/extension/mytax/module/mytax.php",
];
foreach ($dmtPairs as $src => $dst) {
    @mkdir(dirname($dst), 0777, true);
    copy($src, $dst);
    echo str_replace("$site/",'',$dst)."\n";
}

echo "\n=== VERIFY ===\n";
foreach ($dmtPairs as $src => $dst) {
    echo (file_exists($dst)?"OK":"MISS").": ".str_replace("$site/",'',$dst)."\n";
}
echo "\nDONE! Refresh admin: Расширения->Модули\n";