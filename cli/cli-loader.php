<?php
/**
 * WooCommerce Odoo Integration CLI Loader
 *
 * Loads all CLI commands for the plugin.
 * Place this file in the cli/ directory and require it from your main plugin file if WP_CLI is defined.
 */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/class-woo-odoo-integration-cli-product-stock-sync.php';
	require_once __DIR__ . '/class-woo-odoo-integration-cli-product-sync.php';
}
