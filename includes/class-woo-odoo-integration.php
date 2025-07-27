<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://ridwan-arifandi.com
 * @since      1.0.0
 *
 * @package    Woo_Odoo_Integration
 * @subpackage Woo_Odoo_Integration/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Woo_Odoo_Integration
 * @subpackage Woo_Odoo_Integration/includes
 * @author     Ridwan Arifandi <orangerdigiart@gmail.com>
 */
class Woo_Odoo_Integration
{

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Woo_Odoo_Integration_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        if (defined('WOO_ODOO_INTEGRATION_VERSION')) {
            $this->version = WOO_ODOO_INTEGRATION_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'woo-odoo-integration';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();

    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Woo_Odoo_Integration_Loader. Orchestrates the hooks of the plugin.
     * - Woo_Odoo_Integration_i18n. Defines internationalization functionality.
     * - Woo_Odoo_Integration_Admin. Defines all hooks for the admin area.
     * - Woo_Odoo_Integration_Public. Defines all hooks for the public side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies()
    {
        // Load any necessary helper functions or classes
        require_once WOO_ODOO_INTEGRATION_PLUGIN_DIR . 'helper/api.php';


        require_once WOO_ODOO_INTEGRATION_PLUGIN_DIR . 'includes/class-woo-odoo-integration-scheduler.php';
        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once WOO_ODOO_INTEGRATION_PLUGIN_DIR . 'includes/class-woo-odoo-integration-loader.php';

        /**
         * The class responsible for defining internationalization functionality
         * of the plugin.
         */
        require_once WOO_ODOO_INTEGRATION_PLUGIN_DIR . 'includes/class-woo-odoo-integration-i18n.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once WOO_ODOO_INTEGRATION_PLUGIN_DIR . 'admin/class-woo-odoo-integration-admin.php';
        require_once WOO_ODOO_INTEGRATION_PLUGIN_DIR . 'admin/class-woo-odoo-integration-product.php';
        require_once WOO_ODOO_INTEGRATION_PLUGIN_DIR . 'admin/class-woo-odoo-integration-scheduler-admin.php';
        require_once WOO_ODOO_INTEGRATION_PLUGIN_DIR . 'admin/class-woo-odoo-integration-user.php';


        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        require_once WOO_ODOO_INTEGRATION_PLUGIN_DIR . 'public/class-woo-odoo-integration-public.php';

        $this->loader = new Woo_Odoo_Integration_Loader();

    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the Woo_Odoo_Integration_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale()
    {

        $plugin_i18n = new Woo_Odoo_Integration_i18n();

        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');

    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks()
    {

        $admin = new Woo_Odoo_Integration\Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('after_setup_theme', $admin, 'crb_load');
        $this->loader->add_action('carbon_fields_register_fields', $admin, 'crb_register_fields');

        // User/Customer management hooks
        $user_handler = new Woo_Odoo_Integration\Admin\User($this->get_plugin_name(), $this->get_version());

        // Only use checkout order processed hook for customer sync
        $this->loader->add_action('woocommerce_checkout_order_processed', $user_handler, 'sync_customer_to_odoo_after_checkout', 10, 1);

        // Scheduled action hooks (keep for admin manual sync)
        $this->loader->add_action('woo_odoo_integration_sync_customer', $user_handler, 'sync_customer_to_odoo');

        // Admin user profile hooks
        $this->loader->add_action('show_user_profile', $user_handler, 'show_customer_odoo_status');
        $this->loader->add_action('edit_user_profile', $user_handler, 'show_customer_odoo_status');

        $this->loader->add_action('woo_odoo_integration_create_guest_customer_failed', $user_handler, 'handle_guest_customer_creation_failed', 10, 2);

        // Product management hooks
        $product_handler = new Woo_Odoo_Integration\Admin\Product($this->get_plugin_name(), $this->get_version());

        // Bulk action hooks for Products admin page
        $this->loader->add_filter('bulk_actions-edit-product', $product_handler, 'add_bulk_actions');
        $this->loader->add_filter('handle_bulk_actions-edit-product', $product_handler, 'handle_bulk_actions', 10, 3);

        // Admin notices for product sync results
        $this->loader->add_action('admin_notices', $product_handler, 'display_admin_notices');

        // Single product sync hooks
        $this->loader->add_action('woocommerce_product_options_stock_status', $product_handler, 'add_product_sync_button');
        $this->loader->add_action('wp_ajax_woo_odoo_sync_single_product', $product_handler, 'handle_ajax_sync_single_product');

        // Admin script and style hooks for products
        $this->loader->add_action('admin_enqueue_scripts', $product_handler, 'enqueue_admin_scripts');

        // Scheduler for automatic product sync
        $scheduler = new Woo_Odoo_Integration_Scheduler($this->get_plugin_name(), $this->get_version());
        $this->loader->add_action('wp_loaded', $scheduler, 'init_scheduler');

        // Scheduler admin interface
        $scheduler_admin = new Woo_Odoo_Integration\Admin\Scheduler_Admin($this->get_plugin_name(), $this->get_version());
        $this->loader->add_action('admin_menu', $scheduler_admin, 'add_admin_menu');
        $this->loader->add_action('admin_init', $scheduler_admin, 'register_settings');
        $this->loader->add_action('admin_enqueue_scripts', $scheduler_admin, 'enqueue_admin_scripts');

        // AJAX hooks for scheduler admin
        $this->loader->add_action('wp_ajax_woo_odoo_trigger_auto_sync', $scheduler_admin, 'handle_ajax_trigger_sync');
        $this->loader->add_action('wp_ajax_woo_odoo_get_sync_status', $scheduler_admin, 'handle_ajax_get_status');
        $this->loader->add_action('wp_ajax_woo_odoo_clear_sync_queue', $scheduler_admin, 'handle_ajax_clear_queue');

    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks()
    {

        $public = new Woo_Odoo_Integration\Front($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('wp_enqueue_scripts', $public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $public, 'enqueue_scripts');

    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run()
    {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    Woo_Odoo_Integration_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader()
    {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version()
    {
        return $this->version;
    }

}
