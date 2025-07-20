<?php
/**
 * Example usage of WooCommerce Odoo Integration - Product Stock Sync
 *
 * This file demonstrates how to use the product stock sync functionality
 * programmatically and shows examples of hooks and filters available.
 *
 * @package    Woo_Odoo_Integration
 * @author     Ridwan Arifandi <orangerdigiart@gmail.com>
 * @since      1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Example 1: Programmatic Product Stock Sync
 * 
 * Sync specific product IDs programmatically
 */
function example_sync_specific_products()
{
    // Include API functions if not already loaded
    if (!function_exists('woo_odoo_integration_sync_product_stock')) {
        require_once WOO_ODOO_INTEGRATION_PLUGIN_DIR . 'helper/api.php';
    }

    // Sync specific product IDs
    $product_ids = array(123, 456, 789);
    $sync_results = woo_odoo_integration_sync_product_stock($product_ids);

    if (is_wp_error($sync_results)) {
        error_log('Product sync failed: ' . $sync_results->get_error_message());
        return false;
    }

    // Log results
    error_log(sprintf(
        'Product sync completed. Updated: %d, Skipped: %d, Errors: %d',
        $sync_results['updated'],
        $sync_results['skipped'],
        $sync_results['errors']
    ));

    return $sync_results;
}

/**
 * Example 2: Sync All Products with SKU
 * 
 * Find and sync all products that have SKU set
 */
function example_sync_all_products_with_sku()
{
    $products = wc_get_products(array(
        'limit' => -1,
        'meta_query' => array(
            array(
                'key' => '_sku',
                'value' => '',
                'compare' => '!='
            )
        )
    ));

    $product_ids = array();
    foreach ($products as $product) {
        $product_ids[] = $product->get_id();
    }

    if (!empty($product_ids)) {
        return woo_odoo_integration_sync_product_stock($product_ids);
    }

    return new WP_Error('no_products', 'No products with SKU found');
}

/**
 * Example 3: Scheduled Cron Job for Product Sync
 * 
 * Set up automatic product sync every hour
 */
function example_setup_scheduled_product_sync()
{
    // Schedule event if not already scheduled
    if (!wp_next_scheduled('woo_odoo_hourly_product_sync')) {
        wp_schedule_event(time(), 'hourly', 'woo_odoo_hourly_product_sync');
    }
}
add_action('init', 'example_setup_scheduled_product_sync');

// Hook to handle the scheduled sync
function example_handle_scheduled_product_sync()
{
    // Sync all products
    $sync_results = woo_odoo_integration_sync_product_stock();

    // Log the results
    $logger = wc_get_logger();
    $logger->info(sprintf(
        'Scheduled product sync completed. Updated: %d, Skipped: %d, Errors: %d',
        $sync_results['updated'],
        $sync_results['skipped'],
        $sync_results['errors']
    ), array('source' => 'woo-odoo-scheduled-sync'));
}
add_action('woo_odoo_hourly_product_sync', 'example_handle_scheduled_product_sync');

/**
 * Example 4: Custom Hook Handlers
 * 
 * Examples of how to hook into the sync process
 */

// Before product stock sync
function example_before_product_sync($product_ids)
{
    // Log which products are about to be synced
    error_log('Starting product sync for IDs: ' . implode(', ', $product_ids));

    // Maybe send notification
    wp_mail(
        get_option('admin_email'),
        'Product Sync Started',
        'Starting product stock sync for ' . count($product_ids) . ' products.'
    );
}
add_action('woo_odoo_integration_before_sync_product_stock', 'example_before_product_sync');

// After individual product stock update
function example_after_product_stock_updated($product_id, $old_stock, $new_stock)
{
    $product = wc_get_product($product_id);

    // Log the change
    error_log(sprintf(
        'Product %s (ID: %d) stock updated from %d to %d',
        $product->get_name(),
        $product_id,
        $old_stock,
        $new_stock
    ));

    // If stock went to 0, maybe send low stock alert
    if ($new_stock == 0 && $old_stock > 0) {
        wp_mail(
            get_option('admin_email'),
            'Product Out of Stock',
            sprintf('Product %s is now out of stock.', $product->get_name())
        );
    }
}
add_action('woo_odoo_integration_product_stock_updated', 'example_after_product_stock_updated', 10, 3);

// After complete sync
function example_after_complete_sync($sync_results)
{
    // Send summary email if there were errors
    if ($sync_results['errors'] > 0) {
        wp_mail(
            get_option('admin_email'),
            'Product Sync Errors',
            sprintf(
                'Product sync completed with %d errors. Please check the logs.',
                $sync_results['errors']
            )
        );
    }
}
add_action('woo_odoo_integration_after_sync_product_stock', 'example_after_complete_sync');

/**
 * Example 5: Filter Stock Data Before Processing
 * 
 * Modify stock data from Odoo before it's processed
 */
