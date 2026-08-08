<?php
$gpt = 'G:/DOWNLOAD/mytax.ocmod.zip';
$target = 'C:/sites/metalka/mytax.ocmod.zip';

$zip = new ZipArchive();
@unlink($target);
if ($zip->open($target, ZipArchive::CREATE) !== true) die("FAIL");

// install.json - с type=module (как у ChatGPT)
$zip->addFromString('install.json', json_encode([
    'code' => 'mytax',
    'name' => 'Мой налог: кассовые чеки для ИП (НПД)',
    'version' => '2.3.0',
    'author' => 'MyTax-Service',
    'link' => '',
    'type' => 'module'
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$g = new ZipArchive();
if ($g->open($gpt) !== true) die("Не удалось открыть GPT архив");

$zip->addFromString('README.txt', $g->getFromName('README.txt'));
$zip->addFromString('install.php', '<?php function install(){} function uninstall(){}');
$zip->addFromString('admin/controller/module/mytax.php', $g->getFromName('admin/controller/module/mytax.php'));
$zip->addFromString('admin/language/ru-ru/module/mytax.php', $g->getFromName('admin/language/ru-ru/module/mytax.php'));
$zip->addFromString('admin/view/template/module/mytax.twig', $g->getFromName('admin/view/template/module/mytax.twig'));
$zip->addFromString('catalog/model/checkout/mytax.php', $g->getFromName('catalog/model/checkout/mytax.php'));
$zip->addFromString('catalog/language/ru-ru/module/mytax.php', $g->getFromName('catalog/language/ru-ru/module/mytax.php'));

// Admin model - ИСПРАВЛЕННЫЙ: удаляет дубли событий (не будет 4 чеков)
$adminModel = '<?php
namespace Opencart\Admin\Model\Extension\Mytax\Module;
class Mytax extends \Opencart\System\Engine\Model {
    public function install(): void {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "mytax_receipts` (`receipt_id` int(11) NOT NULL AUTO_INCREMENT,`order_id` int(11) NOT NULL,`email` varchar(96) NOT NULL,`fns_receipt_id` varchar(255) DEFAULT NULL,`print_link` varchar(500) DEFAULT NULL,`qr_code_path` varchar(255) DEFAULT NULL,`amount` decimal(15,4) NOT NULL DEFAULT 0.0000,`status` varchar(50) NOT NULL DEFAULT \'pending\',`error_message` text DEFAULT NULL,`date_added` datetime NOT NULL DEFAULT current_timestamp(),PRIMARY KEY (`receipt_id`),UNIQUE KEY `order_id` (`order_id`)) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $this->load->model("setting/event");
        // Удаляем старые события, чтобы не было дублей (4 чека)
        $this->model_setting_event->deleteEventByCode("mytax_order_history");
        $this->model_setting_event->deleteEventByCode("mytax_mail_order_history");
        $this->model_setting_event->deleteEventByCode("mytax_mail_order_add");
        // Добавляем
        $this->model_setting_event->addEvent(["code" => "mytax_order_history", "description" => "Создание чека Мой Налог", "trigger" => "catalog/model/checkout/order.addHistory/before", "action" => "extension/mytax/module/mytax.orderHistory", "status" => 1, "sort_order" => 1]);
        $this->model_setting_event->addEvent(["code" => "mytax_mail_order_history", "description" => "Данные чека в письме", "trigger" => "catalog/view/mail/order_history/before", "action" => "extension/mytax/module/mytax.viewOrderHistory", "status" => 1, "sort_order" => 1]);
        $this->model_setting_event->addEvent(["code" => "mytax_mail_order_add", "description" => "Данные чека в письме", "trigger" => "catalog/view/mail/order_add/before", "action" => "extension/mytax/module/mytax.viewOrderAdd", "status" => 1, "sort_order" => 1]);
    }
    public function uninstall(): void {
        $this->load->model("setting/event");
        $this->model_setting_event->deleteEventByCode("mytax_order_history");
        $this->model_setting_event->deleteEventByCode("mytax_mail_order_history");
        $this->model_setting_event->deleteEventByCode("mytax_mail_order_add");
    }
}';
$zip->addFromString('admin/model/module/mytax.php', $adminModel);

// Catalog controller - ИСПРАВЛЕННЫЙ: передает qr_link в письмо
$catCtrl = '<?php
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
        if ($order_id) {
            $r = $this->model_extension_mytax_checkout_mytax->getReceiptByOrderId($order_id);
            if ($r) {
                $r["qr_link"] = $r["qr_code_path"] ?? "";
                $r["print_link"] = $r["print_link"] ?? "";
                $args["mytax_receipt"] = $r;
            }
        }
    }
    public function viewOrderAdd(string &$route, array &$args): void {
        $this->load->model("extension/mytax/checkout/mytax");
        $order_id = $args["order_id"] ?? 0;
        if ($order_id) {
            $r = $this->model_extension_mytax_checkout_mytax->getReceiptByOrderId($order_id);
            if ($r) {
                $r["qr_link"] = $r["qr_code_path"] ?? "";
                $r["print_link"] = $r["print_link"] ?? "";
                $args["mytax_receipt"] = $r;
            }
        }
    }
}';
$zip->addFromString('catalog/controller/module/mytax.php', $catCtrl);

$g->close();
$zip->close();

echo "ГОТОВО: $target\n";
$z2 = new ZipArchive();
$z2->open($target);
echo "Файлов: " . $z2->numFiles . "\n";
for ($i=0;$i<$z2->numFiles;$i++) echo "  " . $z2->getNameIndex($i) . "\n";
$z2->close();