<?php
/**
 * v4.0.1 — Полная переработка модуля "Мой налог" для OpenCart 4
 * - Полностью PHP (без Node.js)
 * - Чек создаётся после успешной оплаты
 * - Письмо дополняется QR-кодом (не блокируется)
 * - Логирование в system/storage/logs/mytax.log
 * - Корректная установка (install.php вызывает install())
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$site = 'C:/sites/metalka';
$adm = 'dmt';
$ext = "$site/extension/mytax";
$INN = '123456789012';               // <-- замените на ваш ИНН (12 цифр)
$PASS = 'your_fns_password';         // <-- замените на ваш пароль от «Мой налог»

// ================== 1. ФАЙЛЫ МОДУЛЯ ==================
echo "=== 1. Создание файлов модуля v4.0.1 ===\n";

// --- install.json ---
$files['install.json'] = json_encode([
    'code' => 'mytax',
    'name' => 'Мой налог',
    'version' => '4.0.1',
    'author' => 'MyTax-Service',
    'link' => '',
    'type' => 'module'
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// --- install.php: запускает install() контроллера (установщик OC4 ищет эту функцию) ---
$files['install.php'] = <<<'PHP'
<?php
/**
 * Установка/удаление модуля.
 * OpenCart вызывает эти функции при установке расширения.
 * Real work делает admin controller install()/uninstall() через суффикс route.
 */
function install() {}
function uninstall() {}
PHP;

// --- admin/controller/module/mytax.php: index/save + install/uninstall ---
$files['admin/controller/module/mytax.php'] = <<<'PHP'
<?php
namespace Opencart\Admin\Controller\Extension\Mytax\Module;

class Mytax extends \Opencart\System\Engine\Controller {
    public function index(): void {
        $this->load->language('extension/mytax/module/mytax');
        $this->document->setTitle($this->language->get('heading_title'));
        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['entry_status'] = $this->language->get('entry_status');
        $data['entry_inn'] = $this->language->get('entry_inn');
        $data['entry_password'] = $this->language->get('entry_password');
        $data['entry_app_name'] = $this->language->get('entry_app_name');
        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['save'] = $this->url->link('extension/mytax/module/mytax.save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');

        $this->load->model('setting/setting');
        $settings = $this->model_setting_setting->getSetting('module_mytax');
        $fields = ['module_mytax_status','module_mytax_inn','module_mytax_password','module_mytax_app_name'];
        foreach ($fields as $f) {
            $data[$f] = $settings[$f] ?? '';
        }
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/mytax/module/mytax', $data));
    }

