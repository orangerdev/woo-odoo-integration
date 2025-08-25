<?php
/**
 * WooCommerce Odoo Integration - Product Sync CLI Command
 *
 * This CLI command allows manual triggering of WooCommerce <-> Odoo product sync (create/update) via WP-CLI.
 *
 * Usage:
 *   wp woo-odoo sync-products [--page=<n>] [--limit=<n>]
 *
 * @package Woo_Odoo_Integration
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * CLI commands for WooCommerce Odoo Integration product sync (create/update).
 */
class Woo_Odoo_Integration_CLI_Product_Sync {
	/**
	 * Manual trigger for product sync from Odoo to WooCommerce (create/update).
	 *
	 * ## OPTIONS
	 *
	 * [--page=<n>]
	 * : Page number for Odoo API pagination (default: 1)
	 *
	 * [--limit=<n>]
	 * : Number of products per page (default: 80)
	 *
	 * ## EXAMPLES
	 *
	 *     wp woo-odoo sync-products --page=1 --limit=100
	 *
	 */
	public function sync_products( $args, $assoc_args ) {
		$page = isset( $assoc_args['page'] ) ? intval( $assoc_args['page'] ) : 1;
		$limit = isset( $assoc_args['limit'] ) ? intval( $assoc_args['limit'] ) : 80;

		WP_CLI::log( sprintf( 'Starting product sync: page=%d, limit=%d', $page, $limit ) );

		if ( ! function_exists( 'woo_odoo_integration_api_get_product_groups' ) ) {
			require_once dirname( __DIR__ ) . '/helper/api.php';
		}
		if ( ! class_exists( 'Woo_Odoo_Integration_Scheduler' ) ) {
			require_once dirname( __DIR__ ) . '/includes/class-woo-odoo-integration-scheduler.php';
		}

		$product_groups = woo_odoo_integration_api_get_product_groups( $page, $limit );
		if ( is_wp_error( $product_groups ) ) {
			WP_CLI::error( 'Failed to fetch product groups from Odoo: ' . $product_groups->get_error_message() );
			return;
		}

		$scheduler = new Woo_Odoo_Integration_Scheduler( 'woo-odoo-integration', WOO_ODOO_INTEGRATION_VERSION );

		// Overwrite logger to also output to WP_CLI
		add_filter( 'woo_odoo_integration_cli_log_product', function ($msg) {
			\WP_CLI::log( $msg );
			return $msg;
		} );

		WP_CLI::log( $product_groups );

		// $results = $scheduler->sync_odoo_products_to_wc( $product_groups );

		WP_CLI::success( sprintf( 'Product sync completed. Created: %d, Updated: %d, Skipped: %d, Errors: %d',
			$results['created'], $results['updated'], $results['skipped'], $results['errors'] ) );
	}
}

WP_CLI::add_command( 'woo-odoo sync-products', [ 'Woo_Odoo_Integration_CLI_Product_Sync', 'sync_products' ] );
