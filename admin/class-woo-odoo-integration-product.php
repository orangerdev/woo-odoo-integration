<?php

namespace Woo_Odoo_Integration\Admin;

/**
 * Product management functionality for WooCommerce Odoo Integration
 *
 * Handles product-related features including mass actions for syncing
 * product stock from Odoo to WooCommerce. Adds custom bulk actions
 * to WooCommerce product list and handles the synchronization process.
 *
 * @link  https://ridwan-arifandi.com
 * @since 1.0.0
 *
 * @package    Woo_Odoo_Integration
 * @subpackage Woo_Odoo_Integration/admin
 */

/**
 * Product management functionality for WooCommerce Odoo Integration
 *
 * Manages product synchronization between WooCommerce and Odoo ERP.
 * Provides mass action functionality to sync product stock data and
 * handles the integration workflow for product management.
 *
 * @since      1.0.0
 * @package    Woo_Odoo_Integration
 * @subpackage Woo_Odoo_Integration/Admin
 * @author     Ridwan Arifandi <orangerdigiart@gmail.com>
 *
 * @hooks WordPress hooks this class interacts with:
 *             - bulk_actions-edit-product (for adding mass action)
 *             - handle_bulk_actions-edit-product (for processing mass action)
 *             - admin_notices (for displaying sync results)
 */
class Product {
	/**
	 * The ID of this plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * Sets up the admin class with plugin name and version information.
	 * These properties are used throughout the class for identifying
	 * the plugin in WordPress admin context.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param string $plugin_name The name of this plugin
	 * @param string $version     The version of this plugin
	 *
	 * @return void
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;

		add_action(
			'admin_init',
			function () {
				// Check if the bulk action is triggered
				if ( isset( $_REQUEST['dylan'] ) ) {
					$token = woo_odoo_integration_api_get_access_token();

					do_action(
						"qm/info",
						array(
							$token,
						)
					);
				}
			}
		);
	}

	/**
	 * Add custom bulk actions to product list
	 *
	 * Adds "Sync Product Stock from Odoo" option to the bulk actions
	 * dropdown in WooCommerce product management page.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @hooks Called by: bulk_actions-edit-product
	 *
	 * @param array $bulk_actions Existing bulk actions
	 *
	 * @return array                    Modified bulk actions with sync option
	 */
	public function add_bulk_actions( $bulk_actions ) {
		$bulk_actions['woo_odoo_sync_product_stock'] = __( 'Sync Product Stock from Odoo', 'woo-odoo-integration' );

		return $bulk_actions;
	}

	/**
	 * Handle bulk action for syncing product stock
	 *
	 * Processes the "Sync Product Stock from Odoo" bulk action.
	 * Calls the API function to sync stock data and sets up admin
	 * notices to display the results.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @hooks Called by: handle_bulk_actions-edit-product
	 *           Fires the following hooks:
	 *           - do_action('woo_odoo_integration_bulk_sync_started', $post_ids)
	 *           - do_action('woo_odoo_integration_bulk_sync_completed', $sync_results)
	 *
	 * @param string $redirect_to The redirect URL
	 * @param string $doaction    The action being taken
	 * @param array  $post_ids    Array of post IDs to process
	 *
	 * @return string                   Modified redirect URL with query params
	 */
	public function handle_bulk_actions( $redirect_to, $doaction, $post_ids ) {
		if ( 'woo_odoo_sync_product_stock' !== $doaction ) {
			return $redirect_to;
		}

		// Validate that we have products to sync
		if ( empty( $post_ids ) ) {
			return add_query_arg( 'woo_odoo_sync_error', 'no_products', $redirect_to );
		}

		$logger = wc_get_logger();
		$logger->info(
			sprintf(
				'Starting bulk product stock sync for %d products - Endpoint: api/product-stock | Product IDs: %s',
				count( $post_ids ),
				json_encode( $post_ids )
			),
			array( 'source' => 'woo-odoo-product-sync' )
		);

		// Fire before bulk sync hook
		do_action( 'woo_odoo_integration_bulk_sync_started', $post_ids );

		// Load API helper functions
		if ( ! function_exists( 'woo_odoo_integration_sync_product_stock' ) ) {
			include_once plugin_dir_path( dirname( __FILE__ ) ) . 'helper/api.php';
		}

		// Log API call details
		$request_data = array( 'product_ids' => $post_ids );
		$logger->info(
			sprintf(
				'Making API call for bulk product sync - Endpoint: api/product-stock | Request Data: %s',
				json_encode( $request_data )
			),
			array( 'source' => 'woo-odoo-product-sync' )
		);

		// Perform the sync
		$sync_results = woo_odoo_integration_sync_product_stock( $post_ids );

		if ( is_wp_error( $sync_results ) ) {
			$logger->error(
				sprintf(
					'Bulk sync failed - Endpoint: api/product-stock | Request Data: %s | Error: %s | Response: %s',
					json_encode( $request_data ),
					$sync_results->get_error_message(),
					json_encode(
						array(
							'error_code' => $sync_results->get_error_code(),
							'error_message' => $sync_results->get_error_message(),
							'error_data' => $sync_results->get_error_data()
						)
					)
				),
				array( 'source' => 'woo-odoo-product-sync' )
			);

			return add_query_arg(
				array(
					'woo_odoo_sync_error' => 'sync_failed',
					'error_message' => urlencode( $sync_results->get_error_message() )
				),
				$redirect_to
			);
		}

		// Log successful sync
		$logger->info(
			sprintf(
				'Bulk product stock sync completed successfully - Endpoint: api/product-stock | Request Data: %s | Response: %s',
				json_encode( $request_data ),
				json_encode(
					array(

						'updated' => $sync_results['updated'],
						'skipped' => $sync_results['skipped'],
						'errors' => $sync_results['errors']
					)
				)
			),
			array( 'source' => 'woo-odoo-product-sync' )
		);

		// Fire after bulk sync hook
		do_action( 'woo_odoo_integration_bulk_sync_completed', $sync_results );

		// Add success parameters to redirect URL
		$redirect_to = add_query_arg(
			array(
				'woo_odoo_sync_success' => 'true',
				'updated_count' => $sync_results['updated'],
				'skipped_count' => $sync_results['skipped'],
				'error_count' => $sync_results['errors']
			),
			$redirect_to
		);

		$logger->info( 'Bulk product stock sync completed successfully', array( 'source' => 'woo-odoo-product-sync' ) );

		return $redirect_to;
	}

