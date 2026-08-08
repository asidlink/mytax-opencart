<?php
// v3.1.0: исправляем чтение order_id из args (данные события лежат в $args['data'])
$gpt = 'G:/DOWNLOAD/mytax.ocmod.zip';
$target = 'C:/sites/metalka/mytax.ocmod.zip';

$zip = new ZipArchive();
@unlink($target);
$zip->open($target, ZipArchive::CREATE);

$zip->addFromString('install.json', json_encode([
    'code' => 'mytax', 'name' => 'Мой налог',
    'version' => '3.1.0', 'author' => 'MyTax-Service', 'link' => '', 'type' => 'module'
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$g = new ZipArchive(); $g->open($gpt);
$zip->addFromString('README.txt', $g->getFromName('README.txt'));
$zip->addFromString('install.php', '<?php function install(){} function uninstall(){}');
$zip->addFromString('admin/controller/module/mytax.php', $g->getFromName('admin/controller/module/mytax.php'));
$zip->addFromString('admin/language/ru-ru/module/mytax.php', $g->getFromName('admin/language/ru-ru/module/mytax.php'));
$zip->addFromString('admin/view/template/module/mytax.twig', $g->getFromName('admin/view/template/module/mytax.twig'));
$zip->addFromString('catalog/language/ru-ru/module/mytax.php', $g->getFromName('catalog/language/ru-ru/module/mytax.php'));

$adminModel = <<<'PHP'
<?php
namespace Opencart\Admin\Model\Extension\Mytax\Module;
class Mytax extends \Opencart\System\Engine\Model {
    public function install(): void {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "mytax_receipts` (`receipt_id` int(11) NOT NULL AUTO_INCREMENT,`order_id` int(11) NOT NULL,`email` varchar(96) NOT NULL,`fns_receipt_id` varchar(255) DEFAULT NULL,`print_link` varchar(500) DEFAULT NULL,`qr_code_path` varchar(255) DEFAULT NULL,`amount` decimal(15,4) NOT NULL DEFAULT 0.0000,`status` varchar(50) NOT NULL DEFAULT 'pending',`error_message` text DEFAULT NULL,`date_added` datetime NOT NULL DEFAULT current_timestamp(),PRIMARY KEY (`receipt_id`),UNIQUE KEY `order_id` (`order_id`)) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $this->load->model("setting/event");
        $this->model_setting_event->deleteEventByCode("mytax_order_history");
        $this->model_setting_event->deleteEventByCode("mytax_mail_order_history");
        $this->model_setting_event->deleteEventByCode("mytax_mail_order_add");
        $this->model_setting_event->deleteEventByCode("mytax_checkout_success");
        $this->model_setting_event->addEvent(["code" => "mytax_order_history", "trigger" => "catalog/model/checkout/order.addHistory/before", "action" => "extension/mytax/module/mytax.orderHistory", "status" => 1, "sort_order" => 1]);
        $this->model_setting_event->addEvent(["code" => "mytax_mail_order_history", "trigger" => "catalog/view/mail/order_history/before", "action" => "extension/mytax/module/mytax.viewOrderHistory", "status" => 1, "sort_order" => 1]);
        $this->model_setting_event->addEvent(["code" => "mytax_mail_order_add", "trigger" => "catalog/view/mail/order_add/before", "action" => "extension/mytax/module/mytax.viewOrderAdd", "status" => 1, "sort_order" => 1]);
        $this->model_setting_event->addEvent(["code" => "mytax_checkout_success", "trigger" => "catalog/view/checkout/success/before", "action" => "extension/mytax/module/mytax.viewSuccess", "status" => 1, "sort_order" => 1]);
    }
    public function uninstall(): void {
        $this->load->model("setting/event");
        $this->model_setting_event->deleteEventByCode("mytax_order_history");
        $this->model_setting_event->deleteEventByCode("mytax_mail_order_history");
        $this->model_setting_event->deleteEventByCode("mytax_mail_order_add");
        $this->model_setting_event->deleteEventByCode("mytax_checkout_success");
    }
}
PHP;
$zip->addFromString('admin/model/module/mytax.php', $adminModel);

// Catalog model — как v3.0.1 (UTC)
$catModel = file_get_contents('C:/sites/metalka/extension/mytax/catalog/model/checkout/mytax.php');
$zip->addFromString('catalog/model/checkout/mytax.php', $catModel);

// Catalog controller — ИСПРАВЛЕН: читаем order_id из $args['data'] (как передаёт событие OC4)
$catCtrl = <<<'PHP'
<?php
namespace Opencart\Catalog\Controller\Extension\Mytax\Module;
class Mytax extends \Opencart\System\Engine\Controller {
    public function index(array $args): void {}
    public function orderHistory(string &$route, array &$args): void {
        $this->load->model("extension/mytax/checkout/mytax");
        $order_id = $args[0] ?? 0;
        $this->load->model("checkout/order");
        $order_info = $this->model_checkout_order->getOrder($order_id);
        if ($order_info && $order_info["order_status_id"] > 0) {
            $this->model_extension_mytax_checkout_mytax->createReceipt($order_id, $order_info["email"]);
        }
    }
    public function viewSuccess(string &$route, array &$args): void {
        $this->load->model("extension/mytax/checkout/mytax");
        $order_id = $this->session->data["order_id"] ?? 0;
        if ($order_id) {
            $this->load->model("checkout/order");
            $order_info = $this->model_checkout_order->getOrder($order_id);
            if ($order_info && $order_info["order_status_id"] > 0) {
                $this->model_extension_mytax_checkout_mytax->createReceipt($order_id, $order_info["email"]);
            }
        }
    }
    private function getOrderId(array &$args): int {
        // В OC4 событие catalog/view/.../before передаёт $args['data'] = данные шаблона
        $data = $args["data"] ?? $args;
        return (int)($data["order_id"] ?? 0);
    }
    private function setReceipt(array &$args, array $r): void {
        $base = $this->config->get("config_url");
        if (!$base && defined("HTTP_CATALOG")) $base = HTTP_CATALOG;
        if (!$base) $base = "https://xn--80aanved7b4e.xn--p1ai:8443/";
        $r["qr_link"] = $base . ltrim($r["qr_code_path"] ?? "", "/");
        $r["print_link"] = $r["print_link"] ?? "";
        if (isset($args["data"]) && is_array($args["data"])) {
            $args["data"]["mytax_receipt"] = $r;
        } else {
            $args["mytax_receipt"] = $r;
        }
    }
    public function viewOrderHistory(string &$route, array &$args): void {
        $this->load->model("extension/mytax/checkout/mytax");
        $order_id = $this->getOrderId($args);
        if ($order_id) {
            $this->load->model("checkout/order");
            $order_info = $this->model_checkout_order->getOrder($order_id);
            if ($order_info && $order_info["order_status_id"] > 0) {
                $this->model_extension_mytax_checkout_mytax->createReceipt($order_id, $order_info["email"]);
            }
            $r = $this->model_extension_mytax_checkout_mytax->getReceiptByOrderId($order_id);
            if ($r) { $this->setReceipt($args, $r); }
        }
    }
    public function viewOrderAdd(string &$route, array &$args): void {
        $this->load->model("extension/mytax/checkout/mytax");
        $order_id = $this->getOrderId($args);
        if ($order_id) {
            $this->load->model("checkout/order");
            $order_info = $this->model_checkout_order->getOrder($order_id);
            if ($order_info && $order_info["order_status_id"] > 0) {
                $this->model_extension_mytax_checkout_mytax->createReceipt($order_id, $order_info["email"]);
            }
            $r = $this->model_extension_mytax_checkout_mytax->getReceiptByOrderId($order_id);
            if ($r) { $this->setReceipt($args, $r); }
        }
    }
}
PHP;
$zip->addFromString('catalog/controller/module/mytax.php', $catCtrl);

$g->close(); $zip->close();
echo "OK v3.1.0: $target\n";

// Разворачиваем на сайте
$site = 'C:/sites/metalka'; $adm = 'dmt';
function rrd($d){ if(!is_dir($d))return; $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d,RecursiveDirectoryIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST); foreach($it as $f)$f->isDir()?@rmdir($f->getRealPath()):@unlink($f->getRealPath()); @rmdir($d); }
rrd("$site/extension/mytax"); rrd("$site/$adm/controller/extension/mytax"); rrd("$site/$adm/model/extension/mytax"); rrd("$site/$adm/language/ru-ru/extension/mytax"); rrd("$site/$adm/view/template/extension/mytax"); rrd("$site/catalog/controller/extension/mytax"); rrd("$site/catalog/model/extension/mytax"); rrd("$site/catalog/language/ru-ru/extension/mytax");

$z = new ZipArchive(); $z->open($target);
for($i=0;$i<$z->numFiles;$i++){ $n=$z->getNameIndex($i); if(in_array($n,['install.json','install.php','README.txt']))continue; $c=$z->getFromIndex($i); $dst="$site/extension/mytax/$n"; @mkdir(dirname($dst),0777,true); file_put_contents($dst,$c); }
$z->close();

$pairs = [
    "$site/extension/mytax/admin/controller/module/mytax.php" => "$site/$adm/controller/extension/mytax/module/mytax.php",
    "$site/extension/mytax/admin/model/module/mytax.php" => "$site/$adm/model/extension/mytax/module/mytax.php",
    "$site/extension/mytax/admin/language/ru-ru/module/mytax.php" => "$site/$adm/language/ru-ru/extension/mytax/module/mytax.php",
    "$site/extension/mytax/admin/view/template/module/mytax.twig" => "$site/$adm/view/template/extension/mytax/module/mytax.twig",
    "$site/extension/mytax/catalog/controller/module/mytax.php" => "$site/catalog/controller/extension/mytax/module/mytax.php",
    "$site/extension/mytax/catalog/model/checkout/mytax.php" => "$site/catalog/model/extension/mytax/checkout/mytax.php",
    "$site/extension/mytax/catalog/language/ru-ru/module/mytax.php" => "$site/catalog/language/ru-ru/extension/mytax/module/mytax.php",
];
foreach($pairs as $s=>$d){ @mkdir(dirname($d),0777,true); copy($s,$d); }
echo "Deployed v3.1.0\n";
foreach($pairs as $d) if(!file_exists($d)) echo "MISSING: $d\n";
echo "DONE\n";