function example_filter_stock_data($stock_data)
{
    // Example: Apply minimum stock level of 5
    foreach ($stock_data as &$product_group) {
        foreach ($product_group['variants'] as &$variant) {
            if ($variant['quantity'] < 5 && $variant['quantity'] > 0) {
                $variant['quantity'] = 5; // Set minimum stock to 5
            }
        }
    }

    return $stock_data;
}
add_filter('woo_odoo_integration_product_stock_data', 'example_filter_stock_data');

/**
 * Example 6: Custom Admin Notice for Sync Results
 * 
 * Show custom admin notices based on sync results
 */
function example_custom_sync_notice()
{
    // Check if bulk sync was successful
    if (isset($_GET['woo_odoo_sync_success']) && $_GET['woo_odoo_sync_success'] === 'true') {
        $updated = isset($_GET['updated_count']) ? intval($_GET['updated_count']) : 0;

        if ($updated > 0) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong>Great!</strong>
                    Successfully synchronized <?php echo esc_html($updated); ?> product(s) with Odoo.
                    <a href="<?php echo admin_url('edit.php?post_type=product'); ?>">View Products</a>
                </p>
            </div>
            <?php
        }
    }
}
add_action('admin_notices', 'example_custom_sync_notice');

/**
 * Example 7: Validate Product Before Sync
 * 
 * Add custom validation before syncing products
 */
function example_validate_products_before_sync($product_ids)
{
    $valid_products = array();

    foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);

        // Only sync published products with SKU
        if ($product && $product->get_status() === 'publish' && !empty($product->get_sku())) {
            $valid_products[] = $product_id;
        }
    }

    // If no valid products, stop the sync
    if (empty($valid_products)) {
        wp_die('No valid products selected for synchronization.');
    }
}
add_action('woo_odoo_integration_before_sync_product_stock', 'example_validate_products_before_sync');

/**
 * Example 8: REST API Endpoint for External Sync
 * 
 * Create custom REST API endpoint to trigger sync externally
 */
function example_register_sync_endpoint()
{
    register_rest_route('woo-odoo/v1', '/sync-products', array(
        'methods' => 'POST',
        'callback' => 'example_rest_sync_products',
        'permission_callback' => function () {
            return current_user_can('manage_woocommerce');
        }
    ));
}
add_action('rest_api_init', 'example_register_sync_endpoint');

function example_rest_sync_products($request)
{
    $product_ids = $request->get_param('product_ids') ?: array();

    $sync_results = woo_odoo_integration_sync_product_stock($product_ids);

    if (is_wp_error($sync_results)) {
        return new WP_Error('sync_failed', $sync_results->get_error_message(), array('status' => 500));
    }

    return rest_ensure_response(array(
        'success' => true,
        'data' => $sync_results
    ));
}

/**
 * Example 9: WP-CLI Command for Product Sync
 * 
 * Add WP-CLI command to sync products from command line
 */
if (defined('WP_CLI') && WP_CLI) {
    class WOO_Odoo_Product_Sync_CLI
    {

        /**
         * Sync product stock from Odoo
         * 
         * ## EXAMPLES
         * 
         *     wp woo-odoo sync-products
         *     wp woo-odoo sync-products --ids=123,456,789
         * 
         * @param array $args
         * @param array $assoc_args
         */
        public function sync_products($args, $assoc_args)
        {
            $product_ids = array();

            if (isset($assoc_args['ids'])) {
                $product_ids = array_map('intval', explode(',', $assoc_args['ids']));
            }

            WP_CLI::log('Starting product stock sync...');

            $sync_results = woo_odoo_integration_sync_product_stock($product_ids);

            if (is_wp_error($sync_results)) {
                WP_CLI::error('Sync failed: ' . $sync_results->get_error_message());
            }

            WP_CLI::success(sprintf(
                'Sync completed! Updated: %d, Skipped: %d, Errors: %d',
                $sync_results['updated'],
                $sync_results['skipped'],
                $sync_results['errors']
            ));
        }
    }

    WP_CLI::add_command('woo-odoo', 'WOO_Odoo_Product_Sync_CLI');
}

/**
 * Example 10: Debug Function to Test API Connection
 * 
 * Utility function to test Odoo connection and product stock endpoint
 */
function example_test_product_stock_api()
{
    // Test API connection
    $connection_test = woo_odoo_integration_api_test_connection();

    if (is_wp_error($connection_test)) {
        return array(
            'connection' => false,
            'error' => $connection_test->get_error_message()
        );
    }

    // Test product stock endpoint
    $stock_data = woo_odoo_integration_api_get_product_stock();

    if (is_wp_error($stock_data)) {
        return array(
            'connection' => true,
            'product_stock' => false,
            'error' => $stock_data->get_error_message()
        );
    }

    return array(
        'connection' => true,
        'product_stock' => true,
        'products_count' => count($stock_data),
        'sample_data' => isset($stock_data[0]) ? $stock_data[0] : null
    );
}
