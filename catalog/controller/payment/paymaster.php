<?php
namespace Opencart\Catalog\Controller\Extension\paymaster\Payment;

class Paymaster extends \Opencart\System\Engine\Controller {

    private $routeSymbol = VERSION < '4.0.2' ? '|' : '.';

    public function index(): string {
        $data['action'] = 'index.php?route=' . urlencode('extension/paymaster/payment/paymaster' . $this->routeSymbol . 'confirm');

        return $this->load->view('extension/paymaster/payment/paymaster', $data);
    }

    public function confirm(): void {
        $this->load->language('extension/paymaster/payment/paymaster');

        $orderNo = $this->session->data['order_id'];
        $this->load->model('checkout/order');

        $order = $this->model_checkout_order->getOrder($orderNo);
        $base_url = ((empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == 'off') ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]/index.php?route=" . urlencode("extension/paymaster/payment/paymaster" . $this->routeSymbol);
        $request_data = array(
            'merchantId' => $this->config->get('payment_paymaster_merchant_id'),
            'invoice'    => array(
                'description' => $this->language->get('text_order') . ' ' . $orderNo,
                'orderNo'     => (string)$orderNo
                ),
            'amount'     => array(
                'value'       => number_format($order['total'], 2, '.', ''),
                'currency'    => $order['currency_code']
                ),
            'protocol'   => array(
                'callbackUrl' => $base_url . 'callback',
                'returnUrl'   => $base_url . 'return'
                )
        );
        if ($this->config->get('payment_paymaster_send_receipt_data'))
            $request_data['receipt'] = $this->getReceipt($order);

        $response = $this->getResponse(__METHOD__, '/api/v2/invoices', $request_data);
        if (property_exists($response, 'url')) {
            $this->session->data['payment_id'] = $response->paymentId;
            $this->response->redirect($response->url);
        }
        else {
            ob_clean();
            $data['msg_text'] = $this->language->get('text_connect_error');
            $data['msg_stamp_text'] = $this->language->get('text_error_stamp') . ': ' . date("Y-m-d H:i:s");
            $this->response->setOutput($this->load->view('extension/paymaster/payment/error', $data));
        }
    }

