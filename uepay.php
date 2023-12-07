<?php
/*
 * Plugin Name: WooCommerce UePay
 * Plugin URI: https://rudrastyh.com/woocommerce/payment-gateway-plugin.html
 * Description: Take credit card payments on your store.
 * Author: Misha Rudrastyh
 * Author URI: http://rudrastyh.com
 * Version: 1.0.1
 */

require 'lib.php';
// require 'vendor/autoload.php';

add_action('plugins_loaded', 'init_uepay_gateway_class');

function init_uepay_gateway_class()
{
  class WC_Gateway_Uepay extends WC_Payment_Gateway
  {
    public $uepayKey = "";
    public $merchantNo = "";
    public $redirectUrl = "";
    public $notifyUrl = "";
    public $proxyUrl = "";
    function  __construct()
    {
      $this->id = "uepay_gateway";
      $this->has_fields = true;
      $this->method_title = "Uepay";
      $this->method_description = "Uepay payment gateway";
      $this->init_form_fields();
      $this->init_settings();
      $this->enabled = $this->get_option('enabled');
      $this->title = 'UePay';
      $this->uepayKey = $this->get_option('uepayKey');
      $this->merchantNo = $this->get_option('merchantNo');
      $this->redirectUrl = $this->get_option('redirectUrl');
      $this->notifyUrl = $this->get_option('notifyUrl');
      $this->proxyUrl = $this->get_option("proxyUrl");
      add_action('woocommerce_api_uepay_callback', array($this, 'webhook'));
      add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    function WebhookData()
    {
      $body = file_get_contents("php://input");
      $object = json_decode($body, true);
      return $object;
    }

    function webhook()
    {
      $data = $this->WebhookData();
	foreach ($data as $k => $v) {
	    $appendVar = fopen('/var/www/php-log.txt','a');
	    fwrite($appendVar, "$k: $v\n");
	}
      if (Verify($data, $this->get_option("uepayKey"))) {
	if ($data["tradeState"] == "SUCCESS") {
		$order = new WC_Order($data["orderNo"]);
		$order->payment_complete();
		echo "success";
	}
	if ($data["tradeState"] == "BACKOUT" || $data["tradeState"] == "PAYERROR") {
		$order = new WC_Order($data["orderNo"]);
		$appendVar = fopen('/var/www/php-log.txt','a');
		fwrite($appendVar, "cancel\n");
		echo "success";
	}
      } else {
        error_log("webhook error");
        header("HTTP/1.0 404 Not Found");
      }
    }

    function init_form_fields()
    {
      $this->form_fields = array(
        'enabled' => array(
          'title' => __('Enable/Disable', 'woocommerce'),
          'type' => 'checkbox',
          'label' => __('Enable UePay', 'woocommerce'),
          'default' => 'yes'
        ),
        'uepayKey' => array(
          'title' => __('Key', 'woocommerce'),
          'type' => 'text',
          'description' => __('UePay秘鑰', 'woocommerce'),
          'default' => __('', 'woocommerce'),
          'desc_tip'      => false,
        ),
        'merchantNo' => array(
          'title' => __('Merchant No', 'woocommerce'),
          'type' => 'text',
          'description' => __('商戶編號', 'woocommerce'),
          'default' => ''
        ),
        'redirectUrl' => array(
          'title' => __('Redirect Url', 'woocommerce'),
          'type' => 'text',
          'default' => ''
        ),
        'notifyUrl' => array(
          'title' => __('Notify Url', 'woocommerce'),
          'type' => 'text',
          'default' => ''
        ),
        'proxyUrl' => array(
          'title' => __('Proxy Url', 'woocommerce'),
          'type' => 'text',
          'default' => ''
        )
      );
    }

    function process_payment($order_id)
    {
      global $woocommerce;
      $order = new WC_Order($order_id);
      $order->update_status('pending', __('Awaiting UePay payment', 'woocommerce'));
      $amt = round($order->get_total() * 100);
      // Remove cart
      $woocommerce->cart->empty_cart();
      $body = createPayment(
        $this->merchantNo,
        $this->uepayKey,
        $order_id,
        $amt,
        $this->redirectUrl,
        $this->notifyUrl,
        "商品支付",
        '{}',
      );
      // $order->up
      return array(
        'result' => 'success',
        'redirect' => createUrl($body),
      );
    }
  }
}

function add_your_gateway_class($methods)
{
  $methods[] = 'WC_Gateway_Uepay';
  return $methods;
}

function printArray($arr, $level)
{
  if (gettype($arr) != "array") {
    error_log("level", $level, $arr);
  } else {
    foreach ($arr as $key => $value) {
      error_log($key);
      printArray($value, $level + 1);
    }
  }
}

add_filter('woocommerce_payment_gateways', 'add_your_gateway_class');
