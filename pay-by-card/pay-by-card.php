<?php
/**
 * Plugin Name: WooCommerce Pay By Card Gateway
 * Plugin URI: http://elephantation.com
 * Description: Developed a custom payment gateway for pay by card.
 * Author: Naveed Abbas
 * Author URI: http://elephantation.com
 * Version: 1.0
 **/
 
 
defined( 'ABSPATH' ) or exit;


// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}


/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + pay by card gateway
 */
function wc_paybycard_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_PayBy_Card';
	return $gateways;
}

add_filter( 'woocommerce_payment_gateways', 'wc_paybycard_add_to_gateways' );


/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_paybycard_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paybycard_gateway' ) . '">' . __( 'Configure', 'wc-gateway-paybycard' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_paybycard_gateway_plugin_links' );


/**
 * Offline Payment Gateway
 *
 * Provides an Offline Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_PayBy_Card
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		SkyVerge
 */
add_action( 'plugins_loaded', 'wc_paybycard_gateway_init', 11 );

function wc_paybycard_gateway_init() {

	class WC_PayBy_Card extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
	  
			$this->id                 = 'paybycard_gateway';
			$this->icon               = apply_filters('woocommerce_paybycard_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __( 'Pay By Card', 'wc-gateway-paybycard' );
			$this->method_description = __( 'Allows pay by card payments. Very handy if you use your COD gateway for another payment method, and can help with testing. Orders are marked as "processing" when received.', 'wc-gateway-paybycard' );
		  
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
		  
			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description );
		  
			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		  
			// Customer Emails
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		}
	
	
		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
	  
			$this->form_fields = apply_filters( 'wc_paybycard_form_fields', array(
		  
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'wc-gateway-paybycard' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Pay By card Payment', 'wc-gateway-paybycard' ),
					'default' => 'yes'
				),
				
				'title' => array(
					'title'       => __( 'Title', 'wc-gateway-paybycard' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-paybycard' ),
					'default'     => __( 'Pay By Card', 'wc-gateway-paybycard' ),
					'desc_tip'    => true,
				),
				
				'description' => array(
					'title'       => __( 'Description', 'wc-gateway-paybycard' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-gateway-paybycard' ),
					'default'     => __( '', 'wc-gateway-paybycard' ),
					'desc_tip'    => true,
				),
				
				'instructions' => array(
					'title'       => __( 'Instructions', 'wc-gateway-paybycard' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page and emails.', 'wc-gateway-paybycard' ),
					'default'     => '',
					'desc_tip'    => true,
				),
			) );
		}
	
	
		/**
		 * Output for the order received page.
		 */
		public function thankyou_page() {
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) );
			}
		}
	
	
		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		
			if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'wc-pending' ) ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
		}
	
	
		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {
	
			$order = wc_get_order( $order_id );
			
			// Mark as pending (we're awaiting the payment)
			$order->update_status( 'wc-processing', __( 'Payment to be made upon delivery by card', 'wc-gateway-paybycard' ) );
			
			// Reduce stock levels
			$order->reduce_order_stock();
			
			// Remove cart
			WC()->cart->empty_cart();
			
			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url( $order )
			);
		}
	
  } // end \WC_PayBy_Card class
}