<?php

namespace Woo_Odoo_Integration\Admin;

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://ridwan-arifandi.com
 * @since      1.0.0
 *
 * @package    Woo_Odoo_Integration
 * @subpackage Woo_Odoo_Integration/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Handles all WordPress admin area functionality including Carbon Fields
 * initialization, admin styles/scripts enqueuing, and admin-specific
 * features for the WooCommerce Odoo Integration plugin.
 *
 * @since      1.0.0
 * @package    Woo_Odoo_Integration
 * @subpackage Woo_Odoo_Integration/Admin
 * @author     Ridwan Arifandi <orangerdigiart@gmail.com>
 *
 * @hooks      WordPress hooks this class interacts with:
 *             - after_setup_theme (for Carbon Fields initialization)
 *             - admin_enqueue_scripts (for loading admin styles and scripts)
 */
class User
{

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
     * Sets up the admin class with plugin name and version information.
     * These properties are used throughout the class for identifying
     * the plugin in WordPress admin context.
     *
     * @since    1.0.0
     * @access   public
     *
     * @param    string    $plugin_name    The name of this plugin
     * @param    string    $version        The version of this plugin
     *
     * @return   void
     */
    public function __construct($plugin_name, $version)
    {

        $this->plugin_name = $plugin_name;
        $this->version = $version;

    }

    /**
     * Handle customer registration after WooCommerce checkout
     *
     * This function is triggered when a customer completes checkout and creates
     * an account. It automatically syncs the new customer to Odoo ERP system.
     *
     * @since    1.0.0
     * @access   public
     *
     * @hooks    Triggered by:
     *           - woocommerce_created_customer (after customer account creation)
     *           - woocommerce_checkout_create_order_customer (after customer creation during checkout)
     *
     * @param    int      $customer_id    WooCommerce customer ID
     * @param    array    $new_data       Customer data (optional)
     * @param    string   $password_generated    Whether password was auto-generated (optional)
     *
     * @return   void
     */
    public function sync_customer_to_odoo_after_registration($customer_id, $new_data = array(), $password_generated = '')
    {
        // Add immediate logging to track function calls
        $logger = \wc_get_logger();
        $logger->info(sprintf('sync_customer_to_odoo_after_registration called with customer ID: %d', $customer_id), array('source' => 'woo-odoo-customer-sync'));

        // Validate customer ID
        if (empty($customer_id) || !is_numeric($customer_id)) {
            $logger->error('Invalid customer ID provided for Odoo sync', array('source' => 'woo-odoo-customer-sync'));
            return;
        }

        // Check if Odoo integration is enabled
        $is_enabled = carbon_get_theme_option('enable_customer_sync');
        if (empty($is_enabled)) {
            $logger->info('Customer sync is disabled in settings', array('source' => 'woo-odoo-customer-sync'));
            return;
        }

        // Check if customer already synced to avoid duplicates
        $odoo_customer_uuid = get_user_meta($customer_id, '_odoo_customer_uuid', true);
        if (!empty($odoo_customer_uuid)) {
            $logger->info(sprintf('Customer %d already synced with UUID: %s', $customer_id, $odoo_customer_uuid), array('source' => 'woo-odoo-customer-sync'));
            return; // Already synced
        }

        $logger->info(sprintf('Scheduling customer sync for new customer ID %d', $customer_id), array('source' => 'woo-odoo-customer-sync'));

        // Delay sync to ensure all customer data is saved
        wp_schedule_single_event(time() + 10, 'woo_odoo_integration_sync_customer', array($customer_id));

        // Also try immediate sync as fallback (if cron doesn't work)
        $this->sync_customer_to_odoo($customer_id);
    }

    /**
     * Handle customer checkout completion and sync
     *
     * This function is triggered after WooCommerce order is processed during checkout.
     * It automatically syncs all customers (registered and guest) to Odoo ERP system.
     *
     * @since    1.0.0
     * @access   public
     *
     * @hooks    Triggered by:
     *           - woocommerce_checkout_order_processed (after checkout completion)
     *
     * @param    int      $order_id    WooCommerce order ID
     *
     * @return   void
     */
    public function sync_customer_to_odoo_after_checkout($order_id)
    {
        // Add immediate logging to track function calls
        $logger = \wc_get_logger();
        $logger->info(sprintf('sync_customer_to_odoo_after_checkout called with order ID: %d', $order_id), array('source' => 'woo-odoo-customer-sync'));

        if (empty($order_id)) {
            $logger->warning('Empty order ID provided to sync_customer_to_odoo_after_checkout', array('source' => 'woo-odoo-customer-sync'));
            return;
        }

        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            $logger->error(sprintf('Order %d not found', $order_id), array('source' => 'woo-odoo-customer-sync'));
            return;
        }

        // Check if Odoo integration is enabled
        $is_enabled = carbon_get_theme_option('enable_customer_sync');
        if (empty($is_enabled)) {
            $logger->info('Customer sync is disabled in settings', array('source' => 'woo-odoo-customer-sync'));
            return;
        }

