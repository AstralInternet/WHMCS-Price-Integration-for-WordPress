<?php

/**
 * WHMCS Price Integration
 *
 * @author            Astral Internet inc.
 * @copyright         Copyright (C) 2021-2026, Astral Internet inc. - support@astralinternet.com
 * @license           http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 *
 * @package           WHMCS_PI
 * @since             1.0.0
 */

// If this file is called directly, abort.
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Low level WHMCS API transport.
 *
 * Rewritten in 1.0.0:
 *  - Authenticates with API credentials (identifier/secret). The legacy admin
 *    username/password authentication was removed by WHMCS and no longer works
 *    on 8.x and above.
 *  - Uses wp_remote_post() instead of raw cURL, so timeouts, TLS verification
 *    and proxy settings are handled by WordPress.
 *  - Never throws on failure: returns null and records the reason, so a WHMCS
 *    outage degrades gracefully instead of taking pages down.
 */
class whmcsAPI
{

    protected $_apiID; //       API credential identifier
    protected $_apiSecret; //   API credential secret
    protected $_apiUrl; //      API connection URL
    protected $_apiKey; //      Optional API access key

    /**
     * Reason of the last failed call, for the admin screen.
     *
     * @since 1.0.0
     * @var string
     */
    protected $_lastError = '';

    /**
     * Seconds to wait for WHMCS before giving up.
     *
     * Kept short on purpose: this call can happen while a page is rendering,
     * and a hanging WHMCS must never hang the site.
     *
     * @since 1.0.0
     */
    const REQUEST_TIMEOUT = 10;

    /**
     * Class constructor, place define values in self
     */
    public function __construct($p_apiID, $p_apiSecret, $p_apiUrl, $p_apiKey)
    {
        $this->_apiID = $p_apiID;
        $this->_apiSecret = $p_apiSecret;
        $this->_apiUrl = $p_apiUrl;
        $this->_apiKey = $p_apiKey;
    }

    /**
     * Function to send an API request to WHMCS and return a parsed response
     *
     * @param string p_action action to be executed on the API
     * @param array p_params parameters of the request to be sent to the API
     *              Can be extra info for the action call, ex:
     *              array('search' => 'domain.com', 'sorting' => 'ASC')
     *
     * @return object|null Decoded response, or null when the call could not be
     *                     completed. Callers must test the return value.
     */
    public function Whmcs_API_Call($p_action, $p_params = null)
    {
        $this->_lastError = '';

        $url = $this->_ResolveEndpoint($this->_apiUrl);

        if (empty($url)) {
            $this->_lastError = __('The WHMCS API URL is empty or invalid.', 'whmcs-pi');
            return null;
        }

        if (empty($this->_apiID) || empty($this->_apiSecret)) {
            $this->_lastError = __('API identifier or secret is missing.', 'whmcs-pi');
            return null;
        }

        // Prepare request array. WHMCS 8+ expects identifier/secret; the old
        // username/password pair is no longer accepted.
        $apiRequest = array(
            'identifier'   => $this->_apiID,
            'secret'       => $this->_apiSecret,
            'responsetype' => 'json',
            'action'       => $p_action,
        );

        // The access key is optional and only used when WHMCS is configured
        // with one. Sending an empty value makes WHMCS reject the request.
        if (!empty($this->_apiKey)) {
            $apiRequest['accesskey'] = $this->_apiKey;
        }

        if (is_array($p_params)) {
            $apiRequest = array_merge($apiRequest, $p_params);
        }

        return $this->_SendRequest($url, $apiRequest);
    }

    /**
     * Reason why the last call failed, ready to be displayed to an admin.
     *
     * @since 1.0.0
     * @return string Empty string when the last call succeeded.
     */
    public function Get_Last_Error()
    {
        return $this->_lastError;
    }

    /**
     * Tell whether an API response is usable.
     *
     * Every caller should run its response through this before touching the
     * payload, so a WHMCS error never overwrites a valid cache.
     *
     * @since 1.0.0
     * @param object|null $p_response Response returned by Whmcs_API_Call()
     * @return bool
     */
    public static function Is_Successful($p_response)
    {
        return is_object($p_response)
            && isset($p_response->result)
            && $p_response->result === 'success';
    }

    /**
     * Build the full API endpoint from whatever the admin typed in.
     *
     * Accepts the client area root, the includes folder, or the full path to
     * api.php, so a trailing slash or a missing suffix is not a support call.
     * Anything that is not https:// is refused: the credentials travel in the
     * request body.
     *
     * @since 1.0.0
     * @param string $p_url Configured URL
     * @return string Full endpoint URL, or an empty string when unusable
     */
    private function _ResolveEndpoint($p_url)
    {
        $url = trim((string) $p_url);

        if ($url === '') {
            return '';
        }

        /**
         * TLS is not optional: the request body carries the API identifier and
         * secret in clear text. A missing scheme is promoted to https, an
         * explicit http:// is refused outright rather than silently downgraded.
         */
        if (!preg_match('#^https://#i', $url)) {

            if (preg_match('#^http://#i', $url)) {
                $this->_lastError = __('The WHMCS API URL must use https://.', 'whmcs-pi');
                return '';
            }

            $url = 'https://' . $url;
        }

        if (!wp_http_validate_url($url)) {
            return '';
        }

        // Already pointing at the endpoint.
        if (preg_match('#/api\.php$#i', $url)) {
            return $url;
        }

        return rtrim($url, '/') . '/includes/api.php';
    }

    /**
     * Method that performs the HTTP request through the WordPress HTTP API.
     *
     * @since 1.0.0
     * @param string $p_url Endpoint to post to
     * @param array $p_params Request body
     * @return object|null Decoded response, or null on any failure
     */
    private function _SendRequest($p_url, $p_params)
    {
        $response = wp_remote_post($p_url, array(
            'timeout'     => self::REQUEST_TIMEOUT,
            'sslverify'   => true,
            'redirection' => 0,
            'body'        => $p_params,
            'headers'     => array('Accept' => 'application/json'),
            'user-agent'  => 'WHMCS-Price-Integration/1.0 (+' . home_url() . ')',
        ));

        if (is_wp_error($response)) {
            $this->_lastError = sprintf(
                /* translators: %s: transport error message */
                __('Could not reach WHMCS: %s', 'whmcs-pi'),
                $response->get_error_message()
            );
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            $this->_lastError = sprintf(
                /* translators: %d: HTTP status code */
                __('WHMCS answered with HTTP %d.', 'whmcs-pi'),
                $code
            );
            return null;
        }

        $decoded = json_decode(wp_remote_retrieve_body($response));

        if (!is_object($decoded)) {
            $this->_lastError = __('WHMCS did not return valid JSON. Check the API URL.', 'whmcs-pi');
            return null;
        }

        // A well formed error is still a failure: record it so the admin screen
        // can show WHMCS' own wording rather than a generic message.
        if (isset($decoded->result) && $decoded->result !== 'success') {
            $this->_lastError = isset($decoded->message)
                ? (string) $decoded->message
                : __('WHMCS refused the request.', 'whmcs-pi');
        }

        return $decoded;
    }
}
