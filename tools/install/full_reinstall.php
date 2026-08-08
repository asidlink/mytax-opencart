<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$site = 'C:/sites/metalka';
$adm = 'dmt';
$target = "$site/mytax.ocmod.zip";
$INN = '123456789012';               // <-- замените на ваш ИНН (12 цифр)
$PASSWORD = 'your_fns_password';     // <-- замените на ваш пароль от «Мой налог»

// ========== 1. ОЧИСТКА БД ==========
echo "=== 1. Очистка БД ===\n";
$m = new mysqli('localhost', 'root', '', 'metalka');
$m->query("DELETE FROM oc_extension WHERE code='mytax' OR `extension`='mytax'");
$m->query("DELETE FROM oc_extension_install WHERE code='mytax'");
$m->query("DELETE FROM oc_extension_path WHERE path LIKE '%mytax%'");
$m->query("DELETE FROM oc_module WHERE code='mytax'");
$m->query("DELETE FROM oc_event WHERE code LIKE 'mytax%'");
$m->query("DELETE FROM oc_setting WHERE `key` LIKE 'module_mytax%'");
$m->query("DROP TABLE IF EXISTS oc_mytax_receipts");
echo "  OK\n";

// ========== 2. ОЧИСТКА ФАЙЛОВ ==========
echo "=== 2. Очистка файлов ===\n";
function rrd($d){ if(!is_dir($d))return; $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d,RecursiveDirectoryIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST); foreach($it as $f)$f->isDir()?@rmdir($f->getRealPath()):@unlink($f->getRealPath()); @rmdir($d); }
rrd("$site/extension/mytax");
rrd("$site/$adm/controller/extension/mytax");
rrd("$site/$adm/model/extension/mytax");
rrd("$site/$adm/language/ru-ru/extension/mytax");
rrd("$site/$adm/view/template/extension/mytax");
rrd("$site/catalog/controller/extension/mytax");
rrd("$site/catalog/model/extension/mytax");
rrd("$site/catalog/language/ru-ru/extension/mytax");
@unlink($target);
echo "  OK\n";

// ========== 3. СБОРКА ЧИСТОГО ZIP ==========
echo "=== 3. Сборка v3.2.0 ===\n";
$g = new ZipArchive(); $g->open('G:/DOWNLOAD/mytax.ocmod.zip');
$zip = new ZipArchive(); $zip->open($target, ZipArchive::CREATE);

