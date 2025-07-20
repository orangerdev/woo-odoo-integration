<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://ridwan-arifandi.com
 * @since             1.0.0
 * @package           Woo_Odoo_Integration
 *
 * @wordpress-plugin
 * Plugin Name:       WooCommerce Odoo Integration
 * Plugin URI:        https://https://ridwan-arifandi.com/portfolio/woocommerce-odoo-integration/
 * Description:       WooCommerce–Odoo plugin for syncing stock, orders, users, multi-location carts & shipping—no middleware needed.
 * Version:           1.0.0
 * Author:            Ridwan Arifandi
 * Author URI:        https://ridwan-arifandi.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       woo-odoo-integration
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('WOO_ODOO_INTEGRATION_VERSION', '1.0.0');
define('WOO_ODOO_INTEGRATION_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WOO_ODOO_INTEGRATION_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-woo-odoo-integration-activator.php
 */
function activate_woo_odoo_integration()
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-woo-odoo-integration-activator.php';
    Woo_Odoo_Integration_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-woo-odoo-integration-deactivator.php
 */
function deactivate_woo_odoo_integration()
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-woo-odoo-integration-deactivator.php';
    Woo_Odoo_Integration_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_woo_odoo_integration');
register_deactivation_hook(__FILE__, 'deactivate_woo_odoo_integration');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-woo-odoo-integration.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_woo_odoo_integration()
{

    $plugin = new Woo_Odoo_Integration();
    $plugin->run();

}
run_woo_odoo_integration();
