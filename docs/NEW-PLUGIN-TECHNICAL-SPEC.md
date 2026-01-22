# Bumilindo WooCommerce Integration - Technical Specification

> **Document Version:** 1.0  
> **Date:** 4 January 2026  
> **Status:** Planning Phase

---

## 1. Overview

### 1.1 Project Summary

Membuat plugin WordPress baru untuk integrasi WooCommerce dengan Bumilindo API (Altius ERP system). Plugin ini menggantikan plugin "WooCommerce Odoo Integration" yang existing.

### 1.2 Key Differences from Old Plugin

| Aspek | Old Plugin (Odoo) | New Plugin (Bumilindo) |
|-------|-------------------|------------------------|
| Authentication | OAuth2 Client Credentials | Username/Password Login |
| Base URL | Configurable Odoo URL | Bumilindo API URL |
| Customer Sync | Separate API endpoint | Embedded in Checkout |
| Product Sync | Multiple endpoints | Single endpoint |
| Order Sync | `/api/orders` | `/checkout-ecommerce` |
| Payment Details | Not required | Required with details |

### 1.3 Plugin Information

```
Plugin Name:       Bumilindo WooCommerce Integration
Plugin URI:        https://ridwan-arifandi.com/portfolio/bumilindo-woocommerce-integration/
Description:       WooCommerce integration with Bumilindo API for product sync, checkout, and inventory management.
Version:           1.0.0
Author:            Ridwan Arifandi
Text Domain:       bumilindo-woo-integration
```

---

## 2. System Requirements

| Component | Minimum Version |
|-----------|-----------------|
| PHP | 7.4+ |
| WordPress | 5.8+ |
| WooCommerce | 6.0+ |
| Carbon Fields | 3.0+ (bundled) |

---

## 3. Architecture

### 3.1 Directory Structure

```
bumilindo-woo-integration/
├── bumilindo-woo-integration.php      # Main plugin file
├── uninstall.php                       # Cleanup on uninstall
├── composer.json
│
├── includes/
│   ├── class-plugin.php                # Main plugin class
│   ├── class-loader.php                # Hook loader
│   ├── class-activator.php             # Activation hooks
│   ├── class-deactivator.php           # Deactivation hooks
│   └── class-i18n.php                  # Internationalization
│
├── src/
│   ├── Api/
│   │   ├── Client.php                  # API client (authentication, requests)
│   │   ├── Authentication.php          # Login & token management
│   │   ├── ProductApi.php              # Product-related API calls
│   │   └── CheckoutApi.php             # Checkout/Order API calls
│   │
│   ├── Admin/
│   │   ├── Settings.php                # Admin settings (Carbon Fields)
│   │   ├── ProductSync.php             # Product sync admin UI
│   │   └── Assets.php                  # Admin CSS/JS
│   │
│   ├── Frontend/
│   │   ├── Checkout.php                # Checkout modifications (NIK, DOB)
│   │   ├── Cart.php                    # Cart validation (gudang check)
│   │   └── Assets.php                  # Frontend CSS/JS
│   │
│   ├── Sync/
│   │   ├── ProductImporter.php         # Import products from API
│   │   ├── CategorySync.php            # Category synchronization
│   │   ├── ImageHandler.php            # Download & attach images
│   │   └── VariationBuilder.php        # Build WC variations
│   │
│   ├── Webhook/
│   │   └── ProductWebhook.php          # Webhook endpoint for product updates
│   │
│   └── Cli/
│       ├── SyncCommand.php             # WP-CLI sync commands
│       └── TestCommand.php             # WP-CLI test commands
│
├── admin/
│   ├── css/
│   ├── js/
│   └── views/
│
├── public/
│   ├── css/
│   └── js/
│
├── languages/
│   └── bumilindo-woo-integration.pot
│
└── vendor/                             # Composer dependencies
```

### 3.2 Class Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                              Plugin                                  │
│  (Main orchestrator - loads dependencies, registers hooks)          │
└─────────────────────────────────┬───────────────────────────────────┘
                                  │
         ┌────────────────────────┼────────────────────────┐
         │                        │                        │
         ▼                        ▼                        ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Admin Module  │    │ Frontend Module │    │   API Module    │
