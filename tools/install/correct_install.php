<?php
$site = 'C:/sites/metalka';
$adm = 'dmt';
$tM = 'C:/Users/admin/AppData/Local/Temp/mytax_zip_build';
$tP = 'C:/Users/admin/AppData/Local/Temp/zip_final';

// ===== ОЧИСТКА БД И ФАЙЛОВ =====
$m = new mysqli('localhost', 'root', '', 'metalka');
$p = 'oc_';
$m->query("DELETE FROM {$p}extension WHERE code='mytax'");
$m->query("DELETE FROM {$p}extension_install WHERE code='mytax'");
$m->query("DELETE FROM {$p}extension_path WHERE path LIKE '%mytax%'");
$m->query("DELETE FROM {$p}module WHERE code='mytax'");
$m->query("DELETE FROM {$p}event WHERE code LIKE 'mytax%'");
$m->query("DELETE FROM {$p}setting WHERE code LIKE '%mytax%'");
$m->query("DROP TABLE IF EXISTS {$p}mytax_receipts");

// Удаляем все старые файлы mytax
function rrmdir($d) { if(!is_dir($d)) return; $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d,RecursiveDirectoryIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST); foreach($it as $f) $f->isDir()?@rmdir($f->getRealPath()):@unlink($f->getRealPath()); @rmdir($d); }
$dirs = [
    "$site/extension/mytax",
    "$site/$adm/controller/extension/mytax",
    "$site/$adm/model/extension/mytax",
    "$site/$adm/language/ru-ru/extension/mytax",
    "$site/$adm/view/template/extension/mytax",
    "$site/$adm/controller/extension/module/mytax.php", // старый путь
    "$site/$adm/model/extension/module/mytax.php",
    "$site/$adm/language/ru-ru/extension/module/mytax.php",
    "$site/$adm/view/template/extension/module/mytax.twig",
    "$site/catalog/controller/extension/mytax",
    "$site/catalog/model/extension/mytax",
    "$site/catalog/language/ru-ru/extension/mytax",
    "$site/catalog/controller/extension/module/mytax.php",
];
foreach ($dirs as $d) rrmdir($d);
echo "Очищено\n";

// ===== БД =====
$m->query("INSERT INTO {$p}extension SET type='module', code='mytax'");
$extId = $m->insert_id;
$m->query("INSERT INTO {$p}extension_install SET extension_id=$extId, extension_download_id=0, code='mytax', name='Мой налог', version='2.0.1', author='MyTax-Service', status=1, date_added=NOW()");
$instId = $m->insert_id;
$paths = [
    'extension/mytax/admin/controller/module/mytax.php',
    'extension/mytax/admin/model/module/mytax.php',
    'extension/mytax/admin/language/ru-ru/module/mytax.php',
    'extension/mytax/admin/view/template/module/mytax.twig',
    'extension/mytax/catalog/controller/module/mytax.php',
    'extension/mytax/catalog/model/checkout/mytax.php',
    'extension/mytax/catalog/language/ru-ru/module/mytax.php',
];
foreach ($paths as $p2) $m->query("INSERT INTO {$p}extension_path SET extension_install_id=$instId, path='$p2'");
// oc_module ВАЖНО
$m->query("INSERT INTO {$p}module SET name='Мой налог', code='mytax', setting=''");
$m->query("INSERT INTO {$p}event SET code='mytax_order_history', `trigger`='catalog/model/checkout/order.addHistory/before', action='extension/mytax/module/mytax.orderHistory', status=1, sort_order=1");
$m->query("INSERT INTO {$p}event SET code='mytax_mail_order_history', `trigger`='catalog/view/mail/order_history/before', action='extension/mytax/module/mytax.viewOrderHistory', status=1, sort_order=1");
$m->query("INSERT INTO {$p}event SET code='mytax_mail_order_add', `trigger`='catalog/view/mail/order_add/before', action='extension/mytax/module/mytax.viewOrderAdd', status=1, sort_order=1");
$m->query("CREATE TABLE IF NOT EXISTS {$p}mytax_receipts (receipt_id int(11) NOT NULL AUTO_INCREMENT,order_id int(11) NOT NULL,email varchar(96) NOT NULL,fns_receipt_id varchar(255) DEFAULT NULL,print_link varchar(500) DEFAULT NULL,qr_code_path varchar(255) DEFAULT NULL,amount decimal(15,4) NOT NULL DEFAULT 0.0000,status varchar(50) NOT NULL DEFAULT 'pending',error_message text DEFAULT NULL,date_added datetime NOT NULL DEFAULT current_timestamp(),PRIMARY KEY (receipt_id),UNIQUE KEY order_id (order_id)) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
echo "БД готова\n";
$m->close();

// ===== ФАЙЛЫ =====
// В OC4 путь: extension/mytax/admin/controller/module/mytax.php - это extension.mytax.admin.controller.module.mytax
// В OC4 путь В dmt/: dmt/controller/extension/mytax/module/mytax.php

// 1. Файлы в extension/mytax/ (основное расположение)
$catalogCtrl = '<?php
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
        if ($order_id) { $r = $this->model_extension_mytax_checkout_mytax->getReceiptByOrderId($order_id); if ($r) $args["mytax"] = $r; }
    }
    public function viewOrderAdd(string &$route, array &$args): void {
        $this->load->model("extension/mytax/checkout/mytax");
        $order_id = $args["order_id"] ?? 0;
        if ($order_id) { $r = $this->model_extension_mytax_checkout_mytax->getReceiptByOrderId($order_id); if ($r) $args["mytax"] = $r; }
    }
}';

