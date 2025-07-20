<?php
/**
 * API Helper Functions for WooCommerce Odoo Integration
 *
 * This file contains all API-related functions for communicating with Odoo ERP system.
 * All API functions use WordPress HTTP API and implement token-based authentication
 * with automatic token refresh and error handling.
 *
 * @link       https://ridwan-arifandi.com
 * @since      1.0.0
 *
 * @package    Woo_Odoo_Integration
 * @subpackage Woo_Odoo_Integration/helper
 * @author     Ridwan Arifandi <orangerdigiart@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get access token for Odoo API authentication
 *
 * Retrieves access token from WordPress transient storage. If token doesn't exist
 * or has expired, initiates authentication flow to get new token from Odoo.
 *
 * @since    1.0.0
 * @access   public
 *
 * @hooks    Fires the following hooks:
 *           - do_action('woo_odoo_integration_before_auth') - Before authentication
 *           - do_action('woo_odoo_integration_auth_success', $token) - On successful auth
 *           - do_action('woo_odoo_integration_auth_failed', $error) - On auth failure
 *
 * @param    bool    $force_refresh    Force token refresh even if valid token exists (default: false)
 *
 * @return   string|WP_Error          Access token on success, WP_Error on failure
 *
 * @throws   Exception                When API credentials are not configured
 */
function woo_odoo_integration_api_get_access_token($force_refresh = false)
{
    $transient_key = 'woo_odoo_integration_access_token';

    // Check if we should force refresh or token doesn't exist
    if (!$force_refresh) {
        $cached_token = get_transient($transient_key);
        if (false !== $cached_token && !empty($cached_token)) {
            return $cached_token;
        }
    }

    // Fire before authentication hook
    do_action('woo_odoo_integration_before_auth');

    // Get authentication credentials
    $api_base_url = defined('WOO_ODOO_INTEGRATION_API_BASE_URL') ? WOO_ODOO_INTEGRATION_API_BASE_URL : carbon_get_theme_option('odoo_url');
    $client_id = defined('WOO_ODOO_INTEGRATION_CLIENT_ID') ? WOO_ODOO_INTEGRATION_CLIENT_ID : carbon_get_theme_option('odoo_client_id');
    $client_secret = defined('WOO_ODOO_INTEGRATION_CLIENT_SECRET') ? WOO_ODOO_INTEGRATION_CLIENT_SECRET : carbon_get_theme_option('odoo_client_secret');
    $grant_type = defined('WOO_ODOO_INTEGRATION_GRANT_TYPE') ? WOO_ODOO_INTEGRATION_GRANT_TYPE : carbon_get_theme_option('odoo_grant_type');
    $scope = defined('WOO_ODOO_INTEGRATION_SCOPE') ? WOO_ODOO_INTEGRATION_SCOPE : carbon_get_theme_option('odoo_scope');

    // Validate required credentials
    if (empty($api_base_url) || empty($client_id) || empty($client_secret)) {
        $error = new WP_Error(
            'missing_credentials',
            __('Odoo API credentials are not properly configured', 'woo-odoo-integration'),
            array('status' => 500)
        );

        do_action('woo_odoo_integration_auth_failed', $error);
        return $error;
    }

    // Perform authentication
    $auth_result = woo_odoo_integration_api_authenticate($api_base_url, $client_id, $client_secret, $grant_type, $scope);
    if (is_wp_error($auth_result)) {
        do_action('woo_odoo_integration_auth_failed', $auth_result);
        return $auth_result;
    }

    // Use expires_in from response, with fallback to default
    $token_expiry = isset($auth_result['expires_in']) ? intval($auth_result['expires_in']) : 3600;

    // Allow override via constant
    if (defined('WOO_ODOO_INTEGRATION_TOKEN_EXPIRY')) {
        $token_expiry = WOO_ODOO_INTEGRATION_TOKEN_EXPIRY;
    }

    // Store the access token (not the full auth result)
    $access_token = $auth_result['access_token'];
    set_transient($transient_key, $access_token, $token_expiry);

    // Store additional token info for reference
    set_transient('woo_odoo_integration_token_info', $auth_result, $token_expiry);

    do_action('woo_odoo_integration_auth_success', $access_token);

    return $access_token;
}

/**
 * Authenticate with Odoo API using OAuth2 flow
 *
 * Performs OAuth2 authentication with Odoo server to obtain access token.
 * This function handles the actual HTTP request to the authentication endpoint.
 * Uses the exact endpoint format: /api/authentication/oauth2/token
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
 * @return   array|WP_Error            Authentication response data on success, WP_Error on failure
 */
