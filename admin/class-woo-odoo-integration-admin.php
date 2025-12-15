<?php

namespace Woo_Odoo_Integration;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

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
class Admin
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
     * Initialize Carbon Fields library
     *
     * Loads the Carbon Fields library from vendor directory and boots it up
     * for use in the admin area. Carbon Fields is used for creating custom
     * meta boxes, theme options, and other admin interface elements.
     *
     * @since    1.0.0
     * @access   public
     *
     * @hooks    Should be called from after_setup_theme hook to ensure
     *           proper Carbon Fields initialization timing
     *
     * @return   void
     *
     * @throws   Error    If Carbon Fields cannot be loaded from vendor directory
     */
    public function crb_load()
    {
        require_once WOO_ODOO_INTEGRATION_PLUGIN_DIR . '/vendor/autoload.php';
        \Carbon_Fields\Carbon_Fields::boot();
    }
    /**
     * Register Carbon Fields fields
     *
     * This method is intended to register custom fields using Carbon Fields.
     * It should be called during the 'carbon_fields_register_fields' action.
     * You can define your custom fields here for use in the admin area.
     *
     * @since    1.0.0
     * @access   public
     *
     * @hooks    carbon_fields_register_fields
     *
     * @return   void
     */
    public function crb_register_fields()
    {

        Container::make('theme_options', __('Odoo Settings'))
            ->set_page_menu_position(60)
            ->set_page_menu_title(__('Odoo Settings'))
            ->add_fields(array(
                Field::make('text', 'odoo_url', __('Odoo URL'))
                    ->set_help_text(__('Enter your Odoo instance URL')),
                Field::make('text', 'odoo_client_id', __('Client ID'))
                    ->set_help_text(__('Enter your Odoo Client ID')),
                Field::make('text', 'odoo_client_secret', __('Client Secret'))
                    ->set_help_text(__('Enter your Odoo Client Secret')),
                Field::make('text', 'odoo_grant_type', __('Grant Type'))
                    ->set_help_text(__('Enter the grant type, e.g., client_credentials'))
                    ->set_default_value('client_credentials'),
                Field::make('text', 'odoo_scope', __('Scope'))
                    ->set_help_text(__('Enter the scope for the Odoo API, e.g., all'))
                    ->set_default_value('all'),
                
                Field::make('checkbox', 'enable_sync_attribut_color', __('Enable Sync Attribute Color'))
                    ->set_default_value(true)
                    ->set_help_text(__('Enable attribute color when product synchronization with Odoo')),
                Field::make('checkbox', 'enable_user_variation_attribut_color', __('Enable Attribute Color Used for Variation'))
                    ->set_help_text(__('Enable attribute color used for variation when product synchronization with Odoo')),
                Field::make('checkbox', 'enable_visible_attribut_color', __('Enable Attribute Color Visible on the Product Page'))
                    ->set_default_value(true)
                    ->set_help_text(__('Enable attribute color for visible on the product page when product synchronization with Odoo')),

                Field::make('checkbox', 'enable_sync_attribut_size', __('Enable Sync Attribute Size'))
                    ->set_default_value(true)
                    ->set_help_text(__('Enable attribute size when product synchronization with Odoo')),
                Field::make('checkbox', 'enable_user_variation_attribut_size', __('Enable Attribute Size Used for Variation'))
                    ->set_help_text(__('Enable attribute size used for variation when product synchronization with Odoo')),
                Field::make('checkbox', 'enable_visible_attribut_size', __('Enable Attribute Size Visible on the Product Page'))
                    ->set_default_value(true)
                    ->set_help_text(__('Enable attribute size for visible on the product page when product synchronization with Odoo')),

                Field::make('checkbox', 'enable_sync_attribut_location', __('Enable Sync Attribute Location'))
                    ->set_default_value(true)
                    ->set_help_text(__('Enable attribute location when product synchronization with Odoo')),
                Field::make('checkbox', 'enable_user_variation_attribut_location', __('Enable Attribute Location Used for Variation'))
                    ->set_help_text(__('Enable attribute location used for variation when product synchronization with Odoo')),
                Field::make('checkbox', 'enable_visible_attribut_location', __('Enable Attribute Location Visible on the Product Page'))
                    ->set_default_value(true)
                    ->set_help_text(__('Enable attribute location for visible on the product page when product synchronization with Odoo')),

                Field::make('checkbox', 'enable_sync_attribut_brand', __('Enable Sync Attribute Brand'))
                    ->set_help_text(__('Enable attribute brand when product synchronization with Odoo')),
                Field::make('checkbox', 'enable_user_variation_attribut_brand', __('Enable Attribute Brand Used for Variation'))
                    ->set_help_text(__('Enable attribute brand used for variation when product synchronization with Odoo')),
                Field::make('checkbox', 'enable_visible_attribut_brand', __('Enable Attribute Brand Visible on the Product Page'))
                    ->set_help_text(__('Enable attribute brand for visible on the product page when product synchronization with Odoo')),

                Field::make('checkbox', 'enable_sync_attribut_design_code', __('Enable Sync Attribute Design Code'))
                    ->set_help_text(__('Enable attribute design code when product synchronization with Odoo')),
                Field::make('checkbox', 'enable_user_variation_attribut_design_code', __('Enable Attribute Design Code Used for Variation'))
                    ->set_help_text(__('Enable attribute design code used for variation when product synchronization with Odoo')),
                Field::make('checkbox', 'enable_visible_attribut_design_code', __('Enable Attribute Design Code Visible on the Product Page'))
                    ->set_help_text(__('Enable attribute Design Code for visible on the product page when product synchronization with Odoo')),

                Field::make('checkbox', 'enable_sync_attribut_material', __('Enable Sync Attribute Material'))
                    ->set_help_text(__('Enable attribute material when product synchronization with Odoo')),
                Field::make('checkbox', 'enable_user_variation_attribut_material', __('Enable Attribute Material Used for Variation'))
                    ->set_help_text(__('Enable attribute material used for variation when product synchronization with Odoo')),
                Field::make('checkbox', 'enable_visible_attribut_material', __('Enable Attribute Material Visible on the Product Page'))
                    ->set_help_text(__('Enable attribute material for visible on the product page when product synchronization with Odoo')),

                Field::make('checkbox', 'enable_sync_attribut_model', __('Enable Sync Attribute Model'))
                    ->set_help_text(__('Enable attribute model when product synchronization with Odoo')),
                Field::make('checkbox', 'enable_user_variation_attribut_model', __('Enable Attribute Model Used for Variation'))
                    ->set_help_text(__('Enable attribute model used for variation when product synchronization with Odoo')),
                Field::make('checkbox', 'enable_visible_attribut_model', __('Enable Attribute Model Visible on the Product Page'))
                    ->set_help_text(__('Enable attribute model for visible on the product page when product synchronization with Odoo')),

                Field::make('checkbox', 'enable_sync_attribut_process', __('Enable Sync Attribute Process'))
                    ->set_help_text(__('Enable attribute process when product synchronization with Odoo')),
                Field::make('checkbox', 'enable_user_variation_attribut_process', __('Enable Attribute Process Used for Variation'))
                    ->set_help_text(__('Enable attribute process used for variation when product synchronization with Odoo')),
                Field::make('checkbox', 'enable_visible_attribut_process', __('Enable Attribute Process Visible on the Product Page'))
                    ->set_help_text(__('Enable attribute process for visible on the product page when product synchronization with Odoo')),

                Field::make('checkbox', 'enable_sync_attribut_product_type', __('Enable Sync Attribute Type'))
                    ->set_help_text(__('Enable attribute type when product synchronization with Odoo')),
                Field::make('checkbox', 'enable_user_variation_attribut_product_type', __('Enable Attribute Type Used for Variation'))
                    ->set_help_text(__('Enable attribute type used for variation when product synchronization with Odoo')),
                Field::make('checkbox', 'enable_visible_attribut_product_type', __('Enable Attribute Type Visible on the Product Page'))
                    ->set_help_text(__('Enable attribute type for visible on the product page when product synchronization with Odoo')),

                Field::make('checkbox', 'enable_sync_photo_product', __('Enable Sync Product Photo'))
                    ->set_help_text(__('Enable product photo when product synchronization with Odoo')),

                Field::make('text', 'odoo_location_att', __('Attribut Location'))
                    ->set_default_value('pa_location'),
                Field::make('checkbox', 'enable_customer_sync', __('Enable Customer Sync'))
                    ->set_help_text(__('Enable automatic customer synchronization with Odoo')),
                Field::make('checkbox', 'enable_product_sync', __('Enable Product Sync'))
                    ->set_help_text(__('Enable automatic product synchronization with Odoo')),
                Field::make('checkbox', 'enable_order_sync', __('Enable Order Sync'))
                    ->set_help_text(__('Enable automatic order synchronization with Odoo')),
                Field::make('checkbox', 'enable_stock_sync', __('Enable Stock Sync'))
                    ->set_help_text(__('Enable automatic stock synchronization with Odoo')),
                Field::make('checkbox', 'enable_debug_logging', __('Enable Debug Logging'))
                    ->set_help_text(__('Enable detailed API logging for debugging (includes endpoint, request data, and response data)')),

            ));

        Container::make('term_meta', __("Location Configuration", 'woo-odoo-integration'))
            ->where('term_taxonomy', '=', 'pa_location')
            ->add_fields(array(
                Field::make('text', 'odoo_warehouse_id', __('Warehouse ID'))
                    ->set_help_text(__('Enter the Odoo Warehouse ID for this category')),
            ));
    }

}
