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
        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['save'] = $this->url->link('extension/mytax/module/mytax.save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');

        $this->load->model('setting/setting');
        $settings = $this->model_setting_setting->getSetting('module_mytax');
        $fields = ['module_mytax_status','module_mytax_inn','module_mytax_password','module_mytax_app_name'];
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
            $this->model_setting_setting->editSetting('module_mytax', $this->request->post);
            $json['success'] = $this->language->get('text_success');
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
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