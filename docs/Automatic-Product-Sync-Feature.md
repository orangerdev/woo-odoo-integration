# Automatic Product Sync Feature Documentation

## Overview

Sistem automatic product sync memungkinkan sinkronisasi otomatis stock produk WooCommerce dengan Odoo ERP secara terjadwal, berjalan setiap hari di tengah malam sesuai timezone WordPress. Sistem ini menggunakan chunking untuk menangani volume produk yang besar dengan aman.

## Fitur Utama

### 1. Scheduled Sync

- **Waktu**: Setiap hari tengah malam (00:00) berdasarkan timezone WordPress
- **Otomatis**: Tidak memerlukan intervensi manual
- **Reliable**: Menggunakan WordPress Cron system

### 2. Chunking System

- **Default**: 10 produk per batch, interval 5 menit
- **Configurable**: Admin dapat mengatur chunk size (1-50) dan interval (1-60 menit)
- **Safe**: Mencegah timeout dan overload server

### 3. Admin Interface

- **Monitoring**: Real-time status sync process
- **Manual Control**: Trigger sync manual dan cancel sync
- **Configuration**: Pengaturan chunk size dan interval
- **Debug Info**: Informasi cron status dan scheduled events

## Arsitektur Sistem

### Core Classes

#### 1. `Woo_Odoo_Integration_Scheduler`

**File**: `includes/class-woo-odoo-integration-scheduler.php`

**Tanggung jawab**:

- Mengelola WordPress cron events
- Membagi produk ke dalam chunks
- Menjalankan sync process secara batch
- Progress tracking dan logging

**Method utama**:

```php
init_scheduler()              // Initialize cron hooks
start_auto_sync()            // Start chunked sync process
process_sync_chunk()         // Process single chunk
clear_sync_queue()           // Clear all scheduled chunks
force_start_sync()           // Manual trigger
get_sync_status()           // Get current status
```

#### 2. `Woo_Odoo_Integration\Admin\Scheduler_Admin`

**File**: `admin/class-woo-odoo-integration-scheduler-admin.php`

**Tanggung jawab**:

- Admin interface untuk monitoring
- AJAX handlers untuk kontrol manual
- Configuration settings
- Status display dan progress tracking

**Method utama**:

```php
display_admin_page()                // Main admin interface
handle_ajax_trigger_sync()         // Manual sync trigger
handle_ajax_get_status()           // Status refresh
handle_ajax_clear_queue()          // Cancel sync
```

### Hook Registration

Semua hook terdaftar di `class-woo-odoo-integration.php`:

```php
// Scheduler core
$this->loader->add_action('wp_loaded', $scheduler, 'init_scheduler');

// Admin interface
$this->loader->add_action('admin_menu', $scheduler_admin, 'add_admin_menu');
$this->loader->add_action('admin_enqueue_scripts', $scheduler_admin, 'enqueue_admin_scripts');

// AJAX handlers
$this->loader->add_action('wp_ajax_woo_odoo_trigger_auto_sync', $scheduler_admin, 'handle_ajax_trigger_sync');
$this->loader->add_action('wp_ajax_woo_odoo_get_sync_status', $scheduler_admin, 'handle_ajax_get_status');
$this->loader->add_action('wp_ajax_woo_odoo_clear_sync_queue', $scheduler_admin, 'handle_ajax_clear_queue');
```

### WordPress Cron Events

#### 1. Daily Sync Event

- **Hook**: `woo_odoo_auto_sync_product_stock`
- **Schedule**: Daily at midnight (WordPress timezone)
- **Callback**: `Woo_Odoo_Integration_Scheduler::start_auto_sync()`

#### 2. Chunk Processing Events

- **Hook**: `woo_odoo_auto_sync_product_chunk`
- **Schedule**: Single events with calculated delays
- **Callback**: `Woo_Odoo_Integration_Scheduler::process_sync_chunk()`
- **Args**: chunk_index, product_ids array

## Data Flow

### 1. Sync Initiation

