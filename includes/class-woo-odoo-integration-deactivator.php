<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://ridwan-arifandi.com
 * @since      1.0.0
 *
 * @package    Woo_Odoo_Integration
 * @subpackage Woo_Odoo_Integration/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Woo_Odoo_Integration
 * @subpackage Woo_Odoo_Integration/includes
 * @author     Ridwan Arifandi <orangerdigiart@gmail.com>
 */
class Woo_Odoo_Integration_Deactivator
{

    /**
     * Clean up plugin data on deactivation
     *
     * Clears scheduled cron events and transient data to prevent
     * orphaned processes after plugin deactivation.
     *
     * @since    1.0.0
     */
    public static function deactivate()
    {
        // Clear all scheduled sync events
        wp_clear_scheduled_hook('woo_odoo_auto_sync_product_stock');

        // Clear any remaining chunk processing events
        $crons = _get_cron_array();
        if (is_array($crons)) {
            foreach ($crons as $timestamp => $cron) {
                if (isset($cron['woo_odoo_auto_sync_product_chunk'])) {
                    foreach ($cron['woo_odoo_auto_sync_product_chunk'] as $job) {
                        wp_unschedule_event($timestamp, 'woo_odoo_auto_sync_product_chunk', $job['args']);
                    }
                }
            }
        }

        // Clear sync metadata
        delete_option('woo_odoo_auto_sync_meta');

        // Clear cached access tokens
        delete_transient('woo_odoo_integration_access_token');
        delete_transient('woo_odoo_integration_token_info');

        // Clear cached countries data
        delete_transient('woo_odoo_integration_countries');
    }

}
