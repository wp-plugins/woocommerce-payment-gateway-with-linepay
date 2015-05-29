<?php
/*
Plugin Name: WooCommerce Payment Gateway with LinePay
Plugin URI: http://www.planet8.co/
Description: Korean Payment Gateway integrated with LinePay for WooCommerce.
Version: 1.0.2
Author: Planet8
Author URI: http://www.planet8.co/
Copyright : Planet8 proprietary.
Developer : Thomas Jang ( thomas@planet8.co )
*/
if ( ! defined( 'ABSPATH' ) ) exit;

// Define
define( PRODUCT_ID, 'wordpress-linepay' );
define( PRODUCT_VERSION, '1.0.2' );

add_action( 'plugins_loaded', 'woocommerce_linepay_init', 0 );
 
function woocommerce_linepay_init() {

if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

// Localization
load_plugin_textdomain( 'wc-gateway-linepay', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

// Settings Link
function woocommerce_linepay_kr_plugin_settings_link( $links ) {
	$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_linepay_payment', is_ssl() ? 'https' : 'http' );
	$settings_link = '<a href="' . $url . '">' . __( 'Settings', 'wc-gateway-linepay' ) . '</a>'; 
	array_unshift($links, $settings_link); 
	return $links; 
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'woocommerce_linepay_kr_plugin_settings_link' );

// For API Callback
class WC_Gateway_LinePay_Request extends WC_Payment_Gateway {
	public function __construct() {
		global $woocommerce;

		add_action( 'woocommerce_api_wc_gateway_linepay_request', array( $this, 'check_request' ) );
	}

	function check_request() {
		$class = new WC_Gateway_LinePay();
		$class->check_request();
	}
}

class WC_Gateway_LinePay_Confirm extends WC_Payment_Gateway {
	public function __construct() {
		global $woocommerce;

		add_action( 'woocommerce_api_wc_gateway_linepay_confirm', array( $this, 'check_confirm' ) );
	}

	function check_confirm() {
		$class = new WC_Gateway_LinePay();
		$class->check_confirm();
	}
}

class WC_Gateway_LinePay_Cancel extends WC_Payment_Gateway {
	public function __construct() {
		global $woocommerce;

		add_action( 'woocommerce_api_wc_gateway_linepay_cancel', array( $this, 'check_cancel' ) );
	}

	function check_cancel() {
		$class = new WC_Gateway_LinePay();
		$class->check_cancel();
	}
}

class WC_Gateway_LinePay_Meta_Box extends WC_Payment_Gateway {
	public function __construct() {
		global $woocommerce;

		// For Admin Refund
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 30 );

		// For Customer Refund
		add_filter( 'woocommerce_my_account_my_orders_actions',  array( $this, 'woocommerce_my_account_my_orders_actions' ), 10, 2 );
	}

	function add_meta_boxes() {
		add_meta_box( 'wc-gateway-linepay-refund', __( 'Refund Order', 'wc-gateway-linepay' ), array( $this, 'meta_box_refund' ), 'shop_order', 'side', 'high');
	}

	function meta_box_refund() {
		global $woocommerce, $post;

		$order = new WC_Order( $post->ID );

		$orderId				= $order->id;
		$transactionId			= get_post_meta( $orderId, '_linepay_tid', true );
		$order_refund			= get_post_meta( $orderId, '_linepay_refund', true );

		$class = new WC_Gateway_LinePay();

		$this->id = get_post_meta( $order->id, '_payment_method', true );
		$this->init_settings();
		$settings = get_option('woocommerce_'.$this->id.'_settings');
		$this->admin_refund = $settings[ 'admin_refund' ];

		$admin_refund_array		= $this->admin_refund;

		if ( is_array( $admin_refund_array ) ) {
			for ( $i = 0; $i < sizeof( $admin_refund_array ); $i++ ) {
				if ( $i+1 != sizeof( $admin_refund_array ) ) {
					$admin_refund_string .= $this->get_status( $admin_refund_array[ $i ] ) . ', ';
				} else {
					$admin_refund_string .= $this->get_status( $admin_refund_array[ $i ] );
				}
			}
			if ( in_array( 'wc-' . $order->status, $admin_refund_array ) ) {
				$is_refundable = true;
			} else {
				$is_refundable = false;
			}
		} else {
			$admin_refund_string = __( $admin_refund_string, 'wc-gateway-linepay' );
			if ( $order->status == $admin_refund_array ) {
				$is_refundable = true;
			} else {
				$is_refundable = false;
			}
		}

		if ( $order_refund == 'yes' ) {
			$is_refundable = false;
		}

		if ( $is_refundable ) {
			wp_register_script( 'linepay_admin_script', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/assets/js/admin.js' );
			wp_enqueue_script( 'linepay_admin_script' );

			$ask_msg = __( 'Are you sure you want to continue the refund?', 'wc-gateway-linepay' );

			echo __( 'By clicking the \'Refund\' button below, this order will be refunded and cancelled.', 'wc-gateway-linepay' );
			echo "<br><br>";
			echo "<input class='button button-primary' type='button' onclick='javascript:doRefund(\"" . $ask_msg . "\", \"" . home_url( '/wc-api/wc_gateway_linepay_cancel' ) ."\", \"".$orderId."\", \"".$transactionId."\");' value='";
			echo __( 'Refund', 'wc-gateway-linepay' );
			echo "'>";
		} else {
			if ( $order_refund == 'yes' ) {
				echo __( 'This order has already been refunded.', 'wc-gateway-linepay' );
			} else {
				if ( $admin_refund_string ) {
					echo sprintf( __( 'This order cannot be refunded. Refundable order status is(are): %s', 'wc-gateway-linepay' ), $admin_refund_string );
				} else {
					if ( is_ssl() ) {
						$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_' . $this->id, 'https' );
					} else {
						$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_' . $this->id, 'http' );
					}
					echo sprintf( __( 'There are no refundable order status. Please set them first <a href=\'%s\'>here</a>.', 'wc-gateway-linepay' ), $url );
				}
			}
		}
	}

	function woocommerce_my_account_my_orders_actions( $actions, $order ) {
		echo "<input type='hidden' id='ask-refund-msg' value='" . __( 'Are you sure you want to continue the refund?', 'wc-gateway-linepay' ) . "'>";

		$payment_method = get_post_meta( $order->id, '_payment_method', true );

		$orderId		= $order->id;
		$transactionId	= get_post_meta( $order->id, '_linepay_tid', true );

		$class = new WC_Gateway_LinePay();

		$this->id = get_post_meta( $order->id, '_payment_method', true );
		$this->init_settings();
		$settings = get_option( 'woocommerce_'.$this->id.'_settings' );
		$this->customer_refund = $settings[ 'customer_refund' ];

		$customer_refund_array		= $this->customer_refund;

		if ( is_array ( $customer_refund_array ) ) {
			if ( in_array( 'wc-' . $order->status, $customer_refund_array ) ) {
				wp_register_script( 'linepay_frontend_script', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/assets/js/frontend.js' );
				wp_enqueue_script( 'linepay_frontend_script' );

				$cancel_api = home_url( '/wc-api/wc_gateway_linepay_cancel' );
				$redirect = get_permalink( wc_get_page_id( 'myaccount' ) );

				$actions[ 'cancel' ] = array(
					'url'		=> wp_nonce_url( add_query_arg( array( 'customer-cancel' => 'true', 'transactionId' => $transactionId, 'orderId' => $orderId, 'redirect' => $redirect ), $cancel_api ), 'linepay_customer_refund' ),
					'name'		=> __( 'Refund', 'wc-gateway-linepay' ),
				);
			}
		}

		return $actions;
	}

	function get_status( $status ) {
		$order_statuses = wc_get_order_statuses();

		return $order_statuses[ $status ];
	}
}

new WC_Gateway_LinePay_Meta_Box();

// Gateway class
class WC_Gateway_LinePay extends WC_Payment_Gateway {

	public function __construct() {
		global $woocommerce;
		$this->init_form_fields();
		$this->init_settings();
		$this->add_extra_form_fields();

		$this->method_title		= __( $this->method_title, 'wc-gateway-linepay' );

		// General Settings
		$this->title			= $this->get_option( 'title' );
		$this->description		= $this->get_option( 'description' );
		$this->enabled			= $this->get_option( 'enabled' );
		$this->sandbox			= $this->get_option( 'sandbox' );
		$this->channelid		= $this->get_option( 'channelid' );
		$this->channelsecret	= $this->get_option( 'channelsecret' );
		$this->langCd			= $this->get_option( 'langCd' );

		// Design Settings
		$this->checkout_txt		= $this->get_option( 'checkout_txt' );
		$this->checkout_img		= $this->get_option( 'checkout_img' );

		// Actions
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );	

		// Payment listener/API hook
		add_action( 'linepay_process_request', array( $this, 'process_request' ) );
		add_action( 'linepay_process_confirm', array( $this, 'process_confirm' ) );
		add_action( 'linepay_process_cancel', array( $this, 'process_cancel' ) );

		if ( ! $this->is_valid_for_use( $this->allowed_currency ) ) {
			if ( ! $this->allow_other_currency ) {
				$this->enabled = 'no';
			}
		}

		if ( $this->channelid == '' ) {
			$this->enabled = 'no';
		}

		if ( $this->channelsecret == '' ) {
			$this->enabled = 'no';
		}
	}

    function init_form_fields() {
		// General Settings
		$general_array = array(
			'general_title' => array(
				'title' => __( 'General Settings', 'wc-gateway-linepay' ),
				'type' => 'title',
			),
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'wc-gateway-linepay' ),
				'type' => 'checkbox',
				'label' => __( 'Enable this method.', 'wc-gateway-linepay' ),
				'default' => 'yes'
			),
			'title' => array(
				'title' => __( 'Title', 'wc-gateway-linepay' ),
				'type' => 'txt_info',
				'description' => __( 'Title that users will see during checkout. You can customize this in the PRO version.', 'wc-gateway-linepay' ),
				'txt' => __( $this->title_default, 'wc-gateway-linepay' ),
			),
			'sandbox' => array(
				'title' => __( 'Enable/Disable Sandbox Mode', 'wc-gateway-linepay' ),
				'type' => 'checkbox',
				'label' => __( 'Enable sandbox mode.', 'wc-gateway-linepay' ),
				'description' => '',
				'default' => 'no'
			),
			'description' => array(
				'title' => __( 'Description', 'wc-gateway-linepay' ),
				'type' => 'txt_info',
				'description' => __( 'Description that users will see during checkout. You can customize this in the PRO version.', 'wc-gateway-linepay' ),
				'txt' => __( $this->desc_default, 'wc-gateway-linepay' )
			),					
			'channelid' => array(
				'title' => __( 'Channel ID', 'wc-gateway-linepay' ),
				'type' => 'text',
				'description' => sprintf( __( 'Enter your Channel ID. You can get it from: <a href="%s">%s</a>', 'wc-gateway-linepay' ), $this->get_linepay_admin_url(), $this->get_linepay_admin_url() ),
				'default' => '',
			),
			'channelsecret' => array(
				'title' => __( 'Channel Secret Key', 'wc-gateway-linepay' ),
				'type' => 'text',
				'description' => sprintf( __( 'Enter your Channel Secret Key. You can get it from: <a href="%s">%s</a>', 'wc-gateway-linepay' ), $this->get_linepay_admin_url(), $this->get_linepay_admin_url() ),
				'default' => '',
			),
		);

		// Refund Settings
		$refund_array = array(
			'refund_title' => array(
				'title' => __( 'Refund Settings', 'wc-gateway-linepay' ),
				'type' => 'title',
			),
			'admin_refund' => array (
				'title' => __( 'Refundable Status for Administrator', 'wc-gateway-linepay' ),
				'type' => 'multiselect',
				'class' => 'chosen_select',
				'description' => __( 'Select the order status for allowing refund.', 'wc-gateway-linepay' ),
				'options' => $this->get_status_array(),
			),
			'customer_refund' => array (
				'title' => __( 'Refundable Satus for Customer', 'wc-gateway-linepay' ),
				'type' => 'txt_info',
				'txt' => __( 'This feature is only available in the PRO version.', 'wc-gateway-linepay' ),
				'description' => __( 'Select the order status for allowing refund.', 'wc-gateway-linepay' ),
			)
		);

		// Design Settings
		$design_array = array(
			'design_title' => array(
				'title' => __( 'Design Settings', 'wc-gateway-linepay' ),
				'type' => 'title',
			),
			'langCd' => array(
				'title' => __( 'Language', 'wc-gateway-linepay' ),
				'type' => 'select',
				'description' => __( 'Select the language for your LinePay form.', 'wc-gateway-linepay' ),
				'options' => array(
					'kr' => __( 'Korean', 'wc-gateway-linepay' ),
					'ja' => __( 'Japanese', 'wc-gateway-linepay' ),
					'en' => __( 'English', 'wc-gateway-linepay' ),
					'zh-Hans' => __( 'Chinese (Ganche)', 'wc-gateway-linepay' ),
					'zh-Hant' => __( 'Chinese (Bunche)', 'wc-gateway-linepay' ),
					'th' => __( 'Thai', 'wc-gateway-linepay' ),
				),
				'default' => 'kr',
			),
			'checkout_img' => array(
				'title' => __( 'Checkout Processing Image', 'wc-gateway-linepay' ),
				'type' => 'txt_info',
				'txt' => __( 'This feature is only available in the PRO version.', 'wc-gateway-linepay' ),
				'description' => __( 'Please select or upload your image for the checkout processing page. Leave blank to show no image.', 'wc-gateway-linepay' ),
			),	
			'checkout_txt' => array(
				'title' => __( 'Checkout Processing Text', 'wc-gateway-linepay' ),
				'type' => 'txt_info',
				'txt' => __( 'This feature is only available in the PRO version.', 'wc-gateway-linepay' ),
				'description' => __( 'Text that users will see on the checkout processing page. You can use some HTML tags as well.', 'wc-gateway-linepay' ),
			),
		);

		$form_array = array_merge( $general_array, $refund_array );
		$form_array = array_merge( $form_array, $design_array );

		$this->form_fields = $form_array;
	}

	function add_extra_form_fields() {

	}

	function generate_txt_info_html( $key, $value ) {
		ob_start();
		?>
		<tr valign="top">
			<th class="titledesc" scope="row">
				<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value[ 'title' ] ); ?></label>
			</th>
			<td class="forminp"><?php echo esc_html( $value[ 'txt' ] ); ?> 
				<p class="description">
				<?php echo esc_html( $value[ 'description' ] ); ?>
				<input type="hidden" name="woocommerce_linepay_payment_<?php echo esc_attr( $key ); ?>" id="woocommerce_linepay_payment_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_html( $value[ 'txt' ] ); ?>">
				</p>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	function is_valid_for_use( $allowed_currency ) {
		if ( is_array( $allowed_currency ) ) {
			if ( in_array( get_woocommerce_currency(), $allowed_currency ) ) {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	function admin_options() {
		$currency_str = $this->get_currency_str( $this->allowed_currency );

		echo '<h3>' . $this->method_title . '</h3>';

		echo '<div class="inline notice notice-info"><p><strong>' . __( 'Want the PRO version?', 'wc-gateway-linepay' ) . '</strong> ' . __ ( 'Visit <a href="http://planet8.co" target="_blank">http://planet8.co</a>! You can remove our watermarks and customize many options!', 'wc-gateway-linepay' ) . '</p></div>';

		if ( $this->get_option( 'channelid' ) == '' ) {
			echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', 'wc-gateway-linepay' ) . '</strong>: ' . sprintf( __( 'You haven\'t entered a Channel ID yet! You can get it from: <a href="%s">%s</a>', 'wc-gateway-linepay' ), $this->get_linepay_admin_url(), $this->get_linepay_admin_url() ) . '</p></div>';
		} else if ( $this->get_option( 'channelsecret' ) == '' ) {
			echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', 'wc-gateway-linepay' ) . '</strong>: ' . sprintf( __( 'You haven\'t entered a Channel Secret Key yet! You can get it from: <a href="%s">%s</a>', 'wc-gateway-linepay' ), $this->get_linepay_admin_url(), $this->get_linepay_admin_url() ) . '</p></div>';
		} else if ( $this->get_option( 'sandbox' ) == 'yes' ) {
			echo '<div class="inline error"><p><strong>' . __( 'Sandbox mode is enabled!', 'wc-gateway-linepay' ) . '</strong> ' . __( 'Please disable sandbox if you aren\'t testing anything.', 'wc-gateway-linepay' ) . '</p></div>';
		}

		if ( ! $this->is_valid_for_use( $this->allowed_currency ) ) {
			if ( ! $this->allow_other_currency ) {
				echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', 'wc-gateway-linepay' ) .'</strong>: ' . sprintf( __( 'Your currency (%s) is not supported by this payment method. This payment method only supports: %s.', 'wc-gateway-linepay' ), get_woocommerce_currency(), $currency_str ) . '</p></div>';
			} else {
				echo '<div class="inline notice notice-info"><p><strong>' . __( 'Please Note', 'wc-gateway-linepay' ) .'</strong>: ' . sprintf( __( 'Your currency (%s) is not recommended by this payment method. This payment method recommeds the following currency: %s.', 'wc-gateway-linepay' ), get_woocommerce_currency(), $currency_str ) . '</p></div>';
			}
		}

		echo '<table class="form-table">';
		$this->generate_settings_html();
		echo '</table>';
	}

	function receipt( $order_id ) {
		$order = new WC_Order( $order_id );

		echo '<div class="p8-checkout-img"><img src="' . untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/assets/images/checkout_img.png' . '"></div>';

		echo '<div class="p8-checkout-txt">' . __( 'Please wait while your payment is being processed.', 'wc-gateway-linepay' ) . '</div>';

		echo '<div class="p8-checkout-txt">' . __( 'Powered by <a href="http://planet8.co" target="_blank">Planet8</a>.', 'wc-gateway-linepay' ) . '</div>';

		require_once dirname( __FILE__ ) . '/bin/lib/Version.php';

		$currency_check = $this->currency_check( $order, $this->allowed_currency );

		if ( $currency_check ) {
			echo $this->linepay_form( $order_id );
		} else {
			$currency_str = $this->get_currency_str( $this->allowed_currency );

			echo sprintf( __( 'Your currency (%s) is not supported by this payment method. This payment method only supports: %s.', 'wc-gateway-linepay' ), get_post_meta( $order->id, '_order_currency', true ), $currency_str );
		}
	}

	function get_linepay_args( $order ) {
		global $woocommerce;

		$orderId = $order->id;

		$this->billing_phone = $order->billing_phone;

		if ( sizeof( $order->get_items() ) > 0 ) {
			foreach ( $order->get_items() as $item ) {
				if ( $item[ 'qty' ] ) {
					$item_name = $item[ 'name' ];
					$product_id = $item[ 'product_id' ];
				}
			}
		}

		$productName				= sanitize_text_field( $item_name );
		$thumb						= wp_get_attachment_image_src( get_post_thumbnail_id( $product_id ) );
		$productImageUrl			= $thumb[0];
		$currency					= get_woocommerce_currency();
		$mid						= '';
		$confirmUrl					= home_url( '/wc-api/wc_gateway_linepay_confirm' );
		$cancelUrl					= home_url( '/wc-api/wc_gateway_linepay_cancel' );
		$orderId					= $order->id;
		$deliveryPlacePhone			= $order->billing_phone;
		$langCd						= $this->langCd;

		$linepay_args = array(
			'productName'			=> $productName,
			'productImageUrl'		=> $productImageUrl,
			'amount'				=> (int)$order->order_total,
			'currency'				=> $currency,
			'confirmUrl'			=> $confirmUrl,
			'confirmUrlType'		=> 'CLIENT',
			'cancelUrl'				=> $cancelUrl,
			'orderId'				=> $orderId,
			'deliveryPlacePhone'	=> $deliveryPlacePhone,
			'payType'				=> 'NORMAL',
			'langCd'				=> $langCd,
			'capture'				=> 'true'
		);

		$linepay_args = apply_filters( 'woocommerce_linepay_args', $linepay_args );

		return $linepay_args;
	}

	function currency_check( $order, $allowed_currency ) {
		$currency = get_post_meta( $order->id, '_order_currency', true );

		if ( in_array( $currency, $allowed_currency ) ) {
			return true;
		} else {
			return false;
		}
	}

	function linepay_form( $orderId ) {
		global $woocommerce;

		wp_register_style( 'LinePayCSS', plugins_url( 'assets/css/frontend.css', __FILE__ ) );
		wp_enqueue_style( 'LinePayCSS' );

		$order = new WC_Order( $orderId );

		$linepay_args = $this->get_linepay_args( $order );
		$linepay_args_array = array();

		foreach ( $linepay_args as $key => $value ) {
			//$linepay_args_array[] = esc_attr( $key ).'<input type="text" style="width:150px;" id="'.esc_attr( $key ).'" name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" /><br>';
			$linepay_args_array[] = '<input type="hidden" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
		}

		$linepay_form = "
		<div id='p8-logo-bg' class='p8-logo-bg'></div>
		<div id='p8-logo-div' class='p8-logo-div'><div class='p8-logo-container'><img src='" . untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/assets/images/p8_logo_white.png' . "'></div></div>
		<form method='post' id='LinePayForm' name='LinePayForm' action='" . home_url( '/wc-api/wc_gateway_linepay_request' ) . "'>" . implode( '', $linepay_args_array ) . "</form>
		";

		$linepay_form .= "
		<script type='text/javascript'> 
		var payForm = document.LinePayForm;

		function hideP8Logo() {
			jQuery('#p8-logo-bg').css('display', 'none');
			jQuery('#p8-logo-div').css('display', 'none');
		}

		function showP8Logo() {
			jQuery('#p8-logo-bg').css('display', 'block');
			jQuery('#p8-logo-div').css('display', 'table');
		}

		jQuery(document).ready(function($){
			showP8Logo();
			setTimeout('startPayment();', 3000);
		});

		function startPayment() {
			hideP8Logo();
			setTimeout('payForm.submit();', 1000);
		}
		</script>
		";

		return $linepay_form;
	}

	function process_payment( $orderId ) {
		global $woocommerce;

		$order = new WC_Order( $orderId );
		$order->update_status( 'pending' );
		$order->add_order_note( sprintf( __( 'Starting payment process. Timestamp: %s.', 'wc-gateway-linepay' ), $this->get_timestamp() ) );

		if ( $this->check_mobile() ) {
			$add_mobile_meta = get_post_meta( $order->id, '_payment_method_title', true );
			if ( ! stripos( $add_mobile_meta, __( ' (Mobile)', 'wc-gateway-linepay' ) ) ) {
				$add_mobile_meta = $add_mobile_meta.__( ' (Mobile)', 'wc-gateway-linepay' );
			}
			update_post_meta( $order->id, '_payment_method_title', $add_mobile_meta );
		} else {
			$add_mobile_meta = get_post_meta( $order->id, '_payment_method_title', true );
			if ( stripos( $add_mobile_meta, __( ' (Mobile)', 'wc-gateway-linepay' ) ) ) {
				$add_mobile_meta = str_replace( __( ' (Mobile)', 'wc-gateway-linepay' ), '', $add_mobile_meta );
			}
			update_post_meta( $order->id, '_payment_method_title', $add_mobile_meta );
		}

		if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '<' ) ) {
			return array(
				'result' 	=> 'success',
				'redirect'	=> add_query_arg( 'key', $order->order_key, add_query_arg( 'order', $orderId, get_permalink( woocommerce_get_page_id( 'pay' ) ) ) )
			);
		} else {
			return array(
				'result' 	=> 'success',
				'redirect'	=> $order->get_checkout_payment_url( true )
			);
		}
	}

	function check_request() {
		global $woocommerce;
		@ob_clean();
		header( 'HTTP/1.1 200 OK' );
		do_action( 'linepay_process_request', $_REQUEST );
		break;
	}

	function check_confirm() {
		global $woocommerce;
		@ob_clean();
		header( 'HTTP/1.1 200 OK' );
		do_action( 'linepay_process_confirm', $_REQUEST );
		break;
	}

	function check_cancel() {
		global $woocommerce;
		@ob_clean();
		header( 'HTTP/1.1 200 OK' );
		do_action( 'linepay_process_cancel', $_REQUEST );
	}

	function process_request( $params ) {
		global $woocommerce;

		if ( ! empty( $params[ 'orderId' ] ) ) {
			$orderId = $this->get_orderid( $params[ 'orderId' ] );
			$order = new WC_Order( $orderId );

			if ( $order == null ) {
				wp_die( 'LinePay Payment Request Failure. Order does not exist.' );
			} else {
				$this->id = get_post_meta( $order->id, '_payment_method', true );
				$this->init_settings();
				$this->sandbox = $this->get_option( 'sandbox' );
				$this->channelid = $this->get_option( 'channelid' );
				$this->channelsecret = $this->get_option( 'channelsecret' );
			}

			if ( $this->sandbox == 'yes' ) {
				$url = 'https://sandbox-api-pay.line.me/v1/payments/request';
			} else {
				$url = 'https://api-pay.line.me/v1/payments/request';
			}

			$header = array(
				'Content-Type: application/json; charset=UTF-8',
				'X-LINE-ChannelId: ' . $this->channelid,
				'X-LINE-ChannelSecret: ' . $this->channelsecret,
			);

			$data = array(
				'productName'			=> $params[ 'productName' ],
				'productImageUrl'		=> $params[ 'productImageUrl' ],
				'amount'				=> $params[ 'amount' ],
				'currency'				=> $params[ 'currency' ],
				'confirmUrl'			=> $params[ 'confirmUrl' ],
				'confirmUrlType'		=> $params[ 'confirmUrlType' ],
				'cancelUrl'				=> $params[ 'cancelUrl' ],
				'orderId'				=> $params[ 'orderId' ],
				'deliveryPlacePhone'	=> $params[ 'deliveryPlacePhone' ],
				'payType'				=> $params[ 'payType' ],
				'langCd'				=> $params[ 'langCd' ],
				'capture'				=> $params[ 'capture' ]
			);

			$data_string = json_encode( $data );

			$ch = curl_init( $url );
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch, CURLOPT_POST, 1 );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $data_string );
			curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $header );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 20 );
			$result = curl_exec( $ch );
			curl_close( $ch );

			$result = json_decode( $result );

			$requestSuccess = false;

			if ( isset( $result->info ) ) {
				$paymentUrlWeb	= $result->info->paymentUrl->web;
				$transactionId	= $result->info->transactionId;

				$requestSuccess	= true;
			}

			$returnCode			= $result->returnCode;
			$returnMessage		= $result->returnMessage;

			if ( $result->returnCode != '0000' ) $requestSuccess = false;
				
			if ( $requestSuccess ) {
				update_post_meta( $order->id, '_linepay_tid', $transactionId );
				$order->add_order_note( sprintf( __( 'Payment request received. LinePay TID: %s. Timestamp: %s.', 'wc-gateway-linepay' ), $transactionId, $this->get_timestamp() ) );
				wp_redirect( $paymentUrlWeb );
				exit;
			} else {
				$order->update_status( 'failed', sprintf( __( 'Failed to request payment. Message: %s. Timestamp: %s.', 'wc-gateway-linepay' ), $returnMessage, $this->get_timestamp() ) );
				if ( $this->sandbox == 'yes' ) {
					wc_add_notice( sprintf( __( 'An error has occurred while processing your request. Code: %s. Message: %s.', 'wc-gateway-linepay' ), $returnCode, $returnMessage ), 'error' );
				} else {
					wc_add_notice( __( 'An error has occurred while processing your request. Please contact the administrator.', 'wc-gateway-linepay' ), 'error' );
				}
				$cart_url = $woocommerce->cart->get_cart_url();
				wp_redirect( $cart_url );
				exit;
			}
		} else {
			wp_die( 'LinePay Payment Request Failure' );
		}
	}

	function process_confirm ( $params ) {
		global $woocommerce;

		if ( ! empty( $params[ 'transactionId' ] ) ) {
			$orderId = $this->get_orderid_from_meta( '_linepay_tid', $params[ 'transactionId' ] );

			if ( $orderId ) {
				$order = new WC_Order( $orderId );
				$transactionId = $params[ 'transactionId' ];

				if ( $order == null ) {
					wp_die( 'LinePay Payment Confirm Failure. Order does not exist.' );
				} else {
					$this->id = get_post_meta( $order->id, '_payment_method', true );
					$this->init_settings();
					$this->sandbox = $this->get_option( 'sandbox' );
					$this->channelid = $this->get_option( 'channelid' );
					$this->channelsecret = $this->get_option( 'channelsecret' );
				}

				if ( $this->sandbox == 'yes' ) {
					$url = 'https://sandbox-api-pay.line.me/v1/payments/' . $transactionId . '/confirm';
				} else {
					$url = 'https://api-pay.line.me/v1/payments/' . $transactionId . '/confirm';
				}

				$header = array(
					'Content-Type: application/json; charset=UTF-8',
					'X-LINE-ChannelId: ' . $this->channelid,
					'X-LINE-ChannelSecret: ' . $this->channelsecret,
				);

				$data = array(
					'amount'				=> $order->order_total,
					'currency'				=> get_post_meta( $order->id, '_order_currency', true ),
				);

				$data_string = json_encode( $data );

				$ch = curl_init( $url );
				curl_setopt( $ch, CURLOPT_URL, $url );
				curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
				curl_setopt( $ch, CURLOPT_POST, 1 );
				curl_setopt( $ch, CURLOPT_POSTFIELDS, $data_string );
				curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );
				curl_setopt( $ch, CURLOPT_HTTPHEADER, $header );
				curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
				curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 20 );
				$result = curl_exec( $ch );
				curl_close( $ch );

				$result = json_decode( $result );

				$confirmSuccess = true;

				$returnCode			= $result->returnCode;
				$returnMessage		= $result->returnMessage;

				if ( $result->returnCode != '0000' ) $confirmSuccess = false;
					
				if ( $confirmSuccess ) {
					$order->payment_complete();
					$order->add_order_note( sprintf( __( 'Payment confirmed and complete. LinePay TID: %s. Timestamp: %s.', 'wc-gateway-linepay' ), $transactionId, $this->get_timestamp() ) );

					if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '<' ) ) {
						$return = array(
							'result' 	=> 'success',
							'redirect'	=> add_query_arg( 'key', $order->order_key, add_query_arg( 'order', $orderId, get_permalink( woocommerce_get_page_id( 'thanks' ) ) ) )
							);
					} else {
						$return = array(
							'result' 	=> 'success',
							'redirect'	=> $this->get_return_url( $order )
							);
					}

					$woocommerce->cart->empty_cart();
					wp_redirect( $return[ 'redirect' ] );
					exit;
				} else {
					$order->update_status( 'failed', sprintf( __( 'Failed to confirm payment. Message: %s. Timestamp: %s.', 'wc-gateway-linepay' ), $returnMessage, $this->get_timestamp() ) );
					if ( $this->sandbox == 'yes' ) {
						wc_add_notice( sprintf( __( 'An error has occurred while processing your request. Code: %s. Message: %s.', 'wc-gateway-linepay' ), $returnCode, $returnMessage ), 'error' );
					} else {
						wc_add_notice( __( 'An error has occurred while processing your request. Please contact the administrator.', 'wc-gateway-linepay' ), 'error' );
					}
					$cart_url = $woocommerce->cart->get_cart_url();
					wp_redirect( $cart_url );
					exit;
				}
			} else {
				wp_die( 'LinePay Payment Confirm Failure. Order does not exist.' );
			}
		} else {
			wp_die( 'LinePay Payment Confirm Failure' );
		}
	}

	function process_cancel( $params ) {
		global $woocommerce;

		if ( ! empty( $params[ 'orderId' ] ) ) {
			if ( ! empty( $params[ 'transactionId' ] ) ) {
				$orderId			= $this->get_orderid( $params[ 'orderId' ] );
				$transactionId		= $params[ 'transactionId' ];

				$order = new WC_Order( $orderId );

				if ( $order == null ) {
					$return = array(
						'result'	=> 'failure',
						'message'	=> __( 'Order does not exist. Refund failed.', 'wc-gateway-linepay' )
					);
				} else {
					$this->id = get_post_meta( $order->id, '_payment_method', true );
					$this->init_settings();
					$settings = get_option( 'woocommerce_'.$this->id.'_settings' );
					$this->sandbox = $this->get_option( 'sandbox' );
					$this->channelid = $this->get_option( 'channelid' );
					$this->channelsecret = $this->get_option( 'channelsecret' );
				}

				if ( $this->sandbox == 'yes' ) {
					$url = 'https://sandbox-api-pay.line.me/v1/payments/' . $transactionId . '/refund';
				} else {
					$url = 'https://api-pay.line.me/v1/payments/' . $transactionId . '/refund';
				}

				$header = array(
					'Content-Type: application/json; charset=UTF-8',
					'X-LINE-ChannelId: ' . $this->channelid,
					'X-LINE-ChannelSecret: ' . $this->channelsecret,
				);

				$ch = curl_init( $url );
				curl_setopt( $ch, CURLOPT_URL, $url );
				curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
				curl_setopt( $ch, CURLOPT_POST, 1 );
				curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );
				curl_setopt( $ch, CURLOPT_HTTPHEADER, $header );
				curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
				curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 20 );
				$result = curl_exec( $ch );
				curl_close( $ch );

				$result = json_decode( $result );

				$returnCode				= $result->returnCode;
				$returnMessage			= $result->returnMessage;

				$refundTransactionId	= $result->info->refundTransactionId;

				if ( $returnCode == '0000' ) {
					update_post_meta( $order->id, '_linepay_refund', 'yes' );

					if ( $params[ 'customer-cancel' ] ) {
						$order->update_status( 'refunded', sprintf( __( 'Order has been refunded by customer. LinePay Refund TID: %s. Timestamp: %s.', 'wc-gateway-linepay' ), $refundTransactionId, $this->get_timestamp() ) );
					} else {
						$order->update_status( 'refunded', sprintf( __( 'Order has been refunded by administrator. LinePay Refund TID: %s. Timestamp: %s.', 'wc-gateway-linepay' ), $refundTransactionId, $this->get_timestamp() ) );
					}

					$return = array(
						'result'	=> 'success',
						'message'	=> __( 'Order has been refunded.', 'wc-gateway-linepay' )
					);
				} else {
					$order->add_order_note( sprintf( __( 'Refund request received but an error has occurred. Message: %s. Timestamp: %s.', 'wc-gateway-linepay' ), $resultMsg, $this->get_timestamp() ) );

					$return = array(
						'result'	=> 'failure',
						'message'	=> sprintf( __( 'Refund request received but an error has occurred. Message: %s.', 'wc-gateway-linepay' ), $resultMsg )
					);
				}
			} else {
				$return = array(
					'result'	=> 'failure',
					'message'	=> __( 'TID does not exist. Refund failed.', 'wc-gateway-linepay' )
				);
			}
		} else {
			$return = array(
				'result'	=> 'failure',
				'message'	=> __( 'Order does not exist. Refund failed.', 'wc-gateway-linepay' )
			);
		}

		if ( $params[ 'customer-cancel' ] ) {
			if ( $return[ 'result' ] == 'success' ) {
				wc_add_notice( __( 'Order has been refunded.', 'wc-gateway-linepay' ), 'notice' );
				wp_redirect( $params[ 'redirect' ] );
				exit;
			} else {
				if ( $this->sandbox == 'yes' ) {
					wc_add_notice( sprintf( __( 'An error has occurred while processing your request. Code: %s. Message: %s.', 'wc-gateway-linepay' ), $returnCode, $returnMessage ), 'error' );
				} else {
					wc_add_notice( __( 'An error has occurred while processing your request. Please contact the administrator.', 'wc-gateway-linepay' ), 'error' );
				}

				wp_redirect( $params[ 'redirect' ] );
				exit;
			}
		} else {
			echo json_encode( $return );
			exit;
		}
	}

	function get_orderid_from_meta( $key, $value ) {
		global $wpdb;
		$meta = $wpdb->get_results( "SELECT * FROM `" . $wpdb->postmeta . "` WHERE meta_key='" . $wpdb->escape( $key ) . "' AND meta_value='" . $wpdb->escape( $value ) . "'" );
		if ( is_array( $meta ) && ! empty( $meta ) && isset( $meta[0] ) ) {
			$meta = $meta[0];
		}

		if ( is_object( $meta ) ) {
			return $meta->post_id;
		} else {
			return false;
		}
	}

	function get_orderid( $oid ) {
		$orderId = $oid;
		return $orderId;
	}

	function get_timestamp() {
		global $wpdb;
		$query = ' SELECT DATE_FORMAT( NOW( ) , \'%Y%m%d%H%i%s\' ) AS TIMESTAMP ; ';
		$row = $wpdb->get_results( $query );
		$_time = date( 'YmdHis', strtotime( $row[0]->TIMESTAMP ) );
		return $_time;
	}

	function check_mobile() {
		$agent = $_SERVER[ 'HTTP_USER_AGENT' ];
		
		if ( stripos ( $agent, 'iPod' ) || stripos( $agent, 'iPhone' ) || stripos( $agent, 'iPad' ) || stripos( $agent, 'Android' ) ) {
			return true;
		} else {
			return false;
		}
	}

	function get_status_array() {
		$order_statuses = wc_get_order_statuses();

		return $order_statuses;
	}

	function get_currency_str( $currency ) {
		$i = 0;
		foreach ( $currency as $key => $value ) {
			$currency_str .= ( ( $i > 0 ) ? ", " : "" ) . $value;
			$i++;
		}

		return $currency_str;
	}

	function get_linepay_admin_url() {
		$locale = get_locale();

		if ( is_array( $this->supported_languages ) ) {
			if ( in_array( $locale, $this->supported_languages ) ) {
				return "https://pay.line.me/" . $locale . "/center/payment/interlockKey";
			} else {
				return "https://pay.line.me/en_US/center/payment/interlockKey";
			}
		} else {
			return "https://pay.line.me/en_US/center/payment/interlockKey";
		}
	}

	function for_translation_purposes() {
		$translation_array = array(
			__( 'LinePay', 'wc-gateway-linepay' ),
			__( 'Payment via LinePay.', 'wc-gateway-linepay' ),
		);
	}
}	

require_once dirname( __FILE__ ) . '/includes/linepay.php';
}