├─────────────────┤    ├─────────────────┤    ├─────────────────┤
│ • Settings      │    │ • Checkout      │    │ • Client        │
│ • ProductSync   │    │ • Cart          │    │ • Authentication│
│ • Assets        │    │ • Assets        │    │ • ProductApi    │
└────────┬────────┘    └────────┬────────┘    │ • CheckoutApi   │
         │                      │             └────────┬────────┘
         │                      │                      │
         └──────────────────────┴──────────────────────┘
                                │
                    ┌───────────┴───────────┐
                    │                       │
                    ▼                       ▼
         ┌─────────────────┐    ┌─────────────────┐
         │   Sync Module   │    │ Webhook Module  │
         ├─────────────────┤    ├─────────────────┤
         │ • ProductImport │    │ • ProductWebhook│
         │ • CategorySync  │    └─────────────────┘
         │ • ImageHandler  │
         │ • VariationBuild│
         └─────────────────┘
```

---

## 4. API Integration

### 4.1 API Configuration

**Base URL:** `https://api-pipe.bumilindo.co.id/v1` (configurable)

**Authentication:** Bearer Token via Login

### 4.2 Authentication Flow

```
┌──────────────┐                              ┌──────────────┐
│  WordPress   │                              │ Bumilindo API│
└──────┬───────┘                              └──────┬───────┘
       │                                             │
       │  POST /login-ecommerce                      │
       │  {Username, Password}                       │
       │────────────────────────────────────────────►│
       │                                             │
       │  {access_token, expired_at, username}       │
       │◄────────────────────────────────────────────│
       │                                             │
       │  Store in WP Transient                      │
       │  (calculate TTL from expired_at)            │
       │                                             │
```

**Token Storage:**
- Transient Key: `bumilindo_access_token`
- TTL: Calculated from `expired_at` datetime

### 4.3 API Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/login-ecommerce` | POST | Authentication |
| `/getproduct-ecommerce` | GET | Get all products |
| `/checkout-ecommerce` | POST | Submit order/checkout |

### 4.4 API Client Class

```php
namespace Bumilindo\Api;

class Client {
    private string $base_url;
    private ?string $access_token = null;
    
    public function __construct(string $base_url);
    
    // Authentication
    public function authenticate(string $username, string $password): array|WP_Error;
    public function getAccessToken(bool $force_refresh = false): string|WP_Error;
    public function isTokenValid(): bool;
    
    // HTTP Methods
    public function get(string $endpoint, array $params = []): array|WP_Error;
    public function post(string $endpoint, array $data = []): array|WP_Error;
    
    // Utility
    public function clearTokenCache(): bool;
    public function testConnection(): array|WP_Error;
}
```

---

## 5. Features Specification

### 5.1 Admin Settings

**Location:** WordPress Admin → Bumilindo Settings

**Fields:**

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `api_base_url` | text | Yes | `https://api-pipe.bumilindo.co.id/v1` | API Base URL |
| `api_username` | text | Yes | - | API Username (email) |
| `api_password` | password | Yes | - | API Password |
| `enable_debug_logging` | checkbox | No | false | Enable detailed logging |
| `auto_sync_enabled` | checkbox | No | false | Enable automatic product sync |
| `auto_sync_interval` | select | No | daily | Sync frequency |

**Carbon Fields Implementation:**

```php
Container::make('theme_options', __('Bumilindo Settings', 'bumilindo-woo-integration'))
    ->set_page_menu_position(60)
    ->add_tab(__('API Configuration', 'bumilindo-woo-integration'), [
        Field::make('text', 'bumilindo_api_url', __('API Base URL'))
            ->set_default_value('https://api-pipe.bumilindo.co.id/v1'),
        Field::make('text', 'bumilindo_api_username', __('API Username')),
        Field::make('text', 'bumilindo_api_password', __('API Password'))
            ->set_attribute('type', 'password'),
    ])
    ->add_tab(__('Sync Settings', 'bumilindo-woo-integration'), [
        Field::make('checkbox', 'bumilindo_auto_sync', __('Enable Auto Sync')),
        Field::make('select', 'bumilindo_sync_interval', __('Sync Interval'))
            ->set_options([
                'hourly' => 'Every Hour',
                'twicedaily' => 'Twice Daily',
                'daily' => 'Daily',
            ]),
    ])
    ->add_tab(__('Debug', 'bumilindo-woo-integration'), [
        Field::make('checkbox', 'bumilindo_debug_logging', __('Enable Debug Logging')),
    ]);
```

---

### 5.2 Product Sync