function woo_odoo_integration_api_authenticate($api_base_url, $client_id, $client_secret, $grant_type = 'client_credentials', $scope = 'all')
{
    // Build correct authentication endpoint
    $auth_endpoint = trailingslashit($api_base_url) . 'api/authentication/oauth2/token';

    // Prepare authentication data
    $auth_data = array(
        'client_id' => sanitize_text_field($client_id),
        'client_secret' => sanitize_text_field($client_secret),
        'grant_type' => sanitize_text_field($grant_type),
        'scope' => sanitize_text_field($scope),
    );

    $args = array(
        'body' => $auth_data,
        'timeout' => 30,
        'headers' => array(
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json',
        ),
    );

    // Make authentication request
    $response = wp_safe_remote_post($auth_endpoint, $args);

    // Check for HTTP errors
    if (is_wp_error($response)) {
        return new WP_Error(
            'auth_request_failed',
            sprintf(__('Authentication request failed: %s', 'woo-odoo-integration'), $response->get_error_message()),
            array('status' => 500)
        );
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    // Handle non-200 responses
    if (200 !== $status_code) {
        return new WP_Error(
            'auth_failed',
            sprintf(__('Authentication failed with status %d: %s', 'woo-odoo-integration'), $status_code, $body),
            array('status' => $status_code)
        );
    }

    // Parse response
    $auth_response = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error(
            'auth_invalid_response',
            __('Invalid JSON response from authentication server', 'woo-odoo-integration'),
            array('status' => 500)
        );
    }

    // Validate response contains access token
    if (empty($auth_response['access_token'])) {
        return new WP_Error(
            'auth_no_token',
            __('No access token received from authentication server', 'woo-odoo-integration'),
            array('status' => 500)
        );
    }

    // Return full authentication response for better token management
    return array(
        'access_token' => sanitize_text_field($auth_response['access_token']),
        'expires_in' => isset($auth_response['expires_in']) ? intval($auth_response['expires_in']) : 3600,
        'token_type' => isset($auth_response['token_type']) ? sanitize_text_field($auth_response['token_type']) : 'Bearer',
        'scope' => isset($auth_response['scope']) ? sanitize_text_field($auth_response['scope']) : $scope,
    );
}

/**
 * Make authenticated API request to Odoo
 *
 * Generic function for making HTTP requests to Odoo API endpoints with automatic
 * token handling and retry logic for expired tokens.
 *
 * @since    1.0.0
 * @access   public
 *
 * @hooks    Fires the following hooks:
 *           - do_action('woo_odoo_integration_before_api_request', $endpoint, $args)
 *           - do_action('woo_odoo_integration_after_api_request', $endpoint, $response)
 *           - do_action('woo_odoo_integration_api_request_failed', $endpoint, $error)
 *
 * @param    string    $endpoint     API endpoint (relative to base URL)
 * @param    array     $args         Request arguments for wp_remote_request()
 * @param    string    $method       HTTP method (GET, POST, PUT, DELETE)
 * @param    bool      $retry        Internal flag for retry logic (default: true)
 *
 * @return   array|WP_Error         Response data or WP_Error on failure
 */
