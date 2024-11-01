<?php
/*
	Plugin Name: WooCommerce Clover Payment Gateway
	Plugin URI: http://woothemes.com/woocommerce/
	Description: Extends WooCommerce. Provides a clover payment gateway for WooCommerce.
	Version: 1.0
	Author: Clover, Inc.
	Author URI: http://www.clover.com

	Copyright: © 2012 Clover, Inc ( email: support@clover.com )
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

/**
 * Required functions
 **/
if ( ! function_exists( 'is_woocommerce_active' ) ) require_once 'woo-includes/woo-functions.php';

/**
 * Plugin updates
 */
if ( is_admin() ) {
	$woo_plugin_updater_clover = new WooThemes_Plugin_Updater( __FILE__ );
	$woo_plugin_updater_clover->api_key = '';
	$woo_plugin_updater_clover->init();
}

/**
 * Check if WooCommerce is active
 * */
add_action( 'plugins_loaded', 'init_clover_gateway', 0 );

function init_clover_gateway() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) )
		return;

	/**
	 * Localisation
	 */
	load_plugin_textdomain( 'wc-clover', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	class WC_CLOVER extends WC_Payment_Gateway {

		public function __construct() {
			$this->id = 'clover';
			$this->icon = apply_filters( 'woocommerce_bacs_icon', '' );
			$this->has_fields = true;
			$this->method_title = __( 'Clover', 'wc-clover' );
			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			// Define user set variables
			$this->title = $this->settings['title'];
			$this->description = $this->settings['description'];
			$this->merchant_id = $this->settings['merchant_id'];
			$this->merchant_serect = $this->settings['merchant_serect'];
			$this->sandbox = $this->settings['sandbox'];
			$this->autoaccept = $this->settings['autoaccept'];

			// Actions
			add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_bacs', array( &$this, 'thankyou_page' ) );

			// Customer Emails
			add_action( 'woocommerce_after_order_notes', array( &$this, 'clover_checkout_field' ) );
			add_action( 'woocommerce_checkout_process', array( &$this, 'clover_checkout_field_process' ) );
			add_action( 'woocommerce_checkout_update_order_meta', array( &$this, 'clover_checkout_field_update_order_meta' ) );
			add_action( 'woocommerce_order_status_on-hold_to_processing', array( &$this, 'woocommerce_accept_clover_order' ) );
			add_action( 'woocommerce_order_status_cancelled', array( &$this, 'woocommerce_cancel_clover_order' ) );
			add_action( 'woocommerce_order_status_refunded', array( &$this, 'woocommerce_refund_clover_order' ) );
		}

		/**
		 * Order Status completed - This is a paying customer
		 * */
		function woocommerce_accept_clover_order( $order_id ) {
			if ( $this->autoaccept == 'no' ) {
				$order = new WC_Order( $order_id );
				try {
					$cloverId = get_post_meta( $order_id, "clover_order_id", true );
					$this->accept_clover_order( $cloverId, $order->order_total );
					$order->add_order_note( __( 'Clover payment completed', 'wc-clover' ) );
					$order->payment_complete();
				} catch ( Exception $e ) {
					$order->add_order_note( __( 'Failed to accept order from Clover:' . $e->getMessage(), 'wc-clover' ) );
				}
			}
		}

		function accept_clover_order( $cloverOrderId, $total ) {
			if ( $cloverOrderId ) {
				// accept order
				$request = new Clover_Cloverpay_Helper_CloverRequest( $this->merchant_id,
					$this->merchant_serect, $this->sandbox == 'yes' ? 'sandbox' : 'production' );
				$response = $request->SendAcceptOrder( $cloverOrderId, $total );
				$this->parseResult( $response );
			}
		}

		function woocommerce_cancel_clover_order( $order_id ) {
			try {
				$cloverId = get_post_meta( $order_id, "clover_order_id", true );
				if ( $cloverId ) {
					// reject order
					$request = new Clover_Cloverpay_Helper_CloverRequest( $this->merchant_id,
						$this->merchant_serect, $this->sandbox == 'yes' ? 'sandbox' : 'production' );

					if ( $this->autoaccept == 'no' ) {
						$response = $request->SendRejectOrder( $cloverId );
					} else {
						$response = $request->SendRefundOrder( $cloverId );
					}

					$this->parseResult( $response );
				}
			} catch ( Exception $e ) {
				$order = new WC_Order( $order_id );
				$order->add_order_note( __( 'Failed to cancel order from Clover:' . $e->getMessage(), 'wc-clover' ) );
				return;
			}
		}

		function woocommerce_refund_clover_order( $order_id ) {
			try {
				$cloverId = get_post_meta( $order_id, "clover_order_id", true );
				if ( $cloverId ) {
					// refund order
					$request = new Clover_Cloverpay_Helper_CloverRequest( $this->merchant_id,
						$this->merchant_serect, $this->sandbox == 'yes' ? 'sandbox' : 'production' );
					$response = $request->SendRefundOrder( $cloverId );
					$this->parseResult( $response );
				}
			} catch ( Exception $e ) {
				$order = new WC_Order( $order_id );
				$order->add_order_note( __( 'Failed to refund order from Clover:' . $e->getMessage(), 'wc-clover' ) );
				return;
			}
		}

		function parseResult( $response ) {
			if ( $response[0] != '200' ) {
				$body = json_decode( $response[1], true );
				@error_log( $body["error"]["message"] );
				throw new Exception( __( $body["error"]["message"], 'wc-clover' ) );
			}
		}

		function clover_checkout_field( $checkout ) {

			echo '<div id="clover_checkout_field" style="display:none"><h3>' . __( 'Clover Order Id' ) . '</h3>';

			woocommerce_form_field( 'clover_order_id', array( 
					'type' => 'text',
					'class' => array( 'my-field-class form-row-wide' ),
					'label' => __( 'Id' ),
					'placeholder' => __( '' ),
				 ), $checkout->get_value( 'clover_order_id' ) );

			echo '</div>';
		}

		function clover_checkout_field_process() {
			global $woocommerce;

			// Check if set, if its not set add an error.
			if ( $_POST['payment_method'] == 'clover' && ! $_POST['clover_order_id'] ) {
				$woocommerce->add_error( __( 'Please make the payment before continue.', 'wc-clover' ) );
			}
		}

		/**
		 * Update the order meta with field value
		 * */
		function clover_checkout_field_update_order_meta( $order_id ) {
			if ( $_POST['clover_order_id'] )
				update_post_meta( $order_id, 'clover_order_id', esc_attr( $_POST['clover_order_id'] ) );
		}

		/**
		 * Initialise Gateway Settings Form Fields
		 */
		function init_form_fields() {

			$this->form_fields = array( 
				'enabled' => array( 
					'title' => __( 'Enable/Disable', 'wc-clover' ),
					'type' => 'checkbox',
					'label' => __( 'Enable Clover', 'wc-clover' ),
					'default' => 'yes'
				 ),
				'title' => array( 
					'title' => __( 'Title', 'wc-clover' ),
					'type' => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'wc-clover' ),
					'default' => __( 'Clover', 'wc-clover' )
				 ),
				'description' => array( 
					'title' => __( 'Customer Message', 'wc-clover' ),
					'type' => 'textarea',
					'description' => __( 'Give the customer instructions for paying via Clover, and let them know that their order won\'t be shipping until the money is received.', 'woocommerce' ),
					'default' => __( 'Pay by credit card or smartphone, instant rebate may apply', 'wc-clover' )
				 ),
				'merchant_id' => array( 
					'title' => __( 'Merchant Id', 'wc-clover' ),
					'type' => 'text',
					'description' => '',
					'default' => ''
				 ),
				'merchant_serect' => array( 
					'title' => __( 'Merchant Secret', 'wc-clover' ),
					'type' => 'text',
					'description' => '',
					'default' => ''
				 ),
				'autoaccept' => array( 
					'title' => __( 'Auto Accept Order', 'wc-clover' ),
					'type' => 'checkbox',
					'label' => __( 'Auto Accept', 'wc-clover' ),
					'description' => __( 'Bypass on-hold status. Completes the payment when order is placed, otherwise payment is completed when order status is set to processing.', 'wc-clover' ),
					'default' => 'no'
				 ),
				'sandbox' => array( 
					'title' => __( 'Sandbox', 'wc-clover' ),
					'type' => 'checkbox',
					'description' => 'Coming soon, right now it is only for internal use',
					'default' => 'no'
				 ),
			 );
		}

		// End init_form_fields()

		/**
		 * payment_fields function.
		 */
		function payment_fields() {
			global $woocommerce;

			if ( $this->description )
				echo wpautop( wptexturize( $this->description ) );

?>
			<script>
				window.Clover||( function(){
					Clover=function() {( Clover._=Clover._||[] ).push( arguments )}
					var s=document.createElement( 'script' ); s.defer=true
			<?php
			if ( $this->sandbox == 'yes' ) {
				echo "s.src='https://www.cloverdev.net/static/clover.js';";
			} else {
				echo "s.src='https://www.clover.com/static/clover.js';";
			}
?>
					var p=document.getElementsByTagName( 'script' )[0];

          			p.parentNode.insertBefore( s,p );

					Clover( 'setup', { account:'<?php echo $this->merchant_id ?>' } );
				} )();

		        jQuery( '#billing_postcode' ).change( function() {
		          Clover( 'setFirstPurchaseUserZipCode', jQuery( '#billing_postcode' ).val() );
		        } );
		        jQuery( '#billing_phone' ).change( function() {
		          Clover( 'setFirstPurchaseUserPhoneNumber', jQuery( "#billing_phone" ).val() );
		        } );
		        jQuery( '#billing_email' ).change( function() {
		          Clover( 'setFirstPurchaseUserEmail', jQuery( "#billing_email" ).val() );
		        } );
		        jQuery( '#billing_first_name' ).change( function() {
		          Clover( 'setFirstPurchaseUserFullName', jQuery( "#billing_first_name" ).val()+" "+jQuery( "#billing_last_name" ).val() );
		        } );
		        jQuery( '#billing_last_name' ).change( function() {
		          Clover( 'setFirstPurchaseUserFullName', jQuery( "#billing_first_name" ).val()+" "+jQuery( "#billing_last_name" ).val() );
		        } );

		        Clover( 'setFirstPurchaseUserFullName', jQuery( "#billing_first_name" ).val()+" "+jQuery( "#billing_last_name" ).val() );
		        Clover( 'setFirstPurchaseUserZipCode', jQuery( '#billing_postcode' ).val() );
		        Clover( 'setFirstPurchaseUserPhoneNumber', jQuery( "#billing_phone" ).val() );
		        Clover( 'setFirstPurchaseUserEmail', jQuery( "#billing_email" ).val() );

				Clover( 'onOrderAuthorized', function( order ) {
					jQuery( 'input[name="clover_order_id"]' ).val( order.id );
					jQuery( "#place_order" ).click();
				} );
			</script>

			<?php
			$_productTitle = '';
			foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $values ) :
				if ( strlen( $_productTitle ) > 0 ) {
					$_productTitle = $_productTitle.', ';
				}
			$_productTitle = $_productTitle.$values['data']->get_title();
			endforeach;
			// cap the product title to 125 characters
			if ( strlen( $_productTitle ) > 125 ) {
				$_productTitle = substr( $_productTitle, 0, 125 );
			}
?>
			<div id="clover_buy_button" amount="<?php echo number_format( $woocommerce->cart->total, 2, '.', '' ) ?>"
 					title="<?php echo $_productTitle ?>"
          permissions="full_name,email_address"
 					client_order_id=""
 					style="width:140px; height:47px; cursor:pointer; background:url( https://www.clover.com/static/buy.png );"
 					class="_Clover" onclick="Clover( 'onClick',this );"></div>
			<?php
		}

		function thankyou_page() {
			if ( $this->description )
				echo wpautop( wptexturize( $this->description ) );
		}

		/**
		 * Process the payment and return the result
		 * */
		function process_payment( $order_id ) {
			global $woocommerce;

			$order = new WC_Order( $order_id );

			// auto accept the order
			if ( $this->autoaccept == 'yes' ) {
				$cloverId = get_post_meta( $order_id, "clover_order_id", true );
				if ( $cloverId ) {
					try {
						$this->accept_clover_order( $cloverId, $order->order_total );
						$order->add_order_note( __( 'Clover payment completed', 'wc-clover' ) );
						$order->payment_complete();

					} catch ( Exception $e ) {
						$order->add_order_note( __( 'Failed to accept order from Clover:' . $e->getMessage(), 'wc-clover' ) );
					}
				}
			} else {
				// Mark as on-hold ( we're awaiting the payment )
				$order->update_status( 'on-hold', __( 'Clover payment has been authorized', 'wc-clover' ) );

				// Reduce stock levels
				$order->reduce_order_stock();
			}

			// Remove cart
			$woocommerce->cart->empty_cart();

			// Empty awaiting payment session
			unset( $_SESSION['order_awaiting_payment'] );

			// Return thankyou redirect
			return array( 
				'result' => 'success',
				'redirect' => add_query_arg( 'key', $order->order_key, add_query_arg( 'order', $order->id, get_permalink( woocommerce_get_page_id( 'thanks' ) ) ) )
			 );
		}

	}

	/**
	 * Send requests to the Clover Checkout server to perform different actions
	 */
	class Clover_Cloverpay_Helper_CloverRequest {

		var $merchant_id;
		var $merchant_key;
		var $currency;
		var $server_url;
		var $schema_url;
		var $base_url;
		var $checkout_url;
		var $checkout_diagnose_url;
		var $request_url;
		var $request_diagnose_url;
		var $merchant_checkout;

		/**
		 * @param string $id the merchant id
		 * @param string $key the merchant key
		 * @param string $server_type the server type of the server to be used, one
		 *              of 'sandbox' or 'production'.
		 *              defaults to 'sandbox'
		 * @param string $currency the currency of the items to be added to the cart
		 *             , as of now values can be 'USD' or 'GBP'.
		 *             defaults to 'USD'
		 */
		function Clover_Cloverpay_Helper_CloverRequest( $id, $key, $server_type = "sandbox", $currency = "USD" ) {
			$this->merchant_id = $id;
			$this->merchant_key = $key;
			$this->currency = $currency;

			if ( strtolower( $server_type ) == "sandbox" ) {
				$this->server_url = "https://api.cloverdev.net/";
			} else {
				$this->server_url = "https://api.clover.com/";
			}

			$this->base_url = $this->server_url . "api/v1/";
			$this->request_url = $this->base_url . "orders";
		}

		function SendAcceptOrder( $orderId, $amount ) {
			$postargs = "amount=" . $amount;
			$url = $this->request_url . '/' . $orderId . '/accept';
			return $this->SendReq( $url, $this->GetAuthenticationHeaders(), $postargs );
		}

		function SendRejectOrder( $orderId ) {
			$postargs = "";
			$url = $this->request_url . '/' . $orderId . '/reject';
			return $this->SendReq( $url, $this->GetAuthenticationHeaders(), $postargs );
		}

		function SendRefundOrder( $orderId ) {
			$postargs = "";
			$url = $this->request_url . '/' . $orderId . '/refund';
			return $this->SendReq( $url, $this->GetAuthenticationHeaders(), $postargs );
		}

		/**
		 * @access private
		 */
		function GetAuthenticationHeaders() {
			$headers = array( 
				"Authorization" => "Basic " . base64_encode( 
					$this->merchant_id . ':' . $this->merchant_key ),
				"Content-Type" => "application/xml; charset=UTF-8",
				"Accept" => "application/xml; charset=UTF-8",
				"User-Agent" => "Clover_PHP_WooCommerce_code ( 1.0/ropu )"
			 );
			return $headers;
		}

		/**
		 * @access private
		 */
		function SendReq( $url, $header_arr, $postargs, $timeout = false ) {
			$response = wp_remote_post( $url, array( 
					'method' => 'POST',
					'timeout' => 15,
					'redirection' => 5,
					'httpversion' => '1.0',
					'blocking' => true,
					'headers' => $header_arr,
					'body' => $postargs,
					'sslverify' => false
				 )
			 );

			if ( is_wp_error( $response ) ) {
				throw new Exception( __( 'There was a problem connecting to the clover payment gateway.', 'woothemes' ) );
			}
			if ( empty( $response['body'] ) ) {
				throw new Exception( __( 'Empty response.', 'woothemes' ) );
			}

			$body = $response['body'];
			$status_code = $response['response']['code'];
			return array( $status_code, $body );
		}

	}

}

/**
 * Add the gateway to WooCommerce
 * */
function add_clover_gateway( $methods ) {
	$methods[] = 'WC_CLOVER';
	return $methods;
}

add_filter( 'woocommerce_payment_gateways', 'add_clover_gateway' );
