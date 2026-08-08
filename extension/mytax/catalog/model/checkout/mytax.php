<?php
namespace Opencart\Catalog\Model\Extension\Mytax\Checkout;

class Mytax extends \Opencart\System\Engine\Model {
    const API_URL = 'https://lknpd.nalog.ru/api/v1';
    const LOG = 'mytax.log';

    private function log(string $msg): void {
        if (defined('DIR_STORAGE')) {
            $file = DIR_STORAGE . 'logs/' . self::LOG;
            @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
        } else {
            error_log('mytax: ' . $msg);
        }
    }

    public function getReceiptByOrderId(int $order_id): array {
        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mytax_receipts` WHERE `order_id` = '" . (int)$order_id . "' LIMIT 1");
        return $q->num_rows ? $q->row : [];
    }

    public function saveReceipt(int $order_id, string $email, array $d): void {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "mytax_receipts` SET
            `order_id`='" . (int)$order_id . "',
            `email`='" . $this->db->escape($email) . "',
            `fns_receipt_id`='" . $this->db->escape($d['receiptId'] ?? '') . "',
            `print_link`='" . $this->db->escape($d['printLink'] ?? '') . "',
            `qr_code_path`='" . $this->db->escape($d['qrCodePath'] ?? '') . "',
            `amount`='" . (float)($d['amount'] ?? 0) . "',
            `status`='completed',
            `error_message`=NULL,
            `date_added`=NOW()
            ON DUPLICATE KEY UPDATE
            `fns_receipt_id`=VALUES(`fns_receipt_id`),
            `print_link`=VALUES(`print_link`),
            `qr_code_path`=VALUES(`qr_code_path`),
            `amount`=VALUES(`amount`),
            `status`='completed',
            `error_message`=NULL");
        $receipt_id = $d['receiptId'] ?? '';
        $this->log("Чек сохранён: order=$order_id receipt=$receipt_id");
    }

    public function saveError(int $order_id, string $email, string $msg): void {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "mytax_receipts` SET
            `order_id`='" . (int)$order_id . "',
            `email`='" . $this->db->escape($email) . "',
            `status`='error',
            `error_message`='" . $this->db->escape($msg) . "',
            `date_added`=NOW()
            ON DUPLICATE KEY UPDATE
            `status`='error',
            `error_message`=VALUES(`error_message`)");
        $this->log("ОШИБКА чека: order=$order_id msg=$msg");
    }

