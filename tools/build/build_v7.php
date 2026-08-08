<?php
$gpt = 'G:/DOWNLOAD/mytax.ocmod.zip';
$target = 'C:/sites/metalka/mytax.ocmod.zip';

$zip = new ZipArchive();
@unlink($target);
if ($zip->open($target, ZipArchive::CREATE) !== true) die("FAIL");

$zip->addFromString('install.json', json_encode([
    'code' => 'mytax',
    'name' => 'Мой налог: кассовые чеки для ИП (НПД)',
    'version' => '2.7.0',
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
$zip->addFromString('catalog/language/ru-ru/module/mytax.php', $g->getFromName('catalog/language/ru-ru/module/mytax.php'));

// Admin model - идемпотентная установка (как v2.6)
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
        $this->model_setting_event->addEvent(["code" => "mytax_order_history", "description" => "Создание чека при изменении статуса", "trigger" => "catalog/model/checkout/order.addHistory/before", "action" => "extension/mytax/module/mytax.orderHistory", "status" => 1, "sort_order" => 1]);
        $this->model_setting_event->addEvent(["code" => "mytax_mail_order_history", "description" => "Чек в письме об изменении", "trigger" => "catalog/view/mail/order_history/before", "action" => "extension/mytax/module/mytax.viewOrderHistory", "status" => 1, "sort_order" => 1]);
        $this->model_setting_event->addEvent(["code" => "mytax_mail_order_add", "description" => "Чек в письме о новом заказе", "trigger" => "catalog/view/mail/order_add/before", "action" => "extension/mytax/module/mytax.viewOrderAdd", "status" => 1, "sort_order" => 1]);
        $this->model_setting_event->addEvent(["code" => "mytax_checkout_success", "description" => "Чек на странице успеха", "trigger" => "catalog/view/checkout/success/before", "action" => "extension/mytax/module/mytax.viewSuccess", "status" => 1, "sort_order" => 1]);
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

// Catalog model - (как v2.6 с защитой от повторов, QR-генерацией)
$catModel = <<<'PHP'
<?php
namespace Opencart\Catalog\Model\Extension\Mytax\Checkout;
class Mytax extends \Opencart\System\Engine\Model {
	public function getReceiptByOrderId(int $order_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mytax_receipts` WHERE `order_id` = '" . (int)$order_id . "' LIMIT 1");
		if ($query->num_rows) { return $query->row; }
		return [];
	}
	public function saveReceipt(int $order_id, string $email, array $receipt_data): void {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "mytax_receipts` SET `order_id` = '" . (int)$order_id . "', `email` = '" . $this->db->escape($email) . "', `fns_receipt_id` = '" . $this->db->escape($receipt_data['receiptId'] ?? '') . "', `print_link` = '" . $this->db->escape($receipt_data['printLink'] ?? '') . "', `qr_code_path` = '" . $this->db->escape($receipt_data['qrCodePath'] ?? '') . "', `amount` = '" . (float)($receipt_data['amount'] ?? 0) . "', `status` = 'completed', `date_added` = NOW() ON DUPLICATE KEY UPDATE `fns_receipt_id` = VALUES(`fns_receipt_id`), `print_link` = VALUES(`print_link`), `qr_code_path` = VALUES(`qr_code_path`), `amount` = VALUES(`amount`), `status` = 'completed'");
	}
	public function saveError(int $order_id, string $email, string $error_message): void {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "mytax_receipts` SET `order_id` = '" . (int)$order_id . "', `email` = '" . $this->db->escape($email) . "', `status` = 'error', `error_message` = '" . $this->db->escape($error_message) . "', `date_added` = NOW() ON DUPLICATE KEY UPDATE `status` = 'error', `error_message` = VALUES(`error_message`)");
	}
	public function createReceipt(int $order_id, string $email): array {
		$existing = $this->getReceiptByOrderId($order_id);
		if ($existing && $existing['status'] === 'completed' && !empty($existing['fns_receipt_id'])) {
			return ['success' => true, 'receiptId' => $existing['fns_receipt_id'], 'printLink' => $existing['print_link'], 'qrCodePath' => $existing['qr_code_path']];
		}
		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($order_id);
		if (!$order_info) { return ['success' => false, 'error' => 'Заказ не найден']; }
		$order_products = $this->model_checkout_order->getProducts($order_id);
		if (empty($order_products)) { return ['success' => false, 'error' => 'Нет товаров в заказе']; }
		$items = [];
		foreach ($order_products as $product) {
			$items[] = ['id' => $product['product_id'], 'name' => $product['name'], 'price' => (float)$product['price'], 'quantity' => (int)$product['quantity']];
		}
		$itemsJson = json_encode($items, JSON_UNESCAPED_UNICODE);
		$nodePath = defined('NODE_PATH') ? NODE_PATH : 'node';
		$cliScript = 'C:\\TEST\\MyTax-Service\\mytax-cli.js';
		if (!file_exists($cliScript)) {
			$altPaths = [__DIR__ . '/../../../../TEST/MyTax-Service/mytax-cli.js', 'C:/TEST/MyTax-Service/mytax-cli.js'];
			foreach ($altPaths as $p) { if (file_exists($p)) { $cliScript = $p; break; } }
		}
		$command = $nodePath . ' ' . escapeshellarg($cliScript) . ' ' . (int)$order_id . ' ' . escapeshellarg($email);
		$descriptorspec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
		$process = proc_open($command, $descriptorspec, $pipes);
		if (!is_resource($process)) {
			$errorMsg = 'Не удалось запустить Node.js процесс';
			$this->saveError($order_id, $email, $errorMsg);
			return ['success' => false, 'error' => $errorMsg];
		}
		fwrite($pipes[0], $itemsJson);
		fclose($pipes[0]);
		$stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]); fclose($pipes[2]);
		$returnCode = proc_close($process);
		$outputStr = trim($stdout); $lines = explode("\n", $outputStr); $jsonLine = '';
		foreach (array_reverse($lines) as $line) {
			$line = trim($line);
			if (strpos($line, '{') === 0) {
				$decoded = json_decode($line, true);
				if ($decoded && isset($decoded['success'])) { $jsonLine = $line; break; }
			}
		}
		if ($jsonLine) {
			$result = json_decode($jsonLine, true);
			if ($result['success']) {
				$qrPath = $this->generateQRCode($result['printLink'], $order_id);
				$result['qrCodePath'] = $qrPath;
				$this->saveReceipt($order_id, $email, $result);
			} else { $this->saveError($order_id, $email, $result['error'] ?? 'Неизвестная ошибка'); }
			return $result;
		}
		$errorMsg = 'Не удалось получить ответ от CLI скрипта. STDERR: ' . substr($stderr, 0, 300) . ' STDOUT: ' . substr($stdout, 0, 300);
		$this->saveError($order_id, $email, $errorMsg);
		return ['success' => false, 'error' => $errorMsg];
	}
	private function generateQRCode(string $url, int $order_id): string {
		$qrDir = DIR_IMAGE . 'mytax_qr/';
		if (!is_dir($qrDir)) { mkdir($qrDir, 0755, true); }
		$qrFile = 'receipt_' . $order_id . '.png';
		$qrPath = $qrDir . $qrFile;
		$phpqrcodePath = 'C:\\TEST\\MyTax-Service\\phpqrcode\\phpqrcode.php';
		if (file_exists($phpqrcodePath)) {
			include_once($phpqrcodePath);
			\QRcode::png($url, $qrPath, QR_ECLEVEL_L, 6, 2);
			return 'image/mytax_qr/' . $qrFile;
		}
		return '';
	}
}
PHP;
$zip->addFromString('catalog/model/checkout/mytax.php', $catModel);

