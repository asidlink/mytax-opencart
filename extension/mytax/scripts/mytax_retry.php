<?php
/**
 * Автоповтор создания кассовых чеков «Мой налог» (ФНС).
 *
 * Чек создаётся при переходе заказа в статус «Оплачен» (17). Если API ФНС в этот
 * момент недоступен (сбой, профилактика), чек сохраняется со статусом 'error'.
 * Скрипт находит такие заказы (заказ уже оплачен, чек ещё не создан) и повторяет
 * попытку. Когда ФНС снова заработает — чеки создадутся автоматически.
 *
 * Запуск по cron (каждые 5 минут):
 *   0-59/5 * * * * /usr/bin/flock -n /tmp/mytax_retry.lock timeout 120 /usr/bin/php /var/www/metalka/scripts/mytax_retry.php >> /var/www/storage/logs/mytax_retry.log 2>&1
 *
 * При необходимости можно явно указать ID заказов через аргументы командной строки:
 *   php scripts/mytax_retry.php 154 155
 */

// Конфигурация: ищем config.php в текущем каталоге и выше по дереву
// (скрипт можно класть в корень OpenCart, в scripts/ или в extension/mytax/scripts/).
$config_file = '';

$dir = __DIR__;
while (true) {
    if (is_file($dir . '/config.php')) {
        $config_file = $dir . '/config.php';
        break;
    }

    $parent = dirname($dir);

    if ($parent === $dir || !is_dir($parent)) {
        break;
    }

    $dir = $parent;
}

if ($config_file === '') {
    exit("mytax_retry: config.php not found\n");
}

require_once($config_file);

// Startup
require_once(DIR_SYSTEM . 'startup.php');

// Autoloader
$autoloader = new \Opencart\System\Engine\Autoloader();
$autoloader->register('Opencart\Catalog', DIR_APPLICATION);
$autoloader->register('Opencart\Extension', DIR_EXTENSION);
$autoloader->register('Opencart\System', DIR_SYSTEM);

require_once(DIR_SYSTEM . 'vendor.php');

// Путь автозагрузки моделей расширения mytax (в CLI не выполняются
// pre_action'ы каталога, где startup/extension регистрирует эти пути).
$autoloader->register('Opencart\Catalog\Model\Extension\Mytax', DIR_EXTENSION . 'mytax/catalog/model/');

// Registry
$registry = new \Opencart\System\Engine\Registry();
$registry->set('autoloader', $autoloader);

// Config
$config = new \Opencart\System\Engine\Config();
$registry->set('config', $config);

$config->addPath(DIR_CONFIG);
$config->load('default');
$config->load('catalog');
$config->set('application', 'Catalog');

date_default_timezone_set($config->get('date_timezone'));

// Store
$config->set('config_store_id', 0);

// Logging
$log = new \Opencart\System\Library\Log($config->get('error_filename'));
$registry->set('log', $log);

// Error/Exception handlers (как в cron.php)
set_error_handler(function (int $code, string $message, string $file, int $line) use ($log, $config) {
    if (@error_reporting() === 0) {
        return false;
    }

    if ($config->get('error_log')) {
        $log->write('PHP Error: ' . $message . ' in ' . $file . ' on line ' . $line);
    }

    return true;
});