    public function createReceipt(int $order_id, string $email, ?int $order_status_id = null, bool $force = false): array {
        // Модуль отключён? Не создаём чек.
        $this->load->model('setting/setting');
        $s = $this->model_setting_setting->getSetting('module_mytax');
        if (empty($s['module_mytax_status'])) {
            $this->log("Модуль отключён, чек не создаётся: order=$order_id");
            return ['success' => false, 'error' => 'Модуль отключён'];
        }

        // Уже создан?
        $existing = $this->getReceiptByOrderId($order_id);
        if ($existing && $existing['status'] === 'completed' && !empty($existing['fns_receipt_id'])) {
            $this->log("Чек уже создан: order=$order_id");
            return ['success' => true, 'already' => true];
        }

        $this->load->model('checkout/order');
        $o = $this->model_checkout_order->getOrder($order_id);
        if (!$o) {
            $this->log("Заказ не найден: order=$order_id");
            return ['success' => false, 'error' => 'Заказ не найден'];
        }
        // Если письмо рендерится (viewOrderAdd/viewOrderHistory) — статус уже положительный,
        // даже если БД ещё не обновлена (addHistory обновляет статус ПОСЛЕ before-событий).
        if ($force) {
            $this->log("Чек создаётся принудительно (рендер письма): order=$order_id");
        } else {
            // Статус из аргументов события (актуален ДО обновления БД в addHistory),
            // иначе берём текущий статус заказа.
            $effective_status = $order_status_id ?? (int)$o['order_status_id'];
            if ($effective_status <= 0) {
                $this->log("Заказ не оплачен или не найден: order=$order_id status=" . $effective_status);
                return ['success' => false, 'error' => 'Заказ не оплачен'];
            }
        }

        $this->load->model('setting/setting');
        $s = $this->model_setting_setting->getSetting('module_mytax');
        $inn = $s['module_mytax_inn'] ?? '';
        $pass = $s['module_mytax_password'] ?? '';
        if (!$inn || !$pass) {
            $this->log("Нет ИНН/пароля: order=$order_id");
            return ['success' => false, 'error' => 'Нет ИНН/пароля в настройках'];
        }

        $products = $this->model_checkout_order->getProducts($order_id);
        $services = [];
        foreach ($products as $p) {
            for ($i = 0; $i < (int)($p['quantity'] ?? 1); $i++) {
                $services[] = [
                    'name' => $p['name'] . ', id=' . $p['product_id'] . ', Заказ №' . $order_id,
                    'amount' => (float)round($p['price'], 2),
                    'quantity' => 1
                ];
            }
        }
        if (!$services) { $this->log("Нет товаров: order=$order_id"); return ['success' => false, 'error' => 'Нет товаров']; }
        $total = array_sum(array_column($services, 'amount'));

        $lastError = '';
        for ($att = 1; $att <= 3; $att++) {
            try {
                $dev = [
                    'appVersion' => '1.0.0',
                    'sourceType' => 'WEB',
                    'sourceDeviceId' => $this->createDeviceId(),
                    'metaDetails' => ['userAgent' => 'Mozilla/5.0']
                ];
                $auth = $this->fnsRequest(self::API_URL . '/auth/lkfl', json_encode([
                    'username' => $inn,
                    'password' => $pass,
                    'deviceInfo' => $dev
                ], JSON_UNESCAPED_UNICODE));
                if (empty($auth['token'])) throw new \Exception($auth['message'] ?? 'Ошибка авторизации ФНС');
                $token = $auth['token'];
                if (!empty($auth['profile']['inn'])) $inn = $auth['profile']['inn'];

                $now = new \DateTime('now', new \DateTimeZone('UTC'));
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
                    'totalAmount' => $total
                ];
                $res = $this->fnsRequest(self::API_URL . '/income', json_encode($payload, JSON_UNESCAPED_UNICODE), $token);
                if (empty($res['approvedReceiptUuid'])) throw new \Exception($res['message'] ?? 'ФНС не вернула UUID');
                $uuid = $res['approvedReceiptUuid'];
                $link = 'https://lknpd.nalog.ru/api/v1/receipt/' . $inn . '/' . $uuid . '/print';
                $qr = $this->generateQRCode($link, $order_id);
                $data = ['receiptId' => $uuid, 'printLink' => $link, 'qrCodePath' => $qr, 'amount' => $total];
                $this->saveReceipt($order_id, $email, $data);
                return ['success' => true] + $data;
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                $this->log("Попытка $att: $lastError");
                if ($att < 3) sleep(2);
            }
        }
        $this->saveError($order_id, $email, $lastError);
        return ['success' => false, 'error' => $lastError];
    }

    private function fnsRequest(string $url, string $body, string $token = ''): array {
        $h = [
            'accept: application/json, text/plain, */*',
            'content-type: application/json',
            'accept-language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7'
        ];
        if ($token) $h[] = 'authorization: Bearer ' . $token;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $h,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $r = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($r === false) throw new \Exception('cURL: ' . $err);
        $d = json_decode((string)$r, true);
        if (!is_array($d)) throw new \Exception('Не удалось распарсить ответ ФНС');
        return $d;
    }

    private function createDeviceId(): string {
        $id = $this->cache->get('mytax_fns_device_id');
        if (!$id) {
            $hex = 'abcdef0123456789';
            $id = '';
            for ($i = 0; $i < 32; $i++) $id .= $hex[random_int(0, 15)];
            $this->cache->set('mytax_fns_device_id', $id);
        }
        return $id;
    }

    private function generateQRCode(string $url, int $order_id): string {
        $dir = DIR_IMAGE . 'mytax_qr/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $file = 'receipt_' . $order_id . '.png';

        // 1. Встроенная библиотека (поставляется вместе с модулем, работает на Debian/Linux)
        $bundled = DIR_EXTENSION . 'mytax/catalog/model/checkout/phpqrcode.php';
        // 2. Резервные пути для локальной разработки (Windows)
        $fallbacks = [
            'C:/sites/metalka/phpqrcode/phpqrcode.php',
            'C:/TEST/MyTax-Service/phpqrcode/phpqrcode.php'
        ];

        $paths = array_merge([$bundled], $fallbacks);

        foreach ($paths as $p) {
            if (file_exists($p)) {
                include_once($p);
                \QRcode::png($url, $dir . $file, QR_ECLEVEL_L, 6, 2);
                $this->log("QR создан: order=$order_id image=$file");
                return 'image/mytax_qr/' . $file;
            }
        }
        $this->log("Библиотека QR не найдена");
        return '';
    }
}