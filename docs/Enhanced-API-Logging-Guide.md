# Enhanced API Logging for WooCommerce Odoo Integration

## Overview

The WooCommerce Odoo Integration plugin now includes comprehensive API logging that captures:

1. **Endpoint**: The API endpoint being called
2. **Request Data**: All data sent to the API (body, query parameters, etc.)
3. **Response Data**: Complete response received from the API
4. **HTTP Status Codes**: Response status codes for debugging
5. **Request Methods**: HTTP method used (GET, POST, PUT, DELETE)
6. **Timestamps**: When each API call was made

## Features

### Security & Privacy

- **Sensitive Data Masking**: Automatically masks sensitive information like:
  - Client secrets
  - Access tokens
  - Passwords
  - Authorization headers
  - Any field containing "secret", "token", "password", etc.

### Configurable Logging Levels

- **Error Logging**: Always enabled - captures all API errors
- **Debug Logging**: Optional - captures detailed request/response data
- **Info/Warning Logging**: Captures basic API call information

### Admin Configuration

A new setting has been added to the Odoo Settings page:

- **Enable Debug Logging**: Toggle detailed API logging on/off

## Configuration

### Admin Settings

1. Go to **WordPress Admin > Odoo Settings**
2. Check **"Enable Debug Logging"** to enable detailed logging
3. Save settings

### Programmatic Configuration

You can also control logging via constants in `wp-config.php`:

```php
// Force enable debug logging
define('WOO_ODOO_INTEGRATION_DEBUG_LOGGING', true);

// Disable all API logging (not recommended)
define('WOO_ODOO_INTEGRATION_DISABLE_LOGGING', true);
```

## Log Format

### Detailed Log Entry (Debug Mode Enabled)

```
Odoo API Call: POST api/customers | Status: 200 | Request Data: {"name":"John Doe","email":"john@example.com","country_id":"uuid-123"} | Response: {"code":200,"data":[{"uuid":"customer-uuid-456","name":"John Doe"}]}
```

### Simple Log Entry (Debug Mode Disabled)

```
Odoo API Call: POST api/customers | Status: 200
```

### Error Log Entry (Always Detailed)

```
Odoo API Call: POST api/customers | Status: 400 | Request Data: {"name":"","email":"invalid-email"} | Response: {"error_code":"validation_failed","error_message":"Invalid email format","error_data":{"field":"email"}}
```

## Viewing Logs

### WooCommerce Status Logs

1. Go to **WooCommerce > Status > Logs**
2. Look for log files with source: `woo-odoo-api`
3. Log entries are timestamped and categorized by level

### Log Levels

- **Error**: API failures, authentication issues, validation errors
- **Warning**: Token expiration, fallback behaviors
- **Info**: Successful API calls, general information
- **Debug**: Detailed debugging information

## API Functions Enhanced

All the following functions now include comprehensive logging:

### Authentication Functions

- `woo_odoo_integration_api_authenticate()`
- `woo_odoo_integration_api_get_access_token()`

### HTTP Request Functions

- `woo_odoo_integration_api_request()` (main function)
- `woo_odoo_integration_api_get()`
- `woo_odoo_integration_api_post()`
- `woo_odoo_integration_api_put()`
- `woo_odoo_integration_api_delete()`

### Resource-Specific Functions

- `woo_odoo_integration_api_create_customer()`
- `woo_odoo_integration_api_update_customer()`
- `woo_odoo_integration_api_get_countries()`
- `woo_odoo_integration_api_get_product_stock()`
- All other API functions that make HTTP requests

## Logged Information

### Request Data Includes

- **HTTP Method**: GET, POST, PUT, DELETE
- **Endpoint**: Relative API path (e.g., `api/customers`)
- **Request Body**: JSON data sent to API
- **Query Parameters**: URL parameters for GET requests
- **Headers**: Request headers (sensitive ones masked)

### Response Data Includes

- **Status Code**: HTTP response code (200, 400, 401, 500, etc.)
- **Response Body**: Complete JSON response from API
- **Error Details**: Error codes and messages for failed requests
- **Processing Time**: How long the request took

### Sensitive Data Masking

The following fields are automatically masked in logs:

- `client_secret` → `cli***ret`
- `access_token` → `acc***ken`
- `password` → `***masked***`
- `authorization` → `Bea***123` (Bearer tokens)

## Troubleshooting

### Common Log Messages

#### Authentication Success

```
Odoo API Call: POST api/authentication/oauth2/token | Status: 200 | Request Data: {"client_id":"your_id","client_secret":"cli***ret","grant_type":"client_credentials","scope":"all"} | Response: {"access_token":"acc***ken","expires_in":3600,"token_type":"Bearer","scope":"all"}
```

#### Authentication Failure

```
Odoo API Call: POST api/authentication/oauth2/token | Status: 401 | Request Data: {"client_id":"wrong_id","client_secret":"wro***ret"} | Response: {"error_code":"auth_failed","error_message":"Authentication failed with status 401: Invalid credentials"}
```

#### API Request Success

```
Odoo API Call: GET api/countries | Status: 200 | Request Data: {"query_params":{"limit":"5"}} | Response: {"code":200,"data":[{"uuid":"country-1","name":"United States"}]}
```

#### API Request Failure

```
Odoo API Call: GET api/nonexistent-endpoint | Status: 404 | Request Data: {} | Response: {"error_code":"api_error_404","error_message":"Odoo API returned error 404: Endpoint not found"}
```

## Performance Considerations

### Debug Logging Impact

- **Enabled**: Logs include full request/response data (larger log files)
- **Disabled**: Logs include only basic information (smaller log files)

### Log Rotation

- WordPress automatically rotates logs when they get too large
- Consider implementing custom log cleanup for high-traffic sites

## Testing

### Manual Testing

1. Enable debug logging in admin settings
2. Perform API operations (sync customers, products, etc.)
3. Check WooCommerce logs for detailed entries

### Test Script

Use the included test script:

```
/wp-content/plugins/woo-odoo-integration/test-enhanced-logging.php?test_logging=1
```

### WP-CLI Testing

```bash
wp eval "include 'wp-content/plugins/woo-odoo-integration/test-enhanced-logging.php'; test_woo_odoo_integration_enhanced_logging();"
```

## Development Guidelines

### Adding Logging to New Functions

When creating new API functions, use the centralized logging:

```php
function your_new_api_function($data) {
    // Your API logic here
    $response = woo_odoo_integration_api_post('api/your-endpoint', $data);

    // Logging is automatically handled by the base API functions
    return $response;
}
```

### Custom Logging

For non-API operations that need logging:

```php
woo_odoo_integration_log_api_interaction(
    'custom/operation',
    $input_data,
    $result,
    'info',
    'CUSTOM'
);
```

## Security Notes

1. **Never log unmasked sensitive data**
2. **Regularly clean up old log files**
3. **Restrict log file access to administrators only**
4. **Consider disabling debug logging in production**
5. **Monitor log file sizes on high-traffic sites**

## Changelog

### Version 1.0.0

- Added comprehensive API logging
- Implemented sensitive data masking
- Added admin configuration toggle
- Enhanced error logging and debugging
- Documented all logging features

---

This enhanced logging system provides complete visibility into API interactions while maintaining security and performance. Use it for debugging integration issues, monitoring API health, and ensuring proper data flow between WooCommerce and Odoo.
