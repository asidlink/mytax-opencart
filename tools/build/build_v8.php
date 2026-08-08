<?php
$gpt = 'G:/DOWNLOAD/mytax.ocmod.zip';
$target = 'C:/sites/metalka/mytax.ocmod.zip';

$zip = new ZipArchive();
@unlink($target);
if ($zip->open($target, ZipArchive::CREATE) !== true) die("FAIL");

$zip->addFromString('install.json', json_encode([
    'code' => 'mytax',
    'name' => 'Мой налог: кассовые чеки для ИП (НПД)',
    'version' => '3.0.0',
    'author' => 'MyTax-Service',
    'link' => '',
    'type' => 'module'
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$g = new ZipArchive();
$g->open($gpt);

$zip->addFromString('README.txt', $g->getFromName('README.txt'));
$zip->addFromString('install.php', '<?php function install(){} function uninstall(){}');
$zip->addFromString('admin/controller/module/mytax.php', $g->getFromName('admin/controller/module/mytax.php'));
$zip->addFromString('admin/language/ru-ru/module/mytax.php', $g->getFromName('admin/language/ru-ru/module/mytax.php'));
$zip->addFromString('admin/view/template/module/mytax.twig', $g->getFromName('admin/view/template/module/mytax.twig'));
$zip->addFromString('catalog/language/ru-ru/module/mytax.php', $g->getFromName('catalog/language/ru-ru/module/mytax.php'));

// Admin model - как в v2.7 (идемпотентные события)
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
        $this->model_setting_event->addEvent(["code" => "mytax_mail_order_history", "description" => "Чек в письме", "trigger" => "catalog/view/mail/order_history/before", "action" => "extension/mytax/module/mytax.viewOrderHistory", "status" => 1, "sort_order" => 1]);
        $this->model_setting_event->addEvent(["code" => "mytax_mail_order_add", "description" => "Чек в письме", "trigger" => "catalog/view/mail/order_add/before", "action" => "extension/mytax/module/mytax.viewOrderAdd", "status" => 1, "sort_order" => 1]);
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

// Catalog model - ЧИСТЫЙ PHP БЕЗ Node.js! Все запросы напрямую через cURL
$catModel = <<<'PHP'
<?php
namespace Opencart\Catalog\Model\Extension\Mytax\Checkout;
class Mytax extends \Opencart\System\Engine\Model {
    const API_URL = 'https://lknpd.nalog.ru/api/v1';

    public function getReceiptByOrderId(int $order_id): array {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mytax_receipts` WHERE `order_id` = '" . (int)$order_id . "' LIMIT 1");
        if ($query->num_rows) { return $query->row; }
        return [];
    }

    public function saveReceipt(int $order_id, string $email, array $receipt_data): void {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "mytax_receipts` SET `order_id` = '" . (int)$order_id . "', `email` = '" . $this->db->escape($email) . "', `fns_receipt_id` = '" . $this->db->escape($receipt_data['receiptId'] ?? '') . "', `print_link` = '" . $this->db->escape($receipt_data['printLink'] ?? '') . "', `qr_code_path` = '" . $this->db->escape($receipt_data['qrCodePath'] ?? '') . "', `amount` = '" . (float)($receipt_data['amount'] ?? 0) . "', `status` = 'completed', `date_added` = NOW() ON DUPLICATE KEY UPDATE `fns_receipt_id` = VALUES(`fns_receipt_id`), `print_link` = VALUES(`print_link`), `qr_code_path` = VALUES(`qr_code_path`), `amount` = VALUES(`amount`), `status` = 'completed', `error_message` = NULL");
    }

    public function saveError(int $order_id, string $email, string $error_message): void {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "mytax_receipts` SET `order_id` = '" . (int)$order_id . "', `email` = '" . $this->db->escape($email) . "', `status` = 'error', `error_message` = '" . $this->db->escape($error_message) . "', `date_added` = NOW() ON DUPLICATE KEY UPDATE `status` = 'error', `error_message` = VALUES(`error_message`)");
    }

    public function createReceipt(int $order_id, string $email): array {
        // Защита от повторного чека
        $existing = $this->getReceiptByOrderId($order_id);
        if ($existing && $existing['status'] === 'completed' && !empty($existing['fns_receipt_id'])) {
            return ['success' => true, 'receiptId' => $existing['fns_receipt_id'], 'printLink' => $existing['print_link'], 'qrCodePath' => $existing['qr_code_path']];
        }

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);
        if (!$order_info) { return ['success' => false, 'error' => 'Заказ не найден']; }

        $order_products = $this->model_checkout_order->getProducts($order_id);
        if (empty($order_products)) { return ['success' => false, 'error' => 'Нет товаров в заказе']; }

        // Настройки модуля
        $this->load->model('setting/setting');
        $settings = $this->model_setting_setting->getSetting('module_mytax');
        $inn = $settings['module_mytax_inn'] ?? '';
        $password = $settings['module_mytax_password'] ?? '';
        $app_name = $settings['module_mytax_app_name'] ?? 'МЕТАЛЬКА';

        if (empty($inn) || empty($password)) {
            $error = 'Не настроены ИНН/пароль в модуле Мой налог';
            $this->saveError($order_id, $email, $error);
            return ['success' => false, 'error' => $error];
        }

        // Формируем позиции чека (как в mytax-cli.js: каждый товар = отдельная позиция qty=1)
        $services = [];
        foreach ($order_products as $product) {
            $qty = (int)($product['quantity'] ?? 1);
            for ($i = 0; $i < $qty; $i++) {
                $services[] = [
                    'name' => $product['name'] . ', id=' . $product['product_id'] . ', Заказ №' . $order_id,
                    'amount' => (float)round($product['price'], 2),
                    'quantity' => 1
                ];
            }
        }
        if (empty($services)) { return ['success' => false, 'error' => 'Нет товаров']; }

        $totalAmount = array_sum(array_column($services, 'amount'));

        // ==== ВСЁ ЧЕРЕЗ PHP cURL, БЕЗ Node.js ====
        $maxRetries = 3;
        $lastError = '';

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $session = $this->fnsAuth($inn, $password);
                if (empty($session['token'])) { throw new \Exception('Не удалось авторизоваться в ФНС'); }

                $token = $session['token'];
                // Дополнительно подгружаем ИНН из профиля
                if (!empty($session['inn'])) { $inn = $session['inn']; }

                $now = new \DateTime();
                $payload = [
                    'paymentType' => 'CASH',
                    'ignoreMaxTotalIncomeRestriction' => false,
                    'client' => [
                        'contactPhone' => null,
                        'displayName' => null,
                        'incomeType' => 'FROM_INDIVIDUAL',
                        'inn' => null
                    ],
                    'requestTime' => $now->format('Y-m-d\TH:i:s.u\Z'),
                    'operationTime' => $now->format('Y-m-d\TH:i:s.u\Z'),
                    'services' => $services,
                    'totalAmount' => $totalAmount
                ];

                $result = $this->fnsApiCall('income', $payload, $token);

                if (empty($result['approvedReceiptUuid'])) {
                    $lastError = $result['message'] ?? 'ФНС не вернула UUID чека';
                    throw new \Exception($lastError);
                }

                $receiptUuid = $result['approvedReceiptUuid'];
                $printLink = 'https://lknpd.nalog.ru/api/v1/receipt/' . $inn . '/' . $receiptUuid . '/print';

                // Генерация QR-кода
                $qrPath = $this->generateQRCode($printLink, $order_id);

                $receiptData = [
                    'receiptId' => $receiptUuid,
                    'printLink' => $printLink,
                    'qrCodePath' => $qrPath,
                    'amount' => $totalAmount
                ];
                $this->saveReceipt($order_id, $email, $receiptData);

                return ['success' => true] + $receiptData;
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                if ($attempt < $maxRetries) { sleep(2); }
            }
        }

        $this->saveError($order_id, $email, $lastError);
        return ['success' => false, 'error' => $lastError];
    }

    private function fnsAuth(string $inn, string $password): array {
        $deviceInfo = [
            'appVersion' => '1.0.0',
            'sourceType' => 'WEB',
            'sourceDeviceId' => $this->createDeviceId(),
            'metaDetails' => [
                'userAgent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.192 Safari/537.36'
            ]
        ];

        $body = json_encode([
            'username' => $inn,
            'password' => $password,
            'deviceInfo' => $deviceInfo
        ], JSON_UNESCAPED_UNICODE);

        $result = $this->fnsRequest(self::API_URL . '/auth/lkfl', $body);

        if (empty($result['refreshToken'])) {
            throw new \Exception($result['message'] ?? 'Ошибка авторизации в ФНС');
        }

        $this->cache->set('mytax_fns_inn', $result['profile']['inn'] ?? $inn);
        $this->cache->set('mytax_fns_token', $result['token']);
        $this->cache->set('mytax_fns_refresh', $result['refreshToken']);
        $this->cache->set('mytax_fns_token_expire', $result['tokenExpireIn'] ?? '');

        return [
            'token' => $result['token'],
            'refreshToken' => $result['refreshToken'],
            'inn' => $result['profile']['inn'] ?? $inn
        ];
    }

    private function fnsApiCall(string $path, array $payload, string $token): array {
        // Проверяем, не истёк ли токен (небольшой запас)
        $expire = $this->cache->get('mytax_fns_token_expire');
        if ($expire && strtotime($expire) <= time() + 60) {
            // Обновляем токен
            $refresh = $this->cache->get('mytax_fns_refresh');
            if ($refresh) {
                $deviceInfo = [
                    'appVersion' => '1.0.0',
                    'sourceType' => 'WEB',
                    'sourceDeviceId' => $this->createDeviceId(),
                    'metaDetails' => ['userAgent' => 'Mozilla/5.0']
                ];
                $r = $this->fnsRequest(self::API_URL . '/auth/token', json_encode([
                    'deviceInfo' => $deviceInfo,
                    'refreshToken' => $refresh
                ], JSON_UNESCAPED_UNICODE));
                if (!empty($r['token'])) {
                    $token = $r['token'];
                    $this->cache->set('mytax_fns_token', $token);
                    $this->cache->set('mytax_fns_refresh', $r['refreshToken'] ?? $refresh);
                    $this->cache->set('mytax_fns_token_expire', $r['tokenExpireIn'] ?? '');
                }
            }
        }

        return $this->fnsRequest(self::API_URL . '/' . $path, json_encode($payload, JSON_UNESCAPED_UNICODE), $token);
    }

    private function fnsRequest(string $url, string $body, string $token = ''): array {
        $headers = [
            'accept: application/json, text/plain, */*',
            'accept-language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
            'content-type: application/json',
            'referrer: https://lknpd.nalog.ru/'
        ];
        if ($token) { $headers[] = 'authorization: Bearer ' . $token; }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \Exception('cURL ошибка: ' . $curlError);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new \Exception('Не удалось распарсить ответ ФНС (HTTP ' . $httpCode . ')');
        }
        return $decoded;
    }

    private function createDeviceId(): string {
        // Генерируем стабильный deviceId и сохраняем в кэше
        $deviceId = $this->cache->get('mytax_fns_device_id');
        if (!$deviceId) {
            $chars = 'abcdef0123456789';
            $deviceId = '';
            for ($i = 0; $i < 32; $i++) {
                $deviceId .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $this->cache->set('mytax_fns_device_id', $deviceId);
        }
        return $deviceId;
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

// Catalog controller - КАК В v2.7: в письме ЧЕК ЖДЁМ и создаём гарантированно
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
            // Письмо ждёт создания чека: создаём СИНХРОННО перед рендером письма
            $this->load->model("checkout/order");
            $order_info = $this->model_checkout_order->getOrder($order_id);
            if ($order_info && $order_info["order_status_id"] > 0) {
                $this->model_extension_mytax_checkout_mytax->createReceipt($order_id, $order_info["email"]);
            }
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
            // Письмо ждёт создания чека: создаём СИНХРОННО перед рендером письма
            $this->load->model("checkout/order");
            $order_info = $this->model_checkout_order->getOrder($order_id);
            if ($order_info && $order_info["order_status_id"] > 0) {
                $this->model_extension_mytax_checkout_mytax->createReceipt($order_id, $order_info["email"]);
            }
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

echo "ГОТОВО: $target (v3.0.0 — полностью PHP, без Node.js)\n";
$z2 = new ZipArchive();
$z2->open($target);
echo "Файлов: " . $z2->numFiles . "\n";
for ($i=0;$i<$z2->numFiles;$i++) echo "  " . $z2->getNameIndex($i) . "\n";
$z2->close();