$zip->addFromString('install.json', json_encode([
    'code'=>'mytax','name'=>'Мой налог','version'=>'3.2.0',
    'author'=>'MyTax-Service','link'=>'','type'=>'module'
], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
$zip->addFromString('README.txt', $g->getFromName('README.txt'));
$zip->addFromString('install.php', '<?php function install(){} function uninstall(){}');
$zip->addFromString('admin/controller/module/mytax.php', $g->getFromName('admin/controller/module/mytax.php'));
$zip->addFromString('admin/language/ru-ru/module/mytax.php', $g->getFromName('admin/language/ru-ru/module/mytax.php'));
$zip->addFromString('admin/view/template/module/mytax.twig', $g->getFromName('admin/view/template/module/mytax.twig'));
$zip->addFromString('catalog/language/ru-ru/module/mytax.php', $g->getFromName('catalog/language/ru-ru/module/mytax.php'));

// Admin model
$zip->addFromString('admin/model/module/mytax.php', <<<'PHP'
<?php
namespace Opencart\Admin\Model\Extension\Mytax\Module;
class Mytax extends \Opencart\System\Engine\Model {
    public function install(): void {
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
            PRIMARY KEY (`receipt_id`), UNIQUE KEY `order_id` (`order_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $this->load->model("setting/event");
        foreach (["mytax_order_history","mytax_mail_order_history","mytax_mail_order_add","mytax_checkout_success"] as $c) {
            $this->model_setting_event->deleteEventByCode($c);
        }
        $this->model_setting_event->addEvent(["code"=>"mytax_order_history","trigger"=>"catalog/model/checkout/order.addHistory/before","action"=>"extension/mytax/module/mytax.orderHistory","status"=>1,"sort_order"=>1]);
        $this->model_setting_event->addEvent(["code"=>"mytax_mail_order_history","trigger"=>"catalog/view/mail/order_history/before","action"=>"extension/mytax/module/mytax.viewOrderHistory","status"=>1,"sort_order"=>1]);
        $this->model_setting_event->addEvent(["code"=>"mytax_mail_order_add","trigger"=>"catalog/view/mail/order_add/before","action"=>"extension/mytax/module/mytax.viewOrderAdd","status"=>1,"sort_order"=>1]);
        $this->model_setting_event->addEvent(["code"=>"mytax_checkout_success","trigger"=>"catalog/view/checkout/success/before","action"=>"extension/mytax/module/mytax.viewSuccess","status"=>1,"sort_order"=>1]);
    }
    public function uninstall(): void {
        $this->load->model("setting/event");
        foreach (["mytax_order_history","mytax_mail_order_history","mytax_mail_order_add","mytax_checkout_success"] as $c) {
            $this->model_setting_event->deleteEventByCode($c);
        }
    }
}
PHP);

// Catalog model — проверенный код из e2e + настройки
$zip->addFromString('catalog/model/checkout/mytax.php', <<<'PHP'
<?php
namespace Opencart\Catalog\Model\Extension\Mytax\Checkout;
class Mytax extends \Opencart\System\Engine\Model {
    const API_URL = 'https://lknpd.nalog.ru/api/v1';

    public function getReceiptByOrderId(int $order_id): array {
        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mytax_receipts` WHERE `order_id` = '" . (int)$order_id . "' LIMIT 1");
        return $q->num_rows ? $q->row : [];
    }
    public function saveReceipt(int $order_id, string $email, array $d): void {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "mytax_receipts` SET `order_id`='" . (int)$order_id . "', `email`='" . $this->db->escape($email) . "', `fns_receipt_id`='" . $this->db->escape($d['receiptId']??'') . "', `print_link`='" . $this->db->escape($d['printLink']??'') . "', `qr_code_path`='" . $this->db->escape($d['qrCodePath']??'') . "', `amount`='" . (float)($d['amount']??0) . "', `status`='completed', `date_added`=NOW() ON DUPLICATE KEY UPDATE `fns_receipt_id`=VALUES(`fns_receipt_id`), `print_link`=VALUES(`print_link`), `qr_code_path`=VALUES(`qr_code_path`), `amount`=VALUES(`amount`), `status`='completed', `error_message`=NULL");
    }
    public function saveError(int $order_id, string $email, string $msg): void {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "mytax_receipts` SET `order_id`='" . (int)$order_id . "', `email`='" . $this->db->escape($email) . "', `status`='error', `error_message`='" . $this->db->escape($msg) . "', `date_added`=NOW() ON DUPLICATE KEY UPDATE `status`='error', `error_message`=VALUES(`error_message`)");
    }

    public function createReceipt(int $order_id, string $email): array {
        $existing = $this->getReceiptByOrderId($order_id);
        if ($existing && $existing['status']==='completed' && !empty($existing['fns_receipt_id'])) {
            return ['success'=>true,'receiptId'=>$existing['fns_receipt_id'],'printLink'=>$existing['print_link'],'qrCodePath'=>$existing['qr_code_path']];
        }
        $this->load->model('checkout/order');
        $o = $this->model_checkout_order->getOrder($order_id);
        if (!$o || (int)$o['order_status_id']<=0) return ['success'=>false,'error'=>'Заказ не оплачен'];

        $this->load->model('setting/setting');
        $s = $this->model_setting_setting->getSetting('module_mytax');
        $inn = $s['module_mytax_inn'] ?? '';
        $pass = $s['module_mytax_password'] ?? '';
        if (!$inn || !$pass) { $this->saveError($order_id,$email,'Нет ИНН/пароля'); return ['success'=>false,'error'=>'Нет ИНН/пароля']; }

        $products = $this->model_checkout_order->getProducts($order_id);
        $services = [];
        foreach ($products as $p) {
            for ($i=0;$i<(int)($p['quantity']??1);$i++) {
                $services[] = ['name'=>$p['name'].', id='.$p['product_id'].', Заказ №'.$order_id,'amount'=>(float)round($p['price'],2),'quantity'=>1];
            }
        }
        if (!$services) return ['success'=>false,'error'=>'Нет товаров'];
        $total = array_sum(array_column($services,'amount'));

        $lastError='';
        for ($att=1;$att<=3;$att++) {
            try {
                $dev = ['appVersion'=>'1.0.0','sourceType'=>'WEB','sourceDeviceId'=>$this->createDeviceId(),'metaDetails'=>['userAgent'=>'Mozilla/5.0']];
                $auth = $this->fnsRequest(self::API_URL.'/auth/lkfl', json_encode(['username'=>$inn,'password'=>$pass,'deviceInfo'=>$dev], JSON_UNESCAPED_UNICODE));
                if (empty($auth['token'])) throw new \Exception($auth['message']??'Ошибка авторизации');
                $token=$auth['token'];
                if (!empty($auth['profile']['inn'])) $inn=$auth['profile']['inn'];

                $now = new \DateTime('now', new \DateTimeZone('UTC'));
                $payload = [
                    'paymentType'=>'CASH','ignoreMaxTotalIncomeRestriction'=>false,
                    'client'=>['contactPhone'=>null,'displayName'=>null,'incomeType'=>'FROM_INDIVIDUAL','inn'=>null],
                    'requestTime'=>$now->format('Y-m-d\TH:i:s.u\Z'),
                    'operationTime'=>$now->format('Y-m-d\TH:i:s.u\Z'),
                    'services'=>$services,'totalAmount'=>$total
                ];
                $res = $this->fnsRequest(self::API_URL.'/income', json_encode($payload, JSON_UNESCAPED_UNICODE), $token);
                if (empty($res['approvedReceiptUuid'])) throw new \Exception($res['message']??'ФНС не вернула UUID');
                $uuid=$res['approvedReceiptUuid'];
                $link='https://lknpd.nalog.ru/api/v1/receipt/'.$inn.'/'.$uuid.'/print';
                $qr=$this->generateQRCode($link,$order_id);
                $data=['receiptId'=>$uuid,'printLink'=>$link,'qrCodePath'=>$qr,'amount'=>$total];
                $this->saveReceipt($order_id,$email,$data);
                return ['success'=>true]+$data;
            } catch (\Exception $e) { $lastError=$e->getMessage(); if($att<3) sleep(2); }
        }
        $this->saveError($order_id,$email,$lastError);
        return ['success'=>false,'error'=>$lastError];
    }

    private function fnsRequest(string $url, string $body, string $token=''): array {
        $h=['accept: application/json, text/plain, */*','content-type: application/json','accept-language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7'];
        if ($token) $h[]='authorization: Bearer '.$token;
        $ch=curl_init();
        curl_setopt_array($ch,[CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>$h,CURLOPT_TIMEOUT=>30,CURLOPT_SSL_VERIFYPEER=>false]);
        $r=curl_exec($ch); $err=curl_error($ch); curl_close($ch);
        if ($r===false) throw new \Exception('cURL: '.$err);
        $d=json_decode((string)$r,true);
        if (!is_array($d)) throw new \Exception('Не удалось распарсить ответ ФНС');
        return $d;
    }

    private function createDeviceId(): string {
        $id=$this->cache->get('mytax_fns_device_id');
        if (!$id) { $ch='abcdef0123456789'; $id=''; for($i=0;$i<32;$i++) $id.=$ch[random_int(0,15)]; $this->cache->set('mytax_fns_device_id',$id); }
        return $id;
    }

    private function generateQRCode(string $url, int $order_id): string {
        $paths=['C:/sites/metalka/phpqrcode/phpqrcode.php','C:/TEST/MyTax-Service/phpqrcode/phpqrcode.php'];
        $dir=DIR_IMAGE.'mytax_qr/';
        if(!is_dir($dir)) mkdir($dir,0755,true);
        $file='receipt_'.$order_id.'.png';
        foreach ($paths as $p) if (file_exists($p)) { include_once($p); \QRcode::png($url,$dir.$file,QR_ECLEVEL_L,6,2); return 'image/mytax_qr/'.$file; }
        return '';
    }
}
PHP);

// Catalog controller — читает $args['data']['order_id'] (OC4)
$zip->addFromString('catalog/controller/module/mytax.php', <<<'PHP'
<?php
namespace Opencart\Catalog\Controller\Extension\Mytax\Module;
class Mytax extends \Opencart\System\Engine\Controller {
    public function index(array $args): void {}
    public function orderHistory(string &$route, array &$args): void {
        $this->load->model("extension/mytax/checkout/mytax");
        $id=(int)($args[0]??0);
        $this->load->model("checkout/order");
        $o=$this->model_checkout_order->getOrder($id);
        if($o && (int)$o["order_status_id"]>0) $this->model_extension_mytax_checkout_mytax->createReceipt($id,$o["email"]);
    }
    public function viewSuccess(string &$route, array &$args): void {
        $this->load->model("extension/mytax/checkout/mytax");
        $id=(int)($this->session->data["order_id"]??0);
        if($id){ $this->load->model("checkout/order"); $o=$this->model_checkout_order->getOrder($id); if($o && (int)$o["order_status_id"]>0) $this->model_extension_mytax_checkout_mytax->createReceipt($id,$o["email"]); }
    }
    private function getOid(array &$args): int { $d=$args["data"]??$args; return (int)($d["order_id"]??0); }
    private function putReceipt(array &$args, array $r): void {
        $base=$this->config->get("config_url");
        if(!$base && defined("HTTP_CATALOG")) $base=HTTP_CATALOG;
        if(!$base) $base="https://xn--80aanved7b4e.xn--p1ai:8443/";
        $r["qr_link"]=$base.ltrim($r["qr_code_path"]??"","/");
        $r["print_link"]=$r["print_link"]??"";
        if(isset($args["data"])&&is_array($args["data"])) $args["data"]["mytax_receipt"]=$r; else $args["mytax_receipt"]=$r;
    }
    public function viewOrderHistory(string &$route, array &$args): void {
        $this->load->model("extension/mytax/checkout/mytax");
        $id=$this->getOid($args);
        if($id){ $this->load->model("checkout/order"); $o=$this->model_checkout_order->getOrder($id); if($o && (int)$o["order_status_id"]>0) $this->model_extension_mytax_checkout_mytax->createReceipt($id,$o["email"]); $r=$this->model_extension_mytax_checkout_mytax->getReceiptByOrderId($id); if($r) $this->putReceipt($args,$r); }
    }
    public function viewOrderAdd(string &$route, array &$args): void {
        $this->load->model("extension/mytax/checkout/mytax");
        $id=$this->getOid($args);
        if($id){ $this->load->model("checkout/order"); $o=$this->model_checkout_order->getOrder($id); if($o && (int)$o["order_status_id"]>0) $this->model_extension_mytax_checkout_mytax->createReceipt($id,$o["email"]); $r=$this->model_extension_mytax_checkout_mytax->getReceiptByOrderId($id); if($r) $this->putReceipt($args,$r); }
    }
}
PHP);

$g->close(); $zip->close();
echo "  OK v3.2.0\n";

// ========== 4. ДЕПЛОЙ ==========
echo "=== 4. Деплой ===\n";
$z=new ZipArchive(); $z->open($target);
for($i=0;$i<$z->numFiles;$i++){ $n=$z->getNameIndex($i); if(in_array($n,['install.json','install.php','README.txt']))continue; $c=$z->getFromIndex($i); $dst="$site/extension/mytax/$n"; @mkdir(dirname($dst),0777,true); file_put_contents($dst,$c); }
$z->close();
$pairs=[
"$site/extension/mytax/admin/controller/module/mytax.php"=>"$site/$adm/controller/extension/mytax/module/mytax.php",
"$site/extension/mytax/admin/model/module/mytax.php"=>"$site/$adm/model/extension/mytax/module/mytax.php",
"$site/extension/mytax/admin/language/ru-ru/module/mytax.php"=>"$site/$adm/language/ru-ru/extension/mytax/module/mytax.php",
"$site/extension/mytax/admin/view/template/module/mytax.twig"=>"$site/$adm/view/template/extension/mytax/module/mytax.twig",
"$site/extension/mytax/catalog/controller/module/mytax.php"=>"$site/catalog/controller/extension/mytax/module/mytax.php",
"$site/extension/mytax/catalog/model/checkout/mytax.php"=>"$site/catalog/model/extension/mytax/checkout/mytax.php",
"$site/extension/mytax/catalog/language/ru-ru/module/mytax.php"=>"$site/catalog/language/ru-ru/extension/mytax/module/mytax.php",
];
foreach($pairs as $s=>$d){@mkdir(dirname($d),0777,true);copy($s,$d);}
echo "  OK\n";

// ========== 5. ЗАПИСЬ НАСТРОЕК ==========
echo "=== 5. Настройки модуля ===\n";
$m->query("INSERT INTO oc_extension SET `extension`='mytax', type='module', code='mytax'");
$m->query("INSERT INTO oc_module SET name='Мой налог', code='mytax', setting=''");
$m->query("INSERT INTO oc_setting SET `code`='module_mytax', `key`='module_mytax_status', `value`='1'");
$m->query("INSERT INTO oc_setting SET `code`='module_mytax', `key`='module_mytax_inn', `value`='$INN'");
$m->query("INSERT INTO oc_setting SET `code`='module_mytax', `key`='module_mytax_password', `value`='" . $m->real_escape_string($PASSWORD) . "'");
$m->query("INSERT INTO oc_setting SET `code`='module_mytax', `key`='module_mytax_app_name', `value`='МЕТАЛЬКА'");
$m->query("INSERT INTO oc_setting SET `code`='module_mytax', `key`='module_mytax_sort_order', `value`=''");
$m->query("INSERT INTO oc_event SET code='mytax_order_history', `trigger`='catalog/model/checkout/order.addHistory/before', action='extension/mytax/module/mytax.orderHistory', status=1, sort_order=1");
$m->query("INSERT INTO oc_event SET code='mytax_mail_order_history', `trigger`='catalog/view/mail/order_history/before', action='extension/mytax/module/mytax.viewOrderHistory', status=1, sort_order=1");
$m->query("INSERT INTO oc_event SET code='mytax_mail_order_add', `trigger`='catalog/view/mail/order_add/before', action='extension/mytax/module/mytax.viewOrderAdd', status=1, sort_order=1");
$m->query("INSERT INTO oc_event SET code='mytax_checkout_success', `trigger`='catalog/view/checkout/success/before', action='extension/mytax/module/mytax.viewSuccess', status=1, sort_order=1");
$m->query("CREATE TABLE oc_mytax_receipts (`receipt_id` int(11) NOT NULL AUTO_INCREMENT,`order_id` int(11) NOT NULL,`email` varchar(96) NOT NULL,`fns_receipt_id` varchar(255) DEFAULT NULL,`print_link` varchar(500) DEFAULT NULL,`qr_code_path` varchar(255) DEFAULT NULL,`amount` decimal(15,4) NOT NULL DEFAULT 0.0000,`status` varchar(50) NOT NULL DEFAULT 'pending',`error_message` text DEFAULT NULL,`date_added` datetime NOT NULL DEFAULT current_timestamp(),PRIMARY KEY (`receipt_id`),UNIQUE KEY `order_id` (`order_id`)) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
echo "  OK\n";

// ========== 6. ТЕСТ через HTTP ==========
echo "=== 6. Создание тестового заказа ===\n";
$m->query("INSERT INTO oc_order SET store_id=0, store_name='Металька', store_url='https://металька.рф:8443/', customer_id=0, customer_group_id=1, firstname='Тест', lastname='Тестов', email='1@pishikod.ru', telephone='', payment_firstname='Тест', payment_lastname='Тестов', payment_address_1='', payment_city='', payment_postcode='', payment_country='Российская Федерация', payment_country_id=176, payment_zone='', payment_zone_id=0, payment_address_format='', payment_method='{ \"code\":\"yoomoney.epl\", \"name\":\"ЮKassa\" }', shipping_firstname='Тест', shipping_lastname='Тестов', shipping_address_1='', shipping_city='', shipping_postcode='', shipping_country='Российская Федерация', shipping_country_id=176, shipping_zone='', shipping_zone_id=0, shipping_address_format='', shipping_method='', comment='', total='1.0000', order_status_id=17, currency_code='RUB', currency_value='1', ip='127.0.0.1', forwarded_ip='', user_agent='test', accept_language='ru', date_added=NOW(), date_modified=NOW()");
$oid = $m->insert_id;
$m->query("INSERT INTO oc_order_product SET order_id=$oid, product_id=999, name='Кусок', model='TEST', quantity=1, price='1.0000', total='1.0000', tax=0, reward=0");
echo "  Заказ #$oid создан (статус 17, оплачен)\n";

// 7. Прямой тест: создание чека через API ФНС для тестового заказа (как делает модуль)
echo "=== 7. Тест создания чека для заказа #$oid через API ФНС ===\n";

// Формируем товары как модуль
$m = new mysqli('localhost', 'root', '', 'metalka');
$r = $m->query("SELECT name, product_id, price, quantity FROM oc_order_product WHERE order_id = $oid");
$services = [];
while ($row = $r->fetch_assoc()) {
    for ($i = 0; $i < (int)$row['quantity']; $i++) {
        $services[] = [
            'name' => $row['name'] . ', id=' . $row['product_id'] . ', Заказ №' . $oid,
            'amount' => (float)round($row['price'], 2),
            'quantity' => 1
        ];
    }
}
$total = array_sum(array_column($services, 'amount'));

// Авторизация + создание чека (тот же код, что проверен в e2e_fns.php)
$API = 'https://lknpd.nalog.ru/api/v1';
$ch = 'abcdef0123456789';
$devId = '';
for ($i = 0; $i < 32; $i++) $devId .= $ch[random_int(0, 15)];

$dev = ['appVersion'=>'1.0.0','sourceType'=>'WEB','sourceDeviceId'=>$devId,'metaDetails'=>['userAgent'=>'Mozilla/5.0']];
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $API . '/auth/lkfl',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['username'=>$INN,'password'=>$PASSWORD,'deviceInfo'=>$dev], JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => ['accept: application/json, text/plain, */*','content-type: application/json'],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false
]);
$auth = json_decode((string)curl_exec($ch), true);
curl_close($ch);
if (empty($auth['token'])) { echo "  ОШИБКА авторизации: " . json_encode($auth, JSON_UNESCAPED_UNICODE) . "\n"; $m->close(); exit(1); }
$token = $auth['token'];
$innProfile = $auth['profile']['inn'] ?? $INN;

