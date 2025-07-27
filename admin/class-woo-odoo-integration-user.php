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
     * Check if guest customer email already exists and has been synced to Odoo
     *
     * This function checks both registered customers and previous guest orders
     * to prevent duplicate customer creation in Odoo for the same email address.
     *
     * @since    1.0.0
     * @access   private
     *
     * @param    string    $email    Email address to check
     *
     * @return   array|false         Customer data if exists and synced, false otherwise
     */
    private function check_guest_customer_exists($email)
    {
        global $wpdb;

        if (empty($email) || !is_email($email)) {
            return false;
        }

        $email = sanitize_email($email);

        // First check if this email belongs to a registered customer
        $user = get_user_by('email', $email);
        if ($user) {
            $odoo_uuid = get_user_meta($user->ID, '_odoo_customer_uuid', true);
            if (!empty($odoo_uuid)) {
                return array(
                    'type' => 'registered',
                    'customer_id' => $user->ID,
                    'email' => $user->user_email,
                    'name' => $user->display_name,
                    'odoo_uuid' => $odoo_uuid
                );
            }
        }

        // Check in wc_customer_lookup table for guest customers
        $customer_lookup = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_id, first_name, last_name, email, date_registered 
             FROM {$wpdb->prefix}wc_customer_lookup 
             WHERE email = %s 
             ORDER BY date_registered DESC 
             LIMIT 1",
            $email
        ));

        if ($customer_lookup && $customer_lookup->user_id == NULL) {
            // This is a guest customer, check if we have previous orders with Odoo sync
            $previous_orders = wc_get_orders(array(
                'billing_email' => $email,
                'customer_id' => 0, // Guest orders only
                'limit' => 1,
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_query' => array(
                    array(
                        'key' => '_odoo_guest_uuid',
                        'compare' => 'EXISTS'
                    )
                )
            ));

            if (!empty($previous_orders)) {
                $previous_order = $previous_orders[0];
                $odoo_uuid = $previous_order->get_meta('_odoo_guest_uuid');

                if (!empty($odoo_uuid)) {
                    return array(
                        'type' => 'guest',
                        'order_id' => $previous_order->get_id(),
                        'email' => $email,
                        'name' => trim($customer_lookup->first_name . ' ' . $customer_lookup->last_name),
                        'odoo_uuid' => $odoo_uuid,
                        'date_registered' => $customer_lookup->date_registered
                    );
                }
            }
        }

        return false;
    }

    /**
     * Add Odoo sync information to order notes
     *
     * This function adds detailed information about Odoo sync status to order notes
     * for easy tracking and debugging in WooCommerce admin.
     *
     * @since    1.0.0
     * @access   private
     *
     * @param    WC_Order    $order        WooCommerce order object
     * @param    string      $sync_type    Type of sync ('guest_success', 'guest_failed', 'guest_duplicate', 'registered_success', 'registered_failed')
     * @param    array       $data         Additional data to include in notes
     *
     * @return   void
     */
    private function add_odoo_sync_order_note($order, $sync_type, $data = array())
    {
        $note_content = '';
        $timestamp = current_time('Y-m-d H:i:s');

        switch ($sync_type) {
            case 'guest_success':
                $note_content = sprintf(
                    '[Odoo Integration] ✅ Guest customer successfully synced to Odoo at %s
📧 Email: %s
👤 Name: %s
🆔 Odoo UUID: %s
📝 Customer Type: Guest',
                    $timestamp,
                    isset($data['email']) ? $data['email'] : 'N/A',
                    isset($data['name']) ? $data['name'] : 'N/A',
                    isset($data['uuid']) ? $data['uuid'] : 'N/A'
                );
                break;

            case 'guest_failed':
                $note_content = sprintf(
                    '[Odoo Integration] ❌ Guest customer sync failed at %s
📧 Email: %s
👤 Name: %s
❗ Error: %s
💡 Recommendation: Check Odoo API connection and retry sync',
                    $timestamp,
                    isset($data['email']) ? $data['email'] : 'N/A',
                    isset($data['name']) ? $data['name'] : 'N/A',
                    isset($data['error']) ? $data['error'] : 'Unknown error'
                );
                break;

            case 'guest_duplicate':
                $existing_info = '';
                if (isset($data['existing_customer'])) {
                    $existing = $data['existing_customer'];
                    $existing_info = sprintf(
                        '🔗 Existing Customer Type: %s
🆔 Existing UUID: %s',
                        ucfirst($existing['type']),
                        isset($existing['odoo_uuid']) ? $existing['odoo_uuid'] : 'N/A'
                    );

                    if ($existing['type'] === 'guest' && isset($existing['order_id'])) {
                        $existing_info .= sprintf(
                            '
📦 Reference Order ID: %s',
                            $existing['order_id']
                        );
                    }
                }

                $note_content = sprintf(
                    '[Odoo Integration] 🔄 Guest customer sync skipped (duplicate) at %s
📧 Email: %s
👤 Name: %s
💭 Reason: Customer with this email already exists and synced to Odoo
%s',
                    $timestamp,
                    isset($data['email']) ? $data['email'] : 'N/A',
                    isset($data['name']) ? $data['name'] : 'N/A',
                    $existing_info
                );
                break;

            case 'registered_success':
                $note_content = sprintf(
                    '[Odoo Integration] ✅ Registered customer successfully synced to Odoo at %s
👤 Customer ID: %s
📧 Email: %s
🆔 Odoo UUID: %s
📝 Customer Type: Registered',
                    $timestamp,
                    isset($data['customer_id']) ? $data['customer_id'] : 'N/A',
                    isset($data['email']) ? $data['email'] : 'N/A',
                    isset($data['uuid']) ? $data['uuid'] : 'N/A'
                );
                break;

            case 'registered_failed':
                $note_content = sprintf(
                    '[Odoo Integration] ❌ Registered customer sync failed at %s
👤 Customer ID: %s
📧 Email: %s
❗ Error: %s
💡 Recommendation: Check customer data and Odoo API connection',
                    $timestamp,
                    isset($data['customer_id']) ? $data['customer_id'] : 'N/A',
                    isset($data['email']) ? $data['email'] : 'N/A',
                    isset($data['error']) ? $data['error'] : 'Unknown error'
                );
                break;

            default:
                $note_content = sprintf(
                    '[Odoo Integration] ℹ️ Sync event logged at %s
Type: %s
Data: %s',
                    $timestamp,
                    $sync_type,
                    json_encode($data)
                );
                break;
        }

        // Add the note to order
        $order->add_order_note($note_content, false, true);
    }

    /**
     * Sync guest customer to Odoo using order data
     *
     * This function handles guest checkout by creating customer in Odoo
     * using the order billing information. It includes duplicate prevention
     * based on email address using WooCommerce customer lookup data.
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

        // Get guest email and check for existing customer
        $guest_email = $order->get_billing_email();
        $guest_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());

        $logger->info(sprintf(
            'Checking for existing customer with email: %s for order %d',
            $guest_email,
            $order->get_id()
        ), array('source' => 'woo-odoo-customer-sync'));

        // Check if customer already exists and has been synced
        $existing_customer = $this->check_guest_customer_exists($guest_email);

        if ($existing_customer !== false) {
            $logger->info(sprintf(
                'Customer with email %s already exists (type: %s, UUID: %s) - skipping creation for order %d',
                $guest_email,
                $existing_customer['type'],
                isset($existing_customer['odoo_uuid']) ? $existing_customer['odoo_uuid'] : 'none',
                $order->get_id()
            ), array('source' => 'woo-odoo-customer-sync'));

            // Store reference to existing customer in order meta
            $order->add_meta_data('_odoo_guest_duplicate_skipped', current_time('timestamp'));
            $order->add_meta_data('_odoo_existing_customer_type', $existing_customer['type']);
            $order->add_meta_data('_odoo_existing_customer_uuid', $existing_customer['odoo_uuid']);

            if ($existing_customer['type'] === 'guest' && isset($existing_customer['order_id'])) {
                $order->add_meta_data('_odoo_reference_order_id', $existing_customer['order_id']);
            }

            // Add order note for duplicate skipped
            $this->add_odoo_sync_order_note($order, 'guest_duplicate', array(
                'email' => $guest_email,
                'name' => $guest_name,
                'existing_customer' => $existing_customer
            ));

            $order->save();

            $logger->info(sprintf('Completed guest customer sync for order ID: %d (duplicate skipped)', $order->get_id()), array('source' => 'woo-odoo-customer-sync'));
            return;
        }

        // Proceed with creating new guest customer
        $guest_data = array(
            'name' => $guest_name,
            'email' => $guest_email,
            'phone' => $order->get_billing_phone(),
            'address' => array(
                'street' => $order->get_billing_address_1(),
                'street2' => $order->get_billing_address_2(),
                'city' => $order->get_billing_city(),
                'zip' => absint($order->get_billing_postcode()),
            ),
        );

        $logger->info(sprintf(
            'Guest customer data prepared for order %d: %s (%s) - no existing customer found',
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

                // Add order note for sync failure
                $this->add_odoo_sync_order_note($order, 'guest_failed', array(
                    'email' => $guest_email,
                    'name' => $guest_name,
                    'error' => $result->get_error_message()
                ));

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

                // Add order note for successful sync
                $this->add_odoo_sync_order_note($order, 'guest_success', array(
                    'email' => $guest_email,
                    'name' => $guest_name,
                    'uuid' => isset($result['uuid']) ? $result['uuid'] : 'N/A'
                ));

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

    /**
     * Display customer Odoo sync status in user profile
     *
     * Shows Odoo integration status, sync time, and UUID in WordPress admin
     * user profile pages for administrators to monitor sync status.
     *
     * @since    1.0.0
     * @access   public
     *
     * @param    WP_User    $user    WordPress user object
     *
     * @return   void
     */
    public function show_customer_odoo_status($user)
    {
        // Only show for users with customer capabilities or administrators
        if (!current_user_can('manage_woocommerce') && !current_user_can('edit_users')) {
            return;
        }

        // Get Odoo sync data
        $odoo_uuid = get_user_meta($user->ID, '_odoo_customer_uuid', true);
        $sync_failed = get_user_meta($user->ID, '_odoo_sync_failed', true);
        $sync_error = get_user_meta($user->ID, '_odoo_sync_error', true);

        ?>
        <h3><?php _e('WooCommerce Odoo Integration', 'woo-odoo-integration'); ?></h3>
        <table class="form-table" role="presentation">
            <tr>
                <th><label><?php _e('Odoo Sync Status', 'woo-odoo-integration'); ?></label></th>
                <td>
                    <?php if (!empty($odoo_uuid)): ?>
                        <span style="color: green;">✓ <?php _e('Synced to Odoo', 'woo-odoo-integration'); ?></span>
                        <br><strong><?php _e('Odoo UUID:', 'woo-odoo-integration'); ?></strong>
                        <code><?php echo esc_html($odoo_uuid); ?></code>
                    <?php elseif (!empty($sync_failed)): ?>
                        <span style="color: red;">✗ <?php _e('Sync Failed', 'woo-odoo-integration'); ?></span>
                        <br><strong><?php _e('Error:', 'woo-odoo-integration'); ?></strong>
                        <?php echo esc_html($sync_error); ?>
                        <br><strong><?php _e('Failed at:', 'woo-odoo-integration'); ?></strong>
                        <?php echo date('Y-m-d H:i:s', $sync_failed); ?>
                    <?php else: ?>
                        <span style="color: orange;">⚠ <?php _e('Not synced yet', 'woo-odoo-integration'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>

            <?php if (!empty($odoo_uuid)): ?>
                <tr>
                    <th><label><?php _e('Guest Orders with Same Email', 'woo-odoo-integration'); ?></label></th>
                    <td>
                        <?php
                        // Check for guest orders with the same email
                        $guest_orders = wc_get_orders(array(
                            'billing_email' => $user->user_email,
                            'customer_id' => 0, // Guest orders only
                            'limit' => 5,
                            'orderby' => 'date',
                            'order' => 'DESC'
                        ));

                        if (!empty($guest_orders)):
                            echo '<p>' . sprintf(__('Found %d guest order(s) with this email:', 'woo-odoo-integration'), count($guest_orders)) . '</p>';
                            echo '<ul>';
                            foreach ($guest_orders as $guest_order):
                                $guest_uuid = $guest_order->get_meta('_odoo_guest_uuid');
                                $duplicate_skipped = $guest_order->get_meta('_odoo_guest_duplicate_skipped');
                                ?>
                                <li>
                                    <strong>Order #<?php echo $guest_order->get_id(); ?></strong>
                                    (<?php echo $guest_order->get_date_created()->format('Y-m-d H:i'); ?>)
                                    <?php if (!empty($guest_uuid)): ?>
                                        - <span style="color: green;">Synced UUID: <?php echo esc_html($guest_uuid); ?></span>
                                    <?php elseif (!empty($duplicate_skipped)): ?>
                                        - <span style="color: blue;">Duplicate skipped</span>
                                    <?php else: ?>
                                        - <span style="color: orange;">Not synced</span>
                                    <?php endif; ?>
                                </li>
                                <?php
                            endforeach;
                            echo '</ul>';
                        else:
                            echo '<p>' . __('No guest orders found with this email.', 'woo-odoo-integration') . '</p>';
                        endif;
                        ?>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
        <?php
    }

    /**
     * Handle guest customer creation failure
     *
     * This function is triggered when guest customer creation fails during checkout.
     * It logs the error and can be extended to notify administrators or retry sync.
     *
     * @since    1.0.0
     * @access   public
     *
     * @param    WP_Error|array    $response    Error response from Odoo API
     * @param    array              $guest_data  Guest customer data attempted to sync
     *
     * @return   void
     */
    public function handle_guest_customer_creation_failed($response, $guest_data)
    {
        // Log the error
        $logger = wc_get_logger();
        $logger->error(sprintf(
            'Failed to create guest customer in Odoo. Response: %s | Guest Data: %s',
            json_encode($response),
            json_encode($guest_data)
        ), array('source' => 'woo-odoo-guest-customer-creation'));
    }

}