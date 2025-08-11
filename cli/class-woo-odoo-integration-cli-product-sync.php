<?php
/**
 * WooCommerce Odoo Integration - Product Sync CLI Command
 *
 * This CLI command allows manual triggering of WooCommerce <-> Odoo product stock sync via WP-CLI.
 *
 * Usage:
 *   wp woo-odoo sync-products [--chunk_size=<n>] [--interval=<minutes>]
 *
 * @package Woo_Odoo_Integration
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * CLI commands for WooCommerce Odoo Integration product sync.
 */
class Woo_Odoo_Integration_CLI_Product_Sync {
	/**
	 * Manual trigger for product stock sync from WooCommerce to Odoo.
	 *
	 * ## OPTIONS
	 *
	 * [--chunk_size=<n>]
	 * : Number of products per batch (default: 10)
	 *
	 * [--interval=<minutes>]
	 * : Interval between batches in minutes (default: 5)
	 *
	 * ## EXAMPLES
	 *
	 *     wp woo-odoo sync-products --chunk_size=20 --interval=2
	 *
	 */
	public function sync_products( $args, $assoc_args ) {
		$chunk_size = isset( $assoc_args['chunk_size'] ) ? intval( $assoc_args['chunk_size'] ) : 10;
		$interval = isset( $assoc_args['interval'] ) ? intval( $assoc_args['interval'] ) : 5;

		WP_CLI::log( sprintf( 'Starting product sync: chunk_size=%d, interval=%d', $chunk_size, $interval ) );

		// Optionally override chunk settings for this run only.
		update_option( 'woo_odoo_auto_sync_chunk_size', $chunk_size );
		update_option( 'woo_odoo_auto_sync_chunk_interval', $interval );

		if ( ! class_exists( 'Woo_Odoo_Integration_Scheduler' ) ) {
			require_once dirname( __DIR__ ) . '/includes/class-woo-odoo-integration-scheduler.php';
		}

		$scheduler = new Woo_Odoo_Integration_Scheduler( 'woo-odoo-integration', WOO_ODOO_INTEGRATION_VERSION );
		$result = $scheduler->force_start_sync();

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		} else {
			WP_CLI::success( 'Product sync triggered successfully.' );
		}
	}
}

WP_CLI::add_command( 'woo-odoo sync-products', [ 'Woo_Odoo_Integration_CLI_Product_Sync', 'sync_products' ] );
