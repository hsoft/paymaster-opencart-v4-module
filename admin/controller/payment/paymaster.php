<?php
namespace Opencart\Admin\Controller\Extension\paymaster\Payment;
class Paymaster extends \Opencart\System\Engine\Controller {
   
    public function install()
    {
        $fileContent = file_get_contents('../extension/paymaster/install.json');
        $settings = json_decode($fileContent);
        $defaultValues = array(
            'payment_paymaster_base_address' => $settings->base_service_url,
            'payment_paymaster_service_name' => $settings->display_service_name,
            'payment_paymaster_send_receipt_data' => $settings->send_receipt_data
            );
        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('payment_paymaster', $defaultValues);
    }

	public function index(): void {
		$this->load->language('extension/paymaster/payment/paymaster');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/paymaster/payment/paymaster', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/paymaster/payment/paymaster|save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

		$data['payment_paymaster_base_address'] = $this->config->get('payment_paymaster_base_address');
        $data['payment_paymaster_token'] = $this->config->get('payment_paymaster_token');
        $data['payment_paymaster_merchant_id'] = $this->config->get('payment_paymaster_merchant_id');
        $data['payment_paymaster_service_name'] = $this->config->get('payment_paymaster_service_name');

        $data['payment_paymaster_done_status_id'] = $this->config->get('payment_paymaster_done_status_id');
		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
		
		$data['payment_paymaster_send_receipt_data'] = $this->config->get('payment_paymaster_send_receipt_data');

        if (isset($this->request->post['payment_paymaster_tax_rules'])) {
            $data['payment_paymaster_tax_rules'] = $this->request->post['payment_paymaster_tax_rules'];
        } elseif ($this->config->get('payment_paymaster_tax_rules')) {
            $data['payment_paymaster_tax_rules'] = $this->config->get('payment_paymaster_tax_rules');
        } else {
            $data['payment_paymaster_tax_rules'] = array();
        }
        
        $this->load->model('localisation/tax_class');
        $data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();
        $data['tax_rules'] = $this->get_vat_types();
        $data['payment_paymaster_default_vat_type'] = $this->config->get('payment_paymaster_default_vat_type');
        
        $data['payment_subjects'] = $this->get_payment_subjects();
        $data['payment_paymaster_payment_subject'] = $this->config->get('payment_paymaster_payment_subject');
        $data['payment_paymaster_payment_subject_for_shipping'] = $this->config->get('payment_paymaster_payment_subject_for_shipping');
        
        $data['payment_methods'] = $this->get_payment_methods();
        $data['payment_paymaster_payment_method'] = $this->config->get('payment_paymaster_payment_method');
        $data['payment_paymaster_payment_method_for_shipping'] = $this->config->get('payment_paymaster_payment_method_for_shipping');

		$data['payment_paymaster_geo_zone_id'] = $this->config->get('payment_paymaster_geo_zone_id');
		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$data['payment_paymaster_status'] = $this->config->get('payment_paymaster_status');

        $data['payment_paymaster_log'] = $this->config->get('payment_paymaster_log');

        $data['payment_paymaster_sort_order'] = $this->config->get('payment_paymaster_sort_order');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/paymaster/payment/paymaster', $data));
	}

    private function get_vat_types(): array
    {
        return array(
            array(
                'name' => $this->language->get('vat_none'),
                'value' => 'None'
            ),
            array(
                'name' => $this->language->get('vat_0'),
                'value' => 'Vat0'
            ),
            array(
                'name' => $this->language->get('vat_10'),
                'value' => 'Vat10'
            ),
            array(
                'name' => $this->language->get('vat_20'),
                'value' => 'Vat20'
            ),
            array(
                'name' => $this->language->get('vat_110'),
                'value' => 'Vat110'
            ),
            array(
                'name' => $this->language->get('vat_120'),
                'value' => 'Vat120'
            )
        );
    }
    