#### 5.2.1 API Response Structure

```json
{
    "success": true,
    "records": 100,
    "data": [
        {
            "cabang": "BL001/09",
            "nama_cabang": "BUMILINK - SES JENGGOLO",
            "gudang_utama": "BL001/09/0000001",
            "kode_variant": "010335EDA",
            "nama_variant": "SAMSUNG S928 12/256 BLACK SEIN - S24 ULTRA",
            "ukuran": "12/256",
            "warna": "BLACK",
            "spesifikasi": "S24 ULTRA",
            "path_gambar": "https://...",
            "harga": "17999000.0000",
            "stok": "1",
            "child_kategori": "S Series",
            "parent_kategori": "HANDPHONE",
            "product_type": "UMUM",
            "alias_kode2_product": "Samsung Galaxy S24 Ultra SEIN Resmi",
            "spesifikasi2_product": "<HTML description>"
        }
    ]
}
```

#### 5.2.2 WooCommerce Mapping

| API Field | WooCommerce Field | Notes |
|-----------|-------------------|-------|
| `alias_kode2_product` | Product Name (parent) | Used for grouping variants |
| `kode_variant` | SKU | Unique identifier |
| `nama_variant` | Variation description | - |
| `harga` | Regular Price | Convert to float |
| `stok` | Stock Quantity | Convert to int |
| `spesifikasi2_product` | Product Description | HTML content |
| `path_gambar` | Product Image | Download to Media Library |
| `parent_kategori` | Product Category (parent) | Auto-create if not exists |
| `child_kategori` | Product Category (child) | Auto-create if not exists |
| `warna` | Attribute: Color (`pa_warna`) | Variation attribute |
| `ukuran` | Attribute: Size (`pa_ukuran`) | Variation attribute |
| `spesifikasi` | Attribute: Spec (`pa_spesifikasi`) | Variation attribute |
| `gudang_utama` | Product Meta: `_gudang_utama` | For cart validation |
| `cabang` | Product Meta: `_cabang` | Store branch code |
| `nama_cabang` | Product Meta: `_nama_cabang` | Store branch name |

#### 5.2.3 Variant Grouping Logic

```php
/**
 * Group products by alias_kode2_product to create Variable Products
 * 
 * Algorithm:
 * 1. Fetch all products from API
 * 2. Group by alias_kode2_product
 * 3. If group has 1 item → Simple Product
 * 4. If group has >1 items → Variable Product with variations
 */

class ProductImporter {
    
    public function import(array $api_products): array {
        // Group by parent product name
        $grouped = $this->groupByParent($api_products);
        
        $results = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];
        
        foreach ($grouped as $parent_name => $variants) {
            if (count($variants) === 1) {
                // Create/Update Simple Product
                $result = $this->createSimpleProduct($variants[0]);
            } else {
                // Create/Update Variable Product with Variations
                $result = $this->createVariableProduct($parent_name, $variants);
            }
            
            // Update results...
        }
        
        return $results;
    }
    
    private function groupByParent(array $products): array {
        $grouped = [];
        foreach ($products as $product) {
            $parent_key = $product['alias_kode2_product'] ?? $product['nama_variant'];
            $grouped[$parent_key][] = $product;
        }
        return $grouped;
    }
}
```

#### 5.2.4 Category Sync

```php
class CategorySync {
    
    /**
     * Ensure category hierarchy exists
     * 
     * @param string $parent_name Parent category name (e.g., "HANDPHONE")
     * @param string $child_name  Child category name (e.g., "S Series")
     * @return int Child category term_id
     */
    public function ensureCategoryHierarchy(string $parent_name, string $child_name): int {
        // 1. Find or create parent category
        $parent_term = term_exists($parent_name, 'product_cat');
        if (!$parent_term) {
            $parent_term = wp_insert_term($parent_name, 'product_cat');
        }
        $parent_id = is_array($parent_term) ? $parent_term['term_id'] : $parent_term;
        
        // 2. Find or create child category under parent
        $child_term = term_exists($child_name, 'product_cat', $parent_id);
        if (!$child_term) {
            $child_term = wp_insert_term($child_name, 'product_cat', [
                'parent' => $parent_id
            ]);
        }
        
        return is_array($child_term) ? $child_term['term_id'] : $child_term;
    }
}
```

#### 5.2.5 Image Handler

