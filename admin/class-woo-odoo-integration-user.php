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
        // Validate customer ID
        if (empty($customer_id) || !is_numeric($customer_id)) {
            error_log('WooOdoo Integration: Invalid customer ID provided for Odoo sync');
            return;
        }

        // Check if Odoo integration is enabled
        $is_enabled = carbon_get_theme_option('enable_customer_sync');
        if (empty($is_enabled)) {
            return;
        }

        // Check if customer already synced to avoid duplicates
        $odoo_customer_uuid = get_user_meta($customer_id, '_odoo_customer_uuid', true);
        if (!empty($odoo_customer_uuid)) {
            return; // Already synced
        }

        // Delay sync to ensure all customer data is saved
        wp_schedule_single_event(time() + 30, 'woo_odoo_integration_sync_customer', array($customer_id));
    }

    /**
     * Handle customer checkout completion and sync
     *
     * This function is triggered after WooCommerce order is processed during checkout.
     * It automatically syncs the customer to Odoo ERP system if not already synced.
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
        if (empty($order_id)) {
            return;
        }

        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Get customer ID
        $customer_id = $order->get_customer_id();
        if (empty($customer_id)) {
            return; // Guest checkout
        }

        // Check if customer already synced to avoid duplicates
        $odoo_customer_uuid = get_user_meta($customer_id, '_odoo_customer_uuid', true);
        if (!empty($odoo_customer_uuid)) {
            return; // Already synced
        }

        // Schedule sync with slight delay to ensure order data is complete
        wp_schedule_single_event(time() + 60, 'woo_odoo_integration_sync_customer', array($customer_id));
    }

    /**
     * Handle customer profile updates and sync to Odoo
     *
     * This function is triggered when customer profile is updated in WooCommerce.
     * It automatically updates the customer in Odoo if already synced.
     *
     * @since    1.0.0
     * @access   public
     *
     * @hooks    Triggered by:
     *           - profile_update (WordPress user profile update)
     *           - woocommerce_customer_save (WooCommerce customer save)
     *
     * @param    int      $customer_id    WooCommerce customer ID
     * @param    array    $old_user_data  Previous user data (optional)
     *
     * @return   void
     */
    public function sync_customer_to_odoo_after_update($customer_id, $old_user_data = null)
    {
        // Validate customer ID
        if (empty($customer_id) || !is_numeric($customer_id)) {
            return;
        }

        // Check if Odoo integration is enabled
        $is_enabled = carbon_get_theme_option('enable_customer_sync');
        if (empty($is_enabled)) {
            return;
        }

        // Only sync if customer is already synced to Odoo
        $odoo_customer_uuid = get_user_meta($customer_id, '_odoo_customer_uuid', true);
        if (empty($odoo_customer_uuid)) {
            return; // Not synced yet, skip update
        }

        // Schedule update sync
        wp_schedule_single_event(time() + 30, 'woo_odoo_integration_update_customer', array($customer_id));
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
        // Validate customer ID
        if (empty($customer_id) || !is_numeric($customer_id)) {
            error_log('WooOdoo Integration: Invalid customer ID for scheduled sync');
            return;
        }

        // Check if WooCommerce is active
        if (!class_exists('WC_Customer')) {
            error_log('WooOdoo Integration: WooCommerce not active, cannot sync customer');
            return;
        }

        try {
            // Perform customer sync (create new customer)
            $result = woo_odoo_integration_api_sync_customer($customer_id);

            if (is_wp_error($result)) {
                // Log error for admin review
                error_log(sprintf(
                    'WooOdoo Integration: Failed to sync customer %d to Odoo. Error: %s',
                    $customer_id,
                    $result->get_error_message()
                ));

                // Store sync failure for retry
                update_user_meta($customer_id, '_odoo_sync_failed', current_time('timestamp'));
                update_user_meta($customer_id, '_odoo_sync_error', $result->get_error_message());
            } else {
                // Log success
                error_log(sprintf(
                    'WooOdoo Integration: Successfully synced customer %d to Odoo. UUID: %s',
                    $customer_id,
                    isset($result['uuid']) ? $result['uuid'] : 'unknown'
                ));

                // Clear any previous failure markers
                delete_user_meta($customer_id, '_odoo_sync_failed');
                delete_user_meta($customer_id, '_odoo_sync_error');
            }
        } catch (\Exception $e) {
            error_log(sprintf(
                'WooOdoo Integration: Exception during customer sync %d: %s',
                $customer_id,
                $e->getMessage()
            ));
        }
    }

    /**
     * Update customer in Odoo (scheduled action)
     *
     * This function performs the actual API call to update customer data in Odoo.
     * It's called via WordPress cron to avoid blocking user operations.
     *
     * @since    1.0.0
     * @access   public
     *
     * @param    int    $customer_id    WooCommerce customer ID to update
     *
     * @return   void
     */
    public function update_customer_in_odoo($customer_id)
    {
        // Validate customer ID
        if (empty($customer_id) || !is_numeric($customer_id)) {
            error_log('WooOdoo Integration: Invalid customer ID for scheduled update');
            return;
        }

        // Check if WooCommerce is active
        if (!class_exists('WC_Customer')) {
            error_log('WooOdoo Integration: WooCommerce not active, cannot update customer');
            return;
        }

        try {
            // Perform customer update (force update existing customer)
            $result = woo_odoo_integration_api_sync_customer($customer_id, true);

            if (is_wp_error($result)) {
                // Log error for admin review
                error_log(sprintf(
                    'WooOdoo Integration: Failed to update customer %d in Odoo. Error: %s',
                    $customer_id,
                    $result->get_error_message()
                ));

                // Store update failure for retry
                update_user_meta($customer_id, '_odoo_sync_failed', current_time('timestamp'));
                update_user_meta($customer_id, '_odoo_sync_error', $result->get_error_message());
            } else {
                // Log success
                error_log(sprintf(
                    'WooOdoo Integration: Successfully updated customer %d in Odoo. UUID: %s',
                    $customer_id,
                    isset($result['uuid']) ? $result['uuid'] : 'unknown'
                ));

                // Clear any previous failure markers
                delete_user_meta($customer_id, '_odoo_sync_failed');
                delete_user_meta($customer_id, '_odoo_sync_error');
            }
        } catch (\Exception $e) {
            error_log(sprintf(
                'WooOdoo Integration: Exception during customer update %d: %s',
                $customer_id,
                $e->getMessage()
            ));
        }
    }

    /**
     * Handle order completion and customer sync
     *
     * When an order is completed, ensure the customer is synced to Odoo.
     * This covers cases where customers might not have been synced during registration.
     *
     * @since    1.0.0
     * @access   public
     *
     * @hooks    Triggered by:
     *           - woocommerce_order_status_completed
     *           - woocommerce_payment_complete
     *
     * @param    int    $order_id    WooCommerce order ID
     *
     * @return   void
     */
    public function sync_customer_on_order_complete($order_id)
    {
        if (empty($order_id)) {
            return;
        }

        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Get customer ID
        $customer_id = $order->get_customer_id();
        if (empty($customer_id)) {
            return; // Guest checkout
        }

        // Check if customer is already synced
        $odoo_customer_uuid = get_user_meta($customer_id, '_odoo_customer_uuid', true);
        if (!empty($odoo_customer_uuid)) {
            return; // Already synced
        }

        // Schedule sync
        wp_schedule_single_event(time() + 30, 'woo_odoo_integration_sync_customer', array($customer_id));
    }

    /**
     * Add customer sync status to WooCommerce customer admin
     *
     * Displays Odoo sync status in the WordPress admin user profile.
     *
     * @since    1.0.0
     * @access   public
     *
     * @param    \WP_User    $user    WordPress user object
     *
     * @return   void
     */
    public function show_customer_odoo_status($user)
    {
        // Only show for customers (users with customer role or who have made orders)
        if (!in_array('customer', $user->roles) && !wc_customer_bought_product('', $user->ID)) {
            return;
        }

        $odoo_customer_uuid = get_user_meta($user->ID, '_odoo_customer_uuid', true);
        $last_sync = get_user_meta($user->ID, '_odoo_last_sync', true);
        $sync_failed = get_user_meta($user->ID, '_odoo_sync_failed', true);
        $sync_error = get_user_meta($user->ID, '_odoo_sync_error', true);

        ?>
        <h3><?php esc_html_e('Odoo Integration Status', 'woo-odoo-integration'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label><?php esc_html_e('Odoo Customer UUID', 'woo-odoo-integration'); ?></label></th>
                <td>
                    <?php if (!empty($odoo_customer_uuid)): ?>
                        <code><?php echo esc_html($odoo_customer_uuid); ?></code>
                        <p class="description" style="color: green;">
                            <?php esc_html_e('Customer is synced with Odoo', 'woo-odoo-integration'); ?>
                        </p>
                    <?php else: ?>
                        <span style="color: orange;"><?php esc_html_e('Not synced', 'woo-odoo-integration'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if (!empty($last_sync)): ?>
                <tr>
                    <th><label><?php esc_html_e('Last Sync', 'woo-odoo-integration'); ?></label></th>
                    <td><?php echo esc_html(date('Y-m-d H:i:s', $last_sync)); ?></td>
                </tr>
            <?php endif; ?>
            <?php if (!empty($sync_failed)): ?>
                <tr>
                    <th><label><?php esc_html_e('Sync Failed', 'woo-odoo-integration'); ?></label></th>
                    <td>
                        <span style="color: red;"><?php echo esc_html(date('Y-m-d H:i:s', $sync_failed)); ?></span>
                        <?php if (!empty($sync_error)): ?>
                            <p class="description" style="color: red;">
                                <?php echo esc_html($sync_error); ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
        </table>

        <?php if (!empty($odoo_customer_uuid)): ?>
            <p>
                <button type="button" class="button" id="woo-odoo-resync-customer"
                    data-customer-id="<?php echo esc_attr($user->ID); ?>">
                    <?php esc_html_e('Re-sync to Odoo', 'woo-odoo-integration'); ?>
                </button>
            </p>
        <?php endif; ?>
    <?php
    }

    /**
     * Handle manual customer re-sync via AJAX
     *
     * Allows admin users to manually trigger customer sync from the user profile page.
     *
     * @since    1.0.0
     * @access   public
     *
     * @return   void
     */
    public function handle_manual_customer_sync()
    {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'woo_odoo_customer_sync')) {
            wp_die(__('Security check failed', 'woo-odoo-integration'));
        }

        // Check capabilities
        if (!current_user_can('edit_users')) {
            wp_die(__('Insufficient permissions', 'woo-odoo-integration'));
        }

        $customer_id = intval($_POST['customer_id']);
        if (empty($customer_id)) {
            wp_send_json_error(__('Invalid customer ID', 'woo-odoo-integration'));
        }

        // Perform sync with force update
        $result = woo_odoo_integration_api_sync_customer($customer_id, true);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success([
                'message' => __('Customer successfully synced to Odoo', 'woo-odoo-integration'),
                'uuid' => isset($result['uuid']) ? $result['uuid'] : ''
            ]);
        }
    }

    /**
     * Enqueue admin scripts and styles for user management
     *
     * Loads JavaScript and CSS files needed for the admin user interface,
     * including customer sync functionality.
     *
     * @since    1.0.0
     * @access   public
     *
     * @param    string    $hook    Current admin page hook
     *
     * @return   void
     */
    public function enqueue_admin_scripts($hook)
    {
        // Only load on user profile pages
        if (!in_array($hook, ['profile.php', 'user-edit.php', 'users.php'])) {
            return;
        }

        // Enqueue JavaScript
        wp_enqueue_script(
            $this->plugin_name . '-user',
            WOO_ODOO_INTEGRATION_PLUGIN_URL . 'admin/js/woo-odoo-integration-user.js',
            array('jquery'),
            $this->version,
            true
        );

        // Localize script with admin data
        wp_localize_script(
            $this->plugin_name . '-user',
            'woo_odoo_admin',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('woo_odoo_customer_sync'),
                'syncing_text' => __('Syncing...', 'woo-odoo-integration'),
                'resync_text' => __('Re-sync to Odoo', 'woo-odoo-integration'),
                'synced_text' => __('Customer is synced with Odoo', 'woo-odoo-integration'),
                'error_text' => __('Sync failed. Please try again.', 'woo-odoo-integration'),
                'bulk_sync_text' => __('Bulk Sync to Odoo', 'woo-odoo-integration'),
                'bulk_syncing_text' => __('Syncing customers...', 'woo-odoo-integration'),
                'bulk_sync_confirm' => __('Are you sure you want to sync %d customers to Odoo?', 'woo-odoo-integration'),
                'bulk_sync_complete' => __('Bulk sync completed!', 'woo-odoo-integration'),
                'no_customers_selected' => __('Please select customers to sync.', 'woo-odoo-integration'),
            )
        );

        // Enqueue CSS if needed
        wp_enqueue_style(
            $this->plugin_name . '-user',
            WOO_ODOO_INTEGRATION_PLUGIN_URL . 'admin/css/woo-odoo-integration-user.css',
            array(),
            $this->version
        );
    }

    /**
     * Add admin notice for sync failures
     *
     * Displays notices for customers that failed to sync to Odoo,
     * allowing administrators to take corrective action.
     *
     * @since    1.0.0
     * @access   public
     *
     * @return   void
     */
    public function show_sync_failure_notices()
    {
        // Only show on admin pages
        if (!is_admin()) {
            return;
        }

        // Check if user can manage options
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check for failed syncs in the last 24 hours
        $failed_syncs = get_users(array(
            'meta_query' => array(
                array(
                    'key' => '_odoo_sync_failed',
                    'value' => time() - (24 * 60 * 60), // 24 hours in seconds
                    'compare' => '>'
                )
            ),
            'fields' => array('ID', 'display_name')
        ));

        if (!empty($failed_syncs)) {
            ?>
            <div class="notice notice-warning woo-odoo-notice" data-notice-id="sync-failures">
                <p>
                    <strong><?php esc_html_e('WooCommerce Odoo Integration:', 'woo-odoo-integration'); ?></strong>
                    <?php
                    printf(
                        esc_html__('%d customer(s) failed to sync to Odoo in the last 24 hours.', 'woo-odoo-integration'),
                        count($failed_syncs)
                    );
                    ?>
                    <a href="<?php echo esc_url(admin_url('users.php')); ?>">
                        <?php esc_html_e('Review failed syncs', 'woo-odoo-integration'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Add bulk action for customer sync
     *
     * Adds a bulk action option to the Users admin page for syncing
     * multiple customers to Odoo at once.
     *
     * @since    1.0.0
     * @access   public
     *
     * @param    array    $bulk_actions    Current bulk actions
     *
     * @return   array    Modified bulk actions array
     */
    public function add_bulk_customer_sync_action($bulk_actions)
    {
        $bulk_actions['woo_odoo_sync'] = __('Sync to Odoo', 'woo-odoo-integration');
        return $bulk_actions;
    }

    /**
     * Handle bulk customer sync action
     *
     * Processes the bulk sync action when triggered from the Users admin page.
     *
     * @since    1.0.0
     * @access   public
     *
     * @param    string    $redirect_to    Redirect URL
     * @param    string    $doaction       Action being performed
     * @param    array     $user_ids       Selected user IDs
     *
     * @return   string    Modified redirect URL with results
     */
    public function handle_bulk_customer_sync($redirect_to, $doaction, $user_ids)
    {
        if ($doaction !== 'woo_odoo_sync') {
            return $redirect_to;
        }

        $synced = 0;
        $failed = 0;

        foreach ($user_ids as $user_id) {
            $result = woo_odoo_integration_api_sync_customer($user_id, true);

            if (is_wp_error($result)) {
                $failed++;
            } else {
                $synced++;
            }
        }

        $redirect_to = add_query_arg(array(
            'woo_odoo_bulk_sync' => true,
            'synced' => $synced,
            'failed' => $failed
        ), $redirect_to);

        return $redirect_to;
    }

    /**
     * Display bulk sync results notice
     *
     * Shows results after bulk customer sync operation is completed.
     *
     * @since    1.0.0
     * @access   public
     *
     * @return   void
     */
    public function show_bulk_sync_notice()
    {
        if (!isset($_REQUEST['woo_odoo_bulk_sync'])) {
            return;
        }

        $synced = intval($_REQUEST['synced']);
        $failed = intval($_REQUEST['failed']);

        $class = $failed > 0 ? 'notice-warning' : 'notice-success';
        ?>
        <div class="notice <?php echo esc_attr($class); ?> is-dismissible">
            <p>
                <?php
                printf(
                    esc_html__('Bulk customer sync completed: %d synced, %d failed.', 'woo-odoo-integration'),
                    $synced,
                    $failed
                );
                ?>
            </p>
        </div>
        <?php
    }

}