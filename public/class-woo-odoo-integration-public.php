<?php

namespace Woo_Odoo_Integration;

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://ridwan-arifandi.com
 * @since      1.0.0
 *
 * @package    Woo_Odoo_Integration
 * @subpackage Woo_Odoo_Integration/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Woo_Odoo_Integration
 * @subpackage Woo_Odoo_Integration/public
 * @author     Ridwan Arifandi <orangerdigiart@gmail.com>
 */
class Front {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	public function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variations = [] ) {

	    $get_att_location = carbon_get_theme_option('odoo_location_att');

	    if (isset($_REQUEST['variation_id']) && $_REQUEST['variation_id']) :

	        $variation_id = intval($_REQUEST['variation_id']);
	        $variation = wc_get_product($variation_id);
	        
	        $location = $variation->get_attribute($get_att_location);
	        $location = trim($location);

	        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) :

	            $cart_product = $cart_item['data'];
	            $cart_location = $cart_product->get_attribute($get_att_location);
	            $cart_location = trim($cart_location);

	            if ($cart_location && $cart_location !== $location) :
	                
	                wc_add_notice('You can only add products from the same location to the cart.', 'error');
	                
	                return false;

	            endif;

	        endforeach;

	    endif;

	    return $passed;

	}

	public function woo_odoo_integration_checkout_send_order( $order_id ) {

		$result = woo_odoo_integration_api_send_order( $order_id );

		if ( is_wp_error( $result ) ) {
			error_log( 'Odoo Order Sync Failed: ' . $result->get_error_message() );
		} else {
			error_log( 'Odoo Order Sync Success: ' . print_r( $result, true ) );
		}
		
	}

}
