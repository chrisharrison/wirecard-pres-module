<?php

/*
Copyright Chris Harrison 2018
 */

if (!defined('_PS_VERSION_')) { exit(); }

require_once(dirname(__FILE__) . '/induxive/WirecardBase.php');

class WirecardInduxive extends WirecardBase
{
    public $_base_curreny_only = true;
        
	public function __construct()
    {
        $this->name = 'wirecardinduxive';
		$this->tab = 'payments_gateways';
		$this->version = '0.0';
                
        parent::__construct();
		
		$this->currencies = true;
		$this->currencies_mode = 'checkbox';

		$this->displayName = 'Wirecard';
		$this->description = 'Allow payments through the Wirecard service.';
	}

	public function install()
    {
        if (!parent::install()) {
            return false;
        }

        //Hooks
        if(!$this->install_hooks(array(
            'payment',
            'orderDetailDisplayed'
        ))) {
            return false;
        }

        //Create order status
        $this->create_order_status(array(
            array(
                'identify' => 'pending',
                'name' => $this->displayName.' (pending)',
                'color' => '#add8e6'
            ),
            array(
                'identify' => 'cancel',
                'name' => $this->displayName.' (cancelled)',
                'color' => '#DADADA'
            ),
            array(
                'identify' => 'fail',
                'name' => $this->displayName.' (fail)',
                'color' => '#FFDFDF'
            ),
            array(
                'identify' => 'complete',
                'name' => $this->displayName.' (complete)',
                'invoice' => true,
                'send_email' => true,
                'color' => '#DDEEFF'
            )
        ));

        //Set initial configuration
        $config['service_url'] = 'https://checkout.wirecard.com/page/init.php';

        $config['url_response'] = $this->root_uri.'response.php';
        $config['url_response'] = $this->root_uri.'image/logo.jpg';

        //Transaction limits
        $config['limit_min'] = 0;
        $config['limit_max'] = 0;
        $config['limit_action'] = 0;

        $this->config('main_settings', $config);

        return true;
                
	}

