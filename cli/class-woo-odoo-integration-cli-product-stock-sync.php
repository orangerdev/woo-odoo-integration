<?php
/**
 * WooCommerce Odoo Integration - Product Stock Sync CLI Command
 *
 * This CLI command allows manual triggering of WooCommerce <-> Odoo product stock sync via WP-CLI.
 *
 * Usage:
 *   wp woo-odoo sync-product-stock [--chunk_size=<n>] [--interval=<minutes>]
 *
 * @package Woo_Odoo_Integration
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * CLI commands for WooCommerce Odoo Integration product stock sync.
 */
class Woo_Odoo_Integration_CLI_Product_Stock_Sync {
	/**
	 * Manual trigger for product stock sync from Odoo to WooCommerce.
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
	 *     wp woo-odoo sync-product-stock --chunk_size=20 --interval=2
	 *
	 */
	public function sync_product_stock( $args, $assoc_args ) {
		$chunk_size = isset( $assoc_args['chunk_size'] ) ? intval( $assoc_args['chunk_size'] ) : 10;
		$interval = isset( $assoc_args['interval'] ) ? intval( $assoc_args['interval'] ) : 5;

		WP_CLI::log( sprintf( 'Starting product stock sync: chunk_size=%d, interval=%d', $chunk_size, $interval ) );

		// Optionally override chunk settings for this run only.
		update_option( 'woo_odoo_auto_sync_chunk_size', $chunk_size );
		update_option( 'woo_odoo_auto_sync_chunk_interval', $interval );

		if ( ! class_exists( 'Woo_Odoo_Integration_Scheduler' ) ) {
			require_once dirname( __DIR__ ) . '/includes/class-woo-odoo-integration-scheduler.php';
		}

		$scheduler = new Woo_Odoo_Integration_Scheduler( 'woo-odoo-integration', WOO_ODOO_INTEGRATION_VERSION );

		// Terminate sync in progress if any
		$current_status = $scheduler->get_sync_status();
		if ( $current_status && isset( $current_status['status'] ) && $current_status['status'] === 'in_progress' ) {
			WP_CLI::warning( 'A sync is currently in progress. Terminating previous sync...' );
			$scheduler->clear_sync_queue();
		}

		// Overwrite logger to also output to WP_CLI
		add_filter( 'woo_odoo_integration_cli_log_product', function ($msg) {
			\WP_CLI::log( $msg );
			return $msg;
		} );

		$result = $scheduler->force_start_sync( true );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		} else {
			WP_CLI::success( 'Product stock sync triggered successfully.' );
		}
	}
}

WP_CLI::add_command( 'woo-odoo sync-product-stock', [ 'Woo_Odoo_Integration_CLI_Product_Stock_Sync', 'sync_product_stock' ] );
