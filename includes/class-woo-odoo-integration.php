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

        // WooCommerce customer registration and checkout hooks
        $this->loader->add_action('woocommerce_created_customer', $user_handler, 'sync_customer_to_odoo_after_registration', 10, 1);
        $this->loader->add_action('woocommerce_checkout_order_processed', $user_handler, 'sync_customer_to_odoo_after_checkout', 10, 1);

        // Admin user profile hooks
        $this->loader->add_action('show_user_profile', $user_handler, 'show_customer_odoo_status');
        $this->loader->add_action('edit_user_profile', $user_handler, 'show_customer_odoo_status');

        // AJAX hooks for manual sync
        $this->loader->add_action('wp_ajax_woo_odoo_sync_customer', $user_handler, 'handle_manual_customer_sync');

        // Admin script and style hooks
        $this->loader->add_action('admin_enqueue_scripts', $user_handler, 'enqueue_admin_scripts');

        // Admin notice hooks
        $this->loader->add_action('admin_notices', $user_handler, 'show_sync_failure_notices');
        $this->loader->add_action('admin_notices', $user_handler, 'show_bulk_sync_notice');

        // Bulk action hooks for Users admin page
        $this->loader->add_filter('bulk_actions-users', $user_handler, 'add_bulk_customer_sync_action');
        $this->loader->add_filter('handle_bulk_actions-users', $user_handler, 'handle_bulk_customer_sync', 10, 3);

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
