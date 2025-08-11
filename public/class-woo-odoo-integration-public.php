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

	public function validate_add_to_cart( $passed, $product_id, $quantity ) {
		// Get cart items
		$cart_items = WC()->cart->get_cart();

		// Current product location attribute value
		$product = wc_get_product( $product_id );
		$location = $product->get_attribute( 'pa_location' );

		do_action( "qm/debug", [ 
			"location" => $location,
			"product" => $product,
		] );

		$cart_items = WC()->cart->get_cart();

		if ( empty( $cart_items ) )
			return $passed;

		// Check if the product's location matches any item in the cart
		foreach ( $cart_items as $cart_item ) {
			$cart_product = wc_get_product( $cart_item['product_id'] );
			$cart_location = $cart_product->get_attribute( 'pa_location' );

			do_action( "qm/debug", [ 
				"cart_location" => $cart_location,
			] );
			if ( $cart_location === $location ) {
				// If a match is found, allow adding to cart
				return $passed;
			} else {
				// If no match is found, prevent adding to cart
				wc_add_notice( __( 'You can only add products from the same location to the cart.', 'woo-odoo-integration' ), 'error' );
				return false;
			}

		}

		// Custom validation logic for adding products to the cart
		return $passed;
	}

}
