<?php
/**
 * Сборка правильного mytax.ocmod.zip для OpenCart 3
 */
$targetZip = 'C:/sites/metalka/mytax.ocmod.zip';
$tempBuild = 'C:/Users/admin/AppData/Local/Temp/mytax_zip_build';

// Удалим старый архив
if (file_exists($targetZip)) {
    @unlink($targetZip);
    echo "Удалён старый архив\n";
}

$zip = new ZipArchive();
if ($zip->open($targetZip, ZipArchive::CREATE) !== true) {
    die("Не удалось создать архив\n");
}

// 1. install.json из temp
$installJson = "$tempBuild/install.json";
if (file_exists($installJson)) {
    $zip->addFile($installJson, 'install.json');
    echo "  + install.json\n";
} else {
    // Создадим минимальный install.json
    $zip->addFromString('install.json', json_encode([
        'code' => 'mytax',
        'name' => 'Мой налог',
        'description' => 'Автоматическое создание чеков в приложении Мой налог (НПД)',
        'version' => '2.0.1',
        'author' => 'MyTax-Service',
        'link' => 'https://github.com/Ga1maz/fns-receipt-service'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "  + install.json (создан)\n";
}

// 2. admin файлы из temp
$adminFiles = [
    'admin/controller/module/mytax.php',
    'admin/language/ru-ru/module/mytax.php',
    'admin/model/module/mytax.php',
    'admin/view/template/module/mytax.twig',
];
foreach ($adminFiles as $file) {
    $src = "$tempBuild/$file";
    $dst = "upload/$file";
    if (file_exists($src)) {
        $zip->addFile($src, $dst);
        echo "  + $dst\n";
    } else {
        echo "  [WARN] $src не найден\n";
    }
}

// 3. catalog files из старого архива (извлекаем и добавляем)
$oldZip = new ZipArchive();
if ($oldZip->open('C:/sites/metalka/mytax.ocmod.zip') === true) {
    // Извлекаем catalog файлы
    for ($i = 0; $i < $oldZip->numFiles; $i++) {
        $name = $oldZip->getNameIndex($i);
        // Берем только файлы из upload/ (install.json и install.php не нужны в новом архиве)
        if (strpos($name, 'upload/') === 0 || strpos($name, 'upload\\') === 0) {
            $content = $oldZip->getFromIndex($i);
            if ($content !== false) {
                $zip->addFromString($name, $content);
                echo "  + $name (из старого архива)\n";
            }
        }
    }
    $oldZip->close();
    echo "  catalog файлы скопированы из старого архива\n";
} else {
    echo "  [WARN] Не удалось открыть старый архив!\n";
}

// 4. catalog/language/ru-ru/extension/mytax/module/mytax.php из temp
$catalogLang = "$tempBuild/catalog/language/ru-ru/module/mytax.php";
if (file_exists($catalogLang)) {
    $zip->addFile($catalogLang, 'upload/catalog/language/ru-ru/extension/mytax/module/mytax.php');
    echo "  + upload/catalog/language/ru-ru/extension/mytax/module/mytax.php (из temp)\n";
}

$zip->close();

echo "\nАрхив создан: $targetZip\n";

// Проверим
$check = new ZipArchive();
if ($check->open($targetZip) === true) {
    echo "Содержимое ({$check->numFiles} файлов):\n";
    for ($i = 0; $i < $check->numFiles; $i++) {
        echo "  " . $check->getNameIndex($i) . "\n";
    }
    $check->close();
}