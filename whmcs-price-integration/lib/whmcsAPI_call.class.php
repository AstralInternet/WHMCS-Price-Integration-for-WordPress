<?php

/**
 * WHMCS Price Integration
 * 
 * @author            Astral Internet inc.
 * @copyright         2021 Copyright (C) 2021, Astral Internet inc. - support@astralinternet.com
 * @license           http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 * 
 * @wordpress-plugin
 * Plugin Name: 		WHMCS Price Integration
 * Plugin URI:      	https://github.com/AstralInternet/WHMCS-Price-Integration
 * Description:			Provide the ability to add WHMCS prices directly inside a WordPress page using the WHMCS API and WordPRess Gutenberg block.
 * Version:         	0.1
 * Author:				Astral Internet inc.
 * Author URI:			https://www.astralinternet.com/fr
 * License:				GPL v3
 * License URI:			http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: 		whmcs-pi
 * Domain Path:     	/i18n
 * Requires at least:	3.5.0
 * Requires PHP:		5.3
 *
 * 
 */

 // If this file is called directly, abort.
 defined('ABSPATH') or die('No script kiddies please!');

class whmcsAPI
{

    protected $_apiID; //       API ID
    protected $_apiSecret; //   API secret key
    protected $_apiUrl; //      API connection URL
    protected $_apiKey; //      API password

    /**
     * Class constructot, place define values in self
     */
    public function __construct($p_apiID, $p_apiSecret, $p_apiUrl, $p_apiKey)
    {
        $this->_apiID = $p_apiID;
        $this->_apiSecret = $p_apiSecret;
        $this->_apiUrl = $p_apiUrl;
        $this->_apiKey = $p_apiKey;
    }

    /**
     * Function to send an API request to WHMCS an return a parsed response
     * 
     * @param string p_action action to be executed on the API
     * @param array p_params parameters of the request to be sent to the API 
     *              Can be extra info for the action call, ex:
     *              array('search' => 'domain.com', 'sorting' => 'ASC')
     * 
     * @return array Array created from the decoded value of the curl request
     */
    public function Whmcs_API_Call($p_action, $p_params = null)
    {
        // Prepare request array
        $apiRequest['username'] = $this->_apiID;
        $apiRequest['password'] = $this->_apiSecret;
        $apiRequest['accesskey'] = $this->_apiKey;
        $apiRequest['responsetype'] = 'json';
        $apiRequest['action'] = $p_action;

        if (is_array($p_params)) {
            $apiRequest = array_merge($apiRequest, $p_params);
        }

        // Make the request and return info into an array
        return json_decode($this->_SendCurlRequest($apiRequest));
    }

    /**
     * Method that makes a request to the API by curl
     * @param string request Request information to be sent
     * @return array Array that is sent back as an answer from the feed
     */
    private function _SendCurlRequest($p_params)
    {

        //Create the channel
        $ch = curl_init();

        // Set the options of the request
        curl_setopt($ch, CURLOPT_URL, $this->_apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        //curl_setopt($ch, SOAP_SINGLE_ELEMENT_ARRAYS, 1);

        // Add the cURL field has a POST request
        curl_setopt($ch, CURLOPT_POSTFIELDS,
            http_build_query($p_params)
        );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // Execute the request
        $response = curl_exec($ch);

        // Close the channel
        curl_close($ch);
        
        // Return the value
        return $response;
    }
}
