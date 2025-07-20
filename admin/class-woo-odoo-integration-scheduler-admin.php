<?php


/**
 * Admin interface for automatic product sync scheduler
 *
 * Provides admin interface to monitor and control automatic product sync process,
 * including status display, manual trigger, and configuration options.
 *
 * @since      1.0.0
 * @package    WooOdooIntegration
 * @subpackage WooOdooIntegration/Admin
 * @author     Ridwan Arifandi <orangerdigiart@gmail.com>
 *
 * @hooks      WordPress hooks this class uses:
 *             - admin_enqueue_scripts: Load admin styles and scripts
 *             - wp_ajax_woo_odoo_trigger_auto_sync: Handle manual sync trigger
 *             - wp_ajax_woo_odoo_get_sync_status: Get current sync status
 *             - wp_ajax_woo_odoo_clear_sync_queue: Clear sync queue
 */

namespace Woo_Odoo_Integration\Admin;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Scheduler_Admin
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
     * Scheduler instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Woo_Odoo_Integration_Scheduler    $scheduler    Scheduler instance.
     */
    private $scheduler;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version          The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->scheduler = new \Woo_Odoo_Integration_Scheduler($plugin_name, $version);
    }

    /**
     * Add scheduler admin menu
     *
     * Adds sub-menu for scheduler management under WooCommerce menu.
     *
     * @since    1.0.0
     * @access   public
     */
    public function add_admin_menu()
    {
        add_submenu_page(
            'woocommerce',
            __('Odoo Product Sync', 'woo-odoo-integration'),
            __('Odoo Auto Sync', 'woo-odoo-integration'),
            'manage_woocommerce',
            'woo-odoo-auto-sync',
            array($this, 'display_admin_page')
        );
    }

    /**
     * Display admin page for scheduler management
     *
     * Shows current sync status, next scheduled sync time, and manual controls.
     *
     * @since    1.0.0
     * @access   public
     */
    public function display_admin_page()
    {
        // Check user permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'woo-odoo-integration'));
        }

        // Get current sync status
        $sync_status = $this->scheduler->get_sync_status();
        $next_scheduled = wp_next_scheduled('woo_odoo_auto_sync_product_stock');

        // Get timezone info
        $timezone_string = get_option('timezone_string', 'UTC');
        if (!$timezone_string) {
            $gmt_offset = get_option('gmt_offset', 0);
            $timezone_string = sprintf('UTC%+d', $gmt_offset);
        }
        ?>
        <div class="wrap woo-odoo-integration-scheduler">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <!-- Sync Status Card -->
            <div class="postbox">
                <h2 class="hndle"><span><?php _e('Sync Status', 'woo-odoo-integration'); ?></span></h2>
                <div class="inside">
                    <div id="sync-status-container">
                        <?php $this->render_sync_status($sync_status); ?>
                    </div>
                </div>
            </div>

            <!-- Schedule Info Card -->
            <div class="postbox">
                <h2 class="hndle"><span><?php _e('Schedule Information', 'woo-odoo-integration'); ?></span></h2>
                <div class="inside">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Timezone', 'woo-odoo-integration'); ?></th>
                            <td><code><?php echo esc_html($timezone_string); ?></code></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Sync Time', 'woo-odoo-integration'); ?></th>
                            <td><?php _e('Daily at midnight', 'woo-odoo-integration'); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Next Scheduled Sync', 'woo-odoo-integration'); ?></th>
                            <td>
                                <?php if ($next_scheduled): ?>
                                    <code><?php echo esc_html(get_date_from_gmt(date('Y-m-d H:i:s', $next_scheduled), 'Y-m-d H:i:s')); ?></code>
                                    <small>(<?php echo esc_html($timezone_string); ?>)</small>
                                <?php else: ?>
                                    <span class="description"><?php _e('No sync scheduled', 'woo-odoo-integration'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Manual Controls Card -->
            <div class="postbox">
                <h2 class="hndle"><span><?php _e('Manual Controls', 'woo-odoo-integration'); ?></span></h2>
                <div class="inside">
                    <p><?php _e('Use these controls to manually manage the automatic sync process.', 'woo-odoo-integration'); ?>
                    </p>

                    <p>
                        <button type="button" id="trigger-sync" class="button button-primary" <?php echo ($sync_status && $sync_status['status'] === 'in_progress') ? 'disabled' : ''; ?>>
                            <?php _e('Start Sync Now', 'woo-odoo-integration'); ?>
                        </button>

                        <button type="button" id="clear-queue" class="button button-secondary" <?php echo (!$sync_status || $sync_status['status'] !== 'in_progress') ? 'disabled' : ''; ?>>
                            <?php _e('Cancel Current Sync', 'woo-odoo-integration'); ?>
                        </button>

                        <button type="button" id="refresh-status" class="button">
                            <?php _e('Refresh Status', 'woo-odoo-integration'); ?>
                        </button>
                    </p>

                    <div id="manual-controls-feedback"></div>
                </div>
            </div>

            <!-- Configuration Card -->
            <div class="postbox">
                <h2 class="hndle"><span><?php _e('Configuration', 'woo-odoo-integration'); ?></span></h2>
                <div class="inside">
                    <form method="post" action="options.php">
                        <?php settings_fields('woo_odoo_auto_sync_settings'); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="chunk_size"><?php _e('Chunk Size', 'woo-odoo-integration'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="chunk_size" name="woo_odoo_auto_sync_chunk_size"
                                        value="<?php echo esc_attr(get_option('woo_odoo_auto_sync_chunk_size', 10)); ?>" min="1"
                                        max="50" class="small-text" />
                                    <p class="description">
                                        <?php _e('Number of products to process in each batch (1-50)', 'woo-odoo-integration'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="chunk_interval"><?php _e('Chunk Interval', 'woo-odoo-integration'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="chunk_interval" name="woo_odoo_auto_sync_chunk_interval"
                                        value="<?php echo esc_attr(get_option('woo_odoo_auto_sync_chunk_interval', 5)); ?>"
                                        min="1" max="60" class="small-text" />
                                    <span><?php _e('minutes', 'woo-odoo-integration'); ?></span>
                                    <p class="description">
                                        <?php _e('Delay between processing each batch (1-60 minutes)', 'woo-odoo-integration'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button(); ?>
                    </form>
                </div>
            </div>

            <!-- Debug Information (only for admin users) -->
            <?php if (current_user_can('manage_options')): ?>
                <div class="postbox">
                    <h2 class="hndle"><span><?php _e('Debug Information', 'woo-odoo-integration'); ?></span></h2>
                    <div class="inside">
                        <p><strong><?php _e('WordPress Cron Status:', 'woo-odoo-integration'); ?></strong></p>
                        <p>
                            <?php if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON): ?>
                                <span class="description" style="color: orange;">
                                    <?php _e('WordPress cron is disabled. Make sure you have a system cron job set up.', 'woo-odoo-integration'); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: green;">
                                    <?php _e('WordPress cron is enabled.', 'woo-odoo-integration'); ?>
                                </span>
                            <?php endif; ?>
                        </p>

                        <p><strong><?php _e('Scheduled Events:', 'woo-odoo-integration'); ?></strong></p>
                        <ul>
                            <?php
                            $crons = _get_cron_array();
                            $found_events = 0;
                            if (is_array($crons)) {
                                foreach ($crons as $timestamp => $cron) {
                                    foreach ($cron as $hook => $jobs) {
                                        if (strpos($hook, 'woo_odoo_auto_sync') !== false) {
                                            foreach ($jobs as $job) {
                                                echo '<li>' . esc_html($hook) . ' - ' . esc_html(get_date_from_gmt(date('Y-m-d H:i:s', $timestamp), 'Y-m-d H:i:s')) . '</li>';
                                                $found_events++;
                                            }
                                        }
                                    }
                                }
                            }

                            if ($found_events === 0) {
                                echo '<li><em>' . __('No sync events scheduled', 'woo-odoo-integration') . '</em></li>';
                            }
                            ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <style>
            .postbox {
                margin-bottom: 20px;
            }

            .woo-odoo-integration-scheduler .postbox {
                padding: 0 1rem;
            }

            .woo-odoo-integration-scheduler .postbox .inside {
                padding: 0 !important;
            }

            .sync-status {
                padding: 10px;
                border-radius: 4px;
                margin-bottom: 10px;
            }

            .sync-status.in-progress {
                background-color: #fff3cd;
                border: 1px solid #ffeaa7;
                color: #856404;
            }

            .sync-status.completed {
                background-color: #d4edda;
                border: 1px solid #c3e6cb;
                color: #155724;
            }

            .sync-status.idle {
                background-color: #f8f9fa;
                border: 1px solid #dee2e6;
                color: #495057;
            }

            .progress-bar {
                width: 100%;
                height: 20px;
                background-color: #f1f1f1;
                border-radius: 10px;
                overflow: hidden;
                margin: 10px 0;
            }

            .progress-bar-fill {
                height: 100%;
                background-color: #0073aa;
                transition: width 0.3s ease;
            }

            .sync-details {
                font-family: monospace;
                font-size: 12px;
                margin-top: 10px;
            }

            #manual-controls-feedback {
                margin-top: 10px;
            }

            .notice-success,
            .notice-error,
            .notice-info {
                padding: 10px;
                border-left: 4px solid #0073aa;
                background: #fff;
                margin: 10px 0;
            }

            .notice-success {
                border-left-color: #46b450;
            }

            .notice-error {
                border-left-color: #dc3232;
            }

            .notice-info {
                border-left-color: #0073aa;
            }
        </style>
        <?php
    }

    /**
     * Render sync status display
     *
     * @since    1.0.0
     * @access   private
     *
     * @param    array|false    $sync_status    Current sync status data
     */
    private function render_sync_status($sync_status)
    {
        if (!$sync_status) {
            ?>
            <div class="sync-status idle">
                <h3><?php _e('No Active Sync', 'woo-odoo-integration'); ?></h3>
                <p><?php _e('No automatic sync is currently running.', 'woo-odoo-integration'); ?></p>
            </div>
            <?php
            return;
        }

        $status_class = $sync_status['status'];
        $progress_percent = 0;

        if ($sync_status['total_chunks'] > 0) {
            $progress_percent = round(($sync_status['current_chunk'] / $sync_status['total_chunks']) * 100);
        }
        ?>
        <div class="sync-status <?php echo esc_attr($status_class); ?>">
            <?php if ($sync_status['status'] === 'in_progress'): ?>
                <h3><?php _e('Sync In Progress', 'woo-odoo-integration'); ?></h3>
                <div class="progress-bar">
                    <div class="progress-bar-fill" style="width: <?php echo esc_attr($progress_percent); ?>%"></div>
                </div>
                <p>
                    <?php printf(
                        __('Processing chunk %d of %d (%d%% complete)', 'woo-odoo-integration'),
                        $sync_status['current_chunk'],
                        $sync_status['total_chunks'],
                        $progress_percent
                    ); ?>
                </p>
                <div class="sync-details">
                    <strong><?php _e('Products:', 'woo-odoo-integration'); ?></strong>
                    <?php printf('%d processed / %d total', $sync_status['processed_products'], $sync_status['total_products']); ?><br>

                    <strong><?php _e('Updated:', 'woo-odoo-integration'); ?></strong>
                    <?php echo esc_html($sync_status['successful_updates']); ?><br>

                    <strong><?php _e('Errors:', 'woo-odoo-integration'); ?></strong>
                    <?php echo esc_html($sync_status['failed_updates']); ?><br>

                    <strong><?php _e('Started:', 'woo-odoo-integration'); ?></strong>
                    <?php echo esc_html(get_date_from_gmt(date('Y-m-d H:i:s', $sync_status['start_time']), 'Y-m-d H:i:s')); ?>
                </div>
            <?php elseif ($sync_status['status'] === 'completed'): ?>
                <h3><?php _e('Last Sync Completed', 'woo-odoo-integration'); ?></h3>
                <div class="sync-details">
                    <strong><?php _e('Completed:', 'woo-odoo-integration'); ?></strong>
                    <?php echo esc_html(get_date_from_gmt(date('Y-m-d H:i:s', $sync_status['end_time']), 'Y-m-d H:i:s')); ?><br>

                    <strong><?php _e('Duration:', 'woo-odoo-integration'); ?></strong>
                    <?php printf('%d minutes', round(($sync_status['end_time'] - $sync_status['start_time']) / 60)); ?><br>

                    <strong><?php _e('Products Processed:', 'woo-odoo-integration'); ?></strong>
                    <?php echo esc_html($sync_status['processed_products']); ?><br>

                    <strong><?php _e('Updated:', 'woo-odoo-integration'); ?></strong>
                    <?php echo esc_html($sync_status['successful_updates']); ?><br>

                    <strong><?php _e('Errors:', 'woo-odoo-integration'); ?></strong>
                    <?php echo esc_html($sync_status['failed_updates']); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Register settings for configuration options
     *
     * @since    1.0.0
     * @access   public
     */
    public function register_settings()
    {
        register_setting('woo_odoo_auto_sync_settings', 'woo_odoo_auto_sync_chunk_size');
        register_setting('woo_odoo_auto_sync_settings', 'woo_odoo_auto_sync_chunk_interval');
    }

    /**
     * Handle AJAX request to trigger manual sync
     *
     * @since    1.0.0
     * @access   public
     */
    public function handle_ajax_trigger_sync()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'woo_odoo_auto_sync_nonce')) {
            wp_send_json_error(__('Security check failed', 'woo-odoo-integration'));
        }

        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied', 'woo-odoo-integration'));
        }

        // Try to start sync
        $result = $this->scheduler->force_start_sync();

        if ($result) {
            wp_send_json_success(__('Automatic sync started successfully', 'woo-odoo-integration'));
        } else {
            wp_send_json_error(__('Failed to start sync. Another sync may already be in progress.', 'woo-odoo-integration'));
        }
    }

    /**
     * Handle AJAX request to get sync status
     *
     * @since    1.0.0
     * @access   public
     */
    public function handle_ajax_get_status()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'woo_odoo_auto_sync_nonce')) {
            wp_send_json_error(__('Security check failed', 'woo-odoo-integration'));
        }

        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied', 'woo-odoo-integration'));
        }

        $sync_status = $this->scheduler->get_sync_status();

        ob_start();
        $this->render_sync_status($sync_status);
        $html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $html,
            'status' => $sync_status
        ));
    }

    /**
     * Handle AJAX request to clear sync queue
     *
     * @since    1.0.0
     * @access   public
     */
    public function handle_ajax_clear_queue()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'woo_odoo_auto_sync_nonce')) {
            wp_send_json_error(__('Security check failed', 'woo-odoo-integration'));
        }

        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied', 'woo-odoo-integration'));
        }

        // Clear sync queue
        $this->scheduler->clear_sync_queue();

        wp_send_json_success(__('Sync queue cleared successfully', 'woo-odoo-integration'));
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @since    1.0.0
     * @access   public
     *
     * @param    string    $hook_suffix    Current admin page hook suffix
     */
    public function enqueue_admin_scripts($hook_suffix)
    {
        // Only load on our admin page
        if ($hook_suffix !== 'woocommerce_page_woo-odoo-auto-sync') {
            return;
        }

        wp_enqueue_script(
            'woo-odoo-scheduler-admin',
            plugin_dir_url(__FILE__) . '../js/woo-odoo-integration-scheduler.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_localize_script('woo-odoo-scheduler-admin', 'wooOdooScheduler', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('woo_odoo_auto_sync_nonce'),
            'strings' => array(
                'confirm_trigger' => __('Are you sure you want to start the sync now?', 'woo-odoo-integration'),
                'confirm_clear' => __('Are you sure you want to cancel the current sync?', 'woo-odoo-integration'),
                'processing' => __('Processing...', 'woo-odoo-integration'),
                'error' => __('An error occurred. Please try again.', 'woo-odoo-integration'),
            )
        ));
    }
}