    private function get_payment_subjects(): array
    {
        return array(
            array(
                'name' => $this->language->get('payment_subject_commodity'),
                'value' => 'Commodity'
            ),
            array(
                'name' => $this->language->get('payment_subject_excise'),
                'value' => 'Excise'
            ),
            array(
                'name' => $this->language->get('payment_subject_job'),
                'value' => 'Job'
            ),
            array(
                'name' => $this->language->get('payment_subject_service'),
                'value' => 'Service'
            ),
            array(
                'name' => $this->language->get('payment_subject_gambling'),
                'value' => 'Gambling'
            ),
            array(
                'name' => $this->language->get('payment_subject_lottery'),
                'value' => 'Lottery'
            ),
            array(
                'name' => $this->language->get('payment_subject_intellectual_activity'),
                'value' => 'IntellectualActivity'
            ),
            array(
                'name' => $this->language->get('payment_subject_payment'),
                'value' => 'Payment'
            ),
            array(
                'name' => $this->language->get('payment_subject_agent_fee'),
                'value' => 'AgentFee'
            ),
            array(
                'name' => $this->language->get('payment_subject_property_rights'),
                'value' => 'PropertyRights'
            ),
            array(
                'name' => $this->language->get('payment_subject_non_operating_income'),
                'value' => 'NonOperatingIncome'
            ),
            array(
                'name' => $this->language->get('payment_subject_insurance_payment'),
                'value' => 'InsurancePayment'
            ),
            array(
                'name' => $this->language->get('payment_subject_sales_tax'),
                'value' => 'SalesTax'
            ),
            array(
                'name' => $this->language->get('payment_subject_resort_fee'),
                'value' => 'ResortFee'
            ),
            array(
                'name' => $this->language->get('payment_subject_other'),
                'value' => 'Other'
            )
        );
    }

    private function get_payment_methods(): array
    {
        return array(
            array(
                'name' => $this->language->get('payment_method_full_prepayment'),
                'value' => 'FullPrepayment'
            ),
            array(
                'name' => $this->language->get('payment_method_partial_prepayment'),
                'value' => 'PartialPrepayment'
            ),
            array(
                'name' => $this->language->get('payment_method_advance'),
                'value' => 'Advance'
            ),
            array(
                'name' => $this->language->get('payment_method_full_payment'),
                'value' => 'FullPayment'
            ),
            array(
                'name' => $this->language->get('payment_method_partial_payment'),
                'value' => 'PartialPayment'
            ),
            array(
                'name' => $this->language->get('payment_method_credit'),
                'value' => 'Credit'
            )
        );
    }
    
	public function save(): void
    {
        $this->load->language('extension/paymaster/payment/paymaster');

        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/paymaster/payment/paymaster')) {
            $json['error'] = $this->language->get('error_permission');
        }
        if (!$json) {
            $base_address = $this->request->post['payment_paymaster_base_address'];
            if (is_null($base_address) || strpos(strtolower($base_address), 'https://') !== 0 || !filter_var($base_address, FILTER_VALIDATE_URL))
                $json['error'] = $this->language->get('error_base_address');
        }
        if (!$json && !$this->request->post['payment_paymaster_token']) {
            $json['error'] = $this->language->get('error_token');
        }
        if (!$json && !$this->request->post['payment_paymaster_merchant_id']) {
            $json['error'] = $this->language->get('error_entry_merchant_id');
        }
        if (!$json && !$this->request->post['payment_paymaster_service_name']) {
            $json['error'] = $this->language->get('error_entry_service_name');
        }

        if (!$json) {
            $this->load->model('setting/setting');

            $this->model_setting_setting->editSetting('payment_paymaster', $this->request->post);

            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    private function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/paymaster')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        
        if (!$this->request->post['payment_paymaster_merchant_id']) {
            $this->error['merchant_id'] = $this->language->get('error_merchant_id');
        }
        
        if (!$this->request->post['payment_paymaster_secret_key']) {
            $this->error['secret_key'] = $this->language->get('error_secret_key');
        }
        
        return !$this->error;
    }
}
