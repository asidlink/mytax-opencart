<?php
$zipPath = 'C:/sites/metalka/mytax.ocmod.zip';

$z = new ZipArchive();
if ($z->open($zipPath) !== true) {
    die("Failed to open zip\n");
}

echo "=== install.json ===\n";
echo $z->getFromName('install.json') . "\n\n";

echo "=== install.php ===\n";
echo $z->getFromName('install.php') . "\n\n";

echo "=== upload/catalog/controller/extension/mytax/payment/mytax.php ===\n";
echo $z->getFromName('upload/catalog/controller/extension/mytax/payment/mytax.php') . "\n\n";

$z->close();