function woo_odoo_integration_api_request($endpoint, $args = array(), $method = 'GET', $retry = true)
{
    // Get access token
    $access_token = woo_odoo_integration_api_get_access_token();

    if (is_wp_error($access_token)) {
        return $access_token;
    }

    // Build full URL
    $api_base_url = defined('WOO_ODOO_INTEGRATION_API_BASE_URL') ? WOO_ODOO_INTEGRATION_API_BASE_URL : carbon_get_theme_option('odoo_url');
    $url = trailingslashit($api_base_url) . ltrim($endpoint, '/');

    // Prepare request arguments
    $default_args = array(
        'method' => strtoupper($method),
        'timeout' => 30,
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ),
    );

    // Merge with provided arguments
    $request_args = wp_parse_args($args, $default_args);

    // Apply filters to request arguments
    $request_args = apply_filters('woo_odoo_integration_api_request_args', $request_args, $endpoint);

    // Fire before request hook
    do_action('woo_odoo_integration_before_api_request', $endpoint, $request_args);

    // Make the request
    $response = wp_remote_request($url, $request_args);

    // Check for HTTP errors
    if (is_wp_error($response)) {
        $error = new WP_Error(
            'api_request_failed',
            sprintf(__('API request failed: %s', 'woo-odoo-integration'), $response->get_error_message()),
            array('status' => 500, 'endpoint' => $endpoint)
        );

        do_action('woo_odoo_integration_api_request_failed', $endpoint, $error);
        return $error;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    // Handle token expiration (401 Unauthorized) with retry
    if (401 === $status_code && $retry) {
        // Force refresh token and retry once
        $new_token = woo_odoo_integration_api_get_access_token(true);

        if (!is_wp_error($new_token)) {
            return woo_odoo_integration_api_request($endpoint, $args, $method, false);
        }
    }

    // Handle other error status codes
    if ($status_code < 200 || $status_code >= 300) {
        $error = new WP_Error(
            'api_error_' . $status_code,
            sprintf(__('Odoo API returned error %d: %s', 'woo-odoo-integration'), $status_code, $body),
            array('status' => $status_code, 'endpoint' => $endpoint)
        );

        do_action('woo_odoo_integration_api_request_failed', $endpoint, $error);
        return $error;
    }

    // Parse JSON response
    $response_data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $error = new WP_Error(
            'api_invalid_json',
            __('Invalid JSON response from Odoo API', 'woo-odoo-integration'),
            array('status' => 500, 'endpoint' => $endpoint)
        );

        do_action('woo_odoo_integration_api_request_failed', $endpoint, $error);
        return $error;
    }

    // Apply filters to response
    $response_data = apply_filters('woo_odoo_integration_api_response', $response_data, $endpoint);

    // Fire after request hook
    do_action('woo_odoo_integration_after_api_request', $endpoint, $response_data);

    return $response_data;
}

/**
 * Make GET request to Odoo API
 *
 * Wrapper function for GET requests with automatic token handling.
 * Converts query parameters to URL query string.
 *
 * @since    1.0.0
 * @access   public
 *
 * @param    string    $endpoint      API endpoint (relative to base URL)
 * @param    array     $query_args    Query parameters to append to URL
 *
 * @return   array|WP_Error          Response data or WP_Error on failure
 */
function woo_odoo_integration_api_get($endpoint, $query_args = array())
{
    // Add query parameters to endpoint if provided
    if (!empty($query_args)) {
        $endpoint = add_query_arg($query_args, $endpoint);
    }

    return woo_odoo_integration_api_request($endpoint, array(), 'GET');
}

/**
 * Make POST request to Odoo API
 *
 * Wrapper function for POST requests with automatic token handling
 * and JSON encoding of request body.
 *
 * @since    1.0.0
 * @access   public
 *
 * @param    string    $endpoint    API endpoint (relative to base URL)
 * @param    array     $data        POST data to send (will be JSON encoded)
 *
 * @return   array|WP_Error        Response data or WP_Error on failure
 */
function woo_odoo_integration_api_post($endpoint, $data = array())
{
    $args = array(
        'body' => wp_json_encode($data),
    );

    return woo_odoo_integration_api_request($endpoint, $args, 'POST');
}

/**
 * Make PUT request to Odoo API
 *
 * Wrapper function for PUT requests with automatic token handling
 * and JSON encoding of request body.
 *
 * @since    1.0.0
 * @access   public
 *
 * @param    string    $endpoint    API endpoint (relative to base URL)
 * @param    array     $data        PUT data to send (will be JSON encoded)
 *
 * @return   array|WP_Error        Response data or WP_Error on failure
 */
function woo_odoo_integration_api_put($endpoint, $data = array())
{
    $args = array(
        'body' => wp_json_encode($data),
    );

    return woo_odoo_integration_api_request($endpoint, $args, 'PUT');
}

/**
 * Make DELETE request to Odoo API
 *
 * Wrapper function for DELETE requests with automatic token handling.
 *
 * @since    1.0.0
 * @access   public
 *
 * @param    string    $endpoint    API endpoint (relative to base URL)
 *
 * @return   array|WP_Error        Response data or WP_Error on failure
 */
function woo_odoo_integration_api_delete($endpoint)
{
    return woo_odoo_integration_api_request($endpoint, array(), 'DELETE');
}

/**
 * Clear cached access token
 *
 * Removes the access token from WordPress transient storage.
 * Useful for forcing re-authentication or troubleshooting.
 *
 * @since    1.0.0
 * @access   public
 *
 * @return   bool    True if transient was deleted, false otherwise
 */
