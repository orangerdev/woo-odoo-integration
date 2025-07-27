# woo-odoo-integration

WooCommerce–Odoo plugin for syncing stock, orders, users, multi-location carts &amp; shipping—no middleware needed.

## Features

- ✅ **Direct API Integration** - No middleware required
- ✅ **Customer Synchronization** - Automatic customer sync between WooCommerce and Odoo
- ✅ **Product Stock Sync** - Real-time stock synchronization
- ✅ **Order Management** - Order sync and status updates
- ✅ **Enhanced API Logging** - Comprehensive logging with endpoint, request, and response data
- ✅ **Security-First** - Sensitive data masking and secure token management
- ✅ **WordPress Standards** - Built with WordPress and WooCommerce best practices

## Enhanced API Logging

The plugin now includes comprehensive API logging that captures:

1. **Endpoint**: The exact API endpoint being called
2. **Request Data**: Complete request data (body, query parameters)
3. **Response Data**: Full API response data
4. **HTTP Status Codes**: Response codes for debugging
5. **Timestamps**: When each API call was made

### Security Features

- **Automatic Data Masking**: Sensitive information like client secrets and access tokens are automatically masked
- **Configurable Logging**: Enable/disable detailed logging via admin settings
- **Error-First Logging**: Errors are always logged regardless of debug settings

### Configuration

Go to **WordPress Admin > Odoo Settings** and enable **"Enable Debug Logging"** to see full request/response data in logs.

View logs at: **WooCommerce > Status > Logs** (look for 'woo-odoo-api' entries)

For detailed logging documentation, see: `docs/Enhanced-API-Logging-Guide.md`
