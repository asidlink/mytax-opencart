<?php
echo "=== Проверка реального URL QR-кода ===\n\n";

// 1. HTTP_CATALOG из конфигов
$configs = ['C:/sites/metalka/config.php', 'C:/sites/metalka/dmt/config.php'];
foreach ($configs as $cfg) {
    if (file_exists($cfg)) {
        $content = file_get_contents($cfg);
        if (preg_match("/define\('HTTP_CATALOG'[^;]+;/", $content, $m)) {
            echo $cfg . ": " . $m[0] . "\n";
        }
        if (preg_match("/define\('DIR_IMAGE'[^;]+;/", $content, $m)) {
            echo $cfg . ": " . $m[0] . "\n";
        }
    }
}

// 2. Проверка URL файла QR
$qrFile = 'C:/sites/metalka/image/mytax_qr/receipt_130.png';
echo "\nQR файл существует: " . (file_exists($qrFile) ? "ДА" : "НЕТ") . "\n";
echo "Размер: " . (file_exists($qrFile) ? filesize($qrFile) . " bytes" : "n/a") . "\n";

// 3. Посмотрим установленный контроллер (реальный файл на сайте)
$ctrlFiles = [
    'C:/sites/metalka/extension/mytax/catalog/controller/module/mytax.php',
    'C:/sites/metalka/catalog/controller/extension/mytax/module/mytax.php',
];
foreach ($ctrlFiles as $f) {
    echo "\n=== $f ===\n";
    if (file_exists($f)) {
        $c = file_get_contents($f);
        // Найдём строку про qr_link
        if (preg_match_all('/.*qr_link.*/i', $c, $m)) {
            foreach ($m[0] as $line) echo "  " . trim($line) . "\n";
        } else {
            echo "  (нет упоминания qr_link!)\n";
        }
        if (strpos($c, 'HTTP_CATALOG') !== false) {
            echo "  HTTP_CATALOG: присутствует\n";
        } else {
            echo "  HTTP_CATALOG: ОТСУТСТВУЕТ!\n";
        }
        if (strpos($c, 'mytax_receipt') !== false) {
            echo "  mytax_receipt: присутствует\n";
        } else {
            echo "  mytax_receipt: ОТСУТСТВУЕТ!\n";
        }
    } else {
        echo "  ФАЙЛ НЕ НАЙДЕН!\n";
    }
}

// 4. Шаблон письма (что там с QR)
$tpl = 'C:/sites/metalka/catalog/view/template/mail/order_add.twig';
echo "\n=== Шаблон order_add.twig (QR-блок) ===\n";
if (file_exists($tpl)) {
    $c = file_get_contents($tpl);
    if (preg_match('/\{\% if mytax_receipt[\s\S]*?\{% endif %\}/', $c, $m)) {
        echo $m[0] . "\n";
    } else {
        echo "(блок mytax_receipt не найден)\n";
    }
}