```php
class ImageHandler {
    
    /**
     * Download image from URL and attach to product
     * 
     * @param string $image_url Remote image URL
     * @param int    $product_id WooCommerce product ID
     * @return int|WP_Error Attachment ID or error
     */
    public function downloadAndAttach(string $image_url, int $product_id): int|WP_Error {
        // Check if image already downloaded (by URL hash)
        $existing = $this->findExistingByUrl($image_url);
        if ($existing) {
            set_post_thumbnail($product_id, $existing);
            return $existing;
        }
        
        // Download image
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $attachment_id = media_sideload_image($image_url, $product_id, '', 'id');
        
        if (!is_wp_error($attachment_id)) {
            // Store original URL for future reference
            update_post_meta($attachment_id, '_source_url', $image_url);
            set_post_thumbnail($product_id, $attachment_id);
        }
        
        return $attachment_id;
    }
}
```

---

### 5.3 Checkout Integration

#### 5.3.1 Custom Checkout Fields

**Required Fields:**
- NIK (16 digits) - Mandatory
- Tanggal Lahir (Date) - Mandatory

```php
class Checkout {
    
    public function addCustomFields($checkout) {
        echo '<div id="bumilindo_custom_fields">';
        
        // NIK Field
        woocommerce_form_field('billing_nik', [
            'type' => 'text',
            'class' => ['form-row-wide'],
            'label' => __('NIK (Nomor Induk Kependudukan)', 'bumilindo-woo-integration'),
            'required' => true,
            'maxlength' => 16,
            'custom_attributes' => [
                'pattern' => '[0-9]{16}',
                'inputmode' => 'numeric',
            ],
        ], $checkout->get_value('billing_nik'));
        
        // Date of Birth Field
        woocommerce_form_field('billing_tanggal_lahir', [
            'type' => 'date',
            'class' => ['form-row-wide'],
            'label' => __('Tanggal Lahir', 'bumilindo-woo-integration'),
            'required' => true,
        ], $checkout->get_value('billing_tanggal_lahir'));
        
        echo '</div>';
    }
    
    public function validateCustomFields() {
        // NIK Validation
        if (empty($_POST['billing_nik'])) {
            wc_add_notice(__('NIK is required.', 'bumilindo-woo-integration'), 'error');
        } elseif (!preg_match('/^[0-9]{16}$/', $_POST['billing_nik'])) {
            wc_add_notice(__('NIK must be 16 digits.', 'bumilindo-woo-integration'), 'error');
        }
        
        // Date of Birth Validation
        if (empty($_POST['billing_tanggal_lahir'])) {
            wc_add_notice(__('Date of Birth is required.', 'bumilindo-woo-integration'), 'error');
        }
    }
    
    public function saveCustomFields($order_id) {
        if (!empty($_POST['billing_nik'])) {
            update_post_meta($order_id, '_billing_nik', sanitize_text_field($_POST['billing_nik']));
        }
        if (!empty($_POST['billing_tanggal_lahir'])) {
            update_post_meta($order_id, '_billing_tanggal_lahir', sanitize_text_field($_POST['billing_tanggal_lahir']));
        }
    }
}
```

#### 5.3.2 Order Sync to Bumilindo

**Trigger:** `woocommerce_thankyou` hook

**Request Structure:**

