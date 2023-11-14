<?php
namespace Opencart\Catalog\Model\Extension\paymaster\Payment;

class Paymaster extends \Opencart\System\Engine\Model {
    //for versions before 4.0.2.0
    public function getMethod(array $address): array {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . (int)$this->config->get('payment_paymaster_geo_zone_id') . "' AND `country_id` = '" . (int)$address['country_id'] . "' AND (`zone_id` = '" . (int)$address['zone_id'] . "' OR `zone_id` = '0')");

        if (!$this->config->get('payment_paymaster_geo_zone_id')) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }

        $method_data = [];

        if ($status) {
            $method_data = [
                'code'       => 'paymaster',
                'title'      => $this->config->get('payment_paymaster_service_name'),
                'sort_order' => $this->config->get('payment_paymaster_sort_order')
            ];
        }

        return $method_data;
    }

    //for versions since 4.0.2.0
    public function getMethods(array $address): array
    {
        if (!$this->config->get('config_checkout_payment_address')) {
            $status = true;
        } elseif (!$this->config->get('payment_paymaster_geo_zone_id')) {
            $status = true;
        } else {
            $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . (int)$this->config->get('payment_paymaster_geo_zone_id') . "' AND `country_id` = '" . (int)$address['country_id'] . "' AND (`zone_id` = '" . (int)$address['zone_id'] . "' OR `zone_id` = '0')");

            if ($query->num_rows) {
                $status = true;
            } else {
                $status = false;
            }
        }

        $method_data = [];

        if ($status) {
            $option_data['paymaster'] = [
                'code' => 'paymaster.paymaster',
                'name' => $this->config->get('payment_paymaster_service_name')
            ];

            $method_data = [
                'code' => 'paymaster',
                'name' => $this->config->get('payment_paymaster_service_name'),
                'option' => $option_data,
                'sort_order' => $this->config->get('payment_paymaster_sort_order')
            ];
        }

        return $method_data;
    }
}
