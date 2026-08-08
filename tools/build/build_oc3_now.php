<?php
/**
 * Сборка mytax.ocmod.zip для OpenCart 3
 * С ПРАВИЛЬНЫМИ путями OC3: extension/module, extension/mytax/payment
 */
$zip = new ZipArchive();
@unlink('C:/sites/metalka/mytax.ocmod.zip');
if ($zip->open('C:/sites/metalka/mytax.ocmod.zip', ZipArchive::CREATE) !== true) die("FAIL");

$zip->addFromString('install.json', '{"code":"mytax","name":"Мой налог","version":"2.0.1","author":"MyTax-Service"}');

// Admin OC3 path: upload/admin/controller/extension/module/mytax.php
$zip->addFromString('upload/admin/controller/extension/module/mytax.php',
    file_get_contents('C:/Users/admin/AppData/Local/Temp/mytax_zip_build/admin/controller/module/mytax.php'));

$zip->addFromString('upload/admin/language/ru-ru/extension/module/mytax.php',
    file_get_contents('C:/Users/admin/AppData/Local/Temp/mytax_zip_build/admin/language/ru-ru/module/mytax.php'));

$zip->addFromString('upload/admin/model/extension/module/mytax.php',
    file_get_contents('C:/Users/admin/AppData/Local/Temp/mytax_zip_build/admin/model/module/mytax.php'));

$zip->addFromString('upload/admin/view/template/extension/module/mytax.twig',
    file_get_contents('C:/Users/admin/AppData/Local/Temp/mytax_zip_build/admin/view/template/module/mytax.twig'));

// Catalog OC3 path: upload/catalog/controller/extension/mytax/payment/mytax.php
$zip->addFromString('upload/catalog/controller/extension/mytax/payment/mytax.php',
    '<?php
namespace Opencart\Catalog\Controller\Extension\Mytax\Payment;
class Mytax extends \Opencart\System\Engine\Controller {
    public function index(): void { $this->response->setOutput(""); }
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

$zip->addFromString('upload/catalog/model/extension/mytax/checkout/mytax.php',
    file_get_contents('C:/Users/admin/AppData/Local/Temp/zip_final/catalog/model/checkout/mytax.php'));

$zip->addFromString('upload/catalog/language/ru-ru/extension/mytax/payment/mytax.php',
    file_get_contents('C:/Users/admin/AppData/Local/Temp/zip_final/catalog/language/ru-ru/payment/mytax.php'));

$zip->addFromString('upload/catalog/language/ru-ru/extension/mytax/module/mytax.php',
    file_get_contents('C:/Users/admin/AppData/Local/Temp/mytax_zip_build/catalog/language/ru-ru/module/mytax.php'));

// install.php
$zip->addFromString('install.php', '<?php function install() {
    global $registry; if(!$registry) return;
    try {
        $db = $registry->get("db");
        if($db) $db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."mytax_receipts` (`receipt_id` int(11) NOT NULL AUTO_INCREMENT,`order_id` int(11) NOT NULL,`email` varchar(96) NOT NULL,`fns_receipt_id` varchar(255) DEFAULT NULL,`print_link` varchar(500) DEFAULT NULL,`qr_code_path` varchar(255) DEFAULT NULL,`amount` decimal(15,4) NOT NULL DEFAULT 0.0000,`status` varchar(50) NOT NULL DEFAULT \'pending\',`error_message` text DEFAULT NULL,`date_added` datetime NOT NULL DEFAULT current_timestamp(),PRIMARY KEY (`receipt_id`),UNIQUE KEY `order_id` (`order_id`)) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $load = $registry->get("load");
        if($load) {
            $load->model("setting/event");
            $m = $registry->get("model_setting_event");
            if($m) {
                $m->addEvent(["code"=>"mytax_order_history","trigger"=>"catalog/model/checkout/order.addHistory/before","action"=>"extension/mytax/payment/mytax.orderHistory","status"=>1,"sort_order"=>1]);
                $m->addEvent(["code"=>"mytax_mail_order_history","trigger"=>"catalog/view/mail/order_history/before","action"=>"extension/mytax/payment/mytax.viewOrderHistory","status"=>1,"sort_order"=>1]);
                $m->addEvent(["code"=>"mytax_mail_order_add","trigger"=>"catalog/view/mail/order_add/before","action"=>"extension/mytax/payment/mytax.viewOrderAdd","status"=>1,"sort_order"=>1]);
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

echo "OK. Archive created.\n";
$z2 = new ZipArchive();
if ($z2->open('C:/sites/metalka/mytax.ocmod.zip') === true) {
    for ($i=0;$i<$z2->numFiles;$i++) echo "  ".$z2->getNameIndex($i)."\n";
    $z2->close();
}