function woo_odoo_integration_api_clear_token_cache()
{
    return delete_transient('woo_odoo_integration_access_token');
}

/**
 * Test API connection to Odoo
 *
 * Performs a simple API call to test connectivity and authentication.
 * Useful for validating configuration and troubleshooting connection issues.
 *
 * @since    1.0.0
 * @access   public
 *
 * @return   array|WP_Error        Connection status data or WP_Error on failure
 */
function woo_odoo_integration_api_test_connection()
{
    // Try to get basic system info or user profile
    $response = woo_odoo_integration_api_get('system/info');

    if (is_wp_error($response)) {
        return new WP_Error(
            'connection_test_failed',
            sprintf(__('Connection test failed: %s', 'woo-odoo-integration'), $response->get_error_message()),
            array('details' => $response->get_error_data())
        );
    }

    return array(
        'status' => 'success',
        'message' => __('Successfully connected to Odoo API', 'woo-odoo-integration'),
        'timestamp' => current_time('timestamp'),
        'data' => $response,
    );
}

/**
 * Get token information from cache
 *
 * Retrieves full token information including expires_in, token_type, and scope
 * from the cached authentication response.
 *
 * @since    1.0.0
 * @access   public
 *
 * @return   array|false    Token information array or false if not cached
 */
function woo_odoo_integration_api_get_token_info()
{
    return get_transient('woo_odoo_integration_token_info');
}

/**
 * Check if current token is about to expire
 *
 * Determines if the current access token will expire within a specified time frame.
 * Useful for proactive token refresh.
 *
 * @since    1.0.0
 * @access   public
 *
 * @param    int    $threshold_seconds    Seconds before expiry to consider "about to expire" (default: 300 = 5 minutes)
 *
 * @return   bool   True if token expires within threshold, false otherwise
 */
function woo_odoo_integration_api_token_expires_soon($threshold_seconds = 300)
{
    $token_info = woo_odoo_integration_api_get_token_info();

    if (false === $token_info || !isset($token_info['expires_in'])) {
        return true; // Assume expiring if no info available
    }

    // Get remaining time on the transient
    $transient_timeout = get_option('_transient_timeout_woo_odoo_integration_access_token');

    if (false === $transient_timeout) {
        return true; // No timeout set, assume expiring
    }

    $current_time = time();
    $time_until_expiry = $transient_timeout - $current_time;

    return $time_until_expiry <= $threshold_seconds;
}

/**
 * Create customer in Odoo ERP system
 *
 * Creates a new customer record in Odoo using the /api/customers endpoint.
 * Maps WooCommerce customer data to Odoo customer fields.
 *
 * @since    1.0.0
 * @access   public
 *
 * @hooks    Fires the following hooks:
 *           - do_action('woo_odoo_integration_before_create_customer', $customer_data)
 *           - do_action('woo_odoo_integration_after_create_customer', $odoo_customer_data, $wc_customer_id)
 *           - do_action('woo_odoo_integration_create_customer_failed', $error, $customer_data)
 *
 * @param    array    $customer_data    Customer data array with required fields
 * @param    int      $wc_customer_id   WooCommerce customer ID (optional, for meta storage)
 *
 * @return   array|WP_Error           Odoo customer data on success, WP_Error on failure
 *
 * @throws   InvalidArgumentException When required customer fields are missing
 */