```php
class CheckoutApi {
    
    /**
     * Send order to Bumilindo API
     */
    public function sendOrder(WC_Order $order): array|WP_Error {
        $order_data = $this->buildOrderPayload($order);
        
        // Allow modification via filter
        $order_data = apply_filters('bumilindo_checkout_payload', $order_data, $order);
        
        return $this->client->post('/checkout-ecommerce', $order_data);
    }
    
    private function buildOrderPayload(WC_Order $order): array {
        // Get gudang from first item (all items should have same gudang)
        $gudang = $this->getOrderGudang($order);
        
        // Build detail items
        $detail = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $detail[] = [
                'sku' => $product->get_sku(),
                'flag' => $this->isFreeItem($item) ? 'YES' : 'NO',
                'harga' => (string) $item->get_subtotal() / $item->get_quantity(),
                'qty' => (string) $item->get_quantity(),
                'disc_rp' => (string) ($item->get_subtotal() - $item->get_total()),
                'disc_persen' => '0',
            ];
        }
        
        // Build payment details (static with filter)
        $detail_pembayaran = $this->buildPaymentDetails($order);
        
        // Keterangan (static with filter)
        $keterangan = apply_filters(
            'bumilindo_checkout_keterangan', 
            'Order dari WooCommerce', 
            $order
        );
        
        return [
            'gudang' => $gudang,
            'transaction_id' => $this->generateTransactionId($order),
            'tanggal' => $order->get_date_created()->format('Y-m-d'),
            'keterangan' => $keterangan,
            'email_customer' => $order->get_billing_email(),
            'nama_customer' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'alamat' => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
            'telp' => $order->get_billing_phone(),
            'tanggal_lahir' => $order->get_meta('_billing_tanggal_lahir'),
            'nik' => $order->get_meta('_billing_nik'),
            'detail' => $detail,
            'detailpembayaran' => $detail_pembayaran,
        ];
    }
    
    private function generateTransactionId(WC_Order $order): string {
        $year = $order->get_date_created()->format('Y');
        $order_id = $order->get_id();
        
        // Format: TXN-2025-123
        return apply_filters(
            'bumilindo_transaction_id_format',
            sprintf('TXN-%s-%d', $year, $order_id),
            $order
        );
    }
    
    private function buildPaymentDetails(WC_Order $order): array {
        // Default static payment detail
        $default_payment = [
            [
                'bentuk_dana' => 'TRANSFER',
                'setor_kebank' => 'BL001/1010102101001',
                'bank' => 'BCA',
                'nomor_kartukredit' => '',
                'nominal_payment' => (string) $order->get_total(),
            ]
        ];
        
        // Allow customization via filter
        return apply_filters('bumilindo_payment_details', $default_payment, $order);
    }
    
    private function isFreeItem(WC_Order_Item_Product $item): bool {
        // Check if item total is 0 (free item from coupon/discount)
        return $item->get_total() == 0 && $item->get_quantity() > 0;
    }
}
```

---

### 5.4 Cart Validation

#### 5.4.1 Gudang Check on Add to Cart

```php
class Cart {
    
    /**
     * Validate add to cart - clear cart if different gudang
     */
    public function validateAddToCart($passed, $product_id, $quantity, $variation_id = 0) {
        if (!$passed) {
            return $passed;
        }
        
        // Get gudang of product being added
        $adding_product_id = $variation_id ?: $product_id;
        $adding_gudang = get_post_meta($adding_product_id, '_gudang_utama', true);
        
        // If no gudang set, allow add
        if (empty($adding_gudang)) {
            return $passed;
        }
        
        // Check existing cart items
        $cart = WC()->cart;
        if ($cart->is_empty()) {
            return $passed;
        }
        
        foreach ($cart->get_cart() as $cart_item) {
            $cart_product_id = $cart_item['variation_id'] ?: $cart_item['product_id'];
            $cart_gudang = get_post_meta($cart_product_id, '_gudang_utama', true);
            
            // If cart has item with different gudang
            if (!empty($cart_gudang) && $cart_gudang !== $adding_gudang) {
                // Clear cart
                $cart->empty_cart();
                
                // Add notice
                wc_add_notice(
                    __('Cart has been cleared. You can only add products from the same warehouse.', 'bumilindo-woo-integration'),
                    'notice'
                );
                
                // Allow adding the new product
                return $passed;
            }
        }
        
        return $passed;
    }
}
```

---

### 5.5 Webhook for Product Updates

#### 5.5.1 Endpoint Registration

```php
class ProductWebhook {
    
    public function registerEndpoint() {
        register_rest_route('bumilindo/v1', '/product-update', [
            'methods' => 'POST',
            'callback' => [$this, 'handleProductUpdate'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }
    
    public function checkPermission(WP_REST_Request $request) {
        // Use WooCommerce REST API authentication
        return current_user_can('manage_woocommerce') || 
               $this->validateApiKey($request);
    }
    
    private function validateApiKey(WP_REST_Request $request): bool {
        $api_key = $request->get_header('X-API-Key');
        $stored_key = carbon_get_theme_option('bumilindo_webhook_api_key');
        
        return !empty($api_key) && hash_equals($stored_key, $api_key);
    }
    
    public function handleProductUpdate(WP_REST_Request $request) {
        $products = $request->get_json_params();
        
        if (empty($products) || !is_array($products)) {
            return new WP_Error('invalid_data', 'Invalid product data', ['status' => 400]);
        }
        
        $importer = new ProductImporter();
        $results = $importer->import($products);
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Products updated successfully',
            'results' => $results,
        ], 200);
    }
}
```

