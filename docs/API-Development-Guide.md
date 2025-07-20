# API Development Guide - WooCommerce Odoo Integration Plugin

## Overview

This guide provides comprehensive documentation for developing API-related functionality in the WooCommerce Odoo Integration plugin. All API-related functions are centralized in the `helper/api.php` file to maintain clean architecture and code organization.

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Authentication System](#authentication-system)
3. [API Function Structure](#api-function-structure)
4. [Core API Functions](#core-api-functions)
5. [Error Handling](#error-handling)
6. [Security Guidelines](#security-guidelines)
7. [Code Examples](#code-examples)
8. [Testing Guidelines](#testing-guidelines)

## Architecture Overview

### File Location

- **Primary API File**: `helper/api.php`
- **Purpose**: Centralized API communication with Odoo ERP system
- **Integration**: Called from admin and public classes as needed

### Core Principles

1. **Centralization**: All API functions must be placed in `helper/api.php`
2. **WordPress Standards**: Use WordPress HTTP API functions exclusively
3. **Token Management**: Automatic access token handling with transient storage
4. **Error Handling**: Comprehensive error handling and logging
5. **Security**: Proper sanitization, validation, and nonce verification

## Authentication System

### Access Token Flow

The plugin implements a token-based authentication system with the following flow:

```
1. Check if access_token exists in WordPress transient
2. If token exists and valid → Use for API request
3. If token missing/expired → Request new token via AUTH endpoint
4. If token invalid during request → Re-authenticate and retry
5. Store new token in transient with expiration
```

### Token Storage

- **Storage Method**: WordPress Transients API
- **Transient Key**: `woo_odoo_integration_access_token` (stores access token only)
- **Token Info Key**: `woo_odoo_integration_token_info` (stores full auth response)
- **Expiration**: Based on Odoo `expires_in` response value (typically 3600 seconds/1 hour)
- **Fallback**: Re-authentication on token failure
- **Proactive Refresh**: Helper function to check expiration status

## API Function Structure

### Function Naming Convention

All API functions must follow this naming pattern:

```php
woo_odoo_integration_api_{action}_{resource}()
```

Examples:

- `woo_odoo_integration_api_get_products()`
- `woo_odoo_integration_api_create_customer()`
- `woo_odoo_integration_api_update_order()`

### Standard Function Template

```php
/**
 * Brief description of the API function
 *
 * Detailed explanation of what this function does, when to use it,
 * and any important notes about the Odoo API endpoint it calls.
 *
 * @since    1.0.0
 * @access   public
 *
 * @param    array     $data        Data to send to Odoo API
 * @param    bool      $force_auth  Force re-authentication (default: false)
 *
 * @return   array|WP_Error        API response data on success, WP_Error on failure
 *
 * @throws   Exception             When API endpoint is unreachable
 */
function woo_odoo_integration_api_example_function( $data = array(), $force_auth = false ) {
    // Implementation here
}
```

## Core API Functions

### 1. Authentication Functions

#### `woo_odoo_integration_api_authenticate()`

````php
#### `woo_odoo_integration_api_authenticate()`
```php
/**
 * Authenticate with Odoo API and retrieve access token
 *
 * This function handles the OAuth authentication flow with Odoo using the
 * correct endpoint: /api/authentication/oauth2/token
 * Returns full authentication response with token details.
 *
 * @since    1.0.0
 * @access   private
 *
 * @param    string    $api_base_url     Base URL for Odoo API
 * @param    string    $client_id        OAuth2 client ID
 * @param    string    $client_secret    OAuth2 client secret
 * @param    string    $grant_type       OAuth2 grant type (default: 'client_credentials')
 * @param    string    $scope           OAuth2 scope (default: 'all')
 *
 * @return   array|WP_Error           Full auth response on success, WP_Error on failure
 */
function woo_odoo_integration_api_authenticate( $api_base_url, $client_id, $client_secret, $grant_type = 'client_credentials', $scope = 'all' );
````

````

#### `woo_odoo_integration_api_get_access_token()`

```php
/**
 * Retrieve valid access token for Odoo API requests
 *
 * Checks transient storage first, then authenticates if needed.
 * Automatically handles token refresh and validation.
 *
 * @since    1.0.0
 * @access   public
 *
 * @param    bool    $force_refresh    Force token refresh (default: false)
 *
 * @return   string|WP_Error          Valid access token or WP_Error
 */
function woo_odoo_integration_api_get_access_token( $force_refresh = false );
````

### 2. HTTP Request Functions

#### `woo_odoo_integration_api_request()`

```php
/**
 * Make authenticated API request to Odoo
 *
 * Generic function for making HTTP requests to Odoo API endpoints.
 * Handles automatic token validation and retry logic.
 *
 * @since    1.0.0
 * @access   public
 *
 * @param    string    $endpoint     API endpoint (relative to base URL)
 * @param    array     $args         Request arguments for wp_remote_request()
 * @param    string    $method       HTTP method (GET, POST, PUT, DELETE)
 *
 * @return   array|WP_Error         Response data or WP_Error on failure
 */
function woo_odoo_integration_api_request( $endpoint, $args = array(), $method = 'GET' );
```

#### `woo_odoo_integration_api_get()`

```php
/**
 * Make GET request to Odoo API
 *
 * Wrapper for GET requests with automatic token handling.
 *
 * @since    1.0.0
 * @access   public
 *
 * @param    string    $endpoint     API endpoint
 * @param    array     $query_args   Query parameters
 *
 * @return   array|WP_Error         Response data or WP_Error on failure
 */
function woo_odoo_integration_api_get( $endpoint, $query_args = array() );
```

#### `woo_odoo_integration_api_post()`

```php
/**
 * Make POST request to Odoo API
 *
 * Wrapper for POST requests with automatic token handling and data validation.
 *
 * @since    1.0.0
 * @access   public
 *
 * @param    string    $endpoint     API endpoint
 * @param    array     $data         POST data to send
 *
 * @return   array|WP_Error         Response data or WP_Error on failure
 */
function woo_odoo_integration_api_post( $endpoint, $data = array() );
```

### 3. Resource-Specific Functions

#### Product Management

```php
/**
 * Retrieve products from Odoo
 *
 * @param    array    $filters    Product filters (category, price range, etc.)
 * @return   array|WP_Error      Product data or error
 */
function woo_odoo_integration_api_get_products( $filters = array() );

/**
 * Update product in Odoo
 *
 * @param    int      $product_id    Odoo product ID
 * @param    array    $data          Product data to update
 * @return   bool|WP_Error          Success status or error
 */
function woo_odoo_integration_api_update_product( $product_id, $data );
```

#### Customer Management

```php
/**
 * Create customer in Odoo
 *
 * Creates a new customer in Odoo ERP system using the API endpoint:
 * POST /api/customers
 *
 * @param    array    $customer_data    Customer information
 * @param    int      $wc_customer_id   WooCommerce customer ID (optional)
 * @return   array|WP_Error            Customer data with UUID or error
 */
function woo_odoo_integration_api_create_customer( $customer_data, $wc_customer_id = null );

/**
 * Retrieve customer from Odoo
 *
 * Gets customer information from Odoo using the API endpoint:
 * GET /api/customers/{customer_uuid}
 *
 * @param    string   $customer_uuid    Odoo customer UUID
 * @return   array|WP_Error            Customer data or error
 */
function woo_odoo_integration_api_get_customer( $customer_uuid );

/**
 * Update customer in Odoo
 *
 * Updates existing customer information using the API endpoint:
 * PUT /api/customers/{customer_uuid}
 *
 * @param    string   $customer_uuid    Odoo customer UUID
 * @param    array    $customer_data    Updated customer data
 * @return   array|WP_Error            Updated customer data or error
 */
function woo_odoo_integration_api_update_customer( $customer_uuid, $customer_data );

/**
 * Sync WooCommerce customer to Odoo
 *
 * Creates or updates customer in Odoo based on WooCommerce customer data.
 * Automatically handles create vs. update logic.
 *
 * @param    int      $wc_customer_id   WooCommerce customer ID
 * @param    bool     $force_update     Force update even if already synced
 * @return   array|WP_Error            Odoo customer data or error
 */
function woo_odoo_integration_api_sync_customer( $wc_customer_id, $force_update = false );
```

#### Order Management

```php
/**
 * Create order in Odoo
 *
 * @param    array    $order_data    WooCommerce order data
 * @return   int|WP_Error           Odoo order ID or error
 */
function woo_odoo_integration_api_create_order( $order_data );

/**
 * Update order status in Odoo
 *
 * @param    int      $order_id    Odoo order ID
 * @param    string   $status      New order status
 * @return   bool|WP_Error        Success status or error
 */
function woo_odoo_integration_api_update_order_status( $order_id, $status );
```

## Error Handling

### Standard Error Response Format

```php
// Example error handling in API functions
function woo_odoo_integration_api_example() {
    $response = wp_remote_get( $url, $args );

    if ( is_wp_error( $response ) ) {
        return new WP_Error(
            'api_request_failed',
            __( 'Failed to connect to Odoo API', 'woo-odoo-integration' ),
            array( 'status' => 500 )
        );
    }

    $status_code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );

    if ( $status_code !== 200 ) {
        return new WP_Error(
            'api_error_' . $status_code,
            sprintf( __( 'Odoo API returned error: %s', 'woo-odoo-integration' ), $body ),
            array( 'status' => $status_code )
        );
    }

    return json_decode( $body, true );
}
```

### Common Error Codes

| Error Code            | Description            | Action Required       |
| --------------------- | ---------------------- | --------------------- |
| `auth_failed`         | Authentication failed  | Check credentials     |
| `token_expired`       | Access token expired   | Re-authenticate       |
| `invalid_endpoint`    | API endpoint not found | Check endpoint URL    |
| `rate_limit_exceeded` | API rate limit reached | Implement retry logic |
| `validation_error`    | Invalid data sent      | Validate input data   |

## Security Guidelines

### 1. Input Validation and Sanitization

```php
function woo_odoo_integration_api_create_product( $product_data ) {
    // Validate required fields
    if ( empty( $product_data['name'] ) ) {
        return new WP_Error( 'missing_name', __( 'Product name is required', 'woo-odoo-integration' ) );
    }

    // Sanitize input data
    $sanitized_data = array(
        'name'        => sanitize_text_field( $product_data['name'] ),
        'description' => wp_kses_post( $product_data['description'] ),
        'price'       => floatval( $product_data['price'] ),
    );

    // Continue with API request...
}
```

### 2. Nonce Verification (when called from forms)

```php
function woo_odoo_integration_api_handle_form_submission() {
    // Verify nonce when function is called from admin forms
    if ( ! wp_verify_nonce( $_POST['nonce'], 'woo_odoo_api_action' ) ) {
        return new WP_Error( 'security_check_failed', __( 'Security verification failed', 'woo-odoo-integration' ) );
    }

    // Process the request...
}
```

### 3. Capability Checks

```php
function woo_odoo_integration_api_admin_function() {
    // Check user capabilities for admin functions
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return new WP_Error( 'insufficient_permissions', __( 'Insufficient permissions', 'woo-odoo-integration' ) );
    }

    // Continue with API logic...
}
```

## WordPress HTTP API Usage

### Required Functions

Always use WordPress native HTTP functions:

- `wp_remote_get()` - For GET requests
- `wp_remote_post()` - For POST requests
- `wp_safe_remote_post()` - For secure POST requests
- `wp_remote_request()` - For custom HTTP methods
- `wp_remote_retrieve_body()` - Extract response body
- `wp_remote_retrieve_response_code()` - Get HTTP status code
- `wp_remote_retrieve_headers()` - Get response headers

### Example Request Setup

```php
function woo_odoo_integration_api_make_request( $endpoint, $data = null ) {
    $access_token = woo_odoo_integration_api_get_access_token();

    if ( is_wp_error( $access_token ) ) {
        return $access_token;
    }

    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ),
        'timeout' => 30,
    );

    if ( $data !== null ) {
        $args['body'] = wp_json_encode( $data );
        $response = wp_safe_remote_post( $endpoint, $args );
    } else {
        $response = wp_remote_get( $endpoint, $args );
    }

    return woo_odoo_integration_api_handle_response( $response );
}
```

## Code Examples

### Complete Function Example

```php
/**
 * Synchronize WooCommerce product with Odoo
 *
 * Updates product information in Odoo ERP system based on WooCommerce product data.
 * Handles stock levels, pricing, and product attributes synchronization.
 *
 * @since    1.0.0
 * @access   public
 *
 * @param    int      $wc_product_id    WooCommerce product ID
 * @param    array    $sync_options     Synchronization options
 * @param    bool     $force_update     Force update even if unchanged (default: false)
 *
 * @return   array|WP_Error            Sync result data or WP_Error on failure
 *
 * @throws   InvalidArgumentException  When product ID is invalid
 */
function woo_odoo_integration_api_sync_product( $wc_product_id, $sync_options = array(), $force_update = false ) {
    // Validate input
    if ( ! is_numeric( $wc_product_id ) || $wc_product_id <= 0 ) {
        return new WP_Error(
            'invalid_product_id',
            __( 'Invalid WooCommerce product ID provided', 'woo-odoo-integration' ),
            array( 'status' => 400 )
        );
    }

    // Get WooCommerce product
    $wc_product = wc_get_product( $wc_product_id );
    if ( ! $wc_product ) {
        return new WP_Error(
            'product_not_found',
            __( 'WooCommerce product not found', 'woo-odoo-integration' ),
            array( 'status' => 404 )
        );
    }

    // Prepare product data for Odoo
    $product_data = array(
        'name'             => sanitize_text_field( $wc_product->get_name() ),
        'description'      => wp_kses_post( $wc_product->get_description() ),
        'price'            => floatval( $wc_product->get_price() ),
        'stock_quantity'   => intval( $wc_product->get_stock_quantity() ),
        'sku'              => sanitize_text_field( $wc_product->get_sku() ),
        'weight'           => floatval( $wc_product->get_weight() ),
        'dimensions'       => array(
            'length' => floatval( $wc_product->get_length() ),
            'width'  => floatval( $wc_product->get_width() ),
            'height' => floatval( $wc_product->get_height() ),
        ),
    );

    // Get Odoo product ID from meta
    $odoo_product_id = get_post_meta( $wc_product_id, '_odoo_product_id', true );

    if ( $odoo_product_id ) {
        // Update existing product in Odoo
        $endpoint = 'products/' . intval( $odoo_product_id );
        $response = woo_odoo_integration_api_request( $endpoint, array( 'body' => wp_json_encode( $product_data ) ), 'PUT' );
    } else {
        // Create new product in Odoo
        $endpoint = 'products';
        $response = woo_odoo_integration_api_request( $endpoint, array( 'body' => wp_json_encode( $product_data ) ), 'POST' );

        // Store Odoo product ID in WooCommerce
        if ( ! is_wp_error( $response ) && isset( $response['id'] ) ) {
            update_post_meta( $wc_product_id, '_odoo_product_id', intval( $response['id'] ) );
        }
    }

    // Handle response
    if ( is_wp_error( $response ) ) {
        do_action( 'woo_odoo_integration_sync_failed', $wc_product_id, $response );
        return $response;
    }

    // Update sync timestamp
    update_post_meta( $wc_product_id, '_odoo_last_sync', current_time( 'timestamp' ) );

    // Fire success hook
    do_action( 'woo_odoo_integration_sync_success', $wc_product_id, $response );

    return $response;
}
```

## Testing Guidelines

### Unit Testing Structure

```php
class WooOdooIntegrationAPITest extends WP_UnitTestCase {

    public function test_api_authentication() {
        // Mock successful authentication
        $token = woo_odoo_integration_api_authenticate();

        $this->assertIsString( $token );
        $this->assertNotEmpty( $token );
    }

    public function test_api_get_products() {
        // Test product retrieval
        $products = woo_odoo_integration_api_get_products();

        $this->assertIsArray( $products );
        $this->assertArrayHasKey( 'data', $products );
    }

    public function test_api_error_handling() {
        // Test error conditions
        $result = woo_odoo_integration_api_get_products( array( 'invalid' => 'filter' ) );

        $this->assertInstanceOf( 'WP_Error', $result );
    }
}
```

### Manual Testing Checklist

- [ ] Authentication flow works correctly
- [ ] Token refresh happens automatically
- [ ] API requests handle timeouts gracefully
- [ ] Error messages are user-friendly
- [ ] Data validation prevents invalid requests
- [ ] Security measures are properly implemented
- [ ] Transient storage works as expected
- [ ] WordPress hooks fire at appropriate times

## Configuration Constants

Define these constants in `wp-config.php` or plugin settings:

```php
// Odoo API Configuration
define( 'WOO_ODOO_INTEGRATION_API_BASE_URL', 'https://your-odoo-instance.com/' );
define( 'WOO_ODOO_INTEGRATION_CLIENT_ID', 'your_client_id' );
define( 'WOO_ODOO_INTEGRATION_CLIENT_SECRET', 'your_client_secret' );
define( 'WOO_ODOO_INTEGRATION_GRANT_TYPE', 'client_credentials' ); // Optional: defaults to 'client_credentials'
define( 'WOO_ODOO_INTEGRATION_SCOPE', 'all' ); // Optional: defaults to 'all'
define( 'WOO_ODOO_INTEGRATION_TOKEN_EXPIRY', 3600 ); // Optional: override API response expires_in
```

## Authentication System Updates

### Endpoint Format

The authentication system now uses the correct Odoo API endpoint:

```
{{base_url}}/api/authentication/oauth2/token
```

### Authentication Response

The system now properly handles the full Odoo authentication response:

```json
{
	"access_token": "j8xZvkQqTGV5YSVfP5cuOgZQkFZVVA",
	"expires_in": 3600,
	"token_type": "Bearer",
	"scope": "all"
}
```

### New Helper Functions

#### `woo_odoo_integration_api_get_token_info()`

```php
/**
 * Get cached token information including expires_in, token_type, and scope
 *
 * @return   array|false    Token information array or false if not cached
 */
function woo_odoo_integration_api_get_token_info();
```

#### `woo_odoo_integration_api_token_expires_soon()`

```php
/**
 * Check if current token is about to expire
 *
 * @param    int    $threshold_seconds    Seconds before expiry (default: 300)
 * @return   bool   True if token expires within threshold
 */
function woo_odoo_integration_api_token_expires_soon( $threshold_seconds = 300 );
```

## Hooks and Filters

### Available Action Hooks

```php
// Fired before making API request
do_action( 'woo_odoo_integration_before_api_request', $endpoint, $args );

// Fired after successful API request
do_action( 'woo_odoo_integration_after_api_request', $endpoint, $response );

// Fired when API request fails
do_action( 'woo_odoo_integration_api_request_failed', $endpoint, $error );

// Fired when authentication succeeds
do_action( 'woo_odoo_integration_auth_success', $access_token );

// Fired when authentication fails
do_action( 'woo_odoo_integration_auth_failed', $error );
```

### Available Filter Hooks

```php
// Filter API request arguments
$args = apply_filters( 'woo_odoo_integration_api_request_args', $args, $endpoint );

// Filter API response before processing
$response = apply_filters( 'woo_odoo_integration_api_response', $response, $endpoint );

// Filter product data before sending to Odoo
$product_data = apply_filters( 'woo_odoo_integration_product_data', $product_data, $wc_product );
```

---

## Additional Resources

- [WordPress HTTP API Documentation](https://developer.wordpress.org/plugins/http-api/)
- [WordPress Transients API](https://developer.wordpress.org/apis/transients/)
- [WooCommerce REST API Documentation](https://woocommerce.github.io/woocommerce-rest-api-docs/)
- [Odoo API Documentation](https://www.odoo.com/documentation/16.0/developer/reference/backend/http.html)

---

---

## Customer Update API Details

### Update Customer Request Format

The customer update API uses a PUT request to the endpoint `/api/customers/{customer_uuid}` with the following data structure:

```json
{
	"name": "Customer Test",
	"street": "Jl. Melati",
	"street2": "Block B",
	"city": "Kediri",
	"zip": 64133,
	"vat": 112233,
	"phone": "089111222333",
	"mobile": "089111222333",
	"email": "test@email.com",
	"state_id": "ac9c6b82-14c1-461d-91bd-d024b9b93e6e",
	"country_id": "1cd0040e-4217-422a-b64e-a7af2aa6f7b0"
}
```

### Customer Update Response Format

The Odoo API returns the following response structure for customer updates:

```json
{
	"code": 200,
	"data": [
		{
			"uuid": "3a293ab2-6593-4b83-9f9a-1d1009e1d2c2",
			"name": "CUSTOMER TEST",
			"address": "Jl. Melati Block B Kediri JI 64133 Indonesia",
			"street": "Jl. Melati",
			"street2": "Block B",
			"city": "Kediri",
			"zip": "64133",
			"vat": "112233",
			"phone": "089111222333",
			"mobile": "089111222333",
			"email": "test@email.com",
			"state_id": {
				"uuid": "ac9c6b82-14c1-461d-91bd-d024b9b93e6e",
				"name": "Jawa Timur (ID)"
			},
			"country_id": {
				"uuid": "1cd0040e-4217-422a-b64e-a7af2aa6f7b0",
				"name": "Indonesia"
			}
		}
	]
}
```

### WordPress Integration

The plugin automatically handles customer updates in the following scenarios:

1. **Profile Update**: When customer updates their WordPress user profile
2. **WooCommerce Customer Save**: When customer updates their WooCommerce profile
3. **Manual Admin Sync**: When admin manually triggers update from user profile
4. **Bulk Admin Sync**: When admin uses bulk sync on Users admin page

### Customer Data Mapping

| WordPress/WooCommerce Field | Odoo API Field | Data Type      | Notes                  |
| --------------------------- | -------------- | -------------- | ---------------------- |
| First + Last Name           | `name`         | string         | Combined full name     |
| User Email                  | `email`        | string         | Customer email address |
| Billing Address 1           | `street`       | string         | Primary address        |
| Billing Address 2           | `street2`      | string         | Secondary address      |
| Billing City                | `city`         | string         | City name              |
| Billing Postcode            | `zip`          | integer/string | Postal code            |
| Billing Phone               | `phone`        | string         | Primary phone          |
| Billing Phone               | `mobile`       | string         | Mobile (same as phone) |
| Billing State               | `state_id`     | string         | State/Province UUID    |
| Billing Country             | `country_id`   | string         | Country UUID           |
| -                           | `vat`          | integer/string | VAT number (optional)  |

_This documentation should be updated whenever new API functions are added or existing ones are modified. All developers working on API functionality must follow these guidelines to ensure consistency and maintainability._