function woo_odoo_integration_api_create_customer($customer_data, $wc_customer_id = null)
{
    // Fire before create customer hook
    do_action('woo_odoo_integration_before_create_customer', $customer_data);

    // Validate required fields
    $required_fields = array('name', 'email');
    foreach ($required_fields as $field) {
        if (empty($customer_data[$field])) {
            $error = new WP_Error(
                'missing_required_field',
                sprintf(__('Required field "%s" is missing for customer creation', 'woo-odoo-integration'), $field),
                array('status' => 400, 'field' => $field)
            );

            do_action('woo_odoo_integration_create_customer_failed', $error, $customer_data);
            return $error;
        }
    }

    // Prepare customer data for Odoo API
    $odoo_customer_data = array(
        'name' => sanitize_text_field($customer_data['name']),
        'email' => sanitize_email($customer_data['email']),
        'street' => isset($customer_data['street']) ? sanitize_text_field($customer_data['street']) : '',
        'street2' => isset($customer_data['street2']) ? sanitize_text_field($customer_data['street2']) : '',
        'city' => isset($customer_data['city']) ? sanitize_text_field($customer_data['city']) : '',
        'zip' => isset($customer_data['zip']) ? sanitize_text_field($customer_data['zip']) : '',
        'vat' => isset($customer_data['vat']) ? sanitize_text_field($customer_data['vat']) : '',
        'phone' => isset($customer_data['phone']) ? sanitize_text_field($customer_data['phone']) : '',
        'mobile' => isset($customer_data['mobile']) ? sanitize_text_field($customer_data['mobile']) : '',
        'country_id' => isset($customer_data['country_id']) ? sanitize_text_field($customer_data['country_id']) : '',
    );

    // Remove empty fields to avoid API issues
    $odoo_customer_data = array_filter($odoo_customer_data, function ($value) {
        return !empty($value);
    });

    // Apply filters to customer data before sending
    $odoo_customer_data = apply_filters('woo_odoo_integration_customer_data', $odoo_customer_data, $customer_data);

    // Make API request to create customer
    $response = woo_odoo_integration_api_post('api/customers', $odoo_customer_data);

    if (is_wp_error($response)) {
        do_action('woo_odoo_integration_create_customer_failed', $response, $customer_data);
        return $response;
    }

    // Parse Odoo response
    if (!isset($response['code']) || 200 !== $response['code'] || empty($response['data'])) {
        $error = new WP_Error(
            'odoo_create_customer_failed',
            __('Odoo API returned unexpected response format', 'woo-odoo-integration'),
            array('status' => 500, 'response' => $response)
        );

        do_action('woo_odoo_integration_create_customer_failed', $error, $customer_data);
        return $error;
    }

    // Get created customer data
    $created_customer = $response['data'][0];

    // Store Odoo customer UUID in WooCommerce if customer ID provided
    if ($wc_customer_id && isset($created_customer['uuid'])) {
        update_user_meta($wc_customer_id, '_odoo_customer_uuid', sanitize_text_field($created_customer['uuid']));
        update_user_meta($wc_customer_id, '_odoo_last_sync', current_time('timestamp'));
    }

    // Fire after create customer hook
    do_action('woo_odoo_integration_after_create_customer', $created_customer, $wc_customer_id);

    return $created_customer;
}

/**
 * Get customer from Odoo by UUID
 *
 * Retrieves customer information from Odoo using customer UUID.
 *
 * @since    1.0.0
 * @access   public
 *
 * @param    string    $customer_uuid    Odoo customer UUID
 *
 * @return   array|WP_Error           Customer data on success, WP_Error on failure
 */
function woo_odoo_integration_api_get_customer($customer_uuid)
{
    if (empty($customer_uuid)) {
        return new WP_Error(
            'missing_customer_uuid',
            __('Customer UUID is required', 'woo-odoo-integration'),
            array('status' => 400)
        );
    }

    $endpoint = 'api/customers/' . sanitize_text_field($customer_uuid);
    return woo_odoo_integration_api_get($endpoint);
}

/**
 * Get countries from Odoo API with caching
 *
 * Retrieves all countries from Odoo API and caches them in WordPress options
 * to avoid repeated API calls. Countries data is rarely changed.
 *
 * @since    1.0.0
 * @access   public
 *
 * @param    bool    $force_refresh    Force refresh from API (default: false)
 *
 * @return   array|WP_Error          Countries data on success, WP_Error on failure
 */
function woo_odoo_integration_api_get_countries($force_refresh = false)
{
    $cache_key = 'woo_odoo_integration_countries';


    // Check if we should use cached data
    if (!$force_refresh) {
        $cached_countries = get_transient($cache_key);

        // Use cached data if available and not expired (cache for 24 hours)
        if ($cached_countries !== false) {
            return $cached_countries;
        }
    }

    // Fetch countries from API
    $response = woo_odoo_integration_api_get('api/countries', array('limit' => 250));

    if (is_wp_error($response)) {
        // Return cached data if API fails and we have it
        $cached_countries = get_option($cache_key, false);
        if ($cached_countries !== false) {
            error_log('WooOdoo Integration: API failed, using cached countries data');
            return $cached_countries;
        }

        return $response;
    }

    // Parse response
    if (!isset($response['code']) || 200 !== $response['code'] || empty($response['data'])) {
        return new WP_Error(
            'odoo_countries_api_error',
            __('Odoo countries API returned unexpected response format', 'woo-odoo-integration'),
            array('status' => 500, 'response' => $response)
        );
    }

    $countries = $response['data'];

    // Cache the results for 24 hours
    set_transient($cache_key, $countries, 24 * HOUR_IN_SECONDS);

    return $countries;
}

