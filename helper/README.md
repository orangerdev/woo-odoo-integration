# API Helper - WooCommerce Odoo Integration

This file contains all API-related functions for communicating with the Odoo ERP system.

## Quick Start

1. **Configure API Settings**: Set up your Odoo API credentials either via constants in `wp-config.php` or through the plugin admin settings.

2. **Basic API Call**:

```php
// Get products from Odoo
$products = woo_odoo_integration_api_get( 'products' );

if ( is_wp_error( $products ) ) {
    // Handle error
    error_log( $products->get_error_message() );
} else {
    // Process products data
    foreach ( $products['data'] as $product ) {
        // Do something with each product
    }
}
```

3. **Create/Update Data**:

```php
// Create new customer in Odoo
$customer_data = array(
    'name'  => 'John Doe',
    'email' => 'john@example.com',
);

$result = woo_odoo_integration_api_post( 'customers', $customer_data );
```

## Configuration

### Option 1: Constants (Recommended for production)

Add to your `wp-config.php`:

```php
define( 'WOO_ODOO_INTEGRATION_API_BASE_URL', 'https://your-odoo-instance.com/' );
define( 'WOO_ODOO_INTEGRATION_CLIENT_ID', 'your_client_id' );
define( 'WOO_ODOO_INTEGRATION_CLIENT_SECRET', 'your_client_secret' );
define( 'WOO_ODOO_INTEGRATION_GRANT_TYPE', 'client_credentials' ); // Optional
define( 'WOO_ODOO_INTEGRATION_SCOPE', 'all' ); // Optional
define( 'WOO_ODOO_INTEGRATION_TOKEN_EXPIRY', 3600 ); // Optional: Override API expires_in
```

### Option 2: Database Options

The functions will fall back to WordPress options:

- `woo_odoo_integration_api_base_url`
- `woo_odoo_integration_client_id`
- `woo_odoo_integration_client_secret`
- `woo_odoo_integration_grant_type` (defaults to 'client_credentials')
- `woo_odoo_integration_scope` (defaults to 'all')

## Available Functions

### Core Functions

- `woo_odoo_integration_api_get_access_token()` - Get/refresh access token
- `woo_odoo_integration_api_request()` - Generic API request
- `woo_odoo_integration_api_test_connection()` - Test API connectivity

### HTTP Methods

- `woo_odoo_integration_api_get()` - GET requests
- `woo_odoo_integration_api_post()` - POST requests
- `woo_odoo_integration_api_put()` - PUT requests
- `woo_odoo_integration_api_delete()` - DELETE requests

### Utility Functions

- `woo_odoo_integration_api_clear_token_cache()` - Clear cached token
- `woo_odoo_integration_api_get_token_info()` - Get full token information
- `woo_odoo_integration_api_token_expires_soon()` - Check if token expires soon

## Token Management

The system automatically handles access tokens:

1. **First Request**: Authenticates and caches token
2. **Subsequent Requests**: Uses cached token
3. **Token Expiry**: Automatically refreshes when token expires
4. **Error Handling**: Re-authenticates on 401 responses

## Error Handling

All functions return either data arrays or `WP_Error` objects:

```php
$result = woo_odoo_integration_api_get( 'products' );

if ( is_wp_error( $result ) ) {
    $error_code = $result->get_error_code();
    $error_message = $result->get_error_message();
    $error_data = $result->get_error_data();

    // Log or handle error
    error_log( "API Error [{$error_code}]: {$error_message}" );
}
```

## Hooks

The API system provides several action hooks for customization:

### Actions

- `woo_odoo_integration_before_auth` - Before authentication
- `woo_odoo_integration_auth_success` - After successful authentication
- `woo_odoo_integration_auth_failed` - When authentication fails
- `woo_odoo_integration_before_api_request` - Before any API request
- `woo_odoo_integration_after_api_request` - After successful API request
- `woo_odoo_integration_api_request_failed` - When API request fails

### Filters

- `woo_odoo_integration_api_request_args` - Modify request arguments
- `woo_odoo_integration_api_response` - Modify API response data

## Security Features

- Input sanitization using WordPress functions
- Output escaping where appropriate
- Secure HTTP requests via WordPress HTTP API
- Token storage in transients (not database)
- Automatic token refresh reduces exposure

## Development Notes

- All functions use WordPress HTTP API (`wp_remote_*` functions)
- Follows WordPress coding standards
- Comprehensive error handling with `WP_Error`
- PSR-4 compatible structure
- Full PHPDoc documentation

## Testing

Use the connection test function to verify setup:

```php
$test_result = woo_odoo_integration_api_test_connection();

if ( is_wp_error( $test_result ) ) {
    echo 'Connection failed: ' . $test_result->get_error_message();
} else {
    echo 'Connection successful!';
}
```

For complete documentation, see `docs/API-Development-Guide.md`.
