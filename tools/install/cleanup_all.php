<?php
/**
 * Полная очистка всех следов mytax из БД и файлов
 * Чтобы расширение можно было удалить через "Установщик расширений"
 */

$siteRoot = 'C:/sites/metalka';

echo "==========================================\n";
echo "ПОЛНАЯ ОЧИСТКА ВСЕХ СЛЕДОВ MyTax\n";
echo "==========================================\n\n";

// ===== 1. БД =====
echo "--- 1. ОЧИСТКА БД ---\n";
$m = new mysqli('localhost', 'root', '', 'metalka');
if ($m->connect_error) die("Connection failed: " . $m->connect_error . "\n");
$p = 'oc_';

// Сначала удаляем extension (это блокирует удаление через Extension Installer)
$m->query("DELETE FROM `{$p}extension` WHERE `code` = 'mytax'");
echo "  [OK] oc_extension WHERE code='mytax': удалено\n";

// Затем extension_path
$m->query("DELETE FROM `{$p}extension_path` WHERE `path` LIKE '%mytax%'");
echo "  [OK] oc_extension_path: удалено\n";

// Затем extension_install
$m->query("DELETE FROM `{$p}extension_install` WHERE `code` = 'mytax'");
echo "  [OK] oc_extension_install WHERE code='mytax': удалено\n";

// Остальные таблицы
$m->query("DELETE FROM `{$p}event` WHERE `code` LIKE 'mytax\\_%'");
echo "  [OK] oc_event: удалено\n";

$m->query("DELETE FROM `{$p}setting` WHERE `code` LIKE '%mytax%' OR `key` LIKE '%mytax%'");
echo "  [OK] oc_setting: удалено\n";

$m->query("DELETE FROM `{$p}module` WHERE `code` = 'mytax'");
echo "  [OK] oc_module: удалено\n";

$m->query("DROP TABLE IF EXISTS `{$p}mytax_receipts`");
echo "  [OK] oc_mytax_receipts: таблица удалена\n";

// Проверим, остались ли ещё какие-то записи
echo "\n--- Проверка остатков ---\n";
$checks = [
    "extension_install WHERE code LIKE '%mytax%'" => 'extension_install',
    "extension_path WHERE path LIKE '%mytax%'" => 'extension_path',
    "extension WHERE code = 'mytax'" => 'extension',
    "event WHERE code LIKE 'mytax\\_%'" => 'event',
    "setting WHERE code LIKE '%mytax%'" => 'setting',
    "module WHERE code = 'mytax'" => 'module',
];
$found = false;
foreach ($checks as $where => $label) {
    $stmt = $m->query("SELECT COUNT(*) FROM `{$p}{$where}");
    $count = $stmt->fetch_row()[0];
    if ($count > 0) {
        echo "  ⚠️ oc_$label: $count записей\n";
        $found = true;
    }
}
if (!$found) {
    echo "  ✅ Все записи в БД mytax очищены!\n";
}

$m->close();

// ===== 2. ФАЙЛЫ =====
echo "\n--- 2. УДАЛЕНИЕ ФАЙЛОВ ---\n";

// Пути для удаления
$pathsToDelete = [
    // extension/mytax (исходники модуля)
    $siteRoot . '/extension/mytax',
    // dmt/ (админка OpenCart 3)
    $siteRoot . '/dmt/controller/extension/mytax',
    $siteRoot . '/dmt/language/ru-ru/extension/mytax',
    $siteRoot . '/dmt/model/extension/mytax',
    $siteRoot . '/dmt/view/template/extension/mytax',
    // catalog/
    $siteRoot . '/catalog/controller/extension/mytax',
    $siteRoot . '/catalog/language/ru-ru/extension/mytax',
    $siteRoot . '/catalog/model/extension/mytax',
    $siteRoot . '/catalog/view/template/extension/mytax',
    // admin/
    $siteRoot . '/admin/controller/extension/mytax',
    $siteRoot . '/admin/language/ru-ru/extension/mytax',
    $siteRoot . '/admin/model/extension/mytax',
    $siteRoot . '/admin/view/template/extension/mytax',
];

// Старые файлы payment (если были установлены как payment)
$oldFiles = [
    $siteRoot . '/admin/controller/payment/mytax.php',
    $siteRoot . '/admin/model/payment/mytax.php',
    $siteRoot . '/admin/language/ru-ru/payment/mytax.php',
    $siteRoot . '/admin/view/template/payment/mytax.twig',
    $siteRoot . '/catalog/controller/payment/mytax.php',
    $siteRoot . '/catalog/model/checkout/mytax.php',
    $siteRoot . '/catalog/language/ru-ru/payment/mytax.php',
];

// Удаляем директории
foreach ($pathsToDelete as $dir) {
    if (is_dir($dir)) {
        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($rii as $f) {
            $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
        }
        @rmdir($dir);
        echo "  [OK] Удалено: " . str_replace($siteRoot . '/', '', $dir) . "\n";
    }
}

// Удаляем старые файлы
foreach ($oldFiles as $f) {
    if (file_exists($f)) {
        @unlink($f);
        echo "  [OK] Удалён: " . str_replace($siteRoot . '/', '', $f) . "\n";
        // Удаляем пустую родительскую папку
        $parent = dirname($f);
        if (is_dir($parent) && count(scandir($parent)) <= 2) {
            @rmdir($parent);
        }
    }
}

// ===== 3. ПРОВЕРКА ОСТАВШИХСЯ ФАЙЛОВ =====
echo "\n--- 3. Проверка оставшихся файлов ---\n";
$remaining = [];
$searchPaths = [
    $siteRoot . '/dmt',
    $siteRoot . '/catalog',
    $siteRoot . '/admin',
    $siteRoot . '/extension',
];
foreach ($searchPaths as $path) {
    if (!is_dir($path)) continue;
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iter as $f) {
        if ($f->isFile() && stripos($f->getFilename(), 'mytax') !== false) {
            $remaining[] = str_replace($siteRoot . '/', '', $f->getPathname());
        }
    }
}

if ($remaining) {
    echo "  ⚠️ Остались файлы:\n";
    foreach ($remaining as $r) echo "    $r\n";
} else {
    echo "  ✅ Все файлы mytax удалены!\n";
}

echo "\n==========================================\n";
echo "✅ ОЧИСТКА ЗАВЕРШЕНА!\n";
echo "   Теперь можно зайти в Расширения → Установка расширений\n";
echo "   Расширение mytax должно исчезнуть из списка.\n";
echo "==========================================\n";