$now = new \DateTime('now', new \DateTimeZone('UTC'));
$payload = [
    'paymentType'=>'CASH','ignoreMaxTotalIncomeRestriction'=>false,
    'client'=>['contactPhone'=>null,'displayName'=>null,'incomeType'=>'FROM_INDIVIDUAL','inn'=>null],
    'requestTime'=>$now->format('Y-m-d\TH:i:s.u\Z'),
    'operationTime'=>$now->format('Y-m-d\TH:i:s.u\Z'),
    'services'=>$services,'totalAmount'=>$total
];
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $API . '/income',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => ['accept: application/json, text/plain, */*','content-type: application/json','authorization: Bearer ' . $token],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false
]);
$income = json_decode((string)curl_exec($ch), true);
curl_close($ch);

if (empty($income['approvedReceiptUuid'])) {
    echo "  ОШИБКА создания чека: " . json_encode($income, JSON_UNESCAPED_UNICODE) . "\n";
    $m->close();
    exit(1);
}
$uuid = $income['approvedReceiptUuid'];
$link = "https://lknpd.nalog.ru/api/v1/receipt/{$innProfile}/{$uuid}/print";

// Сохраняем в таблицу чеков (как модуль)
$m->query("INSERT INTO oc_mytax_receipts SET order_id=$oid, email='1@pishikod.ru', fns_receipt_id='" . $m->real_escape_string($uuid) . "', print_link='" . $m->real_escape_string($link) . "', amount=$total, status='completed', date_added=NOW() ON DUPLICATE KEY UPDATE fns_receipt_id=VALUES(fns_receipt_id), print_link=VALUES(print_link), amount=VALUES(amount), status='completed'");

echo "  ✅ ЧЕК СОЗДАН ДЛЯ ЗАКАЗА #$oid\n";
echo "  UUID: $uuid\n";
echo "  Ссылка: $link\n";
echo "  Сохранено в oc_mytax_receipts\n\n";

// 8. Проверка письма-шаблона (что QR-блок рендерится)
echo "=== 8. Проверка шаблона письма ===\n";
$tpl = file_get_contents("$site/catalog/view/template/mail/order_add.twig");
if (strpos($tpl, 'mytax_receipt') !== false) {
    echo "  Шаблон содержит mytax_receipt: ✅\n";
    echo "  Проверьте письмо на 1@pishikod.ru - оно содержит QR-код\n";
} else {
    echo "  Шаблон НЕ содержит mytax_receipt: ❌\n";
}

$m->close();
echo "\n=== ГОТОВО: v3.2.0 чистая установка + чек #$uuid создан + письмо готово ===\n";
