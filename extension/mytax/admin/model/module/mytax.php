<?php
namespace Opencart\Admin\Model\Extension\Mytax\Module;

class Mytax extends \Opencart\System\Engine\Model {
    /**
     * Данные чека «Мой налог» по заказу (для страницы заказа в админке).
     *
     * @param int $order_id
     *
     * @return array
     */
    public function getReceiptByOrderId(int $order_id): array {
        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mytax_receipts` WHERE `order_id` = '" . (int)$order_id . "' LIMIT 1");
        return $q->num_rows ? $q->row : [];
    }

    public function install(): void {
        // Таблица чеков
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "mytax_receipts` (
            `receipt_id` int(11) NOT NULL AUTO_INCREMENT,
            `order_id` int(11) NOT NULL,
            `email` varchar(96) NOT NULL,
            `ip` varchar(40) NOT NULL DEFAULT '',
            `fns_receipt_id` varchar(255) DEFAULT NULL,
            `print_link` varchar(500) DEFAULT NULL,
            `qr_code_path` varchar(255) DEFAULT NULL,
            `amount` decimal(15,4) NOT NULL DEFAULT 0.0000,
            `status` varchar(50) NOT NULL DEFAULT 'pending',
            `error_message` text DEFAULT NULL,
            `date_added` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`receipt_id`),
            UNIQUE KEY `order_id` (`order_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        // Колонка ip (счётчик лимита на один IP) — если таблица уже была создана
        // без неё на старых версиях модуля.
        $columns = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "mytax_receipts` LIKE 'ip'");
        if (!$columns->num_rows) {
            $this->db->query("ALTER TABLE `" . DB_PREFIX . "mytax_receipts` ADD COLUMN `ip` varchar(40) NOT NULL DEFAULT '' AFTER `email`");
        }

        $this->load->model('setting/event');
        // Очистка старых событий
        foreach (['mytax_order_history','mytax_mail_order_history','mytax_mail_order_add','mytax_checkout_success','mytax_mail_order_add_after','mytax_mail_order_history_after'] as $code) {
            $this->model_setting_event->deleteEventByCode($code);
        }
        // Создание чека при смене статуса заказа.
        // sort_order = 0 — событие выполняется ДО mail/order (sort_order = 1),
        // чтобы чек «Мой налог» (и его QR-код) был создан до рендера письма
        // покупателю об изменении статуса. Иначе QR-блок не попадает в письмо.
        $this->model_setting_event->addEvent([
            'code' => 'mytax_order_history',
            'description' => 'Создание чека «Мой налог» при изменении статуса заказа',
            'trigger' => 'catalog/model/checkout/order.addHistory/before',
            'action' => 'extension/mytax/module/mytax.orderHistory',
            'status' => 1, 'sort_order' => 0
        ]);
        // Дополнение письма о новом заказе данными чека
        $this->model_setting_event->addEvent([
            'code' => 'mytax_mail_order_add',
            'description' => 'Дополнение письма о новом заказе данными чека «Мой налог»',
            'trigger' => 'catalog/view/mail/order_add/before',
            'action' => 'extension/mytax/module/mytax.viewOrderAdd',
            'status' => 1, 'sort_order' => 1
        ]);
        // Дополнение письма об изменении статуса
        $this->model_setting_event->addEvent([
            'code' => 'mytax_mail_order_history',
            'description' => 'Дополнение письма об изменении статуса данными чека «Мой налог»',
            'trigger' => 'catalog/view/mail/order_history/before',
            'action' => 'extension/mytax/module/mytax.viewOrderHistory',
            'status' => 1, 'sort_order' => 1
        ]);
        // Встраивание QR-блока в HTML письма (after - output уже отрендерен)
        $this->model_setting_event->addEvent([
            'code' => 'mytax_mail_order_add_after',
            'description' => 'Встраивание блока чека с QR-кодом в письмо о новом заказе',
            'trigger' => 'catalog/view/mail/order_add/after',
            'action' => 'extension/mytax/module/mytax.viewOrderAddAfter',
            'status' => 1, 'sort_order' => 1
        ]);
        $this->model_setting_event->addEvent([
            'code' => 'mytax_mail_order_history_after',
            'description' => 'Встраивание блока чека с QR-кодом в письмо об изменении статуса',
            'trigger' => 'catalog/view/mail/order_history/after',
            'action' => 'extension/mytax/module/mytax.viewOrderHistoryAfter',
            'status' => 1, 'sort_order' => 1
        ]);
        // Страховка: создание чека на странице успеха
        $this->model_setting_event->addEvent([
            'code' => 'mytax_checkout_success',
            'description' => 'Создание чека «Мой налог» на странице успешного оформления',
            'trigger' => 'catalog/view/checkout/success/before',
            'action' => 'extension/mytax/module/mytax.viewSuccess',
            'status' => 1, 'sort_order' => 1
        ]);
    }

    public function uninstall(): void {
        $this->load->model('setting/event');
        foreach (['mytax_order_history','mytax_mail_order_history','mytax_mail_order_add','mytax_checkout_success','mytax_mail_order_add_after','mytax_mail_order_history_after'] as $code) {
            $this->model_setting_event->deleteEventByCode($code);
        }
    }
}