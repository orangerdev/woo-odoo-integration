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
		$this->chunk_size = get_option('woo_odoo_auto_sync_chunk_size'); //apply_filters( 'woo_odoo_integration_auto_sync_chunk_size', $this->chunk_size );
		$this->chunk_interval = get_option('woo_odoo_auto_sync_chunk_interval'); //apply_filters( 'woo_odoo_integration_auto_sync_chunk_interval', $this->chunk_interval );

		add_action( 'init', function() {
		    if ( isset($_GET['test_sync']) && current_user_can('manage_options') ) {
		        $this->schedule_immediate_test();
		        echo 'Manual test schedule created!';
		        exit;
		    }
		}); // /?test_sync=1

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
		if ( ! wp_next_scheduled( 'woo_odoo_auto_sync_product' ) ||
			 ! wp_next_scheduled( 'woo_odoo_auto_sync_product_stock' ) ||
	         ! wp_next_scheduled( 'woo_odoo_auto_sync_product_price' ) ) {
	        $this->schedule_daily_sync();
	    }

		// Tambahkan ini di constructor atau init function
		add_action( 'woo_odoo_auto_sync_product', array( $this, 'start_auto_sync_product' ) );
		add_action( 'woo_odoo_auto_sync_product_stock', array( $this, 'start_auto_sync_product_stock' ) );
		add_action( 'woo_odoo_auto_sync_product_price', array( $this, 'start_auto_sync_product_price' ) );

		// add_action( 'woo_odoo_auto_sync_product_chunk', array( $this, 'process_sync_chunk' ), 10, 3 );
		add_action( 'woo_odoo_auto_sync_product_chunk_product', [ $this, 'process_product_chunk' ], 10, 3 );
		add_action( 'woo_odoo_auto_sync_product_chunk_stock', [ $this, 'process_stock_chunk' ], 10, 3 );
		add_action( 'woo_odoo_auto_sync_product_chunk_price', [ $this, 'process_price_chunk' ], 10, 3 );

		$this->log( 'debug', 'Scheduler initialized', array( 'source' => 'woo-odoo-scheduler' ) );
	}

	public function start_auto_sync_product() {
	    update_option('woo_odoo_auto_sync_mode', 'product');
	    $this->start_auto_sync('product');
	}

	public function start_auto_sync_product_stock() {
	    update_option('woo_odoo_auto_sync_mode', 'stock');
	    $this->start_auto_sync('stock');
	}

	public function start_auto_sync_product_price() {
	    update_option('woo_odoo_auto_sync_mode', 'price');
	    $this->start_auto_sync('price');
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
	    // Ambil timezone WP
	    $timezone_string = get_option( 'timezone_string' );
	    if ( ! $timezone_string ) {
	        $gmt_offset = get_option( 'gmt_offset', 0 );
	        $timezone_string = $this->get_timezone_from_offset( $gmt_offset );
	    }

	    try {
	        $timezone = new DateTimeZone( $timezone_string );
	    } catch (Exception $e) {
	        $timezone = new DateTimeZone( 'UTC' );
	        $this->log( 'warning', 'Invalid timezone, using UTC: ' . $e->getMessage(), array( 'source' => 'woo-odoo-scheduler' ) );
	    }

	    // Hitung waktu tengah malam berikutnya
	    $now = new DateTime( 'now', $timezone );
	    $midnight = new DateTime( 'tomorrow midnight', $timezone );
	    $midnight->setTimezone( new DateTimeZone( 'UTC' ) );
	    $timestamp = $midnight->getTimestamp();

	    // Event 1: Product sync (jam 00:00)
	    if ( ! wp_next_scheduled( 'woo_odoo_auto_sync_product' ) ) {
	        wp_schedule_event( $timestamp, 'daily', 'woo_odoo_auto_sync_product' );
	    }

	    // Event 2: Stock sync (jam 02:00)
	    if ( ! wp_next_scheduled( 'woo_odoo_auto_sync_product_stock' ) ) {
	        wp_schedule_event( $timestamp + 2 * HOUR_IN_SECONDS, 'daily', 'woo_odoo_auto_sync_product_stock' );
	    }

	    // Event 3: Price sync (jam 04:00)
	    if ( ! wp_next_scheduled( 'woo_odoo_auto_sync_product_price' ) ) {
	        wp_schedule_event( $timestamp + 4 * HOUR_IN_SECONDS, 'daily', 'woo_odoo_auto_sync_product_price' );
	    }

	    $this->log( 'info', sprintf(
	        'Scheduled daily sync events: Product(00:00), Stock(+2h), Price(+4h) [%s timezone]',
	        $timezone_string
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
	public function start_auto_sync( $mode = 'stock' ) {
	    $this->log( 'info', 'Starting automatic product ' . $mode . ' sync', array( 'source' => 'woo-odoo-scheduler' ) );

	    do_action( 'woo_odoo_before_auto_sync_start' );

	    $this->clear_sync_queue();

	    if($mode === 'product') :
			$product_groups = woo_odoo_integration_api_get_product_groups();
		    // error_log(print_r($product_groups, true));

		    $total_products = count( $product_groups );
		    $chunks = array_chunk( $product_groups, $this->chunk_size );
		    $total_chunks = count( $chunks );

		    $this->log( 'info', sprintf(
		        'Found %d products to sync, divided into %d chunks of %d products each',
		        $total_products,
		        $total_chunks,
		        $this->chunk_size
		    ), array( 'source' => 'woo-odoo-scheduler' ) );

		    update_option( 'woo_odoo_auto_sync_meta', array(
		        'start_time' => current_time( 'timestamp' ),
		        'total_products' => $total_products,
		        'total_chunks' => $total_chunks,
		        'current_chunk' => 0,
		        'processed_products' => 0,
		        'successful_updates' => 0,
		        'failed_updates' => 0,
		        'status' => 'in_progress',
		        'mode' => $mode, // optional, buat reference
		    ) );

		    // Schedule chunks with mode passed
		    $this->schedule_chunk_processing( 0, $chunks[0], 0, $mode );
		    
		    for ( $i = 1; $i < $total_chunks; $i++ ) {
		        $delay = $i * $this->chunk_interval * 60;
		        $this->schedule_chunk_processing( $i, $chunks[$i], $delay, $mode );
		    }

		    do_action( 'woo_odoo_after_auto_sync_start', $total_products, $total_chunks );

		    $this->log( 'info', sprintf(
		        'Scheduled %d chunks for processing with %d minute intervals',
		        $total_chunks,
		        $this->chunk_interval
		    ), array( 'source' => 'woo-odoo-scheduler' ) );
		else:
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

		    update_option( 'woo_odoo_auto_sync_meta', array(
		        'start_time' => current_time( 'timestamp' ),
		        'total_products' => $total_products,
		        'total_chunks' => $total_chunks,
		        'current_chunk' => 0,
		        'processed_products' => 0,
		        'successful_updates' => 0,
		        'failed_updates' => 0,
		        'status' => 'in_progress',
		        'mode' => $mode, // optional, buat reference
		    ) );

		    // Schedule chunks with mode passed
		    $this->schedule_chunk_processing( 0, $chunks[0], 0, $mode );

		    for ( $i = 1; $i < $total_chunks; $i++ ) {
		        $delay = $i * $this->chunk_interval * 60;
		        $this->schedule_chunk_processing( $i, $chunks[$i], $delay, $mode );
		    }

		    do_action( 'woo_odoo_after_auto_sync_start', $total_products, $total_chunks );

		    $this->log( 'info', sprintf(
		        'Scheduled %d chunks for processing with %d minute intervals',
		        $total_chunks,
		        $this->chunk_interval
		    ), array( 'source' => 'woo-odoo-scheduler' ) );
		endif;
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
	private function schedule_chunk_processing( $chunk_index, $product_ids, $delay = 0, $mode = 'stock' ) {
	    $timestamp = time() + $delay;

	    // Tentukan hook berdasarkan mode
	    switch ( $mode ) {
	        case 'price':
	            $hook = 'woo_odoo_auto_sync_product_chunk_price';
	            break;
	        case 'product':
	            $hook = 'woo_odoo_auto_sync_product_chunk_product';
	            break;
	        case 'stock':
	        default:
	            $hook = 'woo_odoo_auto_sync_product_chunk_stock';
	            break;
	    }

	    wp_schedule_single_event( $timestamp, $hook, array(
	        $chunk_index,
	        $product_ids,
	        false // $log_products
	    ));

	    $this->log( 'debug', sprintf(
	        'Scheduled %s chunk %d (%d products) for processing at %s',
	        strtoupper($mode),
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
	// public function process_sync_chunk( $chunk_index, $product_ids, $log_products = false ) {
	// 	$this->log( 'info', sprintf(
	// 		'Processing chunk %d with %d products',
	// 		$chunk_index,
	// 		count( $product_ids )
	// 	), array( 'source' => 'woo-odoo-scheduler' ) );

	// 	// Log detail produk jika diminta
	// 	if ( $log_products && ! empty( $product_ids ) ) {
	// 		foreach ( $product_ids as $pid ) {
	// 			$sku = get_post_meta( $pid, '_sku', true );
	// 			$msg = sprintf( 'Syncing product: ID=%d, SKU=%s', $pid, $sku );
	// 			$this->log( 'info', $msg, array( 'source' => 'woo-odoo-scheduler' ) );
	// 			/**
	// 			 * Filter: woo_odoo_integration_cli_log_product
	// 			 * Allows CLI to output product sync log to terminal
	// 			 */
	// 			apply_filters( 'woo_odoo_integration_cli_log_product', $msg );
	// 		}
	// 	}

	// 	// Fire before process chunk hook
	// 	do_action( 'woo_odoo_before_process_chunk', $chunk_index, $product_ids );

	// 	// Get sync metadata
	// 	$sync_meta = get_option( 'woo_odoo_auto_sync_meta', array() );

	// 	if ( empty( $sync_meta ) || $sync_meta['status'] !== 'in_progress' ) {
	// 		$this->log( 'warning', 'Sync metadata not found or sync not in progress', array( 'source' => 'woo-odoo-scheduler' ) );
	// 		return;
	// 	}

	// 	// Perform stock sync for this chunk
	// 	$sync_results = woo_odoo_integration_sync_product_stock( $product_ids );

	// 	WP_CLI::log( print_r( "AHAYUSSS", true ) );
	// 	WP_CLI::log( print_r( $sync_results, true ) );

	// 	// Update progress tracking
	// 	if ( ! is_wp_error( $sync_results ) ) {
	// 		$sync_meta['current_chunk'] = $chunk_index + 1;
	// 		$sync_meta['processed_products'] += count( $product_ids );
	// 		$sync_meta['successful_updates'] += $sync_results['updated'];
	// 		$sync_meta['failed_updates'] += $sync_results['errors'];

	// 		// Check if this is the last chunk
	// 		if ( $sync_meta['current_chunk'] >= $sync_meta['total_chunks'] ) {
	// 			$sync_meta['status'] = 'completed';
	// 			$sync_meta['end_time'] = current_time( 'timestamp' );

	// 			$this->log( 'info', sprintf(
	// 				'Auto sync completed. Total products: %d, Updated: %d, Errors: %d, Duration: %d minutes',
	// 				$sync_meta['processed_products'],
	// 				$sync_meta['successful_updates'],
	// 				$sync_meta['failed_updates'],
	// 				round( ( $sync_meta['end_time'] - $sync_meta['start_time'] ) / 60 )
	// 			), array( 'source' => 'woo-odoo-scheduler' ) );

	// 			// Fire completion hook
	// 			do_action( 'woo_odoo_auto_sync_completed', $sync_meta );
	// 		}
	// 	} else {
	// 		// Handle error
	// 		$sync_meta['failed_updates'] += count( $product_ids );
	// 		$sync_meta['processed_products'] += count( $product_ids );

	// 		$this->log( 'error', sprintf(
	// 			'Chunk %d failed: %s',
	// 			$chunk_index,
	// 			$sync_results->get_error_message()
	// 		), array( 'source' => 'woo-odoo-scheduler' ) );
	// 	}

	// 	// Perform stock sync for this chunk
	// 	$sync_price_results = woo_odoo_integration_sync_product_price( $product_ids );

	// 	WP_CLI::log( print_r( "AHAYUSSSHHHHHHHHH", true ) );
	// 	WP_CLI::log( print_r( $sync_price_results, true ) );

	// 	// Update progress tracking
	// 	if ( ! is_wp_error( $sync_price_results ) ) {
	// 		$sync_meta['current_chunk'] = $chunk_index + 1;
	// 		$sync_meta['processed_products'] += count( $product_ids );
	// 		$sync_meta['successful_updates'] += $sync_price_results['updated'];
	// 		$sync_meta['failed_updates'] += $sync_price_results['errors'];

	// 		// Check if this is the last chunk
	// 		if ( $sync_meta['current_chunk'] >= $sync_meta['total_chunks'] ) {
	// 			$sync_meta['status'] = 'completed';
	// 			$sync_meta['end_time'] = current_time( 'timestamp' );

	// 			$this->log( 'info', sprintf(
	// 				'Auto sync completed. Total products: %d, Updated: %d, Errors: %d, Duration: %d minutes',
	// 				$sync_meta['processed_products'],
	// 				$sync_meta['successful_updates'],
	// 				$sync_meta['failed_updates'],
	// 				round( ( $sync_meta['end_time'] - $sync_meta['start_time'] ) / 60 )
	// 			), array( 'source' => 'woo-odoo-scheduler' ) );

	// 			// Fire completion hook
	// 			do_action( 'woo_odoo_auto_sync_completed', $sync_meta );
	// 		}
	// 	} else {
	// 		// Handle error
	// 		$sync_meta['failed_updates'] += count( $product_ids );
	// 		$sync_meta['processed_products'] += count( $product_ids );

	// 		$this->log( 'error', sprintf(
	// 			'Chunk %d failed: %s',
	// 			$chunk_index,
	// 			$sync_price_results->get_error_message()
	// 		), array( 'source' => 'woo-odoo-scheduler' ) );
	// 	}

	// 	// Update metadata
	// 	update_option( 'woo_odoo_auto_sync_meta', $sync_meta );

	// 	// Fire after process chunk hook
	// 	do_action( 'woo_odoo_after_process_chunk', $chunk_index, $sync_results );
	// 	do_action( 'woo_odoo_after_process_chunk', $chunk_index, $sync_price_results );

	// 	$this->log( 'info', sprintf(
	// 		'Completed chunk %d. Progress: %d/%d chunks (%d%%)',
	// 		$chunk_index,
	// 		$sync_meta['current_chunk'],
	// 		$sync_meta['total_chunks'],
	// 		round( ( $sync_meta['current_chunk'] / $sync_meta['total_chunks'] ) * 100 )
	// 	), array( 'source' => 'woo-odoo-scheduler' ) );
	// }

	public function process_sync_chunk( $chunk_index, $product_ids, $log_products = false, $mode = 'stock' ) {
	    $this->log('info', sprintf(
	        'Processing chunk %d with %d products for mode %s',
	        $chunk_index,
	        count($product_ids),
	        strtoupper($mode)
	    ), ['source' => 'woo-odoo-scheduler']);

	    if ($log_products && ! empty($product_ids)) {
	        foreach ($product_ids as $pid) {
	            $sku = get_post_meta($pid, '_sku', true);
	            $msg = sprintf('Syncing product: ID=%d, SKU=%s', $pid, $sku);
	            $this->log('info', $msg, ['source' => 'woo-odoo-scheduler']);
	            apply_filters('woo_odoo_integration_cli_log_product', $msg);
	        }
	    }

	    do_action('woo_odoo_before_process_chunk', $chunk_index, $product_ids);

	    $sync_meta = get_option('woo_odoo_auto_sync_meta', []);
	    if (empty($sync_meta) || $sync_meta['status'] !== 'in_progress') {
	        $this->log('warning', 'Sync metadata not found or sync not in progress', ['source' => 'woo-odoo-scheduler']);
	        return;
	    }

	    if ($mode === 'product') {
	    	$product_groups = woo_odoo_integration_api_get_product_groups();
	        $sync_product_results = $this->sync_odoo_products_to_wc($product_groups);

	        if (! is_wp_error($sync_product_results)) {
	            $sync_meta['current_chunk']   = $chunk_index + 1;
	            $sync_meta['processed_products'] += count($product_ids);
	            $sync_meta['successful_updates'] += $sync_product_results['updated'];
	            $sync_meta['failed_updates']    += $sync_product_results['errors'];

	            if ($sync_meta['current_chunk'] >= $sync_meta['total_chunks']) {
	                $sync_meta['status']   = 'completed';
	                $sync_meta['end_time'] = current_time('timestamp');
	                $this->log('info', sprintf(
	                    'Auto price sync completed. Total products: %d, Updated: %d, Errors: %d, Duration: %d minutes',
	                    $sync_meta['processed_products'],
	                    $sync_meta['successful_updates'],
	                    $sync_meta['failed_updates'],
	                    round(($sync_meta['end_time'] - $sync_meta['start_time']) / 60)
	                ), ['source' => 'woo-odoo-scheduler']);
	                do_action('woo_odoo_auto_sync_completed', $sync_meta);
	                delete_option('woo_odoo_auto_sync_mode');
	            }
	        } else {
	            $sync_meta['failed_updates']    += count($product_ids);
	            $sync_meta['processed_products'] += count($product_ids);
	            $this->log('error', sprintf(
	                'Chunk %d price sync failed: %s',
	                $chunk_index,
	                $sync_product_results->get_error_message()
	            ), ['source' => 'woo-odoo-scheduler']);
	        }

	        do_action('woo_odoo_after_process_chunk', $chunk_index, $sync_product_results);
	    }

	    if ($mode === 'stock') {
	        $sync_results = woo_odoo_integration_sync_product_stock($product_ids);

	        if (! is_wp_error($sync_results)) {
	            $sync_meta['current_chunk']   = $chunk_index + 1;
	            $sync_meta['processed_products'] += count($product_ids);
	            $sync_meta['successful_updates'] += $sync_results['updated'];
	            $sync_meta['failed_updates']    += $sync_results['errors'];

	            if ($sync_meta['current_chunk'] >= $sync_meta['total_chunks']) {
	                $sync_meta['status']   = 'completed';
	                $sync_meta['end_time'] = current_time('timestamp');
	                $this->log('info', sprintf(
	                    'Auto stock sync completed. Total products: %d, Updated: %d, Errors: %d, Duration: %d minutes',
	                    $sync_meta['processed_products'],
	                    $sync_meta['successful_updates'],
	                    $sync_meta['failed_updates'],
	                    round(($sync_meta['end_time'] - $sync_meta['start_time']) / 60)
	                ), ['source' => 'woo-odoo-scheduler']);
	                do_action('woo_odoo_auto_sync_completed', $sync_meta);
	                delete_option('woo_odoo_auto_sync_mode');
	            }
	        } else {
	            $sync_meta['failed_updates']    += count($product_ids);
	            $sync_meta['processed_products'] += count($product_ids);
	            $this->log('error', sprintf(
	                'Chunk %d stock sync failed: %s',
	                $chunk_index,
	                $sync_results->get_error_message()
	            ), ['source' => 'woo-odoo-scheduler']);
	        }

	        do_action('woo_odoo_after_process_chunk', $chunk_index, $sync_results);
	    }

	    if ($mode === 'price') {
	        $sync_price_results = woo_odoo_integration_sync_product_price($product_ids);

	        if (! is_wp_error($sync_price_results)) {
	            $sync_meta['current_chunk']   = $chunk_index + 1;
	            $sync_meta['processed_products'] += count($product_ids);
	            $sync_meta['successful_updates'] += $sync_price_results['updated'];
	            $sync_meta['failed_updates']    += $sync_price_results['errors'];

	            if ($sync_meta['current_chunk'] >= $sync_meta['total_chunks']) {
	                $sync_meta['status']   = 'completed';
	                $sync_meta['end_time'] = current_time('timestamp');
	                $this->log('info', sprintf(
	                    'Auto price sync completed. Total products: %d, Updated: %d, Errors: %d, Duration: %d minutes',
	                    $sync_meta['processed_products'],
	                    $sync_meta['successful_updates'],
	                    $sync_meta['failed_updates'],
	                    round(($sync_meta['end_time'] - $sync_meta['start_time']) / 60)
	                ), ['source' => 'woo-odoo-scheduler']);
	                do_action('woo_odoo_auto_sync_completed', $sync_meta);
	                delete_option('woo_odoo_auto_sync_mode');
	            }
	        } else {
	            $sync_meta['failed_updates']    += count($product_ids);
	            $sync_meta['processed_products'] += count($product_ids);
	            $this->log('error', sprintf(
	                'Chunk %d price sync failed: %s',
	                $chunk_index,
	                $sync_price_results->get_error_message()
	            ), ['source' => 'woo-odoo-scheduler']);
	        }

	        do_action('woo_odoo_after_process_chunk', $chunk_index, $sync_price_results);
	    }

	    update_option('woo_odoo_auto_sync_meta', $sync_meta);

	    $this->log('info', sprintf(
	        'Completed chunk %d. Progress: %d/%d chunks (%d%%)',
	        $chunk_index,
	        $sync_meta['current_chunk'],
	        $sync_meta['total_chunks'],
	        round(($sync_meta['current_chunk'] / $sync_meta['total_chunks']) * 100)
	    ), ['source' => 'woo-odoo-scheduler']);
	}

	public function process_product_chunk( $chunk_index, $product_ids, $log_products = false ) {
	    $this->process_sync_chunk( $chunk_index, $product_ids, $log_products, 'product' );
	}

	public function process_stock_chunk( $chunk_index, $product_ids, $log_products = false ) {
	    $this->process_sync_chunk( $chunk_index, $product_ids, $log_products, 'stock' );
	}

	public function process_price_chunk( $chunk_index, $product_ids, $log_products = false ) {
	    $this->process_sync_chunk( $chunk_index, $product_ids, $log_products, 'price' );
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
				if ( isset( $cron['woo_odoo_auto_sync_product_chunk_product'] ) ) {
					foreach ( $cron['woo_odoo_auto_sync_product_chunk_product'] as $key => $job ) {
						wp_unschedule_event( $timestamp, 'woo_odoo_auto_sync_product_chunk_product', $job['args'] );
					}
				}

				if ( isset( $cron['woo_odoo_auto_sync_product_chunk_stock'] ) ) {
					foreach ( $cron['woo_odoo_auto_sync_product_chunk_stock'] as $key => $job ) {
						wp_unschedule_event( $timestamp, 'woo_odoo_auto_sync_product_chunk_stock', $job['args'] );
					}
				}

				if ( isset( $cron['woo_odoo_auto_sync_product_chunk_price'] ) ) {
					foreach ( $cron['woo_odoo_auto_sync_product_chunk_price'] as $key => $job ) {
						wp_unschedule_event( $timestamp, 'woo_odoo_auto_sync_product_chunk_price', $job['args'] );
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
	public function force_start_sync( $log_products = false, $mode = 'stock', $process_all_now = true ) {
		// Check if sync is already in progress
		$current_status = $this->get_sync_status();
		if ( $current_status && $current_status['status'] === 'in_progress' ) {
			$this->log( 'warning', 'Cannot start sync: another sync is already in progress', array( 'source' => 'woo-odoo-scheduler' ) );
			return false;
		}

		$this->log( 'info', sprintf( 'Manually triggering automatic product sync [%s mode]', strtoupper($mode) ), array( 'source' => 'woo-odoo-scheduler' ) );

		// Simpan mode agar bisa dibaca saat proses chunk
		update_option( 'woo_odoo_auto_sync_mode', $mode );

		if($mode === 'product') :
			$product_groups = woo_odoo_integration_api_get_product_groups();

		    $total_products = count( $product_groups );
		    $chunks = array_chunk( $product_groups, $this->chunk_size );
		    $total_chunks = count( $chunks );

		    $this->log( 'info', sprintf(
		        'Found %d products to sync, divided into %d chunks of %d products each',
		        $total_products,
		        $total_chunks,
		        $this->chunk_size
		    ), array( 'source' => 'woo-odoo-scheduler' ) );

		    update_option( 'woo_odoo_auto_sync_meta', array(
		        'start_time' => current_time( 'timestamp' ),
		        'total_products' => $total_products,
		        'total_chunks' => $total_chunks,
		        'current_chunk' => 0,
		        'processed_products' => 0,
		        'successful_updates' => 0,
		        'failed_updates' => 0,
		        'status' => 'in_progress',
		        'mode' => $mode, // optional, buat reference
		    ) );

		    // Schedule chunks with mode passed
		    $this->schedule_chunk_processing( 0, $chunks[0], 0, $mode );
		    for ( $i = 1; $i < $total_chunks; $i++ ) {
		        $delay = $i * $this->chunk_interval * 60;
		        $this->schedule_chunk_processing( $i, $chunks[$i], $delay, $mode );
		    }

		    do_action( 'woo_odoo_after_auto_sync_start', $total_products, $total_chunks );

		    $this->log( 'info', sprintf(
		        'Scheduled %d chunks for processing with %d minute intervals',
		        $total_chunks,
		        $this->chunk_interval
		    ), array( 'source' => 'woo-odoo-scheduler' ) );
		else:
			// Get all products
			$product_ids = $this->get_products_for_sync(); // pastikan ini membaca mode

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

			// Simpan metadata
			update_option( 'woo_odoo_auto_sync_meta', array(
		        'start_time' => current_time( 'timestamp' ),
		        'total_products' => $total_products,
		        'total_chunks' => $total_chunks,
		        'current_chunk' => 0,
		        'processed_products' => 0,
		        'successful_updates' => 0,
		        'failed_updates' => 0,
		        'status' => 'in_progress',
		        'mode' => $mode, // optional, buat reference
		    ) );

			if ( $process_all_now ) {
				// CLI mode: proses langsung
				for ( $i = 0; $i < $total_chunks; $i++ ) {
					$this->process_sync_chunk( $i, $chunks[$i], $log_products, $mode );
				}
			} else {
				// Jadwalkan (cron mode)
				$this->schedule_chunk_processing( 0, $chunks[0], 0, $mode );
				for ( $i = 1; $i < $total_chunks; $i++ ) {
					$delay = $i * $this->chunk_interval * 60;
					$this->schedule_chunk_processing( $i, $chunks[$i], $delay, $mode );
				}
			}

			do_action( 'woo_odoo_after_auto_sync_start', $total_products, $total_chunks );

			$this->log( 'info', sprintf(
				$process_all_now
					? 'Processed %d chunks immediately (CLI mode)'
					: 'Scheduled %d chunks for processing with %d minute intervals',
				$total_chunks,
				$this->chunk_interval
			), array( 'source' => 'woo-odoo-scheduler' ) );
		endif;

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
		wp_clear_scheduled_hook( 'woo_odoo_auto_sync_product' );
		wp_clear_scheduled_hook( 'woo_odoo_auto_sync_product_stock' );
		wp_clear_scheduled_hook( 'woo_odoo_auto_sync_product_price' );

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
	
	/**
	 * Get or create WC product category with optional parent.
	 * Returns term_id or false on failure.
	 */
	private function get_or_create_wc_category( $name, $uuid = '', $parent_id = 0 ) {
	    static $term_cache = [];

	    $cache_key = $uuid ? 'u:' . $uuid : 'n:' . md5( $name . '|' . intval( $parent_id ) );
	    if ( isset( $term_cache[ $cache_key ] ) ) {
	        return $term_cache[ $cache_key ];
	    }

	    if ( $uuid ) {
	        $terms = get_terms( array(
	            'taxonomy'   => 'product_cat',
	            'hide_empty' => false,
	            'meta_query' => array(
	                array(
	                    'key'   => 'odoo_uuid',
	                    'value' => $uuid,
	                ),
	            ),
	            'number' => 1,
	        ) );

	        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
	            $term_cache[ $cache_key ] = $terms[0]->term_id;
	            return $term_cache[ $cache_key ];
	        }
	    }

	    $found = get_terms( array(
	        'taxonomy'   => 'product_cat',
	        'hide_empty' => false,
	        'name'       => $name,
	        'parent'     => $parent_id,
	        'number'     => 1,
	    ) );

	    if ( ! is_wp_error( $found ) && ! empty( $found ) ) {
	        $term_id = $found[0]->term_id;
	        if ( $uuid ) update_term_meta( $term_id, 'odoo_uuid', $uuid );
	        $term_cache[ $cache_key ] = $term_id;
	        return $term_id;
	    }

	    $insert = wp_insert_term( $name, 'product_cat', array( 'parent' => $parent_id ) );
	    if ( is_wp_error( $insert ) ) {
	        return false;
	    }

	    $term_id = intval( $insert['term_id'] );

	    if ( $uuid ) update_term_meta( $term_id, 'odoo_uuid', $uuid );

	    $term_cache[ $cache_key ] = $term_id;
	    return $term_id;
	}

	private function build_category_tree_and_get_id($cat, $all_categories, &$cache) {
	    $uuid = $cat['uuid'];
	    $name = $cat['name'];

	    if (isset($cache[$uuid])) {
	        return $cache[$uuid];
	    }

	    $parent_term_id = 0;

	    if (!empty($cat['parent_id']) && is_array($cat['parent_id'])) {

	        $parent_uuid = $cat['parent_id']['uuid'];

	        foreach ($all_categories as $c) {
	            if ($c['uuid'] === $parent_uuid) {
	                $parent_term_id = $this->build_category_tree_and_get_id($c, $all_categories, $cache);
	            }
	        }
	    }

	    $term_id = $this->get_or_create_wc_category(
	        $name,
	        $uuid,
	        $parent_term_id
	    );

	    if ($term_id) {
	        $cache[$uuid] = $term_id;
	    }

	    return $term_id;
	}


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
	        'errors'  => 0,
	        'details' => array(),
	    );

		$attributes_to_sync = [
		    'size'         => ['label' => 'Size',         'enabled' => boolval(carbon_get_theme_option('enable_sync_attribut_size'))],
		    'color'        => ['label' => 'Color',        'enabled' => boolval(carbon_get_theme_option('enable_sync_attribut_color'))],
		    'location'     => ['label' => 'Location',     'enabled' => boolval(carbon_get_theme_option('enable_sync_attribut_location'))],
		    'model'        => ['label' => 'Model',        'enabled' => boolval(carbon_get_theme_option('enable_sync_attribut_model'))],
		    'process'      => ['label' => 'Process',      'enabled' => boolval(carbon_get_theme_option('enable_sync_attribut_process'))],
		    'brand'        => ['label' => 'Brand',        'enabled' => boolval(carbon_get_theme_option('enable_sync_attribut_brand'))],
		    'product_type' => ['label' => 'Type',         'enabled' => boolval(carbon_get_theme_option('enable_sync_attribut_product_type'))],
		    'material'     => ['label' => 'Material',     'enabled' => boolval(carbon_get_theme_option('enable_sync_attribut_material'))],
		    'design_code'  => ['label' => 'Design Code',  'enabled' => boolval(carbon_get_theme_option('enable_sync_attribut_design_code'))],
		];

		$attributes_used_variation = [
		    'size'         => ['enabled' => boolval(carbon_get_theme_option('enable_user_variation_attribut_size'))],
		    'color'        => ['enabled' => boolval(carbon_get_theme_option('enable_user_variation_attribut_color'))],
		    'location'     => ['enabled' => boolval(carbon_get_theme_option('enable_user_variation_attribut_location'))],
		    'model'        => ['enabled' => boolval(carbon_get_theme_option('enable_user_variation_attribut_model'))],
		    'process'      => ['enabled' => boolval(carbon_get_theme_option('enable_user_variation_attribut_process'))],
		    'brand'        => ['enabled' => boolval(carbon_get_theme_option('enable_user_variation_attribut_brand'))],
		    'product_type' => ['enabled' => boolval(carbon_get_theme_option('enable_user_variation_attribut_product_type'))],
		    'material'     => ['enabled' => boolval(carbon_get_theme_option('enable_user_variation_attribut_material'))],
		    'design_code'  => ['enabled' => boolval(carbon_get_theme_option('enable_user_variation_attribut_design_code'))],
		];
		$attributes_visibility = [
		    'size'         => ['enabled' => boolval(carbon_get_theme_option('enable_visible_attribut_size'))],
		    'color'        => ['enabled' => boolval(carbon_get_theme_option('enable_visible_attribut_color'))],
		    'location'     => ['enabled' => boolval(carbon_get_theme_option('enable_visible_attribut_location'))],
		    'model'        => ['enabled' => boolval(carbon_get_theme_option('enable_visible_attribut_model'))],
		    'process'      => ['enabled' => boolval(carbon_get_theme_option('enable_visible_attribut_process'))],
		    'brand'        => ['enabled' => boolval(carbon_get_theme_option('enable_visible_attribut_brand'))],
		    'product_type' => ['enabled' => boolval(carbon_get_theme_option('enable_visible_attribut_product_type'))],
		    'material'     => ['enabled' => boolval(carbon_get_theme_option('enable_visible_attribut_material'))],
		    'design_code'  => ['enabled' => boolval(carbon_get_theme_option('enable_visible_attribut_design_code'))],
		];

		foreach ($attributes_to_sync as $slug => $data) {
		    if ($data['enabled']) {
		        $this->ensure_product_attribute($data['label'], $slug);
		    }
		}

		$enable_sync_photo_product = boolval(carbon_get_theme_option('enable_sync_photo_product'));

	    $attribute_map = apply_filters( 'woo_odoo_integration_product_attribute_map', array(
		    'size_id'        => 'Size',
		    'color_id'       => 'Color',
		    'location'       => 'Location',
		    'model_id'       => 'Model',
		    'process_id'     => 'Process',
		    'brands_id'      => 'Brand',
		    'type_id'        => 'Type',
		    'material_id'    => 'Material',
		    'design_code_id' => 'Design Code',
		) );

	    $has_changes = function( $product, $new_data ) {
	        foreach ( $new_data as $key => $value ) {
	            $getter = "get_" . $key;
	            if ( method_exists( $product, $getter ) ) {
	                $old = $product->$getter();
	                if ( $old != $value ) {
	                    return true; 
	                }
	            }
	        }
	        return false;
	    };

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

	    // $counter = 0;
	    // $limit   = 5; // limit untuk testing
	    foreach ( $product_groups as $product_data ) {

	        $product_name = $product_data['name'];
	        $product_slug = sanitize_title($product_data['slug']);
	        $product_desc = $product_data['description'];
	        $short_desc   = $product_data['short_description'];
	        $variants     = $product_data['variants'];
	        $product_id   = wc_get_product_id_by_sku( $product_data['uuid'] );

	        // --- Cek produk utama ---
	        $existing_id  = wc_get_product_id_by_sku($product_data['uuid']);
	        $is_update    = false;

			$has_variation_attribute = false;

			foreach ($attributes_used_variation as $slug => $setting) {
			    if (!empty($setting['enabled']) && $setting['enabled'] === true) {
			        // pastikan atribut ini juga aktif disinkronkan
			        if (!empty($attributes_to_sync[$slug]['enabled']) && $attributes_to_sync[$slug]['enabled'] === true) {
			            $has_variation_attribute = true;
			            break;
			        }
			    }
			}

			// Tentukan tipe produk berdasarkan hasil di atas
			if ($existing_id) {
			    $product = $has_variation_attribute
			        ? new WC_Product_Variable($existing_id)
			        : new WC_Product_Simple($existing_id);
			    $is_update = true;
			} else {
			    $product = $has_variation_attribute
			        ? new WC_Product_Variable()
			        : new WC_Product_Simple();
			}

	        // Data baru produk utama
	        $new_product_data = [
	            'name'              => $product_name,
	            'sku'               => $product_data['uuid'],
	            'slug'              => $product_slug,
	            'description'       => $product_desc,
	            'short_description' => $short_desc
	        ];

	        // $quantity = null;
	        // if ( isset( $product_data['variants'][0]['quantity'] ) ) {
	        //     $quantity = $product_data['variants'][0]['quantity'];
	        //     $new_product_data['stock_quantity'] = $quantity;
	        // }
	        
	        $quantity = 0;
			if ( ! empty( $product_data['variants'] ) ) {
			    foreach ( $product_data['variants'] as $variant ) {
			        if ( isset( $variant['quantity'] ) ) {
			            $quantity += (int) $variant['quantity'];
			        }
			    }
			}

			if ( $quantity > 0 ) {
			    $new_product_data['stock_quantity'] = $quantity;
			}

	        if ( $is_update && ! $has_changes($product, $new_product_data) ) {
	            $results['skipped']++;
	            $results['details'][] = array(
	                'skipped'    => $product_data['uuid'],
	                'product_id' => $existing_id
	            );
	            if ($logger) $logger->info("Skipped product {$product_data['uuid']}", array('source'=>'woo-odoo-product-sync'));
	        } else {
	            $product->set_name($product_name);

				$sku_to_set = $product_data['uuid'];
				$existing_sku_id = wc_get_product_id_by_sku($sku_to_set);

				if ($existing_sku_id && $existing_sku_id !== $product->get_id()) {
				    $results['errors']++;
				    $results['details'][] = array(
				        'error'      => 'Duplicate SKU detected',
				        'duplicate'  => $sku_to_set,
				        'product_id' => $existing_sku_id,
				    );
				    if ($logger) {
				        $logger->error("Duplicate SKU detected: {$sku_to_set}", array('source' => 'woo-odoo-product-sync'));
				    }
				    continue;
				}

				try {
				    $product->set_sku($sku_to_set);
				} catch (WC_Data_Exception $e) {
				    $results['errors']++;
				    $results['details'][] = array(
				        'error' => 'Failed to set SKU',
				        'sku'   => $sku_to_set,
				        'msg'   => $e->getMessage()
				    );
				    if ($logger) {
				        $logger->error("Failed to set SKU {$sku_to_set}: " . $e->getMessage(), array('source'=>'woo-odoo-product-sync'));
				    }
				    continue;
				}

	            $product->set_slug($product_slug);
	            $product->set_description($product_desc);
	            $product->set_short_description($short_desc);
                $product->set_manage_stock(true);
                $product->set_stock_quantity($quantity);
                $product->set_stock_status($quantity > 0 ? 'instock' : 'outofstock');

	            if (!empty($product_data['variants'][0]['product_categories'])) {

				    $all_categories = $product_data['variants'][0]['product_categories'];
				    $cache = [];
				    $assigned_term_ids = [];

				    foreach ($all_categories as $cat) {
				        $term_id = $this->build_category_tree_and_get_id(
				            $cat,
				            $all_categories,
				            $cache
				        );

				        if ($term_id) {
				            $assigned_term_ids[] = $term_id;
				        }
				    }

				    $product->set_category_ids(array_unique($assigned_term_ids));
				}

	            if (true === $enable_sync_photo_product) :
		            // Set main image
		            if ( isset( $product_data['variants'][0]['images'][0]['url'] ) ) {
		                $image_url = $product_data['variants'][0]['images'][0]['url'];
		                $attach_id = $this->download_external_image($image_url, $product_id);
		                if ( $attach_id ) {
		                    $product->set_image_id( $attach_id );
		                }
		            }
		        endif;

	            $product_id = $product->save();

	            if ($is_update) {
	                $results['updated']++;
	                $results['details'][] = array(
	                    'updated'    => $product_data['uuid'],
	                    'product_id' => $product_id
	                );
	                if ($logger) $logger->info("Updated product {$product_data['uuid']}", array('source'=>'woo-odoo-product-sync'));
	            } else {
	                $results['created']++;
	                $results['details'][] = array(
	                    'created'    => $product_data['uuid'],
	                    'product_id' => $product_id
	                );
	                if ($logger) $logger->info("Created product {$product_data['uuid']}", array('source'=>'woo-odoo-product-sync'));
	            }
	        }
	        if (true === $enable_sync_photo_product) :
			    if (isset($product_data['variants'][0]['images'][0]['url'])) :
			        $image_url = $product_data['variants'][0]['images'][0]['url'];
			        $attach_id = $this->download_external_image($image_url, $product_id);
			        if ($attach_id) {
			            $product->set_image_id($attach_id);
			        }			        
			    endif;
			// else:
		    //     $old_image_id = $product->get_image_id();

			//     if ($old_image_id) {
			//         $this->remove_attached_image($old_image_id);
			//     }

			//     $product->set_image_id(null);
			endif;

	        $collected_terms = [];

			foreach ($attributes_to_sync as $slug => $data) {
			    if ($data['enabled']) {
			        $collected_terms['pa_' . $slug] = [];
			    }
			}

			foreach ($variants as $variant) {
			    foreach ($attributes_to_sync as $slug => $data) {
			        if (! $data['enabled']) continue;

			        $field_name = $slug . '_id';
			        if (!empty($variant[$field_name]['name'])) {
			            $collected_terms['pa_' . $slug][] = trim($variant[$field_name]['name']);
			        }
			    }

			    if ($attributes_to_sync['location']['enabled'] && !empty($variant['quantity_per_location'])) {
			        foreach ($variant['quantity_per_location'] as $loc) {
			            $loc_name_raw = trim($loc['name']);
			            $loc_name = (strpos($loc_name_raw, '/Stock ') !== false)
			                ? trim(explode('/Stock ', $loc_name_raw)[1])
			                : $loc_name_raw;
			            $collected_terms['pa_location'][] = $loc_name;
			        }
			    }
			}

	        foreach ($collected_terms as $taxonomy => $values) {
	            $collected_terms[$taxonomy] = array_unique($values);
	        }

	        $attributes_data = [];
	        foreach ($collected_terms as $taxonomy => $terms) {
	            if (empty($terms)) continue;

	            $term_ids = [];
	            foreach ($terms as $term) {
	                $term_obj = term_exists($term, $taxonomy);
	                if (!$term_obj) {
	                    $term_obj = wp_insert_term($term, $taxonomy);
	                }
	                if (!is_wp_error($term_obj)) {
	                    $term_ids[] = intval(is_array($term_obj) ? $term_obj['term_id'] : $term_obj);
	                }
	            }

	            $attribute = new WC_Product_Attribute();
	            $attribute->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
	            $attribute->set_name( $taxonomy );
	            $attribute->set_options( $term_ids );

	            $slug_key = str_replace('pa_', '', $taxonomy);
				$is_visibility = isset($attributes_visibility[$slug_key]['enabled'])
				    ? $attributes_visibility[$slug_key]['enabled']
				    : false;

				$attribute->set_visible( $is_visibility );
	            
				$is_variation = isset($attributes_used_variation[$slug_key]['enabled'])
				    ? $attributes_used_variation[$slug_key]['enabled']
				    : false;

				$attribute->set_variation( $is_variation );

	            $attributes_data[$taxonomy] = $attribute;
	        }

	        if (!empty($attributes_data)) {
	            $product->set_attributes($attributes_data);
	            $product->save();
	        }

	        if ( ! $has_variation_attribute ) {
			    continue;
			}

	        foreach ($variants as $variant) {
	            $base_price = !empty($variant['pricelists'][0]['sale_price']) ? $variant['pricelists'][0]['sale_price'] : 0;
	            $base_size  = !empty($variant['size_id']['name']) ? trim($variant['size_id']['name']) : '';
	            $base_color = !empty($variant['color_id']['name']) ? trim($variant['color_id']['name']) : '';

	            if (!empty($variant['quantity_per_location'])) {
	                foreach ($variant['quantity_per_location'] as $loc) {
	                    $loc_name_raw = trim($loc['name']);
	                    $loc_name = (strpos($loc_name_raw, '/Stock ') !== false)
	                        ? trim(explode('/Stock ', $loc_name_raw)[1])
	                        : $loc_name_raw;
	                    $loc_qty = intval($loc['quantity']);

	                    $sku_variation = $variant['uuid'] . '-' . sanitize_title($loc_name);
	                    // $sku_variation = $product_data['uuid'] . '-' . $variant['uuid'] . '-' . sanitize_title($loc_name);
	                    // $sku_variation = $variant['uuid'];

	                    $variation_id = wc_get_product_id_by_sku($sku_variation);
	                    $var_update   = false;

	                    if ($variation_id) {
	                        $variation = new WC_Product_Variation($variation_id);
	                        $var_update = true;
	                    } else {
	                        $variation = new WC_Product_Variation();
	                        $variation->set_parent_id($existing_id ?: $product_id);
	                    }

	                    $variation_name = $product_name;
	                    if ($base_color)  $variation_name .= " ({$base_color})";
	                    if ($base_size)   $variation_name .= " ({$base_size})";
	                    if (!empty($variant['model_id']['name']))       $variation_name .= " ({$variant['model_id']['name']})";
	                    if (!empty($variant['process_id']['name']))     $variation_name .= " ({$variant['process_id']['name']})";
	                    if (!empty($variant['brands_id']['name']))      $variation_name .= " ({$variant['brands_id']['name']})";
	                    if (!empty($variant['type_id']['name']))        $variation_name .= " ({$variant['type_id']['name']})";
	                    if (!empty($variant['material_id']['name']))    $variation_name .= " ({$variant['material_id']['name']})";
	                    if (!empty($variant['design_code_id']['name'])) $variation_name .= " ({$variant['design_code_id']['name']})";
	                    if ($loc_name)    $variation_name .= " ({$loc_name})";

	                    $variation->set_name($variation_name);
	                    try {
						    $variation->set_sku($sku_variation);
						} catch (WC_Data_Exception $e) {
						    $results['errors']++;
						    $results['details'][] = array(
						        'error'         => 'Duplicate variation SKU',
						        'sku'           => $sku_variation,
						        'variation_id'  => $variation->get_id(),
						        'msg'           => $e->getMessage(),
						    );
						    if ($logger) {
						        $logger->error("Duplicate SKU on variation: {$sku_variation} - " . $e->getMessage(), array('source' => 'woo-odoo-product-sync'));
						    }
						    continue;
						}

	                    $variation->set_manage_stock(true);
	                    $variation->set_stock_quantity($loc_qty);
	                    $variation->set_stock_status($loc_qty > 0 ? 'instock' : 'outofstock');
	                    $variation->set_regular_price($base_price);

	                    $var_attributes = [];

						foreach ($attributes_to_sync as $slug => $data) {
						    if (! $data['enabled']) continue;

						    $taxonomy = 'pa_' . $slug;

						    // Ambil nama dari variant
						    $value = '';
						    if ($slug === 'location') {
						        $value = $loc_name;
						    } elseif (!empty($variant[$slug . '_id']['name'])) {
						        $value = trim($variant[$slug . '_id']['name']);
						    }

						    if (!empty($value)) {
						        $term = get_term_by('name', $value, $taxonomy);
						        if ($term) {
						            $var_attributes[$taxonomy] = $term->slug;
						        }
						    }
						}

	                    $variation->set_attributes($var_attributes);

	                    if (true === $enable_sync_photo_product) :
						    if (!empty($variant['images'][0]['url'])) :
						        $image_url = $variant['images'][0]['url'];
						        $image_id = $this->download_external_image($image_url, $variation_id);
						        if ($image_id) {
						            $variation->set_image_id($image_id);
						        }
						    endif;
						// else:
					    //     $old_image_id = $variation->get_image_id();

						//     if ($old_image_id) {
						//         $this->remove_attached_image($old_image_id);
						//     }

						//     $variation->set_image_id(null);
						endif;

	                    $saved_id = $variation->save();

	                    if (
						    isset($variant['pricelists']) &&
						    is_array($variant['pricelists']) &&
						    isset($variant['pricelists'][0]['uuid'])
						) {
						    update_post_meta(
						        $saved_id,
						        '_odoo_pricelists',
						        $variant['pricelists'][0]['uuid']
						    );
						}

						if (
						    isset($loc['uuid'])
						) {
						    update_post_meta(
						        $saved_id,
						        '_odoo_warehouse_id',
						        $loc['uuid']
						    );
						}

	                    if ($var_update) {
	                        $results['details'][] = array(
	                            'variation_updated' => $sku_variation,
	                            'variation_id'      => $saved_id
	                        );
	                    } else {
	                        $results['details'][] = array(
	                            'variation_created' => $sku_variation,
	                            'variation_id'      => $saved_id
	                        );
	                    }
	                }
	            }

	        }

	        // $counter++;
	        // if ($counter >= $limit) break;
	    }

	    if ( $logger ) {
	        $logger->info( 'Sync Odoo products to WooCommerce completed', array( 
	            'source' => 'woo-odoo-product-sync',
	            'result' => $results 
	        ));
	    }

	    return $results;

	}

	/**
	 * Remove attached image from media library by attachment ID
	 *
	 * @param int $attachment_id
	 */
	public function remove_attached_image($attachment_id) {
	    if (wp_attachment_is_image($attachment_id)) {
	        wp_delete_attachment($attachment_id, true);
	    }
	}

	// Tambahkan helper di class yang sama
	private function ensure_product_attribute($name, $slug) {
	    global $wpdb;

	    $attr = $wpdb->get_row( $wpdb->prepare(
	        "SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s",
	        $slug
	    ));

	    $enable_sync_attribut = boolval(carbon_get_theme_option('enable_sync_attribut_'.$slug));

	    if ( ! $attr && $enable_sync_attribut ) {
	        wc_create_attribute( array(
	            'slug'        => $slug, 
	            'name'        => ucfirst( $name ),
	            'type'        => 'select',
	            'order_by'    => 'menu_order',
	            'has_archives'=> false,
	        ));

	        delete_transient('wc_attribute_taxonomies');

	        if ( function_exists('wc_clean_attribute_cache') ) {
	            wc_clean_attribute_cache();
	        } elseif ( method_exists('WC_Cache_Helper', 'invalidate_cache_group') ) {
	            WC_Cache_Helper::invalidate_cache_group('woocommerce-attributes');
	        }

	        register_taxonomy(
	            'pa_' . $slug,
	            array('product'),
	            array(
	                'hierarchical' => false,
	                'label'        => ucfirst( $name ),
	                'query_var'    => true,
	                'rewrite'      => false,
	            )
	        );
	    }
	}

	public function download_external_image($image_url, $post_id = 0) {
	    require_once(ABSPATH . 'wp-admin/includes/file.php');
	    require_once(ABSPATH . 'wp-admin/includes/media.php');
	    require_once(ABSPATH . 'wp-admin/includes/image.php');

	    $tmp = download_url($image_url);
	    if (is_wp_error($tmp)) return 0;

	    $mime = mime_content_type($tmp);
	    $extension = '';
	    switch ($mime) {
	        case 'image/jpeg': $extension = '.jpg'; break;
	        case 'image/png':  $extension = '.png'; break;
	        case 'image/gif':  $extension = '.gif'; break;
	        default: $extension = '.jpg'; // fallback
	    }

	    $name = basename(parse_url($image_url, PHP_URL_PATH));
	    if (!preg_match('/\.(jpg|jpeg|png|gif)$/i', $name)) {
	        $name .= $extension;
	    }
	    $file = array(
	        'name'     => $name,
	        'type'     => $mime,
	        'tmp_name' => $tmp,
	        'size'     => filesize($tmp),
	    );

	    $id = media_handle_sideload($file, $post_id);

	    if (is_wp_error($id)) {
	        @unlink($tmp);
	        return 0;
	    }

	    return $id;
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

	public function schedule_immediate_test() {
	    // Bersihkan event lama dulu biar gak dobel
	    wp_clear_scheduled_hook( 'woo_odoo_auto_sync_product' );
	    wp_clear_scheduled_hook( 'woo_odoo_auto_sync_product_stock' );
	    wp_clear_scheduled_hook( 'woo_odoo_auto_sync_product_price' );

	    // Ambil timezone WP
	    $timezone_string = get_option( 'timezone_string', 'UTC' );
	    try {
	        $timezone = new DateTimeZone( $timezone_string );
	    } catch (Exception $e) {
	        $timezone = new DateTimeZone( 'UTC' );
	    }

	    // Jadwalkan mulai dari waktu sekarang (UTC)
	    $now = new DateTime( 'now', $timezone );
	    $now_utc = clone $now;
	    $now_utc->setTimezone( new DateTimeZone( 'UTC' ) );
	    $timestamp = $now_utc->getTimestamp();

	    // Jadwal: sekarang + jeda 2 jam antar event
	    wp_schedule_single_event( $timestamp, 'woo_odoo_auto_sync_product' );
	    wp_schedule_single_event( $timestamp + 2 * HOUR_IN_SECONDS, 'woo_odoo_auto_sync_product_stock' );
	    wp_schedule_single_event( $timestamp + 4 * HOUR_IN_SECONDS, 'woo_odoo_auto_sync_product_price' );

	    $this->log( 'info', sprintf(
	        '📅 Test schedule created starting now (%s). Product=now, Stock=+2h, Price=+4h (%s timezone)',
	        $now->format('Y-m-d H:i:s'),
	        $timezone_string
	    ), array( 'source' => 'woo-odoo-scheduler' ) );
	}
}