/**
 * Get Odoo country UUID by country name or code
 *
 * Maps WooCommerce country codes to Odoo country UUIDs using the countries API.
 * Supports both country names and ISO codes for mapping.
 *
 * @since    1.0.0
 * @access   public
 *
 * @param    string    $country_identifier    Country name or ISO code from WooCommerce
 *
 * @return   string|false                   Odoo country UUID on success, false if not found
 */
function woo_odoo_integration_get_country_uuid($country_identifier)
{
    if (empty($country_identifier)) {
        return false;
    }

    // Get countries from API (cached)
    $countries = woo_odoo_integration_api_get_countries();

    if (is_wp_error($countries)) {
        error_log('WooOdoo Integration: Failed to get countries for mapping: ' . $countries->get_error_message());
        return false;
    }

    // First try to get full country name from WooCommerce if it's a country code
    $wc_countries = new \WC_Countries();
    $country_name = '';

    // If it looks like a country code (2-3 characters), get the full name
    if (strlen($country_identifier) <= 3 && ctype_alpha($country_identifier)) {
        $country_name = $wc_countries->get_countries()[$country_identifier] ?? '';
    } else {
        $country_name = $country_identifier;
    }

    // Search for country in Odoo data
    foreach ($countries as $country) {
        // Direct name match (case insensitive)
        if (strcasecmp($country['name'], $country_name) === 0) {
            return $country['uuid'];
        }

        // Also try direct match with the original identifier
        if (strcasecmp($country['name'], $country_identifier) === 0) {
            return $country['uuid'];
        }
    }

    // If exact match not found, try partial matching for common variations
    foreach ($countries as $country) {
        $odoo_name = strtolower($country['name']);
        $search_name = strtolower($country_name);

        // Check if names are similar (contains each other)
        if (strpos($odoo_name, $search_name) !== false || strpos($search_name, $odoo_name) !== false) {
            return $country['uuid'];
        }
    }

    // Log if country not found for debugging
    error_log(sprintf(
        'WooOdoo Integration: Country not found in Odoo: "%s" (WC name: "%s")',
        $country_identifier,
        $country_name
    ));

    return false;
}

/**
 * Clear countries cache
 *
 * Removes cached countries data from WordPress options.
 * Useful for forcing refresh or troubleshooting.
 *
 * @since    1.0.0
 * @access   public
 *
 * @return   bool    True if cache was cleared successfully
 */
function woo_odoo_integration_clear_countries_cache()
{
    $result = delete_transient('woo_odoo_integration_countries');

    return $result;
}

/**
 * Update customer in Odoo
 *
 * Updates existing customer information in Odoo ERP system using PUT request.
 * Follows the Odoo API format: PUT /api/customers/{customer_uuid}
 *
 * @since    1.0.0
 * @access   public
 *
 * @param    string    $customer_uuid    Odoo customer UUID
 * @param    array     $customer_data    Updated customer data
 *
 * @return   array|WP_Error           Updated customer data on success, WP_Error on failure
 */
