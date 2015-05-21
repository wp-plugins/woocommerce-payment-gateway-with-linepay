<?php


if ( !defined( 'ABSPATH' ) ) exit;


////////////////////////////// LinePay


class WC_Gateway_LinePay_Payment extends WC_Gateway_LinePay{

	public $id						= 'linepay_payment';

	public $method_title			= 'LinePay';

	public $title_default			= 'LinePay - Powered by Planet8';

	public $desc_default			= 'Payment via LinePay - Powered by Planet8';

	public $allowed_currency		= array( 'USD', 'JPY' );

	public $supported_languages		= array( 'ja_JP', 'ko_KR', 'en_US', 'zh_CN', 'zh_TW', 'th_TH' );

	public $allow_other_currency	= false;
} 


/**
* Add the Gateway to WooCommerce
**/ 
function woocommerce_add_LinePay_Payment( $methods ) {
	$methods[] = 'WC_Gateway_LinePay_Payment';
	return $methods;
}

add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_LinePay_Payment' );
