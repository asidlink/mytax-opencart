<?php
/**
 * Сборка правильного mytax.ocmod.zip с ВСЕМИ файлами внутри
 * для OpenCart 3/4 через Extension Installer
 */
$targetZip = 'C:/sites/metalka/mytax.ocmod.zip';

@unlink($targetZip);

$zip = new ZipArchive();
if ($zip->open($targetZip, ZipArchive::CREATE) !== true) {
    die("Не удалось создать архив\n");
}

// ===== 1. install.json (без install.php - стандартная установка OC) =====
$zip->addFromString('install.json', json_encode([
    'code' => 'mytax',
    'name' => 'Мой налог',
    'description' => 'Автоматическое создание чеков в приложении Мой налог (НПД)',
    'version' => '2.0.1',
    'author' => 'MyTax-Service',
    'link' => 'https://github.com/Ga1maz/fns-receipt-service'
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "  + install.json\n";

// ===== 2. Admin Module files (OC4 путь: admin/controller/module/) =====
$tempBuild = 'C:/Users/admin/AppData/Local/Temp/mytax_zip_build';

// Admin controller - module
$zip->addFile(
    "$tempBuild/admin/controller/module/mytax.php",
    'upload/admin/controller/module/mytax.php'
);
echo "  + upload/admin/controller/module/mytax.php\n";

// Admin language
$zip->addFile(
    "$tempBuild/admin/language/ru-ru/module/mytax.php",
    'upload/admin/language/ru-ru/module/mytax.php'
);
echo "  + upload/admin/language/ru-ru/module/mytax.php\n";

// Admin model
$zip->addFile(
    "$tempBuild/admin/model/module/mytax.php",
    'upload/admin/model/module/mytax.php'
);
echo "  + upload/admin/model/module/mytax.php\n";

// Admin view
$zip->addFile(
    "$tempBuild/admin/view/template/module/mytax.twig",
    'upload/admin/view/template/module/mytax.twig'
);
echo "  + upload/admin/view/template/module/mytax.twig\n";

// ===== 3. Catalog files из zip_final (payment type) =====
$zipFinal = 'C:/Users/admin/AppData/Local/Temp/zip_final';

// Catalog controller (payment)
$content = file_get_contents("$zipFinal/catalog/model/checkout/mytax.php");
// Это model checkout, нужно переделать в controller payment
// Читаем из старого архива
$oldZip = new ZipArchive();
if ($oldZip->open('C:/sites/metalka/old_mytax_backup.zip') !== true) {
    // Возьмем из другого места
}

// Catalog payment controller
$zip->addFromString(
    'upload/catalog/controller/payment/mytax.php',
    file_get_contents("$zipFinal/catalog/model/checkout/mytax.php")
    // временно - потом заменим на правильный controller
);
echo "  + upload/catalog/controller/payment/mytax.php (врем.)\n";

// Catalog model (checkout)
$zip->addFile(
    "$zipFinal/catalog/model/checkout/mytax.php",
    'upload/catalog/model/checkout/mytax.php'
);
echo "  + upload/catalog/model/checkout/mytax.php\n";

// Catalog payment language
$zip->addFile(
    "$zipFinal/catalog/language/ru-ru/payment/mytax.php",
    'upload/catalog/language/ru-ru/payment/mytax.php'
);
echo "  + upload/catalog/language/ru-ru/payment/mytax.php\n";

// Catalog module language
$zip->addFile(
    "$tempBuild/catalog/language/ru-ru/module/mytax.php",
    'upload/catalog/language/ru-ru/module/mytax.php'
);
echo "  + upload/catalog/language/ru-ru/module/mytax.php\n";

// ===== 4. Catalog payment controller (правильный) =====
// Создадим заново на основе model/checkout/mytax.php
$catalogController = <<<'PHP'
<?php
namespace Opencart\Catalog\Controller\Extension\Mytax\Payment;
class Mytax extends \Opencart\System\Engine\Controller {
    public function index(): void {
        $this->response->setOutput('');
    }
    public function orderHistory(string &$route, array &$args): void {
        $this->load->model('extension/mytax/checkout/mytax');
        $order_id = $args[0];
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);
        if ($order_info) {
            $this->model_extension_mytax_checkout_mytax->createReceipt($order_id, $order_info['email']);
        }
    }
    public function viewOrderHistory(string &$route, array &$args): void {
        $this->load->model('extension/mytax/checkout/mytax');
        $order_id = $args['order_id'] ?? 0;
        if ($order_id) {
            $receipt = $this->model_extension_mytax_checkout_mytax->getReceiptByOrderId($order_id);
            if ($receipt) {
                $args['mytax'] = $receipt;
            }
        }
    }
    public function viewOrderAdd(string &$route, array &$args): void {
        $this->load->model('extension/mytax/checkout/mytax');
        $order_id = $args['order_id'] ?? 0;
        if ($order_id) {
            $receipt = $this->model_extension_mytax_checkout_mytax->getReceiptByOrderId($order_id);
            if ($receipt) {
                $args['mytax'] = $receipt;
            }
        }
    }
}
PHP;
$zip->addFromString('upload/catalog/controller/payment/mytax.php', $catalogController);
echo "  + upload/catalog/controller/payment/mytax.php (correct)\n";

$zip->close();

echo "\nАрхив создан: $targetZip\n";
echo "Содержимое:\n";
$check = new ZipArchive();
if ($check->open($targetZip) === true) {
    for ($i = 0; $i < $check->numFiles; $i++) {
        echo "  " . $check->getNameIndex($i) . "\n";
    }
    $check->close();
}