function woo_odoo_integration_api_update_customer($customer_uuid, $customer_data)
{
    if (empty($customer_uuid)) {
        return new WP_Error(
            'missing_customer_uuid',
            __('Customer UUID is required for update', 'woo-odoo-integration'),
            array('status' => 400)
        );
    }

    // Sanitize and validate customer data
    $sanitized_data = array();
    $allowed_fields = array(
        'name',
        'email',
        'street',
        'street2',
        'city',
        'zip',
        'vat',
        'phone',
        'mobile',
        'country_id'
    );

    foreach ($allowed_fields as $field) {
        if (isset($customer_data[$field])) {
            switch ($field) {
                case 'email':
                    if (!empty($customer_data[$field])) {
                        $sanitized_data[$field] = sanitize_email($customer_data[$field]);
                    }
                    break;
                case 'zip':
                case 'vat':
                    // Handle numeric fields
                    if (!empty($customer_data[$field])) {
                        $sanitized_data[$field] = is_numeric($customer_data[$field])
                            ? intval($customer_data[$field])
                            : sanitize_text_field($customer_data[$field]);
                    }
                    break;
                case 'country_id':
                    // Handle country UUID field - should remain as string
                    if (!empty($customer_data[$field])) {
                        $sanitized_data[$field] = sanitize_text_field($customer_data[$field]);
                    }
                    break;
                default:
                    if (!empty($customer_data[$field])) {
                        $sanitized_data[$field] = sanitize_text_field($customer_data[$field]);
                    }
                    break;
            }
        }
    }

    // Ensure we have some data to update
    if (empty($sanitized_data)) {
        return new WP_Error(
            'no_update_data',
            __('No valid data provided for customer update', 'woo-odoo-integration'),
            array('status' => 400)
        );
    }

    // Make PUT request to update customer
    $endpoint = 'api/customers/' . sanitize_text_field($customer_uuid);
    $response = woo_odoo_integration_api_put($endpoint, $sanitized_data);

    if (is_wp_error($response)) {
        return $response;
    }

    // Handle the response format: { "code": 200, "data": [...] }
    if (isset($response['code']) && 200 === $response['code'] && isset($response['data'])) {
        // Return the first customer data from the response
        if (is_array($response['data']) && !empty($response['data'])) {
            return $response['data'][0];
        }
    }

    // Fallback: return the whole response if format is different
    return $response;
}

/**
 * Sync WooCommerce customer to Odoo
 *
 * Creates or updates customer in Odoo based on WooCommerce customer data.
 * Checks if customer already exists in Odoo before creating new one.
 *
 * @since    1.0.0
 * @access   public
 *
 * @param    int      $wc_customer_id    WooCommerce customer ID
 * @param    bool     $force_update      Force update even if already synced (default: false)
 *
 * @return   array|WP_Error           Odoo customer data on success, WP_Error on failure
 */
function woo_odoo_integration_api_sync_customer($wc_customer_id, $force_update = false)
{
    // Validate customer ID
    if (!is_numeric($wc_customer_id) || $wc_customer_id <= 0) {
        return new WP_Error(
            'invalid_customer_id',
            __('Invalid WooCommerce customer ID provided', 'woo-odoo-integration'),
            array('status' => 400)
        );
    }

    // Get WooCommerce customer
    $wc_customer = new WC_Customer($wc_customer_id);
    if (!$wc_customer->get_id()) {
        return new WP_Error(
            'customer_not_found',
            __('WooCommerce customer not found', 'woo-odoo-integration'),
            array('status' => 404)
        );
    }

    // Check if customer is already synced (unless forcing update)
    $odoo_customer_uuid = get_user_meta($wc_customer_id, '_odoo_customer_uuid', true);
    if (!empty($odoo_customer_uuid) && !$force_update) {
        // Customer already exists in Odoo, return existing data
        return woo_odoo_integration_api_get_customer($odoo_customer_uuid);
    }

    // Prepare customer data for Odoo
    $billing_address = array(
        'street' => $wc_customer->get_billing_address_1(),
        'street2' => $wc_customer->get_billing_address_2(),
        'city' => $wc_customer->get_billing_city(),
        'zip' => $wc_customer->get_billing_postcode(),
    );

    // Map WooCommerce country to Odoo country UUID
    $wc_country = $wc_customer->get_billing_country();
    $odoo_country_uuid = woo_odoo_integration_get_country_uuid($wc_country);

    if ($odoo_country_uuid) {
        $billing_address['country_id'] = $odoo_country_uuid;
    }

    $customer_data = array(
        'name' => trim($wc_customer->get_first_name() . ' ' . $wc_customer->get_last_name()),
        'email' => $wc_customer->get_email(),
        'phone' => $wc_customer->get_billing_phone(),
        'mobile' => $wc_customer->get_billing_phone(), // Use same phone for mobile
    );

    // Add billing address data
    $customer_data = array_merge($customer_data, $billing_address);

    // Create or update customer in Odoo
    if (!empty($odoo_customer_uuid) && $force_update) {
        // Update existing customer
        $result = woo_odoo_integration_api_update_customer($odoo_customer_uuid, $customer_data);

        if (!is_wp_error($result)) {
            // Update sync timestamp for successful update
            update_user_meta($wc_customer_id, '_odoo_last_sync', current_time('timestamp'));

            // Fire after update customer hook
            do_action('woo_odoo_integration_after_update_customer', $result, $wc_customer_id);
        }

        return $result;
    } else {
        // Create new customer
        return woo_odoo_integration_api_create_customer($customer_data, $wc_customer_id);
    }
}