// extension/mytax/ files
@mkdir("$site/extension/mytax/admin/controller/module", 0777, true);
copy("$tM/admin/controller/module/mytax.php", "$site/extension/mytax/admin/controller/module/mytax.php");

@mkdir("$site/extension/mytax/admin/model/module", 0777, true);
copy("$tM/admin/model/module/mytax.php", "$site/extension/mytax/admin/model/module/mytax.php");

@mkdir("$site/extension/mytax/admin/language/ru-ru/module", 0777, true);
copy("$tM/admin/language/ru-ru/module/mytax.php", "$site/extension/mytax/admin/language/ru-ru/module/mytax.php");

@mkdir("$site/extension/mytax/admin/view/template/module", 0777, true);
copy("$tM/admin/view/template/module/mytax.twig", "$site/extension/mytax/admin/view/template/module/mytax.twig");

@mkdir("$site/extension/mytax/catalog/controller/module", 0777, true);
file_put_contents("$site/extension/mytax/catalog/controller/module/mytax.php", $catalogCtrl);

@mkdir("$site/extension/mytax/catalog/model/checkout", 0777, true);
copy("$tP/catalog/model/checkout/mytax.php", "$site/extension/mytax/catalog/model/checkout/mytax.php");

@mkdir("$site/extension/mytax/catalog/language/ru-ru/module", 0777, true);
copy("$tM/catalog/language/ru-ru/module/mytax.php", "$site/extension/mytax/catalog/language/ru-ru/module/mytax.php");

// 2. Копируем в dmt/ с ПРАВИЛЬНЫМ путём OC4: dmt/controller/extension/mytax/module/mytax.php
@mkdir("$site/$adm/controller/extension/mytax/module", 0777, true);
copy("$site/extension/mytax/admin/controller/module/mytax.php", "$site/$adm/controller/extension/mytax/module/mytax.php");
echo "  OK: $adm/controller/extension/mytax/module/mytax.php\n";

@mkdir("$site/$adm/model/extension/mytax/module", 0777, true);
copy("$site/extension/mytax/admin/model/module/mytax.php", "$site/$adm/model/extension/mytax/module/mytax.php");
echo "  OK: $adm/model/extension/mytax/module/mytax.php\n";

@mkdir("$site/$adm/language/ru-ru/extension/mytax/module", 0777, true);
copy("$site/extension/mytax/admin/language/ru-ru/module/mytax.php", "$site/$adm/language/ru-ru/extension/mytax/module/mytax.php");
echo "  OK: $adm/language/ru-ru/extension/mytax/module/mytax.php\n";

@mkdir("$site/$adm/view/template/extension/mytax/module", 0777, true);
copy("$site/extension/mytax/admin/view/template/module/mytax.twig", "$site/$adm/view/template/extension/mytax/module/mytax.twig");
echo "  OK: $adm/view/template/extension/mytax/module/mytax.twig\n";

// 3. Копируем в catalog/
@mkdir("$site/catalog/controller/extension/mytax/module", 0777, true);
file_put_contents("$site/catalog/controller/extension/mytax/module/mytax.php", $catalogCtrl);
echo "  OK: catalog/controller/extension/mytax/module/mytax.php\n";

@mkdir("$site/catalog/model/extension/mytax/checkout", 0777, true);
copy("$tP/catalog/model/checkout/mytax.php", "$site/catalog/model/extension/mytax/checkout/mytax.php");
echo "  OK: catalog/model/extension/mytax/checkout/mytax.php\n";

@mkdir("$site/catalog/language/ru-ru/extension/mytax/module", 0777, true);
copy("$tM/catalog/language/ru-ru/module/mytax.php", "$site/catalog/language/ru-ru/extension/mytax/module/mytax.php");
echo "  OK: catalog/language/ru-ru/extension/mytax/module/mytax.php\n";

// ===== ПРОВЕРКА =====
echo "\n=== ПРОВЕРКА ===\n";
$checks = [
    "$adm/controller/extension/mytax/module/mytax.php",  // OC4 правильный путь!
    "$adm/model/extension/mytax/module/mytax.php",
    "$adm/language/ru-ru/extension/mytax/module/mytax.php",
    "$adm/view/template/extension/mytax/module/mytax.twig",
    "catalog/controller/extension/mytax/module/mytax.php",
    "catalog/model/extension/mytax/checkout/mytax.php",
    "catalog/language/ru-ru/extension/mytax/module/mytax.php",
    "extension/mytax/admin/controller/module/mytax.php",
];
$allOk = true;
foreach ($checks as $f) {
    if (file_exists("$site/$f")) {
        echo "  [OK] $f\n";
    } else {
        echo "  [FAIL] $f - ОТСУТСТВУЕТ!\n";
        $allOk = false;
    }
}

if ($allOk) {
    echo "\n✅ ВСЁ ГОТОВО! Обновите админку:\n";
    echo "   https://металька.рф:8443/dmt/\n";
    echo "   Расширения → Модули\n";
}