```
Daily cron trigger → start_auto_sync()
↓
Get products with SKU → divide into chunks
↓
Schedule chunk events → store metadata
```

### 2. Chunk Processing

```
Chunk cron event → process_sync_chunk()
↓
Call woo_odoo_integration_sync_product_stock()
↓
Update progress metadata → log results
```

### 3. Progress Tracking

```php
// Metadata structure
array(
    'start_time' => timestamp,
    'end_time' => timestamp,
    'total_products' => int,
    'total_chunks' => int,
    'current_chunk' => int,
    'processed_products' => int,
    'successful_updates' => int,
    'failed_updates' => int,
    'status' => 'in_progress|completed'
)
```

## API Integration

Menggunakan endpoint yang sama dengan manual sync:

- **Endpoint**: `/api/product-stock`
- **Method**: GET
- **Response**: Array of product groups dengan variants dan stock
- **Authentication**: OAuth2 Bearer token

Mapping produk:

- **WooCommerce SKU** ↔ **Odoo Variant UUID**
- Sistem menggunakan `variant['uuid']` sebagai identifier

## Admin Interface

### Location

**WooCommerce** → **Odoo Auto Sync**

### Sections

#### 1. Sync Status Card

- Current sync progress (jika berjalan)
- Last completed sync details
- Real-time progress bar
- Product counts dan statistics

#### 2. Schedule Information Card

- WordPress timezone setting
- Next scheduled sync time
- Daily midnight schedule info

#### 3. Manual Controls Card

- **Start Sync Now**: Manual trigger
- **Cancel Current Sync**: Stop sync yang berjalan
- **Refresh Status**: Update status display
- Real-time feedback messages

#### 4. Configuration Card

- **Chunk Size**: 1-50 products per batch
- **Chunk Interval**: 1-60 minutes between batches
- WordPress settings API integration

#### 5. Debug Information (Admin only)

- WordPress Cron status
- Scheduled events list
- System diagnostics

### JavaScript Features

**File**: `admin/js/woo-odoo-integration-scheduler.js`

- **Real-time polling**: Auto-refresh status setiap 30 detik saat sync berjalan
- **AJAX controls**: Manual trigger dan cancel tanpa page reload
- **Progress animation**: Smooth progress bar animations
- **Form validation**: Client-side validation untuk settings
- **Visibility handling**: Pause polling saat tab tidak aktif

## Configuration Options

### WordPress Options

```php
// Chunk settings
get_option('woo_odoo_auto_sync_chunk_size', 10);     // 1-50
get_option('woo_odoo_auto_sync_chunk_interval', 5);  // 1-60 minutes

// Progress tracking
get_option('woo_odoo_auto_sync_meta');               // Sync metadata
```

### Filters untuk Customization

```php
// Adjust chunk settings
apply_filters('woo_odoo_integration_auto_sync_chunk_size', 10);
apply_filters('woo_odoo_integration_auto_sync_chunk_interval', 5);

// Customize product query
apply_filters('woo_odoo_integration_auto_sync_product_args', $args);
```

### Action Hooks

```php
// Sync lifecycle hooks
do_action('woo_odoo_before_auto_sync_start');
do_action('woo_odoo_after_auto_sync_start', $total_products, $total_chunks);
do_action('woo_odoo_before_process_chunk', $chunk_index, $product_ids);
do_action('woo_odoo_after_process_chunk', $chunk_index, $sync_results);
do_action('woo_odoo_auto_sync_completed', $sync_meta);
```

## Logging

Menggunakan WooCommerce Logger dengan source contexts:

### Log Sources

```php
'woo-odoo-scheduler'     // General scheduler operations
'woo-odoo-product-sync'  // Product sync operations (inherited)
```

### Log Levels

```php
$logger->info()    // Successful operations, progress updates
$logger->warning() // Non-fatal issues, fallback actions
$logger->error()   // Errors that prevent functionality
$logger->debug()   // Detailed debugging information
```

### Key Log Messages

