<?php
// E2E-тест: создание чека напрямую через PHP-реализацию ФНС (без OpenCart)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$INN = '123456789012';               // <-- замените на ваш ИНН (12 цифр)
$PASSWORD = 'your_fns_password';     // <-- замените на ваш пароль от «Мой налог»

$API_URL = 'https://lknpd.nalog.ru/api/v1';

function createDeviceId() {
    $chars = 'abcdef0123456789';
    $id = '';
    for ($i = 0; $i < 32; $i++) $id .= $chars[random_int(0, 15)];
    return $id;
}

function fnsRequest(string $url, string $body, string $token = '') {
    $headers = [
        'accept: application/json, text/plain, */*',
        'accept-language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
        'content-type: application/json',
        'referrer: https://lknpd.nalog.ru/'
    ];
    if ($token) $headers[] = 'authorization: Bearer ' . $token;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    echo "  [HTTP $code] $url\n";
    if ($err) echo "  cURL error: $err\n";
    $d = json_decode((string)$resp, true);
    if (!is_array($d)) { echo "  RAW: " . substr((string)$resp, 0, 300) . "\n"; return []; }
    return $d;
}

echo "=== E2E тест создания чека (1 рубль) ===\n\n";

// 1. Авторизация
$deviceInfo = ['appVersion'=>'1.0.0','sourceType'=>'WEB','sourceDeviceId'=>createDeviceId(),'metaDetails'=>['userAgent'=>'Mozilla/5.0']];
echo "1. Авторизация (auth/lkfl)...\n";
$auth = fnsRequest($API_URL.'/auth/lkfl', json_encode(['username'=>$INN,'password'=>$PASSWORD,'deviceInfo'=>$deviceInfo], JSON_UNESCAPED_UNICODE));
if (empty($auth['token'])) { echo "  ОШИБКА: " . json_encode($auth, JSON_UNESCAPED_UNICODE) . "\n"; exit(1); }
echo "  OK: token получен, INN профиля: " . ($auth['profile']['inn'] ?? '?') . "\n\n";

$token = $auth['token'];
$inn = $auth['profile']['inn'] ?? $INN;

// 2. Создание чека на 1 рубль
echo "2. Создание чека (income, 1 рубль)...\n";
$now = new DateTime('now', new DateTimeZone('UTC'));
$services = [
    ['name' => 'Кусок, id=999, Заказ №9999', 'amount' => 1.00, 'quantity' => 1]
];
$payload = [
    'paymentType' => 'CASH',
    'ignoreMaxTotalIncomeRestriction' => false,
    'client' => ['contactPhone'=>null,'displayName'=>null,'incomeType'=>'FROM_INDIVIDUAL','inn'=>null],
    'requestTime' => $now->format('Y-m-d\TH:i:s.u\Z'),
    'operationTime' => $now->format('Y-m-d\TH:i:s.u\Z'),
    'services' => $services,
    'totalAmount' => 1.00
];
$income = fnsRequest($API_URL.'/income', json_encode($payload, JSON_UNESCAPED_UNICODE), $token);

if (empty($income['approvedReceiptUuid'])) {
    echo "  ОШИБКА: " . json_encode($income, JSON_UNESCAPED_UNICODE) . "\n";
    // Проверим, может это ошибка времени - выведем что отправили
    echo "  Отправлено requestTime: {$payload['requestTime']}\n";
    echo "  Отправлено operationTime: {$payload['operationTime']}\n";
    exit(1);
}
$receiptUuid = $income['approvedReceiptUuid'];
$printLink = "https://lknpd.nalog.ru/api/v1/receipt/{$inn}/{$receiptUuid}/print";
echo "  ✅ ЧЕК СОЗДАН!\n";
echo "  receiptUuid: $receiptUuid\n";
echo "  printLink: $printLink\n\n";

// 3. Проверка получения чека
echo "3. Проверка PDF чека...\n";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $printLink,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false
]);
$pdf = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "  [HTTP $code] Размер: " . strlen((string)$pdf) . " bytes\n";
echo ($code==200 && strlen($pdf)>1000 ? "  ✅ ЧЕК ДОСТУПЕН!\n" : "  Проверьте ссылку вручную\n");

echo "\n✅ E2E-тест завершён: PHP-реализация ФНС РАБОТАЕТ.\n";