set_exception_handler(function (\Throwable $e) use ($log, $config) {
    if ($config->get('error_log')) {
        $log->write(get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    }
    echo 'mytax_retry: ' . $e->getMessage() . "\n";
});

// Event
$event = new \Opencart\System\Engine\Event($registry);
$registry->set('event', $event);

// Factory
$registry->set('factory', new \Opencart\System\Engine\Factory($registry));

// Loader
$loader = new \Opencart\System\Engine\Loader($registry);
$registry->set('load', $loader);

// Request / Response
$registry->set('request', new \Opencart\System\Library\Request());
$registry->set('response', new \Opencart\System\Library\Response());

// Database
if ($config->get('db_autostart')) {
    $db = new \Opencart\System\Library\DB(
        $config->get('db_engine'),
        $config->get('db_hostname'),
        $config->get('db_username'),
        $config->get('db_password'),
        $config->get('db_database'),
        $config->get('db_port'),
        $config->get('db_ssl_key'),
        $config->get('db_ssl_cert'),
        $config->get('db_ssl_ca')
    );
    $registry->set('db', $db);
    $db->query("SET `time_zone` = '" . $db->escape(date('P')) . "'");
} else {
    exit("mytax_retry: db_autostart is disabled\n");
}

// Cache
$registry->set('cache', new \Opencart\System\Library\Cache($config->get('cache_engine'), $config->get('cache_expire')));

// Template / Language / Url (нужны некоторым моделям)
$template = new \Opencart\System\Library\Template($config->get('template_engine'));
$registry->set('template', $template);
$template->addPath(DIR_TEMPLATE);

$language = new \Opencart\System\Library\Language($config->get('language_code'));
$registry->set('language', $language);
$language->addPath(DIR_LANGUAGE);
$loader->load->language($config->get('language_code'));

$registry->set('url', new \Opencart\System\Library\Url($config->get('site_url')));

// ===== Основная логика =====

// Защита от «перебора» пароля при недоступности ФНС: если предыдущий запуск
// полностью провалился (ФНС не принимает авторизацию — «Не найдено», блокировка
// входа и т.п.), повторяем попытку не чаще одного раза в 60 минут, чтобы не
// спровоцировать временную блокировку учётной записи в ЛК «Мой налог».
$cooldownFile = DIR_LOGS . 'mytax_retry.cooldown';
$cooldown = 3600; // 60 минут

if (is_file($cooldownFile)) {
    $lastRun = (int)trim((string)@file_get_contents($cooldownFile));

    if ($lastRun > 0 && (time() - $lastRun) < $cooldown) {
        echo '[' . date('Y-m-d H:i:s') . "] cooldown active, skip (next run after " . date('Y-m-d H:i:s', $lastRun + $cooldown) . ")\n";
        exit(0);
    }
}

$loader->model('extension/mytax/checkout/mytax');
$loader->model('checkout/order');
$loader->model('setting/setting');

/** @var \Opencart\Catalog\Model\Extension\Mytax\Checkout\Mytax $mytax */
$mytax = $registry->get('model_extension_mytax_checkout_mytax');

// Ручной перебор ID из аргументов CLI (php mytax_retry.php 154 155)
$argv_ids = array_slice($argv ?? [], 1);
$explicit_ids = [];

foreach ($argv_ids as $arg) {
    if (ctype_digit($arg)) {
        $explicit_ids[] = (int)$arg;
    }
}

if ($explicit_ids) {
    $query = $db->query(
        "SELECT r.`order_id`, r.`email` FROM `" . DB_PREFIX . "mytax_receipts` r "
        . "WHERE r.`order_id` IN (" . implode(',', $explicit_ids) . ") AND r.`status` IN ('error','pending')"
    );
} else {
    // Автоматический режим: неоплаченные/сбойные чеки по оплаченным заказам.
    // Ограничиваем выборку, чтобы не «завалить» ФНС после долгого простоя.
    $query = $db->query(
        "SELECT r.`order_id`, r.`email` FROM `" . DB_PREFIX . "mytax_receipts` r "
        . "LEFT JOIN `" . DB_PREFIX . "order` o ON (o.`order_id` = r.`order_id`) "
        . "WHERE r.`status` IN ('error','pending') AND o.`order_status_id` = 17 "
        . "ORDER BY r.`receipt_id` ASC LIMIT 3"
    );
}

$total = 0;
$ok = 0;
$fail = 0;

foreach ($query->rows as $row) {
    $order_id = (int)$row['order_id'];
    $email = (string)$row['email'];

    echo '[' . date('Y-m-d H:i:s') . "] retry receipt order=$order_id\n";

    try {
        $result = $mytax->createReceipt($order_id, $email, 17, false);
        $total++;

        if (!empty($result['success'])) {
            $ok++;
            echo '[' . date('Y-m-d H:i:s') . "] order=$order_id OK (receipt=" . ($result['receiptId'] ?? '') . ")\n";
        } else {
            $fail++;
            echo '[' . date('Y-m-d H:i:s') . "] order=$order_id FAILED: " . ($result['error'] ?? '?') . "\n";
        }
    } catch (\Throwable $e) {
        $total++;
        $fail++;
        echo '[' . date('Y-m-d H:i:s') . "] order=$order_id EXCEPTION: " . $e->getMessage() . "\n";
    }
}

echo '[' . date('Y-m-d H:i:s') . "] done: total=$total ok=$ok fail=$fail\n";

// Если запуск полностью провалился — запоминаем время, чтобы не дёргать ФНС
// слишком часто (защита от блокировки входа в ЛК «Мой налог»).
if ($total > 0 && $ok === 0) {
    @file_put_contents($cooldownFile, (string)time(), LOCK_EX);
    echo '[' . date('Y-m-d H:i:s') . "] all attempts failed, set cooldown until " . date('Y-m-d H:i:s', time() + $cooldown) . "\n";
} elseif ($ok > 0) {
    @unlink($cooldownFile);
}