	/**
	 * Display admin notices for bulk sync results
	 *
	 * Shows success or error messages after bulk sync operations.
	 * Displays detailed information about updated, skipped, and failed products.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @hooks Called by: admin_notices
	 *
	 * @return void
	 */
	public function display_admin_notices() {
		// Check if we're on the products page
		global $pagenow, $typenow;

		if ( 'edit.php' !== $pagenow || 'product' !== $typenow ) {
			return;
		}

		// Handle success notice
		if ( isset( $_GET['woo_odoo_sync_success'] ) && 'true' === $_GET['woo_odoo_sync_success'] ) {
			$updated_count = isset( $_GET['updated_count'] ) ? intval( $_GET['updated_count'] ) : 0;
			$skipped_count = isset( $_GET['skipped_count'] ) ? intval( $_GET['skipped_count'] ) : 0;
			$error_count = isset( $_GET['error_count'] ) ? intval( $_GET['error_count'] ) : 0;

			$message = sprintf(
				/* translators: %1$d: updated count, %2$d: skipped count, %3$d: error count */
				__( 'Product stock sync completed! Updated: %1$d, Skipped: %2$d, Errors: %3$d', 'woo-odoo-integration' ),
				$updated_count,
				$skipped_count,
				$error_count
			);

			$notice_type = $error_count > 0 ? 'warning' : 'success';

			printf(
				'<div class="notice notice-%s is-dismissible"><p><strong>%s:</strong> %s</p></div>',
				esc_attr( $notice_type ),
				esc_html__( 'WooCommerce Odoo Integration', 'woo-odoo-integration' ),
				esc_html( $message )
			);
		}

		// Handle error notices
		if ( isset( $_GET['woo_odoo_sync_error'] ) ) {
			$error_type = sanitize_text_field( $_GET['woo_odoo_sync_error'] );
			$error_message = '';

			switch ( $error_type ) {
				case 'no_products':
					$error_message = __( 'No products selected for synchronization.', 'woo-odoo-integration' );
					break;

				case 'sync_failed':
					$error_message = isset( $_GET['error_message'] )
						? urldecode( sanitize_text_field( $_GET['error_message'] ) )
						: __( 'Product synchronization failed. Please check the logs for more details.', 'woo-odoo-integration' );
					break;

				default:
					$error_message = __( 'An unknown error occurred during synchronization.', 'woo-odoo-integration' );
					break;
			}

			printf(
				'<div class="notice notice-error is-dismissible"><p><strong>%s:</strong> %s</p></div>',
				esc_html__( 'WooCommerce Odoo Integration', 'woo-odoo-integration' ),
				esc_html( $error_message )
			);
		}
	}

