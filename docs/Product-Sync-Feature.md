# WooCommerce Odoo Integration - Product Stock Sync Feature

## Overview

Fitur Product Stock Sync memungkinkan sinkronisasi stok produk dari Odoo ERP ke WooCommerce dengan mudah. Fitur ini mencakup bulk action untuk sinkronisasi massal dan tombol individual untuk setiap produk.

## Fitur Utama

### 1. Mass Action "Sync Product Stock from Odoo"

Tambahkan opsi baru pada dropdown bulk actions di halaman Products WooCommerce:

- **Lokasi**: WooCommerce > Products > Bulk Actions dropdown
- **Nama**: "Sync Product Stock from Odoo"
- **Fungsi**: Sinkronisasi stok produk yang dipilih dari Odoo

### 2. Single Product Sync Button

Tombol untuk sinkronisasi individual di halaman edit product:

- **Lokasi**: Product Edit Page > Product Data > Inventory tab
- **Nama**: "Sync Stock from Odoo"
- **Fungsi**: AJAX sync untuk satu produk

## Cara Kerja

### Mapping SKU

- **WooCommerce**: Menggunakan field `_sku` produk
- **Odoo**: Menggunakan `uuid` dari variant
- **Format**: SKU WooCommerce harus sama dengan UUID variant Odoo

### API Endpoint

```php
GET /api/product-stock
```

**Response Format:**

```json
{
	"code": 200,
	"data": [
		{
			"uuid": "aaef5d2a-8b73-4ebd-8380-d1a68d0f0057",
			"name": "Group 12",
			"variants": [
				{
					"uuid": "f5a47062-526a-4aeb-8dca-cbffe2baf03c",
					"name": "[20080017382] B208 PK6/80 L70",
					"quantity": 1,
					"uom_id": {
						"uuid": "7fea8741-5251-4efc-97aa-cd4d2755b5b3",
						"name": "pcs"
					}
				}
			]
		}
	]
}
```

## Penggunaan

### Bulk Sync (Mass Action)

1. Buka halaman **WooCommerce > Products**
2. Pilih produk yang ingin disinkronisasi
3. Pilih **"Sync Product Stock from Odoo"** dari dropdown Bulk Actions
4. Klik **Apply**
5. Konfirmasi dialog yang muncul
6. Tunggu proses selesai dan lihat hasilnya di admin notice

### Single Product Sync

1. Edit produk individual
2. Buka tab **Inventory** dalam Product Data
3. Klik tombol **"Sync Stock from Odoo"** di bagian Odoo Integration
4. Tunggu proses AJAX selesai
5. Halaman akan refresh otomatis (opsional)

## Implementasi Teknis

### File yang Ditambahkan/Dimodifikasi

1. **`helper/api.php`**

   - `woo_odoo_integration_api_get_product_stock()`
   - `woo_odoo_integration_sync_product_stock()`

2. **`admin/class-woo-odoo-integration-product.php`**

   - Class lengkap untuk menangani product sync
   - Bulk actions handler
   - AJAX handler
   - Admin notices

3. **`admin/js/woo-odoo-integration-product.js`**

   - JavaScript untuk single product sync
   - Loading states dan user feedback
   - Error handling

4. **`admin/css/woo-odoo-integration-product.css`**

   - Styling untuk sync buttons
   - Loading animations
   - Admin notices enhancement

5. **`includes/class-woo-odoo-integration.php`**
   - Hook registration untuk Product class

### Hooks yang Diregistrasi

```php
// Bulk actions
$this->loader->add_filter('bulk_actions-edit-product', $product_handler, 'add_bulk_actions');
$this->loader->add_filter('handle_bulk_actions-edit-product', $product_handler, 'handle_bulk_actions', 10, 3);

// Admin notices
$this->loader->add_action('admin_notices', $product_handler, 'display_admin_notices');

// Single product sync
$this->loader->add_action('woocommerce_product_options_stock_status', $product_handler, 'add_product_sync_button');
$this->loader->add_action('wp_ajax_woo_odoo_sync_single_product', $product_handler, 'handle_ajax_sync_single_product');

// Assets
$this->loader->add_action('admin_enqueue_scripts', $product_handler, 'enqueue_admin_scripts');
```

## WordPress Hooks yang Digunakan

### Action Hooks

```php
// Before/after sync hooks
do_action('woo_odoo_integration_before_get_product_stock');
do_action('woo_odoo_integration_after_get_product_stock', $stock_data);
do_action('woo_odoo_integration_before_sync_product_stock', $product_ids);
do_action('woo_odoo_integration_after_sync_product_stock', $sync_results);
do_action('woo_odoo_integration_product_stock_updated', $product_id, $old_stock, $new_stock);

// Bulk sync hooks
do_action('woo_odoo_integration_bulk_sync_started', $post_ids);
do_action('woo_odoo_integration_bulk_sync_completed', $sync_results);
```

### Filter Hooks

```php
// Data filtering
apply_filters('woo_odoo_integration_product_stock_data', $stock_data);
apply_filters('woo_odoo_integration_refresh_on_sync', true);
```

## Logging

Semua operasi sync menggunakan WooCommerce Logger dengan source context yang sesuai:

- **Source**: `woo-odoo-product-sync`
- **Levels**: info, warning, error, debug

**Contoh Log:**

```php
$logger = wc_get_logger();
$logger->info('Starting product stock sync from Odoo', array('source' => 'woo-odoo-product-sync'));
```

## Error Handling

### API Errors

- Connection failures
- Authentication errors
- Invalid response formats
- Missing data

### Validation Errors

- Invalid product IDs
- Missing SKU
- No stock data from Odoo

### User Feedback

- Admin notices untuk bulk operations
- AJAX responses untuk single product sync
- Loading states dan confirmations

## Security

### Nonce Verification

```php
wp_verify_nonce($_POST['nonce'], 'woo_odoo_sync_single_product')
```

### Capability Checks

```php
current_user_can('manage_woocommerce')
```

### Data Sanitization

```php
sanitize_text_field(), intval(), esc_html(), esc_attr()
```

## Performance Considerations

1. **Caching**: API responses tidak di-cache karena data stock sering berubah
2. **Batch Processing**: Bulk sync memproses semua produk sekaligus
3. **AJAX**: Single product sync menggunakan AJAX untuk UX yang lebih baik
4. **Logging**: Comprehensive logging untuk debugging tanpa performance impact yang signifikan

## Troubleshooting

### Common Issues

1. **No SKU mappings found**

   - Pastikan produk WooCommerce memiliki SKU
   - Pastikan variant Odoo memiliki UUID yang valid

2. **API connection failed**

   - Check API credentials
   - Verify network connectivity
   - Check API endpoint URL

3. **Permission denied**
   - Ensure user has `manage_woocommerce` capability
   - Check nonce verification

### Debug Mode

Enable WordPress debug mode untuk melihat log detail:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check logs di `/wp-content/debug.log` atau WooCommerce > Status > Logs.