    public function save(): void {
        $this->load->language('extension/mytax/module/mytax');
        $json = [];
        if (!$this->user->hasPermission('modify', 'extension/mytax/module/mytax')) {
            $json['error']['warning'] = $this->language->get('error_permission');
        }
        if (!$json) {
            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting('module_mytax', $this->request->post);
            $json['success'] = $this->language->get('text_success');
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function install(): void {
        $this->load->model('extension/mytax/module/mytax');
        $this->model_extension_mytax_module_mytax->install();
    }

    public function uninstall(): void {
        $this->load->model('extension/mytax/module/mytax');
        $this->model_extension_mytax_module_mytax->uninstall();
    }
}
PHP;

// --- admin/model/module/mytax.php: install() создаёт таблицу и события ---
$files['admin/model/module/mytax.php'] = <<<'PHP'
<?php
namespace Opencart\Admin\Model\Extension\Mytax\Module;

class Mytax extends \Opencart\System\Engine\Model {
    public function install(): void {
        // Таблица чеков
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "mytax_receipts` (
            `receipt_id` int(11) NOT NULL AUTO_INCREMENT,
            `order_id` int(11) NOT NULL,
            `email` varchar(96) NOT NULL,
            `fns_receipt_id` varchar(255) DEFAULT NULL,
            `print_link` varchar(500) DEFAULT NULL,
            `qr_code_path` varchar(255) DEFAULT NULL,
            `amount` decimal(15,4) NOT NULL DEFAULT 0.0000,
            `status` varchar(50) NOT NULL DEFAULT 'pending',
            `error_message` text DEFAULT NULL,
            `date_added` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`receipt_id`),
            UNIQUE KEY `order_id` (`order_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $this->load->model('setting/event');
        // Очистка старых событий
        foreach (['mytax_order_history','mytax_mail_order_history','mytax_mail_order_add','mytax_checkout_success'] as $code) {
            $this->model_setting_event->deleteEventByCode($code);
        }
        // Создание чека при смене статуса заказа
        $this->model_setting_event->addEvent([
            'code' => 'mytax_order_history',
            'trigger' => 'catalog/model/checkout/order.addHistory/before',
            'action' => 'extension/mytax/module/mytax.orderHistory',
            'status' => 1, 'sort_order' => 1
        ]);
        // Дополнение письма о новом заказе данными чека
        $this->model_setting_event->addEvent([
            'code' => 'mytax_mail_order_add',
            'trigger' => 'catalog/view/mail/order_add/before',
            'action' => 'extension/mytax/module/mytax.viewOrderAdd',
            'status' => 1, 'sort_order' => 1
        ]);
        // Дополнение письма об изменении статуса
        $this->model_setting_event->addEvent([
            'code' => 'mytax_mail_order_history',
            'trigger' => 'catalog/view/mail/order_history/before',
            'action' => 'extension/mytax/module/mytax.viewOrderHistory',
            'status' => 1, 'sort_order' => 1
        ]);
        // Страховка: создание чека на странице успеха
        $this->model_setting_event->addEvent([
            'code' => 'mytax_checkout_success',
            'trigger' => 'catalog/view/checkout/success/before',
            'action' => 'extension/mytax/module/mytax.viewSuccess',
            'status' => 1, 'sort_order' => 1
        ]);
    }

    public function uninstall(): void {
        $this->load->model('setting/event');
        foreach (['mytax_order_history','mytax_mail_order_history','mytax_mail_order_add','mytax_checkout_success'] as $code) {
            $this->model_setting_event->deleteEventByCode($code);
        }
    }
}
PHP;

// --- admin/language/ru-ru/module/mytax.php ---
$files['admin/language/ru-ru/module/mytax.php'] = <<<'PHP'
<?php
$_['heading_title'] = 'Мой налог';
$_['text_edit'] = 'Редактирование модуля';
$_['text_enabled'] = 'Включено';
$_['text_disabled'] = 'Отключено';
$_['entry_status'] = 'Статус';
$_['entry_inn'] = 'ИНН';
$_['entry_password'] = 'Пароль (личный кабинет Мой налог)';
$_['entry_app_name'] = 'Название приложения';
$_['button_save'] = 'Сохранить';
$_['button_cancel'] = 'Отмена';
$_['text_success'] = 'Настройки сохранены';
$_['error_permission'] = 'Нет прав на изменение';
PHP;

// --- admin/view/template/module/mytax.twig ---
$files['admin/view/template/module/mytax.twig'] = <<<'TWIG'
{{ header }}{{ column_left }}
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <div class="float-end">
        <button type="submit" form="form-mytax" data-bs-toggle="tooltip" title="{{ button_save }}" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i></button>
        <a href="{{ back }}" data-bs-toggle="tooltip" title="{{ button_cancel }}" class="btn btn-light"><i class="fa-solid fa-reply"></i></a>
      </div>
      <h1>{{ heading_title }}</h1>
      <ul class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ back }}">{{ text_edit }}</a></li>
      </ul>
    </div>
  </div>
  <div class="container-fluid">
    <div class="card">
      <div class="card-header"><i class="fa-solid fa-pencil"></i> {{ text_edit }}</div>
      <div class="card-body">
        <form id="form-mytax" action="{{ save }}" method="post" data-oc-toggle="ajax">
          <div class="row mb-3">
            <label class="col-sm-2 col-form-label">{{ entry_status }}</label>
            <div class="col-sm-10">
              <select name="module_mytax_status" class="form-select">
                <option value="1" {% if module_mytax_status %}selected{% endif %}>{{ text_enabled }}</option>
                <option value="0" {% if not module_mytax_status %}selected{% endif %}>{{ text_disabled }}</option>
              </select>
            </div>
          </div>
          <div class="row mb-3">
            <label class="col-sm-2 col-form-label">{{ entry_inn }}</label>
            <div class="col-sm-10"><input type="text" name="module_mytax_inn" value="{{ module_mytax_inn }}" class="form-control" /></div>
          </div>
          <div class="row mb-3">
            <label class="col-sm-2 col-form-label">{{ entry_password }}</label>
            <div class="col-sm-10"><input type="password" name="module_mytax_password" value="{{ module_mytax_password }}" class="form-control" /></div>
          </div>
          <div class="row mb-3">
            <label class="col-sm-2 col-form-label">{{ entry_app_name }}</label>
            <div class="col-sm-10"><input type="text" name="module_mytax_app_name" value="{{ module_mytax_app_name }}" class="form-control" /></div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
{{ footer }}
TWIG;

// --- catalog/model/checkout/mytax.php: ПОЛНАЯ PHP-реализация API ФНС ---
$files['catalog/model/checkout/mytax.php'] = <<<'PHP'
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
        $this->log("Чек сохранён: order=$order_id receipt={$d['receiptId'] ?? ''}");
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

    public function createReceipt(int $order_id, string $email): array {
        // Уже создан?
        $existing = $this->getReceiptByOrderId($order_id);
        if ($existing && $existing['status'] === 'completed' && !empty($existing['fns_receipt_id'])) {
            $this->log("Чек уже создан: order=$order_id");
            return ['success' => true, 'already' => true];
        }

        $this->load->model('checkout/order');
        $o = $this->model_checkout_order->getOrder($order_id);
        if (!$o || (int)$o['order_status_id'] <= 0) {
            $this->log("Заказ не оплачен или не найден: order=$order_id status=" . ($o['order_status_id'] ?? 'null'));
            return ['success' => false, 'error' => 'Заказ не оплачен'];
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
        foreach (['C:/sites/metalka/phpqrcode/phpqrcode.php', 'C:/TEST/MyTax-Service/phpqrcode/phpqrcode.php'] as $p) {
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
PHP;

// --- catalog/controller/module/mytax.php: события + письмо ---
$files['catalog/controller/module/mytax.php'] = <<<'PHP'
<?php
namespace Opencart\Catalog\Controller\Extension\Mytax\Module;

class Mytax extends \Opencart\System\Engine\Controller {
    public function index(array $args): void {}

    /**
     * Создание чека при изменении статуса заказа.
     * Trigger: catalog/model/checkout/order.addHistory/before
     */
    public function orderHistory(string &$route, array &$args): void {
        $id = (int)($args[0] ?? 0);
        if (!$id) return;
        $this->load->model('extension/mytax/checkout/mytax');
        $this->load->model('checkout/order');
        $o = $this->model_checkout_order->getOrder($id);
        if ($o && (int)$o['order_status_id'] > 0) {
            $this->model_extension_mytax_checkout_mytax->createReceipt($id, $o['email']);
        }
    }

    /** Страховка: создание чека на странице успеха */
    public function viewSuccess(string &$route, array &$args): void {
        $id = (int)($this->session->data['order_id'] ?? 0);
        if (!$id) return;
        $this->load->model('extension/mytax/checkout/mytax');
        $this->load->model('checkout/order');
        $o = $this->model_checkout_order->getOrder($id);
        if ($o && (int)$o['order_status_id'] > 0) {
            $this->model_extension_mytax_checkout_mytax->createReceipt($id, $o['email']);
        }
    }

    private function getOrderId(array &$args): int {
        // Данные шаблона передаются в $args целиком (view/.../before)
        return (int)($args['order_id'] ?? 0);
    }

    private function getReceiptData(int $order_id): array {
        $this->load->model('extension/mytax/checkout/mytax');
        $r = $this->model_extension_mytax_checkout_mytax->getReceiptByOrderId($order_id);
        if (!$r || $r['status'] !== 'completed') return [];

        $base = $this->config->get('config_url');
        if (!$base && defined('HTTP_CATALOG')) $base = HTTP_CATALOG;
        if (!$base) $base = 'https://xn--80aanved7b4e.xn--p1ai:8443/';
        $r['qr_link'] = $base . ltrim($r['qr_code_path'] ?? '', '/');
        $r['print_link'] = $r['print_link'] ?? '';
        $r['receipt_number'] = $r['fns_receipt_id'] ?? '';
        $r['amount'] = $r['amount'] ?? '';
        $r['date'] = $r['date_added'] ?? '';
        return $r;
    }

    /** Дополняет письмо о НОВОМ заказе данными чека */
    public function viewOrderAdd(string &$route, array &$args): void {
        $id = $this->getOrderId($args);
        if (!$id) return;
        // Гарантируем создание чека перед рендером письма
        $this->load->model('extension/mytax/checkout/mytax');
        $this->load->model('checkout/order');
        $o = $this->model_checkout_order->getOrder($id);
        if ($o && (int)$o['order_status_id'] > 0) {
            $this->model_extension_mytax_checkout_mytax->createReceipt($id, $o['email']);
        }
        $r = $this->getReceiptData($id);
        if ($r) $args['mytax_receipt'] = $r;
    }

    /** Дополняет письмо об ИЗМЕНЕНИИ СТАТУСА данными чека */
    public function viewOrderHistory(string &$route, array &$args): void {
        $id = $this->getOrderId($args);
        if (!$id) return;
        $this->load->model('extension/mytax/checkout/mytax');
        $this->load->model('checkout/order');
        $o = $this->model_checkout_order->getOrder($id);
        if ($o && (int)$o['order_status_id'] > 0) {
            $this->model_extension_mytax_checkout_mytax->createReceipt($id, $o['email']);
        }
        $r = $this->getReceiptData($id);
        if ($r) $args['mytax_receipt'] = $r;
    }
}
PHP;

// --- catalog/language/ru-ru/module/mytax.php ---
$files['catalog/language/ru-ru/module/mytax.php'] = '<?php' . PHP_EOL;

// --- README ---
$files['README.txt'] = "Мой налог v4.0.1\nЧистый PHP без Node.js\n";

// Запись файлов
foreach ($files as $rel => $content) {
    $path = "$ext/$rel";
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, $content);
    echo "  [OK] $rel\n";
}

// ================== 2. ДЕПЛОЙ В ADM/CATALOG ==================
echo "=== 2. Деплой ===\n";
function rrd($d) { if (!is_dir($d)) return; $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $f) $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath()); @rmdir($d); }
rrd("$site/$adm/controller/extension/mytax");
rrd("$site/$adm/model/extension/mytax");
rrd("$site/$adm/language/ru-ru/extension/mytax");
rrd("$site/$adm/view/template/extension/mytax");
rrd("$site/catalog/controller/extension/mytax");
rrd("$site/catalog/model/extension/mytax");
rrd("$site/catalog/language/ru-ru/extension/mytax");

foreach ($files as $rel => $content) {
    $dst = "$ext/$rel";
    @mkdir(dirname($dst), 0777, true);
    file_put_contents($dst, $content);
}
$pairs = [
    "$ext/admin/controller/module/mytax.php" => "$site/$adm/controller/extension/mytax/module/mytax.php",
    "$ext/admin/model/module/mytax.php" => "$site/$adm/model/extension/mytax/module/mytax.php",
    "$ext/admin/language/ru-ru/module/mytax.php" => "$site/$adm/language/ru-ru/extension/mytax/module/mytax.php",
    "$ext/admin/view/template/module/mytax.twig" => "$site/$adm/view/template/extension/mytax/module/mytax.twig",
    "$ext/catalog/controller/module/mytax.php" => "$site/catalog/controller/extension/mytax/module/mytax.php",
    "$ext/catalog/model/checkout/mytax.php" => "$site/catalog/model/extension/mytax/checkout/mytax.php",
    "$ext/catalog/language/ru-ru/module/mytax.php" => "$site/catalog/language/ru-ru/extension/mytax/module/mytax.php",
];
foreach ($pairs as $s => $d) { @mkdir(dirname($d), 0777, true); copy($s, $d); }
echo "  OK ($adm + catalog)\n";

// ================== 3. БД ==================
echo "=== 3. База данных ===\n";
$m = new mysqli('localhost', 'root', '', 'metalka');
// Очистка старых записей mytax
$m->query("DELETE FROM oc_extension WHERE code='mytax' OR `extension`='mytax'");
$m->query("DELETE FROM oc_extension_install WHERE code='mytax'");
$m->query("DELETE FROM oc_extension_path WHERE path LIKE '%mytax%'");
$m->query("DELETE FROM oc_module WHERE code='mytax'");
$m->query("DELETE FROM oc_event WHERE code LIKE 'mytax%'");
$m->query("DELETE FROM oc_setting WHERE `key` LIKE 'module_mytax%'");
$m->query("CREATE TABLE IF NOT EXISTS oc_mytax_receipts (
    receipt_id int(11) NOT NULL AUTO_INCREMENT,
    order_id int(11) NOT NULL,
    email varchar(96) NOT NULL,
    fns_receipt_id varchar(255) DEFAULT NULL,
    print_link varchar(500) DEFAULT NULL,
    qr_code_path varchar(255) DEFAULT NULL,
    amount decimal(15,4) NOT NULL DEFAULT 0.0000,
    status varchar(50) NOT NULL DEFAULT 'pending',
    error_message text DEFAULT NULL,
    date_added datetime NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (receipt_id),
    UNIQUE KEY order_id (order_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Регистрация расширения, модуля, настроек
$m->query("INSERT INTO oc_extension SET `extension`='mytax', type='module', code='mytax'");
$m->query("INSERT INTO oc_module SET name='Мой налог', code='mytax', setting=''");
$m->query("INSERT INTO oc_setting SET `code`='module_mytax', `key`='module_mytax_status', `value`='1'");
$m->query("INSERT INTO oc_setting SET `code`='module_mytax', `key`='module_mytax_inn', `value`='$INN'");
$m->query("INSERT INTO oc_setting SET `code`='module_mytax', `key`='module_mytax_password', `value`='" . $m->real_escape_string($PASS) . "'");
$m->query("INSERT INTO oc_setting SET `code`='module_mytax', `key`='module_mytax_app_name', `value`='МЕТАЛЬКА'");

// События
$events = [
    ['mytax_order_history', 'catalog/model/checkout/order.addHistory/before', 'extension/mytax/module/mytax.orderHistory'],
    ['mytax_mail_order_add', 'catalog/view/mail/order_add/before', 'extension/mytax/module/mytax.viewOrderAdd'],
    ['mytax_mail_order_history', 'catalog/view/mail/order_history/before', 'extension/mytax/module/mytax.viewOrderHistory'],
    ['mytax_checkout_success', 'catalog/view/checkout/success/before', 'extension/mytax/module/mytax.viewSuccess'],
];
foreach ($events as $e) {
    $m->query("INSERT INTO oc_event SET code='{$e[0]}', `trigger`='{$e[1]}', action='{$e[2]}', status=1, sort_order=1");
}
$m->close();
echo "  OK: расширение, модуль, настройки, таблица, 4 события\n";

// ================== 4. ВЕРСИЯ / ARCHIVE ==================
echo "=== 4. Сборка zip ===\n";
$zip = "$site/mytax.ocmod.zip";
@unlink($zip);
$z = new ZipArchive();
if ($z->open($zip, ZipArchive::CREATE) === true) {
    foreach ($files as $rel => $content) $z->addFromString($rel, $content);
    $z->close();
    echo "  OK: $zip\n";
} else {
    echo "  НЕ УДАЛОСЬ создать zip\n";
}

echo "\n=== ГОТОВО: модуль v4.0.1 установлен ===\n";
echo "Файлы модуля:\n";
foreach ($files as $rel => $c) echo "  - extension/mytax/$rel\n";
echo "\nПроверьте: Расширения -> Модули -> Мой налог (должен быть включён)\n";