	/**
	 * Add sync product stock button to individual product edit page
	 *
	 * Adds a "Sync Stock from Odoo" button in the product data meta box
	 * for manual synchronization of individual products.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @hooks Called by: woocommerce_product_options_stock_status
	 *
	 * @return void
	 */
	public function add_product_sync_button() {
		global $post;

		if ( ! $post || 'product' !== $post->post_type ) {
			return;
		}

		$product = wc_get_product( $post->ID );
		if ( ! $product || empty( $product->get_sku() ) ) {
			return;
		}

		?>
		<div class="options_group">
			<p class="form-field">
				<label><?php esc_html_e( 'Odoo Integration', 'woo-odoo-integration' ); ?></label>
				<button type="button" class="button woo-odoo-sync-single-product"
					data-product-id="<?php echo esc_attr( $post->ID ); ?>"
					data-sku="<?php echo esc_attr( $product->get_sku() ); ?>">
					<?php esc_html_e( 'Sync Stock from Odoo', 'woo-odoo-integration' ); ?>
				</button>
				<span class="description">
					<?php esc_html_e( 'Sync this product\'s stock quantity from Odoo ERP system.', 'woo-odoo-integration' ); ?>
				</span>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle AJAX request for single product sync
	 *
	 * Processes AJAX requests to sync individual product stock from Odoo.
	 * Returns JSON response with sync results or error messages.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @hooks Called by: wp_ajax_woo_odoo_sync_single_product
	 *
	 * @return void    Outputs JSON response and exits
	 */
	public function handle_ajax_sync_single_product() {
		// Verify nonce for security
		if ( ! wp_verify_nonce( $_POST['nonce'], 'woo_odoo_sync_single_product' ) ) {
			wp_die( __( 'Security check failed', 'woo-odoo-integration' ) );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Insufficient permissions', 'woo-odoo-integration' ) );
		}

		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;

		if ( ! $product_id ) {
			wp_send_json_error( __( 'Invalid product ID', 'woo-odoo-integration' ) );
		}

		// Load API helper functions
		if ( ! function_exists( 'woo_odoo_integration_sync_product_stock' ) ) {
			include_once plugin_dir_path( dirname( __FILE__ ) ) . 'helper/api.php';
		}

		// Perform the sync for single product
		$sync_results = woo_odoo_integration_sync_product_stock( array( $product_id ) );

		if ( is_wp_error( $sync_results ) ) {
			wp_send_json_error( $sync_results->get_error_message() );
		}

		if ( $sync_results['updated'] > 0 ) {
			wp_send_json_success(
				array(
					'message' => __( 'Product stock synchronized successfully!', 'woo-odoo-integration' ),
					'details' => $sync_results['details']
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'No stock updates were made.', 'woo-odoo-integration' ),
					'details' => $sync_results['details']
				)
			);
		}
	}

	/**
	 * Enqueue admin scripts and styles for product management
	 *
	 * Loads JavaScript and CSS files required for product synchronization
	 * functionality. Only loads on relevant admin pages.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @hooks Called by: admin_enqueue_scripts
	 *
	 * @param string $hook The current admin page hook
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Only load on product pages
		if ( ! in_array( $hook, array( 'edit.php', 'post.php', 'post-new.php' ) ) ) {
			return;
		}

		global $typenow;
		if ( 'product' !== $typenow ) {
			return;
		}

		// Enqueue product management JavaScript
		wp_enqueue_script(
			$this->plugin_name . '-product',
			plugin_dir_url( __FILE__ ) . 'js/woo-odoo-integration-product.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		// Localize script with data
		wp_localize_script(
			$this->plugin_name . '-product',
			'woo_odoo_product',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'woo_odoo_sync_single_product' ),
				'plugin_name' => __( 'WooCommerce Odoo Integration', 'woo-odoo-integration' ),
				'refresh_on_sync' => apply_filters( 'woo_odoo_integration_refresh_on_sync', true ),
				'messages' => array(
					'syncing' => __( 'Syncing...', 'woo-odoo-integration' ),
					'sync_button' => __( 'Sync Stock from Odoo', 'woo-odoo-integration' ),
					'sync_failed' => __( 'Stock synchronization failed. Please try again.', 'woo-odoo-integration' ),
					'ajax_error' => __( 'Network error occurred. Please check your connection and try again.', 'woo-odoo-integration' ),
					'invalid_product_id' => __( 'Invalid product ID.', 'woo-odoo-integration' ),
					'no_products_selected' => __( 'Please select products to sync.', 'woo-odoo-integration' ),
					'confirm_bulk_sync' => __( 'Are you sure you want to sync stock for %d selected products? This action will update stock quantities from Odoo.', 'woo-odoo-integration' ),
					'dismiss' => __( 'Dismiss this notice.', 'woo-odoo-integration' )
				)
			)
		);

		// Enqueue admin styles if needed
		wp_enqueue_style(
			$this->plugin_name . '-product',
			plugin_dir_url( __FILE__ ) . 'css/woo-odoo-integration-product.css',
			array(),
			$this->version,
			'all'
		);
	}
}
