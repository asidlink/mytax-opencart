<?php
/**
 * Сборка исправленного mytax.ocmod.zip
 * Основа - правильная структура от ChatGPT (install.json с type=module + пути без префикса)
 * Исправления:
 * 1. Не создаются дубли событий (install() удаляет старые перед добавлением) -> не будет 4 чеков
 * 2. В письмо передаётся QR-код (qr_link из qr_code_path) -> QR будет в e-mail
 */
$tM = 'C:/Users/admin/AppData/Local/Temp/mytax_zip_build';
$tP = 'C:/Users/admin/AppData/Local/Temp/zip_final';
$gpt = 'G:/DOWNLOAD/mytax.ocmod.zip';

$zip = new ZipArchive();
@unlink('G:/DOWNLOAD/mytax_fixed.ocmod.zip');
if ($zip->open('G:/DOWNLOAD/mytax_fixed.ocmod.zip', ZipArchive::CREATE) !== true) die("FAIL");

// install.json - КЛЮЧЕВОЙ МОМЕНТ: type=module + code=mytax
$zip->addFromString('install.json', json_encode([
    'code' => 'mytax',
    'name' => 'Мой налог: кассовые чеки для ИП (НПД)',
    'version' => '2.3.0',
    'author' => 'MyTax-Service',
    'link' => '',
    'type' => 'module'
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "  + install.json (с type=module)\n";

// README.txt из GPT
$g = new ZipArchive();
$g->open($gpt);
$zip->addFromString('README.txt', $g->getFromName('README.txt'));
echo "  + README.txt\n";

// install.php - пустой, как в GPT (OC4 сам обрабатывает)
$zip->addFromString('install.php', '<?php function install(){} function uninstall(){}');
echo "  + install.php\n";

// ===== ADMIN =====
// Controller - из GPT (правильный, с install/uninstall методами)
$adminCtrl = $g->getFromName('admin/controller/module/mytax.php');
$zip->addFromString('admin/controller/module/mytax.php', $adminCtrl);
echo "  + admin/controller/module/mytax.php\n";

// Model - ИСПРАВЛЕННЫЙ: удаляет старые события перед добавлением (чтобы не было 4 чеков!)
$adminModel = <<<'PHP'
<?php
namespace Opencart\Admin\Model\Extension\Mytax\Module;
class Mytax extends \Opencart\System\Engine\Model {
    public function install(): void {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "mytax_receipts` (`receipt_id` int(11) NOT NULL AUTO_INCREMENT,`order_id` int(11) NOT NULL,`email` varchar(96) NOT NULL,`fns_receipt_id` varchar(255) DEFAULT NULL,`print_link` varchar(500) DEFAULT NULL,`qr_code_path` varchar(255) DEFAULT NULL,`amount` decimal(15,4) NOT NULL DEFAULT 0.0000,`status` varchar(50) NOT NULL DEFAULT 'pending',`error_message` text DEFAULT NULL,`date_added` datetime NOT NULL DEFAULT current_timestamp(),PRIMARY KEY (`receipt_id`),UNIQUE KEY `order_id` (`order_id`)) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $this->load->model('setting/event');
        // Удаляем старые события, чтобы не создавать дубли (иначе 4 чека!)
        $this->model_setting_event->deleteEventByCode('mytax_order_history');
        $this->model_setting_event->deleteEventByCode('mytax_mail_order_history');
        $this->model_setting_event->deleteEventByCode('mytax_mail_order_add');
        // Добавляем заново
        $this->model_setting_event->addEvent(['code' => 'mytax_order_history', 'description' => 'Создание чека Мой Налог', 'trigger' => 'catalog/model/checkout/order.addHistory/before', 'action' => 'extension/mytax/module/mytax.orderHistory', 'status' => 1, 'sort_order' => 1]);
        $this->model_setting_event->addEvent(['code' => 'mytax_mail_order_history', 'description' => 'Данные чека в письме', 'trigger' => 'catalog/view/mail/order_history/before', 'action' => 'extension/mytax/module/mytax.viewOrderHistory', 'status' => 1, 'sort_order' => 1]);
        $this->model_setting_event->addEvent(['code' => 'mytax_mail_order_add', 'description' => 'Данные чека в письме', 'trigger' => 'catalog/view/mail/order_add/before', 'action' => 'extension/mytax/module/mytax.viewOrderAdd', 'status' => 1, 'sort_order' => 1]);
    }
    public function uninstall(): void {
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('mytax_order_history');
        $this->model_setting_event->deleteEventByCode('mytax_mail_order_history');
        $this->model_setting_event->deleteEventByCode('mytax_mail_order_add');
    }
}
PHP;
$zip->addFromString('admin/model/module/mytax.php', $adminModel);
echo "  + admin/model/module/mytax.php (ИСПРАВЛЕН: идемпотентный install)\n";

// Language - из GPT
$zip->addFromString('admin/language/ru-ru/module/mytax.php', $g->getFromName('admin/language/ru-ru/module/mytax.php'));
echo "  + admin/language/ru-ru/module/mytax.php\n";

// View - из GPT
$zip->addFromString('admin/view/template/module/mytax.twig', $g->getFromName('admin/view/template/module/mytax.twig'));
echo "  + admin/view/template/module/mytax.twig\n";

// ===== CATALOG =====
// Controller - ИСПРАВЛЕННЫЙ: передаёт qr_link в письмо (QR-код будет виден!)
$catCtrl = <<<'PHP'
<?php
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
                $r['qr_link'] = $r['qr_code_path'] ?? '';
                $r['print_link'] = $r['print_link'] ?? '';
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
                $r['qr_link'] = $r['qr_code_path'] ?? '';
                $r['print_link'] = $r['print_link'] ?? '';
                $args["mytax_receipt"] = $r;
            }
        }
    }
}
PHP;
$zip->addFromString('catalog/controller/module/mytax.php', $catCtrl);
echo "  + catalog/controller/module/mytax.php (ИСПРАВЛЕН: передаёт qr_link)\n";

// Model - из GPT (наш рабочий, генерирует QR)
$zip->addFromString('catalog/model/checkout/mytax.php', $g->getFromName('catalog/model/checkout/mytax.php'));
echo "  + catalog/model/checkout/mytax.php\n";

// Language - из GPT
$zip->addFromString('catalog/language/ru-ru/module/mytax.php', $g->getFromName('catalog/language/ru-ru/module/mytax.php'));
echo "  + catalog/language/ru-ru/module/mytax.php\n";

$g->close();
$zip->close();

echo "\n=== ГОТОВО: G:/DOWNLOAD/mytax_fixed.ocmod.zip ===\n";
$z2 = new ZipArchive();
$z2->open('G:/DOWNLOAD/mytax_fixed.ocmod.zip');
echo "Файлов: {$z2->numFiles}\n";
for ($i=0;$i<$z2->numFiles;$i++) echo "  ".$z2->getNameIndex($i)."\n";
$z2->close();