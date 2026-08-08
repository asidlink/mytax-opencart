<?php
/**
 * Сборка mytax.ocmod.zip для OpenCart 4.1.0.3
 * Правильные пути OC4: extension/mytax/admin/... и extension/mytax/catalog/...
 * БЕЗ upload/ префикса (OC4 не использует upload/ как OC3)
 */
$zip = new ZipArchive();
@unlink('C:/sites/metalka/mytax.ocmod.zip');
if ($zip->open('C:/sites/metalka/mytax.ocmod.zip', ZipArchive::CREATE) !== true) die("FAIL");

$tM = 'C:/Users/admin/AppData/Local/Temp/mytax_zip_build';
$tP = 'C:/Users/admin/AppData/Local/Temp/zip_final';

// install.json в корне
$zip->addFromString('install.json', json_encode([
    'code' => 'mytax',
    'name' => 'Мой налог',
    'description' => 'Автоматическое создание чеков в приложении Мой налог (НПД) при изменении статуса заказа',
    'version' => '2.0.1',
    'author' => 'MyTax-Service',
    'link' => 'https://github.com/Ga1maz/fns-receipt-service'
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "  + install.json\n";

// ===== ADMIN (OC4: extension/mytax/admin/) =====
// В OC4 admin часть лежит в extension/{code}/admin/
// admin/controller/payment/mytax.php (тип payment, а не module!)
$zip->addFromString(
    'extension/mytax/admin/controller/payment/mytax.php',
    file_get_contents("$tP/admin/controller/payment/mytax.php")
);
echo "  + extension/mytax/admin/controller/payment/mytax.php\n";

// admin/model/payment/mytax.php
$zip->addFromString(
    'extension/mytax/admin/model/payment/mytax.php',
    file_get_contents("$tP/admin/model/payment/mytax.php")
);
echo "  + extension/mytax/admin/model/payment/mytax.php\n";

// admin/language/ru-ru/payment/mytax.php
$zip->addFromString(
    'extension/mytax/admin/language/ru-ru/payment/mytax.php',
    file_get_contents("$tP/admin/language/ru-ru/payment/mytax.php")
);
echo "  + extension/mytax/admin/language/ru-ru/payment/mytax.php\n";

// admin/view/template/payment/mytax.twig
$zip->addFromString(
    'extension/mytax/admin/view/template/payment/mytax.twig',
    file_get_contents("$tP/admin/view/template/payment/mytax.twig")
);
echo "  + extension/mytax/admin/view/template/payment/mytax.twig\n";

// ===== CATALOG (OC4: extension/mytax/catalog/) =====
// catalog/controller/payment/mytax.php
$zip->addFromString(
    'extension/mytax/catalog/controller/payment/mytax.php',
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
echo "  + extension/mytax/catalog/controller/payment/mytax.php\n";

// catalog/model/checkout/mytax.php
$zip->addFromString(
    'extension/mytax/catalog/model/checkout/mytax.php',
    file_get_contents("$tP/catalog/model/checkout/mytax.php")
);
echo "  + extension/mytax/catalog/model/checkout/mytax.php\n";

// catalog/language/ru-ru/payment/mytax.php
$zip->addFromString(
    'extension/mytax/catalog/language/ru-ru/payment/mytax.php',
    file_get_contents("$tP/catalog/language/ru-ru/payment/mytax.php")
);
echo "  + extension/mytax/catalog/language/ru-ru/payment/mytax.php\n";

// install.php
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
            if($m) $m->deleteEventByCode("mytax_order_history");
            $m->deleteEventByCode("mytax_mail_order_history");
            $m->deleteEventByCode("mytax_mail_order_add");
        }
    } catch(Exception $e) {}
}');
echo "  + install.php\n";

$zip->close();

echo "\nАрхив создан. Содержимое:\n";
$z2 = new ZipArchive();
if ($z2->open('C:/sites/metalka/mytax.ocmod.zip') === true) {
    for ($i=0;$i<$z2->numFiles;$i++) echo "  ".$z2->getNameIndex($i)."\n";
    $z2->close();
}