---

### 5.6 WP-CLI Commands

```php
class SyncCommand {
    
    /**
     * Sync products from Bumilindo API
     * 
     * ## EXAMPLES
     * 
     *     wp bumilindo sync products
     *     wp bumilindo sync products --dry-run
     * 
     * @when after_wp_load
     */
    public function products($args, $assoc_args) {
        $dry_run = isset($assoc_args['dry-run']);
        
        WP_CLI::log('Fetching products from Bumilindo API...');
        
        $api = new ProductApi();
        $products = $api->getProducts();
        
        if (is_wp_error($products)) {
            WP_CLI::error('Failed to fetch products: ' . $products->get_error_message());
            return;
        }
        
        WP_CLI::log(sprintf('Found %d products', count($products)));
        
        if ($dry_run) {
            WP_CLI::success('Dry run completed. No changes made.');
            return;
        }
        
        $importer = new ProductImporter();
        $results = $importer->import($products);
        
        WP_CLI::success(sprintf(
            'Sync completed. Created: %d, Updated: %d, Skipped: %d, Errors: %d',
            $results['created'],
            $results['updated'],
            $results['skipped'],
            count($results['errors'])
        ));
    }
    
    /**
     * Test API connection
     * 
     * ## EXAMPLES
     * 
     *     wp bumilindo test connection
     */
    public function connection($args, $assoc_args) {
        WP_CLI::log('Testing API connection...');
        
        $client = new Client(carbon_get_theme_option('bumilindo_api_url'));
        $result = $client->testConnection();
        
        if (is_wp_error($result)) {
            WP_CLI::error('Connection failed: ' . $result->get_error_message());
            return;
        }
        
        WP_CLI::success('Connection successful!');
    }
}

// Register commands
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('bumilindo sync', [SyncCommand::class, 'products']);
    WP_CLI::add_command('bumilindo test', [SyncCommand::class, 'connection']);
}
```

---

## 6. Hooks & Filters Reference

### 6.1 Actions

| Hook | Parameters | Description |
|------|------------|-------------|
| `bumilindo_before_auth` | - | Before authentication attempt |
| `bumilindo_auth_success` | `$token` | After successful authentication |
| `bumilindo_auth_failed` | `$error` | After failed authentication |
| `bumilindo_before_product_sync` | - | Before product sync starts |
| `bumilindo_after_product_sync` | `$results` | After product sync completes |
| `bumilindo_product_imported` | `$product_id`, `$api_data` | After single product imported |
| `bumilindo_before_checkout_sync` | `$order` | Before sending order to API |
| `bumilindo_after_checkout_sync` | `$order`, `$response` | After order sent to API |
| `bumilindo_cart_cleared_gudang` | `$old_gudang`, `$new_gudang` | When cart cleared due to gudang mismatch |

### 6.2 Filters

| Filter | Parameters | Default | Description |
|--------|------------|---------|-------------|
| `bumilindo_api_base_url` | `$url` | From settings | Modify API base URL |
| `bumilindo_checkout_payload` | `$payload`, `$order` | Built payload | Modify checkout payload |
| `bumilindo_payment_details` | `$details`, `$order` | Static data | Customize payment details |
| `bumilindo_checkout_keterangan` | `$keterangan`, `$order` | Static text | Customize order notes |
| `bumilindo_transaction_id_format` | `$txn_id`, `$order` | `TXN-{Y}-{ID}` | Customize transaction ID |
| `bumilindo_product_data` | `$data`, `$api_data` | Mapped data | Modify product data before save |
| `bumilindo_is_free_item` | `$is_free`, `$item` | From price check | Override free item detection |

---

## 7. Database Schema

### 7.1 Post Meta (Products)

| Meta Key | Description |
|----------|-------------|
| `_gudang_utama` | Warehouse code for cart validation |
| `_cabang` | Branch code |
| `_nama_cabang` | Branch name |
| `_bumilindo_kode_variant` | Original variant code from API |
| `_bumilindo_last_sync` | Last sync timestamp |

### 7.2 Post Meta (Orders)

| Meta Key | Description |
|----------|-------------|
| `_billing_nik` | Customer NIK |
| `_billing_tanggal_lahir` | Customer date of birth |
| `_bumilindo_transaction_id` | Transaction ID sent to API |
| `_bumilindo_sync_status` | Sync status (pending/success/failed) |
| `_bumilindo_sync_response` | API response (JSON) |

