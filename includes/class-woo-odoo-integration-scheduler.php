<?php
/**
 * Scheduler for automatic product stock synchronization
 *
 * Handles automated product stock sync using WordPress cron system with chunking
 * to process large numbers of products efficiently. Runs daily at midnight based
 * on WordPress timezone setting.
 *
 * @since      1.0.0
 * @package    WooOdooIntegration
 * @subpackage WooOdooIntegration/Core
 * @author     Ridwan Arifandi <orangerdigiart@gmail.com>
 *
 * @hooks      WordPress hooks this class uses:
 *             - wp_loaded: Initialize scheduler
 *             - woo_odoo_auto_sync_product_stock: Main sync event
 *             - woo_odoo_auto_sync_product_chunk: Process chunk event
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) )
	exit;

class Woo_Odoo_Integration_Scheduler {
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
	 * WooCommerce logger instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      WC_Logger    $logger    Logger for debugging and monitoring.
	 */
	private $logger;

	/**
	 * Chunk size for processing products.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      int    $chunk_size    Number of products to process per batch.
	 */
	private $chunk_size = 10;

	/**
	 * Interval between chunks in minutes.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      int    $chunk_interval    Minutes between chunk processing.
	 */
	private $chunk_interval = 5;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    string    $plugin_name       The name of this plugin.
	 * @param    string    $version          The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;

		// Allow customization of chunk settings via filters
		$this->chunk_size = apply_filters( 'woo_odoo_integration_auto_sync_chunk_size', $this->chunk_size );
		$this->chunk_interval = apply_filters( 'woo_odoo_integration_auto_sync_chunk_interval', $this->chunk_interval );
	}

	/**
	 * Get logger instance with lazy initialization
	 *
	 * Ensures WooCommerce is loaded before attempting to get logger.
	 *
	 * @since    1.0.0
	 * @access   private
	 *
	 * @return   WC_Logger|null    Logger instance or null if WooCommerce not available
	 */
	private function get_logger() {
		if ( null === $this->logger ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				$this->logger = wc_get_logger();
			} else {
				// Fallback to error_log if WooCommerce not available
				return null;
			}
		}

		return $this->logger;
	}

	/**
	 * Log message with fallback to error_log
	 *
	 * @since    1.0.0
	 * @access   private
	 *
	 * @param    string    $level     Log level (info, warning, error, debug)
	 * @param    string    $message   Message to log
	 * @param    array     $context   Log context
	 */
	private function log( $level, $message, $context = array() ) {
		$logger = $this->get_logger();

		if ( $logger ) {
			// Use WooCommerce logger
			switch ( $level ) {
				case 'info':
					$logger->info( $message, $context );
					break;
				case 'warning':
					$logger->warning( $message, $context );
					break;
				case 'error':
					$logger->error( $message, $context );
					break;
				case 'debug':
					$logger->debug( $message, $context );
					break;
				default:
					$logger->info( $message, $context );
					break;
			}
		} else {
			// Fallback to error_log
			$source = isset( $context['source'] ) ? '[' . $context['source'] . '] ' : '[woo-odoo-scheduler] ';
			error_log( $source . strtoupper( $level ) . ': ' . $message );
		}
	}

	/**
	 * Initialize the scheduler
	 *
	 * Sets up WordPress cron events for automatic product sync.
	 * Schedules main sync event to run daily at midnight based on WordPress timezone.
	 *
	 * @since    1.0.0
	 * @access   public
	 *
	 * @hooks    Registers the following WordPress hooks:
	 *           - woo_odoo_auto_sync_product_stock: Daily sync event
	 *           - woo_odoo_auto_sync_product_chunk: Chunk processing event
	 */
	public function init_scheduler() {
		// Schedule main sync event if not already scheduled
		if ( ! wp_next_scheduled( 'woo_odoo_auto_sync_product_stock' ) ) {
			$this->schedule_daily_sync();
		}

		// Register cron hooks
		add_action( 'woo_odoo_auto_sync_product_stock', array( $this, 'start_auto_sync' ) );
		add_action( 'woo_odoo_auto_sync_product_chunk', array( $this, 'process_sync_chunk' ) );

		$this->log( 'debug', 'Scheduler initialized', array( 'source' => 'woo-odoo-scheduler' ) );
	}

	/**
	 * Schedule daily sync at midnight
	 *
	 * Calculates next midnight based on WordPress timezone and schedules the sync event.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function schedule_daily_sync() {
		// Get WordPress timezone
		$timezone_string = get_option( 'timezone_string' );

		if ( ! $timezone_string ) {
			// Fallback to GMT offset if timezone string not set
			$gmt_offset = get_option( 'gmt_offset', 0 );
			$timezone_string = $this->get_timezone_from_offset( $gmt_offset );
		}

		try {
			$timezone = new DateTimeZone( $timezone_string );
		} catch (Exception $e) {
			// Fallback to UTC if timezone is invalid
			$timezone = new DateTimeZone( 'UTC' );
			$this->log( 'warning', 'Invalid timezone, using UTC: ' . $e->getMessage(), array( 'source' => 'woo-odoo-scheduler' ) );
		}

		// Calculate next midnight in WordPress timezone
		$now = new DateTime( 'now', $timezone );
		$midnight = new DateTime( 'tomorrow midnight', $timezone );

		// Convert to UTC for WordPress cron
		$midnight->setTimezone( new DateTimeZone( 'UTC' ) );
		$timestamp = $midnight->getTimestamp();

		// Schedule daily event
		wp_schedule_event( $timestamp, 'daily', 'woo_odoo_auto_sync_product_stock' );

		$this->log( 'info', sprintf(
			'Scheduled daily product sync at midnight (%s timezone). Next run: %s UTC',
			$timezone_string,
			$midnight->format( 'Y-m-d H:i:s' )
		), array( 'source' => 'woo-odoo-scheduler' ) );
	}

	/**
	 * Get timezone string from GMT offset
	 *
	 * Converts WordPress GMT offset to timezone string.
	 *
	 * @since    1.0.0
	 * @access   private
	 *
	 * @param    float    $offset    GMT offset in hours
	 *
	 * @return   string             Timezone string
	 */
	private function get_timezone_from_offset( $offset ) {
		$hours = intval( $offset );
		$minutes = abs( ( $offset - $hours ) * 60 );
		$sign = $offset >= 0 ? '+' : '-';

		return sprintf( 'Etc/GMT%s%d%s', $sign === '+' ? '-' : '+', abs( $hours ), $minutes ? ':' . $minutes : '' );
	}

	/**
	 * Start automatic product sync
	 *
	 * Initiates the chunked product sync process by getting all products that need syncing
	 * and scheduling them for batch processing.
	 *
	 * @since    1.0.0
	 * @access   public
	 *
	 * @hooks    Fires the following hooks:
	 *           - do_action('woo_odoo_before_auto_sync_start')
	 *           - do_action('woo_odoo_after_auto_sync_start', $total_products, $total_chunks)
	 */
	public function start_auto_sync() {
		$this->log( 'info', 'Starting automatic product stock sync', array( 'source' => 'woo-odoo-scheduler' ) );

		// Fire before auto sync hook
		do_action( 'woo_odoo_before_auto_sync_start' );

		// Clear any existing sync queue
		$this->clear_sync_queue();

		// Get all products that need syncing
		$product_ids = $this->get_products_for_sync();

		if ( empty( $product_ids ) ) {
			$this->log( 'info', 'No products found for syncing', array( 'source' => 'woo-odoo-scheduler' ) );
			return;
		}

		$total_products = count( $product_ids );
		$chunks = array_chunk( $product_ids, $this->chunk_size );
		$total_chunks = count( $chunks );

		$this->log( 'info', sprintf(
			'Found %d products to sync, divided into %d chunks of %d products each',
			$total_products,
			$total_chunks,
			$this->chunk_size
		), array( 'source' => 'woo-odoo-scheduler' ) );

		// Store sync metadata
		update_option( 'woo_odoo_auto_sync_meta', array(
			'start_time' => current_time( 'timestamp' ),
			'total_products' => $total_products,
			'total_chunks' => $total_chunks,
			'current_chunk' => 0,
			'processed_products' => 0,
			'successful_updates' => 0,
			'failed_updates' => 0,
			'status' => 'in_progress'
		) );

		// Schedule first chunk immediately
		$this->schedule_chunk_processing( 0, $chunks[0] );

		// Schedule remaining chunks with intervals
		for ( $i = 1; $i < $total_chunks; $i++ ) {
			$delay = $i * $this->chunk_interval * 60; // Convert minutes to seconds
			$this->schedule_chunk_processing( $i, $chunks[ $i ], $delay );
		}

		// Fire after auto sync start hook
		do_action( 'woo_odoo_after_auto_sync_start', $total_products, $total_chunks );

		$this->log( 'info', sprintf(
			'Scheduled %d chunks for processing with %d minute intervals',
			$total_chunks,
			$this->chunk_interval
		), array( 'source' => 'woo-odoo-scheduler' ) );
	}

	/**
	 * Get products that need syncing
	 *
	 * Returns all published WooCommerce products that have SKUs.
	 *
	 * @since    1.0.0
	 * @access   private
	 *
	 * @return   array    Array of product IDs
	 */
	private function get_products_for_sync() {
		$args = array(
			'post_type' => 'product',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key' => '_sku',
					'value' => '',
					'compare' => '!='
				)
			)
		);

		// Apply filters to allow customization
		$args = apply_filters( 'woo_odoo_integration_auto_sync_product_args', $args );

		return get_posts( $args );
	}

	/**
	 * Schedule chunk processing
	 *
	 * Schedules a single chunk of products to be processed at a specific time.
	 *
	 * @since    1.0.0
	 * @access   private
	 *
	 * @param    int      $chunk_index    Index of the chunk
	 * @param    array    $product_ids    Array of product IDs in this chunk
	 * @param    int      $delay         Delay in seconds from now (default: 0)
	 */
	private function schedule_chunk_processing( $chunk_index, $product_ids, $delay = 0 ) {
		$timestamp = time() + $delay;

		wp_schedule_single_event( $timestamp, 'woo_odoo_auto_sync_product_chunk', array(
			'chunk_index' => $chunk_index,
			'product_ids' => $product_ids
		) );

		$this->log( 'debug', sprintf(
			'Scheduled chunk %d (%d products) for processing at %s',
			$chunk_index,
			count( $product_ids ),
			date( 'Y-m-d H:i:s', $timestamp )
		), array( 'source' => 'woo-odoo-scheduler' ) );
	}

	/**
	 * Process a single chunk of products
	 *
	 * Synchronizes stock for a batch of products and updates progress tracking.
	 *
	 * @since    1.0.0
	 * @access   public
	 *
	 * @param    int      $chunk_index    Index of the chunk being processed
	 * @param    array    $product_ids    Array of product IDs to process
	 *
	 * @hooks    Fires the following hooks:
	 *           - do_action('woo_odoo_before_process_chunk', $chunk_index, $product_ids)
	 *           - do_action('woo_odoo_after_process_chunk', $chunk_index, $sync_results)
	 */
	public function process_sync_chunk( $chunk_index, $product_ids, $log_products = false ) {
		$this->log( 'info', sprintf(
			'Processing chunk %d with %d products',
			$chunk_index,
			count( $product_ids )
		), array( 'source' => 'woo-odoo-scheduler' ) );

		// Log detail produk jika diminta
		if ( $log_products && ! empty( $product_ids ) ) {
			foreach ( $product_ids as $pid ) {
				$sku = get_post_meta( $pid, '_sku', true );
				$msg = sprintf( 'Syncing product: ID=%d, SKU=%s', $pid, $sku );
				$this->log( 'info', $msg, array( 'source' => 'woo-odoo-scheduler' ) );
				/**
				 * Filter: woo_odoo_integration_cli_log_product
				 * Allows CLI to output product sync log to terminal
				 */
				apply_filters( 'woo_odoo_integration_cli_log_product', $msg );
			}
		}

		// Fire before process chunk hook
		do_action( 'woo_odoo_before_process_chunk', $chunk_index, $product_ids );

		// Get sync metadata
		$sync_meta = get_option( 'woo_odoo_auto_sync_meta', array() );

		if ( empty( $sync_meta ) || $sync_meta['status'] !== 'in_progress' ) {
			$this->log( 'warning', 'Sync metadata not found or sync not in progress', array( 'source' => 'woo-odoo-scheduler' ) );
			return;
		}

		// Perform stock sync for this chunk
		$sync_results = woo_odoo_integration_sync_product_stock( $product_ids );

		// Update progress tracking
		if ( ! is_wp_error( $sync_results ) ) {
			$sync_meta['current_chunk'] = $chunk_index + 1;
			$sync_meta['processed_products'] += count( $product_ids );
			$sync_meta['successful_updates'] += $sync_results['updated'];
			$sync_meta['failed_updates'] += $sync_results['errors'];

			// Check if this is the last chunk
			if ( $sync_meta['current_chunk'] >= $sync_meta['total_chunks'] ) {
				$sync_meta['status'] = 'completed';
				$sync_meta['end_time'] = current_time( 'timestamp' );

				$this->log( 'info', sprintf(
					'Auto sync completed. Total products: %d, Updated: %d, Errors: %d, Duration: %d minutes',
					$sync_meta['processed_products'],
					$sync_meta['successful_updates'],
					$sync_meta['failed_updates'],
					round( ( $sync_meta['end_time'] - $sync_meta['start_time'] ) / 60 )
				), array( 'source' => 'woo-odoo-scheduler' ) );

				// Fire completion hook
				do_action( 'woo_odoo_auto_sync_completed', $sync_meta );
			}
		} else {
			// Handle error
			$sync_meta['failed_updates'] += count( $product_ids );
			$sync_meta['processed_products'] += count( $product_ids );

			$this->log( 'error', sprintf(
				'Chunk %d failed: %s',
				$chunk_index,
				$sync_results->get_error_message()
			), array( 'source' => 'woo-odoo-scheduler' ) );
		}

		// Update metadata
		update_option( 'woo_odoo_auto_sync_meta', $sync_meta );

		// Fire after process chunk hook
		do_action( 'woo_odoo_after_process_chunk', $chunk_index, $sync_results );

		$this->log( 'info', sprintf(
			'Completed chunk %d. Progress: %d/%d chunks (%d%%)',
			$chunk_index,
			$sync_meta['current_chunk'],
			$sync_meta['total_chunks'],
			round( ( $sync_meta['current_chunk'] / $sync_meta['total_chunks'] ) * 100 )
		), array( 'source' => 'woo-odoo-scheduler' ) );
	}

	/**
	 * Clear sync queue
	 *
	 * Removes all scheduled chunk processing events and resets sync metadata.
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public function clear_sync_queue() {
		// Clear all scheduled chunk events
		$crons = _get_cron_array();

		if ( is_array( $crons ) ) {
			foreach ( $crons as $timestamp => $cron ) {
				if ( isset( $cron['woo_odoo_auto_sync_product_chunk'] ) ) {
					foreach ( $cron['woo_odoo_auto_sync_product_chunk'] as $key => $job ) {
						wp_unschedule_event( $timestamp, 'woo_odoo_auto_sync_product_chunk', $job['args'] );
					}
				}
			}
		}

		// Reset sync metadata
		delete_option( 'woo_odoo_auto_sync_meta' );

		$this->log( 'debug', 'Cleared sync queue and metadata', array( 'source' => 'woo-odoo-scheduler' ) );
	}

	/**
	 * Get sync status
	 *
	 * Returns current status of automatic sync process.
	 *
	 * @since    1.0.0
	 * @access   public
	 *
	 * @return   array|false    Sync metadata or false if no sync in progress
	 */
	public function get_sync_status() {
		return get_option( 'woo_odoo_auto_sync_meta', false );
	}

	/**
	 * Force start sync
	 *
	 * Manually triggers the automatic sync process, bypassing the scheduled time.
	 * Useful for testing or immediate sync needs.
	 *
	 * @since    1.0.0
	 * @access   public
	 *
	 * @return   bool    True if sync started successfully
	 */
	/**
	 * Force start sync
	 *
	 * Manually triggers the automatic sync process, bypassing the scheduled time.
	 * If $process_all_now = true, all chunks will be processed immediately (no interval, blocking).
	 *
	 * @param bool $process_all_now If true, process all chunks immediately (CLI mode)
	 * @return bool True if sync started successfully
	 */
	public function force_start_sync( $process_all_now = false ) {
		// Check if sync is already in progress
		$current_status = $this->get_sync_status();
		if ( $current_status && $current_status['status'] === 'in_progress' ) {
			$this->log( 'warning', 'Cannot start sync: another sync is already in progress', array( 'source' => 'woo-odoo-scheduler' ) );
			return false;
		}

		$this->log( 'info', 'Manually triggering automatic product sync', array( 'source' => 'woo-odoo-scheduler' ) );

		// Get all products that need syncing
		$product_ids = $this->get_products_for_sync();
		if ( empty( $product_ids ) ) {
			$this->log( 'info', 'No products found for syncing', array( 'source' => 'woo-odoo-scheduler' ) );
			return true;
		}

		$total_products = count( $product_ids );
		$chunks = array_chunk( $product_ids, $this->chunk_size );
		$total_chunks = count( $chunks );

		$this->log( 'info', sprintf(
			'Found %d products to sync, divided into %d chunks of %d products each',
			$total_products,
			$total_chunks,
			$this->chunk_size
		), array( 'source' => 'woo-odoo-scheduler' ) );

		// Store sync metadata
		update_option( 'woo_odoo_auto_sync_meta', array(
			'start_time' => current_time( 'timestamp' ),
			'total_products' => $total_products,
			'total_chunks' => $total_chunks,
			'current_chunk' => 0,
			'processed_products' => 0,
			'successful_updates' => 0,
			'failed_updates' => 0,
			'status' => 'in_progress'
		) );

		if ( $process_all_now ) {
			// Process all chunks immediately (blocking, CLI mode)
			for ( $i = 0; $i < $total_chunks; $i++ ) {
				$this->process_sync_chunk( $i, $chunks[ $i ], true );
			}
		} else {
			// Schedule first chunk immediately
			$this->schedule_chunk_processing( 0, $chunks[0] );
			// Schedule remaining chunks with intervals
			for ( $i = 1; $i < $total_chunks; $i++ ) {
				$delay = $i * $this->chunk_interval * 60; // Convert minutes to seconds
				$this->schedule_chunk_processing( $i, $chunks[ $i ], $delay );
			}
		}

		// Fire after auto sync start hook
		do_action( 'woo_odoo_after_auto_sync_start', $total_products, $total_chunks );

		$this->log( 'info', sprintf(
			$process_all_now
			? 'Processed %d chunks immediately (CLI mode)'
			: 'Scheduled %d chunks for processing with %d minute intervals',
			$total_chunks,
			$this->chunk_interval
		), array( 'source' => 'woo-odoo-scheduler' ) );

		return true;
	}

	/**
	 * Unschedule all sync events
	 *
	 * Removes all scheduled sync events. Used during plugin deactivation.
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public function unschedule_all_events() {
		// Unschedule daily sync
		wp_clear_scheduled_hook( 'woo_odoo_auto_sync_product_stock' );

		// Clear any remaining chunk processing events
		$this->clear_sync_queue();

		$this->log( 'info', 'Unscheduled all automatic sync events', array( 'source' => 'woo-odoo-scheduler' ) );
	}

	/**
	 * Update chunk settings
	 *
	 * Updates the chunk size and interval for processing.
	 *
	 * @since    1.0.0
	 * @access   public
	 *
	 * @param    int    $chunk_size       Number of products per chunk
	 * @param    int    $chunk_interval   Minutes between chunks
	 */
	public function update_chunk_settings( $chunk_size, $chunk_interval ) {
		$this->chunk_size = max( 1, intval( $chunk_size ) );
		$this->chunk_interval = max( 1, intval( $chunk_interval ) );

		$this->log( 'info', sprintf(
			'Updated chunk settings: size=%d, interval=%d minutes',
			$this->chunk_size,
			$this->chunk_interval
		), array( 'source' => 'woo-odoo-scheduler' ) );
	}
	// ...existing code...
	/**
	 * Sync Odoo products to WooCommerce
	 *
	 * Mendapatkan data produk dari Odoo, lalu insert/update ke WooCommerce (variable/simple, gambar, meta, kategori, dsb)
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param array $product_groups   Data produk Odoo (hasil dari woo_odoo_integration_api_get_product_groups)
	 * @return array   Hasil proses sync (jumlah updated, created, error, log detail)
	 */
	public function sync_odoo_products_to_wc( $product_groups ) {
		$logger = $this->get_logger();
		$results = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'errors' => 0,
			'details' => array(),
		);

		// Mapping attribute Odoo ke WooCommerce (bisa diubah via filter)
		$attribute_map = apply_filters( 'woo_odoo_integration_product_attribute_map', array(
			'type_id' => 'Type',
			'material_id' => 'Material',
			'color_id' => 'Color',
			'size_id' => 'Size',
			'location' => 'Location',
		) );

		if ( $logger ) {
			$logger->info( 'Sync Odoo products to WooCommerce started', array( 'source' => 'woo-odoo-product-sync' ) );
		}

		if ( ! is_array( $product_groups ) || empty( $product_groups ) ) {
			$results['errors']++;
			$results['details'][] = array( 'error' => 'No product groups data received from Odoo.' );
			if ( $logger ) {
				$logger->error( 'No product groups data received from Odoo.', array( 'source' => 'woo-odoo-product-sync' ) );
			}
			return $results;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		foreach ( $product_groups as $group ) {
			$uuid = isset( $group['uuid'] ) ? $group['uuid'] : '';
			if ( empty( $uuid ) ) {
				$results['skipped']++;
				$results['details'][] = array( 'skipped' => 'Missing UUID', 'group' => $group );
				continue;
			}


			// Cek produk berdasarkan SKU (UUID)
			$product_id = wc_get_product_id_by_sku( $uuid );
			$is_update = false;
			$product = false;
			$is_variable = ( isset( $group['variants'] ) && is_array( $group['variants'] ) && count( $group['variants'] ) > 0 );

			if ( $product_id ) {
				$product = wc_get_product( $product_id );
				$is_update = true;
				// Jika produk sudah ada tapi bukan variable, dan sekarang ada variants, upgrade ke variable
				if ( $is_variable && $product && $product->get_type() !== 'variable' ) {
					$product = new WC_Product_Variable( $product_id );
				}
			} else {
				if ( $is_variable ) {
					$product = new WC_Product_Variable();
				} else {
					$product = new WC_Product_Simple();
				}
				$product->set_sku( $uuid );
			}

			// Set nama produk
			if ( isset( $group['name'] ) ) {
				$product->set_name( $group['name'] );
			}

			// Set deskripsi
			if ( isset( $group['description'] ) ) {
				$product->set_description( $group['description'] );
			}
			if ( isset( $group['short_description'] ) ) {
				$product->set_short_description( $group['short_description'] );
			}

			// Set harga (hanya untuk simple, variable harga diambil dari variation)
			if ( ! $is_variable && isset( $group['price'] ) ) {
				$product->set_regular_price( $group['price'] );
			}

			// Set stok (hanya untuk simple, variable stok diambil dari variation)
			if ( ! $is_variable && isset( $group['stock_quantity'] ) ) {
				$product->set_manage_stock( true );
				$product->set_stock_quantity( $group['stock_quantity'] );
			}

			// Set kategori (by name, create if not exist)
			if ( isset( $group['category'] ) && ! empty( $group['category'] ) ) {
				$cat_name = $group['category'];
				$term = get_term_by( 'name', $cat_name, 'product_cat' );
				if ( ! $term ) {
					$term = wp_insert_term( $cat_name, 'product_cat' );
					if ( is_wp_error( $term ) ) {
						$results['errors']++;
						$results['details'][] = array( 'error' => 'Failed to create category', 'category' => $cat_name, 'msg' => $term->get_error_message() );
						if ( $logger ) {
							$logger->error( 'Failed to create category: ' . $cat_name . ' - ' . $term->get_error_message(), array( 'source' => 'woo-odoo-product-sync' ) );
						}
						continue;
					}
					$term_id = $term['term_id'];
				} else {
					$term_id = $term->term_id;
				}
				$product->set_category_ids( array( $term_id ) );
			}

			// Set gambar utama (featured image)
			if ( isset( $group['image'] ) && ! empty( $group['image'] ) ) {
				$image_url = $group['image'];
				$attach_id = $this->maybe_sideload_image( $image_url, $product_id ? $product_id : 0 );
				if ( $attach_id ) {
					$product->set_image_id( $attach_id );
				}
			}

			// Set gallery images
			if ( isset( $group['gallery'] ) && is_array( $group['gallery'] ) ) {
				$gallery_ids = array();
				foreach ( $group['gallery'] as $img_url ) {
					$attach_id = $this->maybe_sideload_image( $img_url, $product_id ? $product_id : 0 );
					if ( $attach_id ) {
						$gallery_ids[] = $attach_id;
					}
				}
				if ( ! empty( $gallery_ids ) ) {
					$product->set_gallery_image_ids( $gallery_ids );
				}
			}

			// Set attributes (menggunakan filter mapping)
			$attributes = array();
			$variation_attributes = array();
			if ( $is_variable ) {
				// Kumpulkan semua kombinasi attribute dari variants
				foreach ( $group['variants'] as $variant ) {
					foreach ( $attribute_map as $odoo_key => $wc_label ) {
						if ( isset( $variant[ $odoo_key ] ) && ! empty( $variant[ $odoo_key ] ) ) {
							$attr_value = $variant[ $odoo_key ];
							$taxonomy = wc_sanitize_taxonomy_name( $wc_label );
							// Pastikan attribute terdaftar di WooCommerce
							if ( ! taxonomy_exists( 'pa_' . $taxonomy ) ) {
								wc_create_attribute( array( 'name' => $wc_label, 'slug' => $taxonomy, 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false ) );
								register_taxonomy( 'pa_' . $taxonomy, 'product', array( 'hierarchical' => false ) );
							}
							// Set attribute value (create term if not exist)
							$term = get_term_by( 'name', $attr_value, 'pa_' . $taxonomy );
							if ( ! $term ) {
								$term = wp_insert_term( $attr_value, 'pa_' . $taxonomy );
								if ( is_wp_error( $term ) ) {
									$results['errors']++;
									$results['details'][] = array( 'error' => 'Failed to create attribute term', 'attribute' => $wc_label, 'value' => $attr_value, 'msg' => $term->get_error_message() );
									if ( $logger ) {
										$logger->error( 'Failed to create attribute term: ' . $wc_label . ' - ' . $attr_value . ' - ' . $term->get_error_message(), array( 'source' => 'woo-odoo-product-sync' ) );
									}
									continue;
								}
								$term_id = $term['term_id'];
							} else {
								$term_id = $term->term_id;
							}
							wp_set_object_terms( $product->get_id(), $attr_value, 'pa_' . $taxonomy, true );
							// Kumpulkan semua value untuk attribute ini
							if ( ! isset( $variation_attributes[ 'pa_' . $taxonomy ] ) ) {
								$variation_attributes[ 'pa_' . $taxonomy ] = array();
							}
							if ( ! in_array( $attr_value, $variation_attributes[ 'pa_' . $taxonomy ] ) ) {
								$variation_attributes[ 'pa_' . $taxonomy ][] = $attr_value;
							}
						}
					}
				}
				// Set attributes ke produk variable
				foreach ( $variation_attributes as $tax => $values ) {
					$attributes[ $tax ] = array(
						'name' => $tax,
						'value' => implode( ' | ', $values ),
						'is_visible' => 1,
						'is_variation' => 1,
						'is_taxonomy' => 1,
					);
				}
				if ( ! empty( $attributes ) ) {
					$product->set_attributes( $attributes );
				}
			} else {
				// Produk simple, mapping attribute dari group
				foreach ( $attribute_map as $odoo_key => $wc_label ) {
					if ( isset( $group[ $odoo_key ] ) && ! empty( $group[ $odoo_key ] ) ) {
						$attr_value = $group[ $odoo_key ];
						$taxonomy = wc_sanitize_taxonomy_name( $wc_label );
						if ( ! taxonomy_exists( 'pa_' . $taxonomy ) ) {
							wc_create_attribute( array( 'name' => $wc_label, 'slug' => $taxonomy, 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false ) );
							register_taxonomy( 'pa_' . $taxonomy, 'product', array( 'hierarchical' => false ) );
						}
						$term = get_term_by( 'name', $attr_value, 'pa_' . $taxonomy );
						if ( ! $term ) {
							$term = wp_insert_term( $attr_value, 'pa_' . $taxonomy );
							if ( is_wp_error( $term ) ) {
								$results['errors']++;
								$results['details'][] = array( 'error' => 'Failed to create attribute term', 'attribute' => $wc_label, 'value' => $attr_value, 'msg' => $term->get_error_message() );
								if ( $logger ) {
									$logger->error( 'Failed to create attribute term: ' . $wc_label . ' - ' . $attr_value . ' - ' . $term->get_error_message(), array( 'source' => 'woo-odoo-product-sync' ) );
								}
								continue;
							}
							$term_id = $term['term_id'];
						} else {
							$term_id = $term->term_id;
						}
						wp_set_object_terms( $product->get_id(), $attr_value, 'pa_' . $taxonomy, true );
						$attributes[ 'pa_' . $taxonomy ] = array(
							'name' => 'pa_' . $taxonomy,
							'value' => $attr_value,
							'is_visible' => 1,
							'is_variation' => 0,
							'is_taxonomy' => 1,
						);
					}
				}
				if ( ! empty( $attributes ) ) {
					$product->set_attributes( $attributes );
				}
			}

			// Set meta data (custom fields)
			if ( isset( $group['meta'] ) && is_array( $group['meta'] ) ) {
				foreach ( $group['meta'] as $meta_key => $meta_value ) {
					$product->update_meta_data( $meta_key, $meta_value );
				}
			}

			// Save product
			try {
				$product_id_saved = $product->save();
				if ( $is_variable ) {
					// Sinkronisasi variations
					$existing_variations = array();
					$children = $product->get_children();
					foreach ( $children as $child_id ) {
						$existing_variations[] = $child_id;
					}
					foreach ( $group['variants'] as $variant ) {
						$variant_sku = isset( $variant['uuid'] ) ? $variant['uuid'] : '';
						if ( empty( $variant_sku ) )
							continue;
						// Cari variation by SKU
						$variation_id = wc_get_product_id_by_sku( $variant_sku );
						$variation = false;
						if ( $variation_id && in_array( $variation_id, $existing_variations ) ) {
							$variation = new WC_Product_Variation( $variation_id );
						} else {
							$variation = new WC_Product_Variation();
							$variation->set_parent_id( $product_id_saved );
							$variation->set_sku( $variant_sku );
						}
						// Set attributes for this variation
						$var_attr = array();
						foreach ( $attribute_map as $odoo_key => $wc_label ) {
							if ( isset( $variant[ $odoo_key ] ) && ! empty( $variant[ $odoo_key ] ) ) {
								$taxonomy = 'pa_' . wc_sanitize_taxonomy_name( $wc_label );
								$var_attr[ $taxonomy ] = $variant[ $odoo_key ];
							}
						}
						$variation->set_attributes( $var_attr );
						// Set harga
						if ( isset( $variant['pricelists'][0]['sale_price'] ) ) {
							$variation->set_regular_price( $variant['pricelists'][0]['sale_price'] );
						}
						// Set stok
						if ( isset( $variant['quantity'] ) ) {
							$variation->set_manage_stock( true );
							$variation->set_stock_quantity( $variant['quantity'] );
						}
						// Set gambar utama variation (ambil gambar pertama dari images)
						if ( isset( $variant['images'][0]['url'] ) ) {
							$attach_id = $this->maybe_sideload_image( $variant['images'][0]['url'], $product_id_saved );
							if ( $attach_id ) {
								$variation->set_image_id( $attach_id );
							}
						}
						// Simpan variation
						$variation->save();
					}
				}
				if ( $is_update ) {
					$results['updated']++;
					$results['details'][] = array( 'updated' => $uuid, 'product_id' => $product_id_saved );
					if ( $logger ) {
						$logger->info( 'Updated product: ' . $uuid, array( 'source' => 'woo-odoo-product-sync', 'product_id' => $product_id_saved ) );
					}
				} else {
					$results['created']++;
					$results['details'][] = array( 'created' => $uuid, 'product_id' => $product_id_saved );
					if ( $logger ) {
						$logger->info( 'Created product: ' . $uuid, array( 'source' => 'woo-odoo-product-sync', 'product_id' => $product_id_saved ) );
					}
				}
			} catch (Exception $e) {
				$results['errors']++;
				$results['details'][] = array( 'error' => 'Failed to save product', 'uuid' => $uuid, 'msg' => $e->getMessage() );
				if ( $logger ) {
					$logger->error( 'Failed to save product: ' . $uuid . ' - ' . $e->getMessage(), array( 'source' => 'woo-odoo-product-sync' ) );
				}
			}
		}

		if ( $logger ) {
			$logger->info( 'Sync Odoo products to WooCommerce completed', array( 'source' => 'woo-odoo-product-sync', 'result' => $results ) );
		}

		return $results;

	}

	/**
	 * Download and sideload image if not already in media library
	 *
	 * @param string $image_url
	 * @param int $post_id
	 * @return int|false Attachment ID or false
	 */
	private function maybe_sideload_image( $image_url, $post_id = 0 ) {
		if ( empty( $image_url ) ) {
			return false;
		}
		// Check if image already exists in media library by URL
		$attachment_id = $this->get_attachment_id_by_url( $image_url );
		if ( $attachment_id ) {
			return $attachment_id;
		}
		// Download and sideload
		$tmp = download_url( $image_url );
		if ( is_wp_error( $tmp ) ) {
			return false;
		}
		$file_array = array(
			'name' => basename( $image_url ),
			'tmp_name' => $tmp,
		);
		$attach_id = media_handle_sideload( $file_array, $post_id );
		if ( is_wp_error( $attach_id ) ) {
			@unlink( $tmp );
			return false;
		}
		return $attach_id;
	}

	/**
	 * Get attachment ID by URL
	 *
	 * @param string $image_url
	 * @return int|false
	 */
	private function get_attachment_id_by_url( $image_url ) {
		global $wpdb;
		$query = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid=%s AND post_type='attachment'", $image_url );
		$id = $wpdb->get_var( $query );
		return $id ? intval( $id ) : false;
	}
}

