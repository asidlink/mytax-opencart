<?php
namespace Opencart\Catalog\Controller\Extension\Mytax\Module;

class Mytax extends \Opencart\System\Engine\Controller {
    public function index(array $args): void {}

    /**
     * Создание чека при изменении статуса заказа.
     * Trigger: catalog/model/checkout/order.addHistory/before
     *
     * ВАЖНО: событие срабатывает ДО обновления order_status_id в БД,
     * поэтому новый статус берём из $args[1] (order_status_id события).
     */
    public function orderHistory(string &$route, array &$args): void {
        $id = (int)($args[0] ?? 0);
        if (!$id) return;
        $status_id = (int)($args[1] ?? 0);
        $this->load->model('extension/mytax/checkout/mytax');
        $this->load->model('checkout/order');
        $o = $this->model_checkout_order->getOrder($id);
        if ($o) {
            $this->model_extension_mytax_checkout_mytax->createReceipt($id, $o['email'], $status_id);
        }
    }

    /** Страховка: создание чека на странице успеха */
    public function viewSuccess(string &$route, array &$args): void {
        $id = (int)($this->session->data['order_id'] ?? 0);
        if (!$id) return;
        $this->load->model('extension/mytax/checkout/mytax');
        $this->load->model('checkout/order');
        $o = $this->model_checkout_order->getOrder($id);
        if ($o) {
            $this->model_extension_mytax_checkout_mytax->createReceipt($id, $o['email'], (int)$o['order_status_id']);
        }
    }

    private function getOrderId(array &$args): int {
        // Данные шаблона передаются в $args целиком (view/.../before)
        return (int)($args['order_id'] ?? 0);
    }

    private function getReceiptData(int $order_id): array {
        $this->load->model('extension/mytax/checkout/mytax');
        $r = $this->model_extension_mytax_checkout_mytax->getReceiptByOrderId($order_id);
        if (!$r || $r['status'] !== 'completed') return [];
        // Нет QR-изображения — не показываем блок чека в письме.
        if (empty($r['qr_code_path'])) return [];

        $base = $this->config->get('config_url');
        if (!$base && defined('HTTP_CATALOG')) $base = HTTP_CATALOG;
        if (!$base) $base = 'https://xn--80aanved7b4e.xn--p1ai:8443/';
        $r['qr_link'] = $base . ltrim($r['qr_code_path'] ?? '', '/');
        $r['print_link'] = $r['print_link'] ?? '';
        $r['receipt_number'] = $r['fns_receipt_id'] ?? '';
        $r['amount'] = $r['amount'] ?? '';
        $r['date'] = $r['date_added'] ?? '';
        return $r;
    }

    /** Дополняет письмо о НОВОМ заказе данными чека */
    public function viewOrderAdd(string &$route, array &$args): void {
        $id = $this->getOrderId($args);
        if (!$id) return;
        // Чек формируется отдельным событием (addHistory) — только когда заказ
        // переходит в статус «Оплачен». Здесь лишь подставляем уже созданный чек в письмо.
        $r = $this->getReceiptData($id);
        if ($r) $args['mytax_receipt'] = $r;
    }

    /** Дополняет письмо об ИЗМЕНЕНИИ СТАТУСА данными чека */
    public function viewOrderHistory(string &$route, array &$args): void {
        $id = $this->getOrderId($args);
        if (!$id) return;
        // Чек формируется отдельным событием (addHistory) — только когда заказ
        // переходит в статус «Оплачен». Здесь лишь подставляем уже созданный чек в письмо.
        $r = $this->getReceiptData($id);
        if ($r) $args['mytax_receipt'] = $r;
    }

    /**
     * Встраивает блок чека с QR-кодом в уже отрендеренное HTML письмо.
     * Trigger: catalog/view/mail/order_add/after, catalog/view/mail/order_history/after
     */
    public function viewOrderAddAfter(string &$route, array &$args, string &$output): void {
        $id = $this->getOrderId($args);
        if (!$id || !$output) return;
        $r = $this->getReceiptData($id);
        if (!$r) return;
        $output = $this->injectReceiptBlock($output, $r);
    }

    /** Аналогично для письма об изменении статуса */
    public function viewOrderHistoryAfter(string &$route, array &$args, string &$output): void {
        $id = $this->getOrderId($args);
        if (!$id || !$output) return;
        $r = $this->getReceiptData($id);
        if (!$r) return;
        $output = $this->injectReceiptBlock($output, $r);
    }

    private function injectReceiptBlock(string $html, array $r): string {
        $block = '<br/><table style="border-collapse: collapse; width: 100%; border-top: 1px solid #DDDDDD; border-left: 1px solid #DDDDDD; margin-bottom: 20px;">'
            . '<thead><tr><td style="font-size: 12px; border-right: 1px solid #DDDDDD; border-bottom: 1px solid #DDDDDD; background-color: #EFEFEF; font-weight: bold; text-align: left; padding: 7px; color: #222222;" colspan="2">Кассовый чек (Мой налог / ФНС)</td></tr></thead>'
            . '<tbody><tr>'
            . '<td style="font-size: 12px; border-right: 1px solid #DDDDDD; border-bottom: 1px solid #DDDDDD; text-align: left; padding: 7px; vertical-align: top;">'
            . '<b>Номер чека:</b> ' . htmlspecialchars((string)($r['receipt_number'] ?? ''), ENT_QUOTES, 'UTF-8') . '<br/>'
            . '<b>Сумма:</b> ' . number_format((float)($r['amount'] ?? 0), 2, '.', ' ') . ' руб.<br/>'
            . '<b>Дата:</b> ' . htmlspecialchars((string)($r['date'] ?? ''), ENT_QUOTES, 'UTF-8') . '<br/>'
            . '<b>Ссылка на чек:</b> <a href="' . htmlspecialchars((string)($r['print_link'] ?? ''), ENT_QUOTES, 'UTF-8') . '">Открыть чек ФНС</a><br/>'
            . '<br/><span style="font-size: 11px; color: #666666;">Отсканируйте QR-код камерой смартфона, чтобы проверить чек в приложении ФНС.</span>'
            . '</td>'
            . '<td style="font-size: 12px; border-right: 1px solid #DDDDDD; border-bottom: 1px solid #DDDDDD; text-align: center; padding: 7px; vertical-align: top; width: 160px;">'
            . '<img src="' . htmlspecialchars((string)($r['qr_link'] ?? ''), ENT_QUOTES, 'UTF-8') . '" alt="QR-код чека ФНС" style="width: 140px; height: 140px; border: none;"/>'
            . '</td>'
            . '</tr></tbody></table>';

        // Вставляем перед закрывающим </body> в HTML-письмах, либо в конец для текстовых.
        if (stripos($html, '</body>') !== false) {
            return str_ireplace('</body>', $block . '</body>', $html);
        }
        return $html . $block;
    }
}
