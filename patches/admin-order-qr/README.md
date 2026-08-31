# Патч «QR-код чека на странице заказа в админке» (v4.1.0)

Начиная с версии **4.1.0** модуль умеет показывать QR-код кассового чека **в админке OpenCart**
в блоке **«Метод оплаты»** страницы заказа (`Заказы → Заказ #XXX`).

Для этого нужна правка **двух файлов ядра админки** (в OpenCart 4 у страницы заказа нет
событий-хуков для встраивания блока, поэтому файлы меняются напрямую). Файлы в этой папке
уже содержат готовые изменения — просто скопируйте их поверх одноимённых файлов.

## Что показывает патч

Если для заказа создан кассовый чек «Мой налог» (`oc_mytax_receipts`, статус `completed`,
есть `qr_code_path`), администратор в блоке «Метод оплаты» видит:

- QR-код чека ФНС (изображение `image/mytax_qr/receipt_<order_id>.png`);
- номер чека ФНС;
- сумму и дату;
- ссылку **«Открыть чек ФНС»** (печатная форма на `lknpd.nalog.ru`).

Если чека нет — блок не отображается, «Метод оплаты» выглядит как обычно.

## Файлы

| Файл в репозитории | Куда копировать на сайте |
|---|---|
| `dmt/controller/sale/order.php` | `<DOCROOT>/dmt/controller/sale/order.php` |
| `dmt/view/template/sale/order_info.twig` | `<DOCROOT>/dmt/view/template/sale/order_info.twig` |
| `dmt/language/en-gb/sale/order.php` | `<DOCROOT>/dmt/language/en-gb/sale/order.php` |
| `extension/ocn_language_russian/admin/language/ru-ru/sale/order.php` | `<DOCROOT>/extension/ocn_language_russian/admin/language/ru-ru/sale/order.php` |

> Если папка админки на вашем сайте называется не `dmt`, а иначе (например, `admin`),
> скопируйте файлы в соответствующую папку с сохранением структуры.

## Что именно меняется

### `dmt/controller/sale/order.php` (метод `info()`)

После получения данных заказа загружается чек «Мой налог»:

```php
// Мой налог: чек заказа (QR-код) для блока «Метод оплаты»
$data['mytax_receipt'] = [];

if (!empty($order_info['order_id'])) {
    $this->load->model('extension/mytax/module/mytax');
    $receipt = $this->model_extension_mytax_module_mytax->getReceiptByOrderId((int)$order_info['order_id']);

    if ($receipt && $receipt['status'] === 'completed' && !empty($receipt['qr_code_path'])) {
        $base = defined('HTTP_CATALOG') ? HTTP_CATALOG : 'https://<site>/';

        $data['mytax_receipt'] = [
            'qr_link'        => rtrim($base, '/') . '/' . ltrim($receipt['qr_code_path'], '/'),
            'print_link'     => $receipt['print_link'] ?? '',
            'receipt_number' => $receipt['fns_receipt_id'] ?? '',
            'amount'         => number_format((float)($receipt['amount'] ?? 0), 2, '.', ' '),
            'date'           => date($this->language->get('date_format_short'), strtotime((string)$receipt['date_added']))
        ];
    }
}
```

### `extension/mytax/admin/model/module/mytax.php` (входит в состав модуля)

Метод `getReceiptByOrderId()`:

```php
public function getReceiptByOrderId(int $order_id): array {
    $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mytax_receipts` WHERE `order_id` = '" . (int)$order_id . "' LIMIT 1");
    return $q->num_rows ? $q->row : [];
}
```

### `dmt/view/template/sale/order_info.twig`

В блок «Метод оплаты» добавлен вывод QR-кода (блок вне `#output-payment-method`,
поэтому не затирается JS при смене метода оплаты):

```twig
{% if mytax_receipt %}
  <hr class="my-2"/>
  <div class="d-flex align-items-center">
    <img src="{{ mytax_receipt.qr_link }}" alt="{{ text_mytax_receipt }}" style="width: 90px; height: 90px;" class="border me-3"/>
    <div class="small">
      <div><i class="fa-solid fa-receipt"></i> {{ text_mytax_receipt }}</div>
      <div><strong>{{ mytax_receipt.receipt_number }}</strong></div>
      <div>{{ mytax_receipt.amount }} руб. &middot; {{ mytax_receipt.date }}</div>
      <div><a href="{{ mytax_receipt.print_link }}" target="_blank"><i class="fa-solid fa-up-right-from-square"></i> {{ text_mytax_receipt_open }}</a></div>
    </div>
  </div>
{% endif %}
```

### Языковые файлы

Добавлены ключи:

```php
$_['text_mytax_receipt']         = 'Кассовый чек (Мой налог / ФНС)';
$_['text_mytax_receipt_open']    = 'Открыть чек ФНС';
```

## После установки

Очистите кэш шаблонов:

```bash
rm -rf <STORAGE>/cache/template/*
```
