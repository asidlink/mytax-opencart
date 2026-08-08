<?php
$site = 'C:/sites/metalka';
$adm = 'dmt';
$gpt = 'C:/sites/metalka/mytax.ocmod.zip';

$zip = new ZipArchive();
if ($zip->open($gpt) !== true) die("FAIL");

// ===== 1. Очистка БД =====
$m = new mysqli('localhost', 'root', '', 'metalka');
$p = 'oc_';
$m->query("DELETE FROM {$p}extension WHERE code='mytax'");
$m->query("DELETE FROM {$p}extension_install WHERE code='mytax'");
$m->query("DELETE FROM {$p}extension_path WHERE path LIKE '%mytax%'");
$m->query("DELETE FROM {$p}module WHERE code='mytax'");
$m->query("DELETE FROM {$p}event WHERE code LIKE 'mytax%'");
$m->query("DELETE FROM {$p}setting WHERE code LIKE '%mytax%'");

// oc_extension с extension='mytax'
$m->query("INSERT INTO {$p}extension SET `extension`='mytax', `type`='module', `code`='mytax'");
$extId = $m->insert_id;
$m->query("INSERT INTO {$p}extension_install SET extension_id=$extId, extension_download_id=0, code='mytax', name='Мой налог', version='2.5.0', author='MyTax-Service', status=1, date_added=NOW()");
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

$m->query("INSERT INTO {$p}module SET name='Мой налог', code='mytax', setting=''");
echo "module id=" . $m->insert_id . "\n";

// События (уже идемпотентно)
$m->query("INSERT INTO {$p}event SET code='mytax_order_history', `trigger`='catalog/model/checkout/order.addHistory/before', action='extension/mytax/module/mytax.orderHistory', status=1, sort_order=1");
$m->query("INSERT INTO {$p}event SET code='mytax_mail_order_history', `trigger`='catalog/view/mail/order_history/before', action='extension/mytax/module/mytax.viewOrderHistory', status=1, sort_order=1");
$m->query("INSERT INTO {$p}event SET code='mytax_mail_order_add', `trigger`='catalog/view/mail/order_add/before', action='extension/mytax/module/mytax.viewOrderAdd', status=1, sort_order=1");
$m->query("INSERT INTO {$p}event SET code='mytax_checkout_success', `trigger`='catalog/view/checkout/success/before', action='extension/mytax/module/mytax.viewSuccess', status=1, sort_order=1");
echo "event x4\n";

$m->query("CREATE TABLE IF NOT EXISTS {$p}mytax_receipts (
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
echo "table ready\n";
$m->close();

// ===== 2. Копирование файлов =====
function rrmdir($d) { if(!is_dir($d)) return; $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d,RecursiveDirectoryIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST); foreach($it as $f) $f->isDir()?@rmdir($f->getRealPath()):@unlink($f->getRealPath()); @rmdir($d); }
rrmdir("$site/extension/mytax");
rrmdir("$site/$adm/controller/extension/mytax");
rrmdir("$site/$adm/model/extension/mytax");
rrmdir("$site/$adm/language/ru-ru/extension/mytax");
rrmdir("$site/$adm/view/template/extension/mytax");
rrmdir("$site/catalog/controller/extension/mytax");
rrmdir("$site/catalog/model/extension/mytax");
rrmdir("$site/catalog/language/ru-ru/extension/mytax");

// Распаковка zip в extension/mytax/ (OC4 расширяет пути из архива)
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if ($name == 'install.json' || $name == 'install.php' || $name == 'README.txt') continue;
    $content = $zip->getFromIndex($i);
    $dst = "$site/extension/mytax/" . $name;
    @mkdir(dirname($dst), 0777, true);
    file_put_contents($dst, $content);
    echo "  [OK] extension/mytax/$name\n";
}
$zip->close();

// Копируем в dmt/
$pairs = [
    "$site/extension/mytax/admin/controller/module/mytax.php" => "$site/$adm/controller/extension/mytax/module/mytax.php",
    "$site/extension/mytax/admin/model/module/mytax.php" => "$site/$adm/model/extension/mytax/module/mytax.php",
    "$site/extension/mytax/admin/language/ru-ru/module/mytax.php" => "$site/$adm/language/ru-ru/extension/mytax/module/mytax.php",
    "$site/extension/mytax/admin/view/template/module/mytax.twig" => "$site/$adm/view/template/extension/mytax/module/mytax.twig",
    "$site/extension/mytax/catalog/controller/module/mytax.php" => "$site/catalog/controller/extension/mytax/module/mytax.php",
    "$site/extension/mytax/catalog/model/checkout/mytax.php" => "$site/catalog/model/extension/mytax/checkout/mytax.php",
    "$site/extension/mytax/catalog/language/ru-ru/module/mytax.php" => "$site/catalog/language/ru-ru/extension/mytax/module/mytax.php",
];
foreach ($pairs as $src => $dst) {
    @mkdir(dirname($dst), 0777, true);
    copy($src, $dst);
    echo "  [OK] " . str_replace("$site/", '', $dst) . "\n";
}

echo "\n=== ПРОВЕРКА ===\n";
$ok = true;
foreach (array_values($pairs) as $f) {
    if (file_exists($f)) echo "  [OK] " . str_replace("$site/", '', $f) . "\n";
    else { echo "  [FAIL] " . str_replace("$site/", '', $f) . "\n"; $ok = false; }
}
echo ($ok ? "\n✅ ВСЁ УСТАНОВЛЕНО v2.5.0!\n" : "\n❌ ЕСТЬ ПРОБЛЕМЫ!\n");