    public function callback(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $content = file_get_contents('php://input');

            $this->addLogEntry(__METHOD__, 'PayMaster callback request', array('content' => $content));

            $request = json_decode($content);

            if ($request->status == 'Settled' &&
                $request->merchantId == $this->config->get('payment_paymaster_merchant_id')) {

                $orderNo = $request->invoice->orderNo;
                $this->load->model('checkout/order');
                $order = $this->model_checkout_order->getOrder($orderNo);

                if (isset($order) &&
                    !$this->alreadyPaid($orderNo) &&
                    $request->amount->value == (float)$order['total'] &&
                    $request->amount->currency == $order['currency_code']) {

                    $this->checkAndComplete(__METHOD__, $order, $request->id);
                }
            }
        }
    }

    public function return(): void
    {
        if (array_key_exists('order_id', $this->session->data)) {
            $orderNo = $this->session->data['order_id'];

            $this->load->model('checkout/order');
            $order = $this->model_checkout_order->getOrder($orderNo);

            if (isset($order)) {
                if ($this->alreadyPaid($orderNo)) {
                    $this->toSuccessPage();
                } else {
                    if (array_key_exists('payment_id', $this->session->data)) {
                        $paymentId = $this->session->data['payment_id'];

                        $status = $this->checkAndComplete(__METHOD__, $order, $paymentId);
                        if ($status == 'Settled') {
                            $this->toSuccessPage();
                        } else if ($status == 'Rejected') {
                            $this->response->redirect($this->url->link('checkout/failure'));
                        }
                    }
                }
            }
        }
        $this->response->redirect($this->url->link('account/order', array_key_exists('customer_token', $this->session->data) ? ('customer_token=' . $this->session->data['customer_token']) : ''));
    }

    private function alreadyPaid(int $orderNo): bool
    {
        $query = $this->db->query(
            'SELECT * FROM `' . DB_PREFIX . 'order_history` WHERE `order_id` = ' . $orderNo . ' AND `order_status_id` = ' . $this->config->get('payment_paymaster_done_status_id')
        );
        return $query->num_rows;
    }

    private function toSuccessPage()
    {
        $this->cart->clear();

        unset($this->session->data['order_id']);
        unset($this->session->data['payment_id']);

        $this->response->redirect($this->url->link('checkout/success'));
    }

    private function checkAndComplete($method, $order, $payment_id): string
    {
        $status = 'Unknown';
        $response = $this->getResponse($method, '/api/v2/payments/' . $payment_id);
        if (isset($response)) {
            $status = $response->status;
            if ($response->status == 'Settled' &&
                $response->merchantId == $this->config->get('payment_paymaster_merchant_id') &&
                $response->invoice->orderNo == $order['order_id'] &&
                $response->amount->value == (float)$order['total'] &&
                $response->amount->currency == $order['currency_code']) {

                $this->load->language('extension/paymaster/payment/paymaster');
                $this->model_checkout_order->addHistory($order['order_id'], (int)$this->config->get('payment_paymaster_done_status_id'), $this->language->get('text_paid_with_paymaster'));
            }
        }
        return $status;
    }

    private function getResponse($method, $relative_url, $request_data = null)
    {
        $pmtoken = $this->config->get('payment_paymaster_token');
        $options = array(
            'http' => array(
                'ignore_errors' => true,
                'method'  => is_null($request_data) ? 'GET' : 'POST',
                'header'  =>  "Authorization: Bearer $pmtoken\r\n" .
                    "Content-Type: application/json\r\n" .
                    "Accept: application/json\r\n",
                'content' => json_encode($request_data, JSON_UNESCAPED_SLASHES)
            )
        );

        if (!is_null($request_data))
            $this->addLogEntry($method, 'request to [' . $relative_url . ']', $request_data);

        $context  = stream_context_create($options);
        $result = file_get_contents($this->config->get('payment_paymaster_base_address') . $relative_url, false, $context);

        $this->addLogEntry($method, 'response from [' . $relative_url . ']', array('headers' => json_encode($http_response_header), 'body' => $result));

        return json_decode($result);
    }

    private function getReceipt($order): array
    {
        $order_products = $this->cart->getProducts();

        $products_amount = 0;
        $receipt_items = array();
        foreach ($order_products as $order_product) {
            $receipt_items[] = array(
                'name' => $order_product['name'],
                'quantity' => $order_product['quantity'],
                'price' => $order_product['price'],
                'vatType' => $this->getVatType($order_product['tax_class_id']),
                'paymentSubject' => $this->config->get('payment_paymaster_payment_subject'),
                'paymentMethod' => $this->config->get('payment_paymaster_payment_method')
            );
            $products_amount += $order_product['total'];
        }

        if (!empty($order['shipping_method'])) {
            $receipt_items[] = array(
                'name' => $order['shipping_method'],
                'price' => $order['total'] - $products_amount,
                'quantity' => 1,
                'vatType' => $this->config->get('payment_paymaster_default_vat_type'),
                'paymentSubject' => $this->config->get('payment_paymaster_payment_subject_for_shipping'),
                'paymentMethod' => $this->config->get('payment_paymaster_payment_method_for_shipping')
            );
        }

        return array(
            'client' => array(
                'email' => $order['email']
            ),
            'items' => $receipt_items
        );
    }

    private function getVatType($tax_class_id): string
    {
        $vatType = $this->config->get('payment_paymaster_default_vat_type');
        foreach ($this->config->get('payment_paymaster_tax_rules') as $tax_rule) {
            if ($tax_rule['tax_class_id'] == $tax_class_id) {
                $vatType = $tax_rule['vat_type'];
                break;
            }
        }
        return $vatType;
    }

    private function addLogEntry(string $method, string $comment, array $data): bool
    {
        if ($this->config->get('payment_paymaster_log')) {
            $this->log->write('----- PAYMASTER START LOG -----');
            $this->log->write('----- Method: ' . $method . ' -----');
            $this->log->write('----- Comment: ' . $comment . ' -----');
            $this->log->write($data);
            $this->log->write('----- PAYMASTER END LOG -----');
        }
        return true;
    }
}