```php
// Scheduler initialization
"Scheduled daily product sync at midnight (timezone). Next run: timestamp"

// Sync start
"Starting automatic product stock sync"
"Found X products to sync, divided into Y chunks"

// Progress updates
"Processing chunk X with Y products"
"Completed chunk X. Progress: X/Y chunks (Z%)"

// Completion
"Auto sync completed. Total products: X, Updated: Y, Errors: Z, Duration: N minutes"
```

## Error Handling

### Levels

1. **System Errors**

   - Invalid timezone configuration → fallback to UTC
   - WordPress cron disabled → warning message in admin
   - API authentication failure → retry with fresh token

2. **Sync Errors**

   - No products found → log and exit gracefully
   - API endpoint unreachable → error logging, metadata update
   - Individual product failures → continue with next product

3. **Recovery Mechanisms**
   - **Interrupted sync**: Metadata tracks progress, manual clear available
   - **Orphaned cron events**: Deactivation hook cleanup
   - **Token expiry**: Automatic refresh mechanism

## Performance Considerations

### Resource Management

- **Chunking**: Prevents memory exhaustion dan timeout
- **Intervals**: Reduces server load dengan delayed processing
- **Caching**: Token caching mengurangi auth requests
- **Progress tracking**: Efficient metadata updates

### Scalability

- **Large catalogs**: Tested untuk ribuan produk
- **Server resources**: Configurable chunk settings untuk server limitations
- **API rate limits**: Built-in delays mencegah API overload

### Monitoring

- **Progress tracking**: Real-time status updates
- **Comprehensive logging**: Detailed operation logs
- **Admin dashboard**: Visual progress indicators
- **Debug information**: System status diagnostics

## Troubleshooting

### Common Issues

1. **Sync tidak berjalan otomatis**

   ```php
   // Check WordPress cron
   wp_next_scheduled('woo_odoo_auto_sync_product_stock');

   // Check DISABLE_WP_CRON constant
   defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
   ```

2. **Sync terhenti di tengah jalan**

   - Gunakan "Cancel Current Sync" di admin
   - Check server error logs
   - Verify API connectivity

3. **Tidak ada produk yang ter-update**

   - Pastikan produk memiliki SKU
   - Verify API endpoint response
   - Check UUID mapping di logs

4. **Performance issues**
   - Reduce chunk size (5-10 products)
   - Increase interval (10-15 minutes)
   - Check server resource usage

### Debug Mode

Enable WordPress debug untuk detailed logging:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check logs di:

- `/wp-content/debug.log`
- **WooCommerce** → **Status** → **Logs** → **woo-odoo-scheduler**

### Manual Recovery

Jika sync corruption atau orphaned events:

```php
// Clear all sync data
delete_option('woo_odoo_auto_sync_meta');
wp_clear_scheduled_hook('woo_odoo_auto_sync_product_stock');

// Or use admin interface "Cancel Current Sync"
```

## Security Considerations

### Permissions

- Admin interface: `manage_woocommerce` capability
- AJAX endpoints: Nonce verification + capability check
- Settings: WordPress settings API dengan validation

### Data Protection

- **Input sanitization**: All user input sanitized
- **API credentials**: Stored in WordPress options (database)
- **Nonce verification**: All AJAX requests verified
- **Capability checks**: User permissions validated

## Future Enhancements

### Planned Features

1. **Email notifications**: Sync completion/failure alerts
2. **Selective sync**: Filter products berdasarkan categories/attributes
3. **Retry mechanism**: Auto-retry failed chunks
4. **Performance metrics**: Detailed timing dan throughput stats
5. **API rate limiting**: Built-in rate limit respect

### Integration Points

- **WooCommerce Subscriptions**: Sync subscription products
- **WooCommerce Multisite**: Network-wide sync management
- **Third-party plugins**: Hook system untuk extensions
- **REST API**: External trigger endpoints

## Conclusion

Automatic product sync feature menyediakan solusi reliable dan scalable untuk menjaga sinkronisasi stock antara WooCommerce dan Odoo ERP. Dengan chunking system, comprehensive monitoring, dan robust error handling, sistem ini dapat menangani volume produk besar sambil menjaga performa server dan user experience.
