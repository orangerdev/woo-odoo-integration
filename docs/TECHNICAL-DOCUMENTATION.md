# WooCommerce Odoo Integration - Dokumentasi Teknikal

> **Versi:** 1.0.4  
> **Author:** Ridwan Arifandi  
> **License:** GPL-2.0+

## Daftar Isi

1. [Ringkasan](#ringkasan)
2. [Arsitektur Plugin](#arsitektur-plugin)
3. [Persyaratan Sistem](#persyaratan-sistem)
4. [Struktur File](#struktur-file)
5. [Instalasi & Konfigurasi](#instalasi--konfigurasi)
6. [API Integration](#api-integration)
7. [Fitur Utama](#fitur-utama)
8. [Hooks & Filters](#hooks--filters)
9. [CLI Commands](#cli-commands)
10. [Database Schema](#database-schema)
11. [Troubleshooting](#troubleshooting)

---

## Ringkasan

**WooCommerce Odoo Integration** adalah plugin WordPress yang menyediakan integrasi langsung antara WooCommerce dan Odoo ERP tanpa memerlukan middleware. Plugin ini mendukung sinkronisasi:

- ✅ **Produk** - Sinkronisasi produk dan variasi dari Odoo ke WooCommerce
- ✅ **Stock** - Sinkronisasi stok real-time
- ✅ **Harga** - Sinkronisasi harga produk
- ✅ **Customer** - Sinkronisasi pelanggan (registered & guest)
- ✅ **Order** - Pengiriman order ke Odoo setelah checkout
- ✅ **Multi-lokasi** - Dukungan multi-warehouse dengan validasi cart

---

## Arsitektur Plugin

### Diagram Arsitektur

```
┌─────────────────────────────────────────────────────────────────┐
│                        WordPress/WooCommerce                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌─────────────────┐    ┌─────────────────┐    ┌──────────────┐ │
│  │   Admin Area    │    │   Public Area   │    │   WP-CLI     │ │
│  │                 │    │                 │    │              │ │
│  │ • Settings      │    │ • Cart Valid.   │    │ • sync-stock │ │
│  │ • Product Sync  │    │ • Order Sync    │    │ • sync-price │ │
│  │ • User Profile  │    │                 │    │ • sync-prods │ │
│  │ • Scheduler     │    │                 │    │              │ │
│  └────────┬────────┘    └────────┬────────┘    └──────┬───────┘ │
│           │                      │                     │         │
│           └──────────────────────┴─────────────────────┘         │
│                                  │                               │
│                    ┌─────────────▼─────────────┐                 │
│                    │       Helper/API          │                 │
│                    │                           │                 │
│                    │ • OAuth2 Authentication   │                 │
│                    │ • Token Management        │                 │
│                    │ • API Request Handler     │                 │
│                    │ • Error Handling          │                 │
│                    │ • Logging                 │                 │
│                    └─────────────┬─────────────┘                 │
│                                  │                               │
└──────────────────────────────────┼───────────────────────────────┘
                                   │
                          ┌────────▼────────┐
                          │   Odoo ERP API  │
                          │                 │
                          │ • /api/auth     │
                          │ • /api/products │
                          │ • /api/stock    │
                          │ • /api/orders   │
                          │ • /api/customers│
                          └─────────────────┘
```

### Design Pattern

Plugin ini menggunakan **WordPress Plugin Boilerplate pattern** dengan pemisahan:

- **Loader Pattern** - Centralized hook registration
- **Namespace Organization** - PSR-4 compatible namespacing
- **Dependency Injection** - Constructor-based DI untuk plugin name & version

---

## Persyaratan Sistem

| Komponen | Versi Minimum |
|----------|---------------|
| PHP | 7.4+ |
| WordPress | 5.0+ |
| WooCommerce | 4.0+ |
| Carbon Fields | 3.0+ (bundled) |

### Dependencies (via Composer)

```json
{
  "require": {
    "htmlburger/carbon-fields": "^3.0"
  }
}
```

---

## Struktur File

```
woo-odoo-integration/
├── woo-odoo-integration.php      # Bootstrap file utama
├── uninstall.php                 # Cleanup saat uninstall
├── composer.json                 # Composer dependencies
│
├── includes/                     # Core classes
│   ├── class-woo-odoo-integration.php           # Main plugin class
│   ├── class-woo-odoo-integration-loader.php    # Hook loader
│   ├── class-woo-odoo-integration-i18n.php      # Internationalization
│   ├── class-woo-odoo-integration-activator.php # Activation hooks
│   ├── class-woo-odoo-integration-deactivator.php
│   └── class-woo-odoo-integration-scheduler.php # Cron scheduler
│
├── admin/                        # Admin functionality
│   ├── class-woo-odoo-integration-admin.php     # Settings & Carbon Fields
│   ├── class-woo-odoo-integration-product.php   # Product sync admin
│   ├── class-woo-odoo-integration-user.php      # Customer sync
│   ├── class-woo-odoo-integration-scheduler-admin.php
│   ├── css/                      # Admin styles
│   ├── js/                       # Admin scripts
│   └── partials/                 # Admin templates
│
├── public/                       # Public-facing functionality
│   ├── class-woo-odoo-integration-public.php    # Frontend logic
│   ├── css/
│   ├── js/
│   └── partials/
│
├── helper/                       # Helper functions
│   └── api.php                   # API communication layer
│
├── cli/                          # WP-CLI commands
│   ├── cli-loader.php
│   ├── class-woo-odoo-integration-cli-product-sync.php
│   ├── class-woo-odoo-integration-cli-product-stock-sync.php
│   └── class-woo-odoo-integration-cli-product-price-sync.php
│
├── languages/                    # Translations
│   └── woo-odoo-integration.pot
│
└── docs/                         # Documentation
```

---

## Instalasi & Konfigurasi

### 1. Instalasi

```bash
# Via Composer (development)
cd wp-content/plugins/
composer create-project your-repo/woo-odoo-integration

# Manual
# Upload folder ke wp-content/plugins/
# Aktifkan via WordPress Admin
```

### 2. Konfigurasi

Setelah aktivasi, pergi ke **WordPress Admin → Odoo Settings**:

| Field | Deskripsi | Contoh |
|-------|-----------|--------|
| `Odoo URL` | Base URL Odoo API | `https://odoo.example.com` |
| `Client ID` | OAuth2 Client ID | `your_client_id` |
| `Client Secret` | OAuth2 Client Secret | `your_secret` |
| `Grant Type` | OAuth2 grant type | `client_credentials` |
| `Scope` | API scope | `all` |

### 3. Konfigurasi via Constants (Opsional)

Tambahkan di `wp-config.php` untuk override settings:

```php
define('WOO_ODOO_INTEGRATION_API_BASE_URL', 'https://odoo.example.com');
define('WOO_ODOO_INTEGRATION_CLIENT_ID', 'your_client_id');
define('WOO_ODOO_INTEGRATION_CLIENT_SECRET', 'your_secret');
define('WOO_ODOO_INTEGRATION_GRANT_TYPE', 'client_credentials');
define('WOO_ODOO_INTEGRATION_SCOPE', 'all');
define('WOO_ODOO_INTEGRATION_TOKEN_EXPIRY', 3600);
```

### 4. Pengaturan Atribut Produk

Plugin mendukung sinkronisasi atribut berikut (dapat diaktifkan/nonaktifkan):

| Atribut | Option Key |
|---------|------------|
| Color | `enable_sync_attribut_color` |
| Size | `enable_sync_attribut_size` |
| Location | `enable_sync_attribut_location` |
| Brand | `enable_sync_attribut_brand` |
| Design Code | `enable_sync_attribut_design_code` |
| Material | `enable_sync_attribut_material` |
| Model | `enable_sync_attribut_model` |
| Process | `enable_sync_attribut_process` |
| Product Type | `enable_sync_attribut_product_type` |

---

## API Integration

### Authentication Flow

Plugin menggunakan **OAuth2 Client Credentials** flow:

```
┌─────────────┐                          ┌─────────────┐
│  WordPress  │                          │  Odoo API   │
└──────┬──────┘                          └──────┬──────┘
       │                                        │
       │ POST /api/authentication/oauth2/token  │
       │ {client_id, client_secret, grant_type} │
       │───────────────────────────────────────►│
       │                                        │
       │         {access_token, expires_in}     │
       │◄───────────────────────────────────────│
       │                                        │
       │    Store token in WP Transient         │
       │                                        │
```

### API Functions

#### Core API Functions

```php
// Get access token (with caching)
woo_odoo_integration_api_get_access_token($force_refresh = false);

// Generic API request
woo_odoo_integration_api_request($endpoint, $args = [], $method = 'GET', $retry = true);

// Convenience wrappers
woo_odoo_integration_api_get($endpoint, $query_args = []);
woo_odoo_integration_api_post($endpoint, $data = []);
woo_odoo_integration_api_put($endpoint, $data = []);
woo_odoo_integration_api_delete($endpoint);

// Token management
woo_odoo_integration_api_clear_token_cache();
woo_odoo_integration_api_test_connection();
woo_odoo_integration_api_get_token_info();
woo_odoo_integration_api_token_expires_soon($threshold_seconds = 300);
```

#### Customer API Functions

```php
// Create customer in Odoo
woo_odoo_integration_api_create_customer($customer_data, $wc_customer_id = null);

// Create guest customer
woo_odoo_integration_api_create_guest_customer($guest_data);

// Get customer by UUID
woo_odoo_integration_api_get_customer($customer_uuid);
```

#### Product API Functions

```php
// Get product stock from Odoo
woo_odoo_integration_api_get_product_stock($limit = 99999);

// Sync product stock to WooCommerce
woo_odoo_integration_sync_product_stock($product_ids);

// Get product groups
woo_odoo_integration_api_get_product_groups($page = 1, $limit = 80);
```

#### Order API Functions

```php
// Send order to Odoo
woo_odoo_integration_api_send_order($order_id);
```

#### Utility Functions

```php
// Get countries list (cached)
woo_odoo_integration_api_get_countries($force_refresh = false);

// Get country UUID by name/code
woo_odoo_integration_get_country_uuid($country_identifier);

// Clear countries cache
woo_odoo_integration_clear_countries_cache();
```

### API Endpoints Used

| Endpoint | Method | Deskripsi |
|----------|--------|-----------|
| `/api/authentication/oauth2/token` | POST | OAuth2 authentication |
| `/api/customers` | POST | Create customer |
| `/api/customers/{uuid}` | GET | Get customer |
| `/api/product-stock` | GET/POST | Get product stock |
| `/api/product-groups` | GET | Get product groups |
| `/api/countries` | GET | Get countries list |
| `/api/orders` | POST | Create order |

### Error Handling

Semua API functions mengembalikan `WP_Error` jika terjadi kesalahan:

```php
$result = woo_odoo_integration_api_get('api/products');

if (is_wp_error($result)) {
    $error_code = $result->get_error_code();
    $error_message = $result->get_error_message();
    $error_data = $result->get_error_data();
    
    // Handle error...
}
```

---

## Fitur Utama

### 1. Product Sync

#### Manual Bulk Sync (Admin)

```php
// Available via Products → Bulk Actions → "Sync Product Stock from Odoo"
```

#### Single Product Sync

Tombol "Sync Stock from Odoo" tersedia di halaman edit produk (metabox inventory).

#### Automatic Scheduled Sync

Plugin menjadwalkan 3 event cron harian:

| Event | Waktu | Hook |
|-------|-------|------|
| Product Sync | 00:00 | `woo_odoo_auto_sync_product` |
| Stock Sync | 02:00 | `woo_odoo_auto_sync_product_stock` |
| Price Sync | 04:00 | `woo_odoo_auto_sync_product_price` |

Konfigurasi scheduler:

```php
// Via WordPress Options
update_option('woo_odoo_auto_sync_chunk_size', 10);     // Products per batch
update_option('woo_odoo_auto_sync_chunk_interval', 5);  // Minutes between batches
```

### 2. Customer Sync

#### Registered Customers

Sinkronisasi terjadi secara otomatis saat checkout (`woocommerce_thankyou` hook):

```php
// Customer data stored:
update_user_meta($user_id, '_odoo_customer_uuid', $uuid);
update_user_meta($user_id, '_odoo_last_sync', time());
```

#### Guest Customers

Guest checkout disinkronisasi dengan data tersimpan di order meta:

```php
$order->update_meta_data('_odoo_guest_uuid', $uuid);
```

### 3. Order Sync

Order dikirim ke Odoo setelah checkout complete:

```php
// Triggered by woocommerce_thankyou hook
woo_odoo_integration_api_send_order($order_id);
```

### 4. Multi-Location Cart Validation

Plugin memvalidasi bahwa produk dalam cart harus dari lokasi yang sama:

```php
// Konfigurasi atribut lokasi
carbon_get_theme_option('odoo_location_att'); // default: 'pa_location'
```

Warehouse ID dapat dikonfigurasi per location term:

```php
// Term meta for pa_location taxonomy
carbon_get_term_meta($term_id, 'odoo_warehouse_id');
```

---

## Hooks & Filters

### Actions

#### Authentication

```php
// Before authentication attempt
do_action('woo_odoo_integration_before_auth');

// After successful authentication
do_action('woo_odoo_integration_auth_success', $access_token);

// After failed authentication
do_action('woo_odoo_integration_auth_failed', $error);
```

#### API Requests

```php
// Before any API request
do_action('woo_odoo_integration_before_api_request', $endpoint, $request_args);

// After successful API request
do_action('woo_odoo_integration_after_api_request', $endpoint, $response_data);

// After failed API request
do_action('woo_odoo_integration_api_request_failed', $endpoint, $error);
```

#### Customer Sync

```php
// Before creating customer in Odoo
do_action('woo_odoo_integration_before_create_customer', $customer_data);

// After successful customer creation
do_action('woo_odoo_integration_after_create_customer', $odoo_customer_data, $wc_customer_id);

// After failed customer creation
do_action('woo_odoo_integration_create_customer_failed', $error, $customer_data);

// Guest customer hooks
do_action('woo_odoo_integration_before_create_guest_customer', $guest_data);
do_action('woo_odoo_integration_after_create_guest_customer', $created_customer, $guest_data);
do_action('woo_odoo_integration_create_guest_customer_failed', $error, $guest_data);
```

#### Product Sync

```php
// Bulk sync started
do_action('woo_odoo_integration_bulk_sync_started', $post_ids);

// Bulk sync completed
do_action('woo_odoo_integration_bulk_sync_completed', $sync_results);

// Auto sync
do_action('woo_odoo_before_auto_sync_start');
do_action('woo_odoo_after_auto_sync_start', $total_products, $total_chunks);
```

#### Scheduled Tasks

```php
// Customer sync scheduled task
do_action('woo_odoo_integration_sync_customer', $customer_id);
```

### Filters

```php
// Modify API request arguments
apply_filters('woo_odoo_integration_api_request_args', $request_args, $endpoint);

// Modify API response data
apply_filters('woo_odoo_integration_api_response', $response_data, $endpoint);

// Modify customer data before sending to Odoo
apply_filters('woo_odoo_integration_customer_data', $odoo_customer_data, $customer_data);

// Modify guest customer data
apply_filters('woo_odoo_integration_guest_customer_data', $odoo_customer_data, $guest_data);

// Modify auto sync chunk size
apply_filters('woo_odoo_integration_auto_sync_chunk_size', $chunk_size);

// Modify chunk interval
apply_filters('woo_odoo_integration_auto_sync_chunk_interval', $chunk_interval);
```

---

## CLI Commands

Plugin menyediakan WP-CLI commands untuk operasi manual:

### Product Sync

```bash
# Sync products from Odoo
wp woo-odoo sync-products --page=1 --limit=100
```

### Stock Sync

```bash
# Sync product stock
wp woo-odoo sync-stock --page=1 --limit=100
```

### Price Sync

```bash
# Sync product prices
wp woo-odoo sync-price --page=1 --limit=100
```

### Options

| Option | Deskripsi | Default |
|--------|-----------|---------|
| `--page` | Page number untuk pagination | 1 |
| `--limit` | Jumlah produk per page | 80 |

---

## Database Schema

### User Meta

| Meta Key | Deskripsi | Type |
|----------|-----------|------|
| `_odoo_customer_uuid` | UUID customer di Odoo | string |
| `_odoo_last_sync` | Timestamp sinkronisasi terakhir | int |

### Order Meta

| Meta Key | Deskripsi | Type |
|----------|-----------|------|
| `_odoo_guest_uuid` | UUID guest customer di Odoo | string |
| `_odoo_order_id` | Order ID di Odoo | string |

### Term Meta (pa_location)

| Meta Key | Deskripsi | Type |
|----------|-----------|------|
| `_odoo_warehouse_id` | Warehouse ID di Odoo | string |

### WordPress Transients

| Transient Key | Deskripsi | Expiry |
|---------------|-----------|--------|
| `woo_odoo_integration_access_token` | OAuth2 access token | Dynamic (from API) |
| `woo_odoo_integration_token_info` | Full token info | Dynamic |
| `woo_odoo_integration_countries` | Countries cache | 24 hours |

### WordPress Options

| Option Key | Deskripsi |
|------------|-----------|
| `woo_odoo_auto_sync_chunk_size` | Chunk size untuk auto sync |
| `woo_odoo_auto_sync_chunk_interval` | Interval antar chunk (menit) |
| `woo_odoo_auto_sync_mode` | Mode sync aktif (product/stock/price) |

---

## Troubleshooting

### Logging

Plugin menggunakan WooCommerce Logger. View logs di:

**WooCommerce → Status → Logs**

Log sources:
- `woo-odoo-api` - API interactions
- `woo-odoo-customer-sync` - Customer sync operations
- `woo-odoo-product-sync` - Product sync operations
- `woo-odoo-scheduler` - Scheduler operations
- `woo-odoo-countries` - Countries cache operations

### Enable Debug Logging

1. Pergi ke **WordPress Admin → Odoo Settings**
2. Aktifkan **"Enable Debug Logging"**

Ini akan mencatat:
- Full request data
- Full response data
- Endpoint calls
- HTTP status codes

### Common Issues

#### 1. Authentication Failed

```
Error: Authentication failed with status 401
```

**Solusi:**
- Verifikasi Client ID dan Client Secret
- Pastikan Grant Type dan Scope sesuai
- Cek apakah Odoo API endpoint accessible

#### 2. Token Expired

```
Error: 401 Unauthorized
```

**Solusi:**
- Plugin otomatis retry dengan token refresh
- Manual: Gunakan `woo_odoo_integration_api_clear_token_cache()`

#### 3. Product Sync Timeout

**Solusi:**
- Kurangi chunk size: `update_option('woo_odoo_auto_sync_chunk_size', 5);`
- Tingkatkan PHP timeout
- Gunakan CLI untuk large syncs

#### 4. Customer Not Synced

**Checklist:**
- [ ] `enable_customer_sync` diaktifkan di settings
- [ ] Customer belum memiliki `_odoo_customer_uuid`
- [ ] Tidak ada error di WooCommerce logs

### Testing Connection

```php
// Test API connection
$result = woo_odoo_integration_api_test_connection();

if (is_wp_error($result)) {
    echo "Connection failed: " . $result->get_error_message();
} else {
    echo "Connected successfully!";
}
```

### Manual Token Refresh

```php
// Force refresh token
$token = woo_odoo_integration_api_get_access_token(true);

// Clear token cache
woo_odoo_integration_api_clear_token_cache();
```

---

## Security Considerations

### Data Masking

Sensitive data dalam logs secara otomatis di-mask:
- `client_secret`
- `access_token`
- `password`
- `authorization`

### Input Sanitization

Semua input disanitasi menggunakan WordPress functions:
- `sanitize_text_field()`
- `sanitize_email()`
- `absint()`
- `wp_json_encode()`

### Nonce Verification

AJAX requests menggunakan WordPress nonce:

```php
wp_verify_nonce($_POST['nonce'], 'woo_odoo_sync_single_product');
```

### Capability Checks

Admin actions memerlukan capability `manage_woocommerce`.

---

## Changelog

### Version 1.0.4
- Enhanced API logging with endpoint, request, and response data
- Automatic sensitive data masking
- Improved error handling

### Version 1.0.3
- Added automatic product sync scheduler
- CLI commands for manual sync

### Version 1.0.2
- Guest customer sync support
- Multi-location cart validation

### Version 1.0.1
- Customer sync functionality
- Order sync to Odoo

### Version 1.0.0
- Initial release
- Basic product stock sync
- OAuth2 authentication

---

## Support

- **Author:** Ridwan Arifandi
- **Email:** orangerdigiart@gmail.com
- **Website:** https://ridwan-arifandi.com