	public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }
        return true;
 	}
        
    public function getContent()
    {
        $data['success'] = false;

        if(isset($_POST['submit'])) {

            //Submit settings form

            //Process input
            if(!filter_var($_POST['service_url'], FILTER_VALIDATE_URL)) {
                $_POST['service_url'] = '';
            }

            if(!filter_var($_POST['url_response'], FILTER_VALIDATE_URL)) {
                $_POST['url_response'] = '';
            }
            if(!filter_var($_POST['url_about'], FILTER_VALIDATE_URL)) {
                $_POST['url_about'] = '';
            }
            if(!filter_var($_POST['url_logo'], FILTER_VALIDATE_URL)) {
                $_POST['url_logo'] = '';
            }

            $this->config('main_settings', array_merge($_POST, array(
                'order_status_pending' => (int) $_POST['order_status_pending'],
                'order_status_cancel' => (int) $_POST['order_status_cancel'],
                'order_status_fail' => (int) $_POST['order_status_fail'],
                'order_status_complete' => (int) $_POST['order_status_complete'],

                'limit_min' => (float) $_POST['limit_min'],
                'limit_max' => (float) $_POST['limit_max'],
                'limit_action' => (int) $_POST['limit_action']
            )));

            $data['success'] = true;

        }

        $data['path'] = $this->_path;


        //Get form values
        $data['form_values'] = $this->config('main_settings');

        //Touch up form values for display
        if($data['form_values']['limit_min'] <= 0) {
            $data['form_values']['limit_min'] = '';
        }
        if($data['form_values']['limit_max'] <= 0) {
            $data['form_values']['limit_max'] = '';
        }


        $data['order_states'] = $this->get_all_order_status();

        $data['limit_actions'] = array(
            array('id' => 0, 'description' => "Don't display payment option"),
            array('id' => 1, 'description' => "Display non-clickable payment option with warning")
        );

        //Display the backend module form
        return $this->display_view('backend_view', $data, true);
                
	}

	public function hookPayment($params)
    {
        if (!$this->active) {
            return;
        }

        //TRANSACTION LIMITS
        $cart = new Cart($this->cookie->id_cart);
        $cart_total = (float) $cart->getOrderTotal(true);

        $main_settings = $this->config('main_settings');

        //Disable max if required
        $limit_max = $main_settings['limit_max'];
        if($main_settings['limit_max'] <= 0) {
            $limit_max = $cart_total+1;
        }

        if ($cart_total < $main_settings['limit_min'] || $cart_total > $limit_max) {
            if ($main_settings['limit_action'] == 0) {
                return '';
            }
            else {
                $this->smarty->assign(array(
                    'root_uri' => $this->root_uri
                ));
                return $this->display(__FILE__, '/views/choose_payment_limit.tpl');
            }
        }

        //If converting currency
        $currency_text = '';
        if ($this->_base_curreny_only) {
            $default_currency = Currency::getDefaultCurrency();
            if(Currency::getCurrent()->id != $default_currency->id) {
                $currency_text = ' <b>(We will be charging your card in '.$default_currency->iso_code.' at the current rates)</b>';
            }
        }

        $this->smarty->assign(array(
            'root_uri' => $this->root_uri,
            'currency_text' => $currency_text
        ));

        return $this->display(__FILE__, '/views/choose_payment.tpl');
	}
        
    public function hookOrderDetailDisplayed($params)
    {
        $main_settings = $this->config('main_settings');

        //Check if this order has a status that is eligible to restart payment process
        $order = $params['order'];
        $current_state = (int) $order->getCurrentState();
        if (
            $current_state == (int) $main_settings['order_status_pending'] ||
            $current_state == (int) $main_settings['order_status_fail'] ||
            $current_state == (int) $main_settings['order_status_cancel']
        ) {
            //Show view
            $this->smarty->assign(array(
                'root_uri' => $this->root_uri,
                'order_id' => $order->id,
                'check_token' => $order->secure_key
            ));
            return $this->display(__FILE__, '/views/order_detail.tpl');
        }
    }

    public function paymentPage()
    {
        $settings = $this->config('main_settings');
        $context = Context::getContext();

        if(!isset($_GET['id'])) {

            // CONVERT CART TO ORDER

            $cart = $context->cart;

            // Convert cart to order (validate order)
            $this->validateOrder(
                $cart->id,
                $settings['order_status_pending'],
                $cart->getOrderTotal(true, Cart::BOTH),
                'Wirecard',
                NULL,
                array(),
                null,
                false,
                $cart->secure_key
            );

            $order = new Order($this->currentOrder);
        }
        else {

            // REPAY EXISTING ORDER

            $order = new Order((int) $_GET['id']);

            //Check that the secure key is correct
            if(!isset($_GET['check']) || $_GET['check'] != $order->secure_key) {
                exit('Invalid check.');
            }

            //Check that the order status is eligible for repay
            $current_state = (int) $order->getCurrentState();
            if (
                $current_state != (int) $settings['order_status_pending'] &&
                $current_state != (int) $settings['order_status_fail'] &&
                $current_state != (int) $settings['order_status_cancel']
            ) {
                exit('Cannot restart payment for this order.');
            }
        }

        //Get order currency
        $currency = new Currency($order->id_currency);

        //Determine amount
        $amount = $order->total_paid;

        //Convert to base currency if required
        if($this->_base_curreny_only) {
            $base_currency = Currency::getDefaultCurrency();
            if($currency->id != $base_currency->id) {
                $amount = Tools::convertPrice($amount, Currency::getCurrent(), false);
                $currency = $base_currency;
            }
        }

        $paymentParams = [
            'customerId' => $settings['customerId'],
            'language' => 'en',
            'paymentType' => 'CCARD',
            'amount' => number_format((float)$amount, 2),
            'currency' => $currency->iso_code,
            'orderDescription' => $order->id,
            'successUrl' => $settings['url_response'],
            'cancelUrl' => $settings['url_response'],
            'failureUrl' => $settings['url_response'],
            'confirmUrl' => $settings['url_response'],
            'pendingUrl' => $settings['url_response'],
            'serviceUrl' => $settings['url_about'],
            'customerStatement' => Configuration::get('PS_SHOP_NAME'),
            'orderReference' => $order->id,
            'imageUrl' => $settings['url_logo'],
            'induxive_orderId' => $order->id,
        ];

        if (!empty($settings['shopId'])) {
            $paymentParams['shopId'] = $settings['shopId'];
        }

        $paymentParams['requestFingerprintOrder'] = $this->getRequestFingerprintOrder($paymentParams);
        $paymentParams['requestFingerprint'] = $this->getRequestFingerprint($paymentParams, $settings['secret']);

        $this->smarty->assign('paymentParams', $paymentParams);
        $this->smarty->assign('paymentUrl', $settings['service_url']);
        return $this->display(__FILE__, '/views/confirmation.tpl');
    }

    public function responsePage()
    {
        $settings = $this->config('main_settings');

        $response = $this->getReturnState($_POST, $settings['secret']);

        $orderStatusMap = [
            'FAILURE' => $settings['order_status_fail'],
            'CANCEL' => $settings['order_status_cancel'],
            'PENDING' => $settings['order_status_pending'],
            'SUCCESS' => $settings['order_status_complete'],
        ];

        // Set order status
        $order = new Order((int) $_POST['induxive_orderId']);
        $order->setCurrentState($orderStatusMap[$response['state']]);

        if ($response['state'] == 'FAILURE') {
            $this->log('Error from Wirecard: '.$response['message'], 'info');
        }

        $this->smarty->assign('historyLink', $this->link->getPageLink('history.php'));
        return $this->display(__FILE__, '/views/responses/' . strtolower($response['state']) . '.tpl');
    }

    private function getRequestFingerprintOrder($params)
    {
        $ret = "";
        foreach ($params as $key => $value) {
            $ret .= "$key,";
        }
        $ret .= "requestFingerprintOrder,secret";
        return $ret;
    }

    private function getRequestFingerprint($params, $secret)
    {
        $ret = "";
        foreach ($params as $key => $value) {
            $ret .= "$value";
        }
        $ret .= "$secret";
        return hash_hmac("sha512", $ret, $secret);
    }

    private function getReturnState($params, $secret)
    {
        $paymentState = isset($params['paymentState']) ? $params['paymentState'] : '';

        if (!in_array($paymentState, [
            'FAILURE',
            'CANCEL',
            'PENDING',
            'SUCCESS'
        ])) {
            return [
                'state' => 'FAILURE',
                'message' => 'Invalid paymentState'
            ];
        }


        if (in_array($paymentState, ['PENDING', 'SUCCESS'])) {
            if (!$this->checkReturnParametersValid($params, $secret)) {
                return [
                    'state' => 'FAILURE',
                    'message' => 'Invalid return params'
                ];
            }
        }

        return [
            'state' => $paymentState,
            'message' => isset($params['message']) ? $params['message'] : ''
        ];
    }

    private function checkReturnParametersValid($params, $secret)
    {
        // gets the fingerprint-specific response parameters sent by Wirecard
        $responseFingerprintOrder = isset($params["responseFingerprintOrder"]) ? $params["responseFingerprintOrder"] : "";
        $responseFingerprint = isset($params["responseFingerprint"]) ? $params["responseFingerprint"] : "";
        // values of the response parameters for computing the fingerprint
        $fingerprintSeed = "";
        // array containing the names of the response parameters used by Wirecard to compute the response fingerprint
        $order = explode(",", $responseFingerprintOrder);
        // checks if there are required response parameters in responseFingerprintOrder
        if (in_array("paymentState", $order) && in_array("secret", $order)) {
            // collects all values of response parameters used for computing the fingerprint
            for ($i = 0; $i < count($order); $i++) {
                $name = $order[$i];
                $value = isset($params[$name]) ? $params[$name] : "";
                $fingerprintSeed .= $value; // adds value of response parameter to fingerprint
                if (strcmp($name, "secret") == 0) {
                    $fingerprintSeed .= $secret; // adds your secret to fingerprint
                }
            }
            $fingerprint = hash_hmac("sha512", $fingerprintSeed, $secret);
            // checks if computed fingerprint and responseFingerprint have the same value
            if (strcmp($fingerprint, $responseFingerprint) == 0) {
                return true; // fingerprint check passed successfully
            }
        }
        return false;
    }
}