// Catalog controller - КЛЮЧЕВОЕ ИСПРАВЛЕНИЕ: в обработчиках писем СНАЧАЛА создаём чек, ПОТОМ читаем из БД
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
    public function viewOrderHistory(string &$route, array &$args): void {
        $this->load->model("extension/mytax/checkout/mytax");
        $order_id = $args["order_id"] ?? 0;
        if ($order_id) {
            // ==== ГАРАНТИРУЕМ НАЛИЧИЕ ЧЕКА ПЕРЕД ФОРМИРОВАНИЕМ ПИСЬМА ====
            $this->load->model("checkout/order");
            $order_info = $this->model_checkout_order->getOrder($order_id);
            if ($order_info && $order_info["order_status_id"] > 0) {
                $this->model_extension_mytax_checkout_mytax->createReceipt($order_id, $order_info["email"]);
            }
            // ================================================================
            $r = $this->model_extension_mytax_checkout_mytax->getReceiptByOrderId($order_id);
            if ($r) {
                $base = $this->config->get("config_url");
                if (!$base && defined("HTTP_CATALOG")) $base = HTTP_CATALOG;
                if (!$base) $base = "https://xn--80aanved7b4e.xn--p1ai:8443/";
                $r["qr_link"] = $base . ltrim($r["qr_code_path"] ?? "", "/");
                $r["print_link"] = $r["print_link"] ?? "";
                $args["mytax_receipt"] = $r;
            }
        }
    }
    public function viewOrderAdd(string &$route, array &$args): void {
        $this->load->model("extension/mytax/checkout/mytax");
        $order_id = $args["order_id"] ?? 0;
        if ($order_id) {
            // ==== ГАРАНТИРУЕМ НАЛИЧИЕ ЧЕКА ПЕРЕД ФОРМИРОВАНИЕМ ПИСЬМА ====
            $this->load->model("checkout/order");
            $order_info = $this->model_checkout_order->getOrder($order_id);
            if ($order_info && $order_info["order_status_id"] > 0) {
                $this->model_extension_mytax_checkout_mytax->createReceipt($order_id, $order_info["email"]);
            }
            // ================================================================
            $r = $this->model_extension_mytax_checkout_mytax->getReceiptByOrderId($order_id);
            if ($r) {
                $base = $this->config->get("config_url");
                if (!$base && defined("HTTP_CATALOG")) $base = HTTP_CATALOG;
                if (!$base) $base = "https://xn--80aanved7b4e.xn--p1ai:8443/";
                $r["qr_link"] = $base . ltrim($r["qr_code_path"] ?? "", "/");
                $r["print_link"] = $r["print_link"] ?? "";
                $args["mytax_receipt"] = $r;
            }
        }
    }
}
PHP;
$zip->addFromString('catalog/controller/module/mytax.php', $catCtrl);

$g->close();
$zip->close();

echo "ГОТОВО: $target (v2.7.0)\n";
$z2 = new ZipArchive();
$z2->open($target);
echo "Файлов: " . $z2->numFiles . "\n";
for ($i=0;$i<$z2->numFiles;$i++) echo "  " . $z2->getNameIndex($i) . "\n";
$z2->close();