        // Get customer ID (registered user) or prepare guest data
        $customer_id = $order->get_customer_id();

        if (!empty($customer_id)) {
            // Registered customer
            $logger->info(sprintf('Processing registered customer sync for customer ID %d from order %d', $customer_id, $order_id), array('source' => 'woo-odoo-customer-sync'));

            // Check if customer already synced to avoid duplicates
            $odoo_customer_uuid = get_user_meta($customer_id, '_odoo_customer_uuid', true);
            if (!empty($odoo_customer_uuid)) {
                $logger->info(sprintf('Customer %d already synced with UUID: %s', $customer_id, $odoo_customer_uuid), array('source' => 'woo-odoo-customer-sync'));
                return; // Already synced
            }

            $logger->info(sprintf('Scheduling customer sync for registered customer ID %d', $customer_id), array('source' => 'woo-odoo-customer-sync'));

            // Schedule sync with slight delay to ensure order data is complete
            wp_schedule_single_event(time() + 10, 'woo_odoo_integration_sync_customer', array($customer_id));

            // Also try immediate sync as fallback (if cron doesn't work)
            $this->sync_customer_to_odoo($customer_id);
        } else {
            // Guest checkout - sync guest customer data
            $logger->info(sprintf('Processing guest customer sync for order %d', $order_id), array('source' => 'woo-odoo-customer-sync'));

            // Directly sync guest customer
            $this->sync_guest_customer_to_odoo($order);
        }
    }

    /**
     * Sync guest customer to Odoo using order data
     *
     * This function handles guest checkout by creating customer in Odoo
     * using the order billing information.
     *
     * @since    1.0.0
     * @access   public
     *
     * @param    WC_Order    $order    WooCommerce order object
     *
     * @return   void
     */
    public function sync_guest_customer_to_odoo($order)
    {
        $logger = \wc_get_logger();
        $logger->info(sprintf('Starting guest customer sync for order ID: %d', $order->get_id()), array('source' => 'woo-odoo-customer-sync'));

        // Check if API helper functions exist
        if (!function_exists('woo_odoo_integration_api_create_guest_customer')) {
            $logger->error('API helper function woo_odoo_integration_api_create_guest_customer not found', array('source' => 'woo-odoo-customer-sync'));
            return;
        }

        // Extract guest customer data from order
        $guest_data = array(
            'name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
            'address' => array(
                'street' => $order->get_billing_address_1(),
                'street2' => $order->get_billing_address_2(),
                'city' => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
                'zip' => $order->get_billing_postcode(),
                'country' => $order->get_billing_country()
            ),
            'order_id' => $order->get_id(),
            'is_guest' => true
        );

        $logger->info(sprintf(
            'Guest customer data prepared for order %d: %s (%s)',
            $order->get_id(),
            $guest_data['name'],
            $guest_data['email']
        ), array('source' => 'woo-odoo-customer-sync'));

        try {
            // Log the API call details before making the request
            $logger->info(sprintf(
                'Making API call to create guest customer for order %d - Endpoint: api/customers | Request Data: %s',
                $order->get_id(),
                json_encode(woo_odoo_integration_mask_sensitive_data($guest_data))
            ), array('source' => 'woo-odoo-customer-sync'));

            // Create guest customer in Odoo
            $result = woo_odoo_integration_api_create_guest_customer($guest_data);

            if (is_wp_error($result)) {
                $logger->error(sprintf(
                    'Failed to sync guest customer for order %d. Error: %s | Endpoint: api/customers | Request Data: %s | Response: %s',
                    $order->get_id(),
                    $result->get_error_message(),
                    json_encode(woo_odoo_integration_mask_sensitive_data($guest_data)),
                    json_encode(array(
                        'error_code' => $result->get_error_code(),
                        'error_message' => $result->get_error_message(),
                        'error_data' => $result->get_error_data()
                    ))
                ), array('source' => 'woo-odoo-customer-sync'));

                // Store failure in order meta
                $order->add_meta_data('_odoo_guest_sync_failed', current_time('timestamp'));
                $order->add_meta_data('_odoo_guest_sync_error', $result->get_error_message());
                $order->save();
            } else {
                $logger->info(sprintf(
                    'Successfully synced guest customer for order %d. Customer UUID: %s | Endpoint: api/customers | Request Data: %s | Response: %s',
                    $order->get_id(),
                    isset($result['uuid']) ? $result['uuid'] : 'unknown',
                    json_encode(woo_odoo_integration_mask_sensitive_data($guest_data)),
                    json_encode(woo_odoo_integration_mask_sensitive_data($result))
                ), array('source' => 'woo-odoo-customer-sync'));

                // Store success in order meta
                $order->add_meta_data('_odoo_guest_uuid', isset($result['uuid']) ? $result['uuid'] : '');
                $order->add_meta_data('_odoo_guest_sync_time', current_time('timestamp'));
                $order->save();
            }
        } catch (\Exception $e) {
            $logger->error(sprintf(
                'Exception during guest customer sync for order %d. Exception: %s | Endpoint: api/customers | Request Data: %s',
                $order->get_id(),
                $e->getMessage(),
                json_encode(woo_odoo_integration_mask_sensitive_data($guest_data))
            ), array('source' => 'woo-odoo-customer-sync'));
        }

        $logger->info(sprintf('Completed guest customer sync for order ID: %d', $order->get_id()), array('source' => 'woo-odoo-customer-sync'));
    }

    /**
     * Sync customer to Odoo (scheduled action)
     *
     * This function performs the actual API call to sync customer data to Odoo.
     * It's called via WordPress cron to avoid blocking the checkout process.
     *
     * @since    1.0.0
     * @access   public
     *
     * @param    int    $customer_id    WooCommerce customer ID to sync
     *
     * @return   void
     */
    public function sync_customer_to_odoo($customer_id)
    {
        // Start with comprehensive logging
        $logger = \wc_get_logger();
        $logger->info(sprintf('Starting sync_customer_to_odoo for customer ID: %d', $customer_id), array('source' => 'woo-odoo-customer-sync'));

        // Validate customer ID
        if (empty($customer_id) || !is_numeric($customer_id)) {
            $logger->error('Invalid customer ID for scheduled sync', array('source' => 'woo-odoo-customer-sync'));
            return;
        }

        // Check if WooCommerce is active
        if (!class_exists('WC_Customer')) {
            $logger->error('WooCommerce not active, cannot sync customer', array('source' => 'woo-odoo-customer-sync'));
            return;
        }

        // Check if API helper functions exist
        if (!function_exists('woo_odoo_integration_api_sync_customer')) {
            $logger->error('API helper function woo_odoo_integration_api_sync_customer not found', array('source' => 'woo-odoo-customer-sync'));
            return;
        }

        // Check if customer exists in WordPress
        $user = get_user_by('id', $customer_id);
        if (!$user) {
            $logger->error(sprintf('Customer %d not found in WordPress', $customer_id), array('source' => 'woo-odoo-customer-sync'));
            return;
        }

        $logger->info(sprintf('Customer %d found, proceeding with sync', $customer_id), array('source' => 'woo-odoo-customer-sync'));

        try {
            // Perform customer sync (create new customer)
            $logger->info(sprintf('Calling woo_odoo_integration_api_sync_customer for customer %d', $customer_id), array('source' => 'woo-odoo-customer-sync'));

            // Get customer data for logging
            $customer_data = array(
                'customer_id' => $customer_id,
                'email' => $user->user_email,
                'display_name' => $user->display_name
            );

            $logger->info(sprintf(
                'Making API call to sync customer %d - Endpoint: api/customers | Request Data: %s',
                $customer_id,
                json_encode(woo_odoo_integration_mask_sensitive_data($customer_data))
            ), array('source' => 'woo-odoo-customer-sync'));

            $result = woo_odoo_integration_api_sync_customer($customer_id);

            if (is_wp_error($result)) {
                // Log error for admin review
                $logger->error(sprintf(
                    'Failed to sync customer %d to Odoo. Error: %s | Endpoint: api/customers | Request Data: %s | Response: %s',
                    $customer_id,
                    $result->get_error_message(),
                    json_encode(woo_odoo_integration_mask_sensitive_data($customer_data)),
                    json_encode(array(
                        'error_code' => $result->get_error_code(),
                        'error_message' => $result->get_error_message(),
                        'error_data' => $result->get_error_data()
                    ))
                ), array('source' => 'woo-odoo-customer-sync'));

                // Store sync failure for retry
                update_user_meta($customer_id, '_odoo_sync_failed', current_time('timestamp'));
                update_user_meta($customer_id, '_odoo_sync_error', $result->get_error_message());
            } else {
                // Log success
                $logger->info(sprintf(
                    'Successfully synced customer %d to Odoo. UUID: %s | Endpoint: api/customers | Request Data: %s | Response: %s',
                    $customer_id,
                    isset($result['uuid']) ? $result['uuid'] : 'unknown',
                    json_encode(woo_odoo_integration_mask_sensitive_data($customer_data)),
                    json_encode(woo_odoo_integration_mask_sensitive_data($result))
                ), array('source' => 'woo-odoo-customer-sync'));

                // Clear any previous failure markers
                delete_user_meta($customer_id, '_odoo_sync_failed');
                delete_user_meta($customer_id, '_odoo_sync_error');
            }
        } catch (\Exception $e) {
            $logger->error(sprintf(
                'Exception during customer sync %d: %s. Stack trace: %s',
                $customer_id,
                $e->getMessage(),
                $e->getTraceAsString()
            ), array('source' => 'woo-odoo-customer-sync'));
        }

        $logger->info(sprintf('Completed sync_customer_to_odoo for customer ID: %d', $customer_id), array('source' => 'woo-odoo-customer-sync'));
    }

}