### 7.3 Transients

| Transient Key | TTL | Description |
|---------------|-----|-------------|
| `bumilindo_access_token` | Dynamic (from API) | API access token |
| `bumilindo_token_expires_at` | Same as token | Token expiration datetime |

### 7.4 Options

| Option Key | Description |
|------------|-------------|
| `_bumilindo_api_url` | API Base URL |
| `_bumilindo_api_username` | API Username |
| `_bumilindo_api_password` | API Password (encrypted) |
| `_bumilindo_auto_sync` | Auto sync enabled |
| `_bumilindo_sync_interval` | Sync interval |
| `_bumilindo_debug_logging` | Debug logging enabled |
| `_bumilindo_webhook_api_key` | Webhook API key |

---

## 8. Error Codes

| Code | Description | Resolution |
|------|-------------|------------|
| `auth_failed` | Authentication failed | Check username/password |
| `invalid_credentials` | Invalid API credentials | Verify credentials in settings |
| `token_expired` | Access token expired | Will auto-refresh |
| `api_error` | General API error | Check API status |
| `invalid_response` | Invalid JSON response | Contact API provider |
| `product_not_found` | Product SKU not found | Check SKU mapping |
| `checkout_failed` | Checkout sync failed | Check order data |

---

## 9. Migration from Old Plugin

### 9.1 Data Migration

The new plugin does NOT automatically migrate data from old plugin. Manual steps:

1. **Products:** Re-sync from Bumilindo API (recommended)
2. **Orders:** Historical orders remain unchanged
3. **Settings:** Must be reconfigured

### 9.2 Deactivation Steps

1. Deactivate old plugin "WooCommerce Odoo Integration"
2. Activate new plugin "Bumilindo WooCommerce Integration"
3. Configure API settings
4. Run initial product sync
5. Test checkout flow
6. Delete old plugin (optional)

---

## 10. Testing Checklist

### 10.1 API Tests

- [ ] Authentication with valid credentials
- [ ] Authentication with invalid credentials
- [ ] Token refresh on expiration
- [ ] Get products endpoint
- [ ] Checkout endpoint

### 10.2 Product Sync Tests

- [ ] Simple product creation
- [ ] Variable product creation
- [ ] Variation attributes (warna, ukuran, spesifikasi)
- [ ] Category hierarchy creation
- [ ] Image download and attachment
- [ ] Stock quantity update
- [ ] Price update

### 10.3 Checkout Tests

- [ ] NIK field validation (16 digits)
- [ ] Date of birth field validation
- [ ] Order sync to API
- [ ] Transaction ID format
- [ ] Payment details structure

### 10.4 Cart Tests

- [ ] Add product from gudang A
- [ ] Add product from same gudang A (should work)
- [ ] Add product from different gudang B (should clear cart)
- [ ] Notice displayed when cart cleared

### 10.5 Webhook Tests

- [ ] Endpoint accessible
- [ ] Authentication required
- [ ] Product update via webhook
- [ ] Invalid data handling

---

## 11. Implementation Timeline

| Phase | Tasks | Estimated Time |
|-------|-------|----------------|
| **Phase 1** | Plugin scaffold, API Client, Authentication | 2-3 hours |
| **Phase 2** | Admin Settings, Product Sync (Simple) | 3-4 hours |
| **Phase 3** | Product Sync (Variable), Category, Image | 3-4 hours |
| **Phase 4** | Checkout Fields, Order Sync | 2-3 hours |
| **Phase 5** | Cart Validation, Webhook | 2 hours |
| **Phase 6** | CLI Commands, Testing | 2 hours |
| **Phase 7** | Documentation, Cleanup | 1 hour |

**Total Estimated:** 15-19 hours

---

## 12. Open Questions / Assumptions

1. **Variant Grouping:** Assuming `alias_kode2_product` is used to group variants. Need to verify with real data.

2. **Payment Details:** Currently static. Will need filter customization for different payment methods.

3. **Gudang per Product:** Assuming each product/variation has one gudang. If multiple, need additional logic.

4. **Image URL:** Assuming `path_gambar` contains valid, accessible URL.

5. **API Rate Limiting:** Unknown. May need throttling for large syncs.

---

**Document prepared by:** GitHub Copilot  
**Review status:** Pending client review
