<?php
namespace Opencart\Admin\Controller\Extension\Mytax\Module;

class Mytax extends \Opencart\System\Engine\Controller {
    public function index(): void {
        $this->load->language('extension/mytax/module/mytax');
        $this->document->setTitle($this->language->get('heading_title'));
        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['entry_status'] = $this->language->get('entry_status');
        $data['entry_inn'] = $this->language->get('entry_inn');
        $data['entry_password'] = $this->language->get('entry_password');
        $data['entry_app_name'] = $this->language->get('entry_app_name');
        $data['entry_limit_global'] = $this->language->get('entry_limit_global');
        $data['entry_limit_ip'] = $this->language->get('entry_limit_ip');
        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['save'] = $this->url->link('extension/mytax/module/mytax.save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');
        $data['test_connection'] = $this->url->link('extension/mytax/module/mytax.testConnection', 'user_token=' . $this->session->data['user_token']);

        $this->load->model('setting/setting');
        $settings = $this->model_setting_setting->getSetting('module_mytax');
        $fields = ['module_mytax_status','module_mytax_inn','module_mytax_password','module_mytax_app_name','module_mytax_limit_global','module_mytax_limit_ip'];
        foreach ($fields as $f) {
            $data[$f] = $settings[$f] ?? '';
        }
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/mytax/module/mytax', $data));
    }

    public function save(): void {
        $this->load->language('extension/mytax/module/mytax');
        $json = [];
        if (!$this->user->hasPermission('modify', 'extension/mytax/module/mytax')) {
            $json['error']['warning'] = $this->language->get('error_permission');
        }
        if (!$json) {
            $this->load->model('setting/setting');
            // OpenCart 4 пропускает POST через htmlspecialchars(ENT_COMPAT) (Request::clean).
            // Пароль/ИНН с кавычками и спецсимволами (например «"», «&») сохранились бы
            // закодированными, и авторизация в «Мой налог» не прошла бы. Декодируем.
            $post = $this->request->post;
            array_walk_recursive($post, function (&$value) {
                if (is_string($value)) {
                    $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
                }
            });
            $this->model_setting_setting->editSetting('module_mytax', $post);
            $json['success'] = $this->language->get('text_success');
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Проверка соединения с API «Мой налог» (ФНС) по ИНН/паролю из формы.
     *
     * @return void
     */
    public function testConnection(): void {
        $this->load->language('extension/mytax/module/mytax');
        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/mytax/module/mytax')) {
            $json['error'] = $this->language->get('error_permission');
        }

        $inn = trim(html_entity_decode((string)($this->request->post['module_mytax_inn'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $pass = html_entity_decode((string)($this->request->post['module_mytax_password'] ?? ''), ENT_QUOTES, 'UTF-8');

        if (!$inn || !$pass) {
            $json['error'] = $this->language->get('error_test_empty');
        }

        if (empty($json['error'])) {
            $auth = $this->fnsAuth($inn, $pass);

            if (!empty($auth['token'])) {
                $json['success'] = $this->language->get('text_test_ok');

                if (!empty($auth['profile']['inn'])) {
                    $json['success'] .= ' (' . $this->language->get('text_test_inn') . ': ' . $auth['profile']['inn'] . ')';
                }
            } else {
                $json['error'] = $this->language->get('text_test_fail') . ': ' . ($auth['message'] ?? $this->language->get('text_test_unknown'));
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Авторизация в ЛК «Мой налог» (ФНС).
     *
     * @param string $inn
     * @param string $pass
     *
     * @return array Ответ API (token при успехе, message при ошибке)
     */
    private function fnsAuth(string $inn, string $pass): array {
        $deviceId = '';
        $hex = 'abcdef0123456789';
        for ($i = 0; $i < 32; $i++) $deviceId .= $hex[random_int(0, 15)];

        $payload = [
            'username' => $inn,
            'password' => $pass,
            'deviceInfo' => [
                'appVersion' => '1.0.0',
                'sourceType' => 'WEB',
                'sourceDeviceId' => $deviceId,
                'metaDetails' => ['userAgent' => 'Mozilla/5.0']
            ]
        ];

        $headers = [
            'accept: application/json, text/plain, */*',
            'content-type: application/json',
            'accept-language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://lknpd.nalog.ru/api/v1/auth/lkfl',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $result = curl_exec($ch);
        if (is_resource($ch) || $ch instanceof \CurlHandle) {
            curl_close($ch);
        }

        if ($result === false) {
            return ['message' => 'cURL error'];
        }

        $decoded = json_decode((string)$result, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function install(): void {
        $this->load->model('extension/mytax/module/mytax');
        $this->model_extension_mytax_module_mytax->install();
    }

    public function uninstall(): void {
        $this->load->model('extension/mytax/module/mytax');
        $this->model_extension_mytax_module_mytax->uninstall();
    }
}
