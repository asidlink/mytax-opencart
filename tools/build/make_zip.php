<?php
// Сборка ZIP для Extension Installer OC4
// OC4 копирует файлы как extension/{code}/...
// Значит в архиве пути БЕЗ префикса extension/mytax/
$tM = 'C:/Users/admin/AppData/Local/Temp/mytax_zip_build';
$tP = 'C:/Users/admin/AppData/Local/Temp/zip_final';

$zip = new ZipArchive();
@unlink('C:/sites/metalka/mytax.ocmod.zip');
if ($zip->open('C:/sites/metalka/mytax.ocmod.zip', ZipArchive::CREATE) !== true) die("FAIL");

// install.json - OC4 читает code
$zip->addFromString('install.json', json_encode([
    'code' => 'mytax',
    'name' => 'Мой налог: кассовые чеки для ИП (НПД)',
    'version' => '2.0.1',
    'author' => 'MyTax-Service'
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// OC4 скопирует эти файлы как extension/mytax/{path}
// Пути в архиве: БЕЗ extension/mytax/ в начале!
$zip->addFromString('admin/controller/module/mytax.php', file_get_contents("$tM/admin/controller/module/mytax.php"));
$zip->addFromString('admin/model/module/mytax.php', file_get_contents("$tM/admin/model/module/mytax.php"));
$zip->addFromString('admin/language/ru-ru/module/mytax.php', file_get_contents("$tM/admin/language/ru-ru/module/mytax.php"));
$zip->addFromString('admin/view/template/module/mytax.twig', file_get_contents("$tM/admin/view/template/module/mytax.twig"));

$catalogCtrl = '<?php namespace Opencart\Catalog\Controller\Extension\Mytax\Module;
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
$zip->addFromString('catalog/controller/module/mytax.php', $catalogCtrl);
$zip->addFromString('catalog/model/checkout/mytax.php', file_get_contents("$tP/catalog/model/checkout/mytax.php"));
$zip->addFromString('catalog/language/ru-ru/module/mytax.php', file_get_contents("$tM/catalog/language/ru-ru/module/mytax.php"));

$zip->addFromString('install.php', '<?php
function install() {
    global $registry; if(!$registry) return;
    try {
        $db = $registry->get("db");
        if($db) $db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."mytax_receipts` (`receipt_id` int(11) NOT NULL AUTO_INCREMENT,`order_id` int(11) NOT NULL,`email` varchar(96) NOT NULL,`fns_receipt_id` varchar(255) DEFAULT NULL,`print_link` varchar(500) DEFAULT NULL,`qr_code_path` varchar(255) DEFAULT NULL,`amount` decimal(15,4) NOT NULL DEFAULT 0.0000,`status` varchar(50) NOT NULL DEFAULT \'pending\',`error_message` text DEFAULT NULL,`date_added` datetime NOT NULL DEFAULT current_timestamp(),PRIMARY KEY (`receipt_id`),UNIQUE KEY `order_id` (`order_id`)) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $load = $registry->get("load");
        if($load) {
            $load->model("setting/event");
            $m = $registry->get("model_setting_event");
            if($m) {
                $m->addEvent(["code"=>"mytax_order_history","trigger"=>"catalog/model/checkout/order.addHistory/before","action"=>"extension/mytax/module/mytax.orderHistory","status"=>1,"sort_order"=>1]);
                $m->addEvent(["code"=>"mytax_mail_order_history","trigger"=>"catalog/view/mail/order_history/before","action"=>"extension/mytax/module/mytax.viewOrderHistory","status"=>1,"sort_order"=>1]);
                $m->addEvent(["code"=>"mytax_mail_order_add","trigger"=>"catalog/view/mail/order_add/before","action"=>"extension/mytax/module/mytax.viewOrderAdd","status"=>1,"sort_order"=>1]);
            }
        }
    } catch(Exception $e) {}
}
function uninstall() {
    global $registry; if(!$registry) return;
    try {
        $load = $registry->get("load");
        if($load) {
            $load->model("setting/event");
            $m = $registry->get("model_setting_event");
            if($m) {
                $m->deleteEventByCode("mytax_order_history");
                $m->deleteEventByCode("mytax_mail_order_history");
                $m->deleteEventByCode("mytax_mail_order_add");
            }
        }
    } catch(Exception $e) {}
}');

$zip->close();

echo "OK. Archive:\n";
$z2 = new ZipArchive();
$z2->open('C:/sites/metalka/mytax.ocmod.zip');
for ($i=0;$i<$z2->numFiles;$i++) echo "  ".$z2->getNameIndex($i)."\n";
$z2->close();