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
 * Requires PHP:		7.2
 *
 * 
 */

 // If this file is called directly, abort.
defined('ABSPATH') or die('No script kiddies please!');



class WHMCS_PI_Main
{
 
	/**
	 * Upon plugin activation, will create a new entry in the option table for
	 * the automatic purge trigger.
	 * 
	 * @since    1.0.0
	 * @return void
	 */
	public static function activate()
	{
		// Check and add if needed the WHMCS API Url option
		if (!get_option('whmcs-pi_api_url')) {

            // Add the options with the default value
			update_option('whmcs-pi_api_url', "");
		}

		// Check and add if needed the WHMCS API ID option
		if (!get_option('whmcs-pi_api_id')) {

            // Add the options with the default value
			update_option('whmcs-pi_api_id', "");
		}

		// Check and add if needed the WHMCS API Secret option
		if (!get_option('whmcs-pi_api_secret')) {

            // Add the options with the default value
			update_option('whmcs-pi_api_secret', "");
		}

		// Check and add if needed the WHMCS API access key option
		if (!get_option('whmcs-pi_api_accesskey')) {

            // Add the options with the default value
			update_option('whmcs-pi_api_accesskey', "");
		}

		// Update/create the temporary product cache array
		update_option('whmcs-pi_productcache', "");
		
		// Update/create the temporary domain cache array
		update_option('whmcs-pi_domaincache', "");
	}

	/**
	 * Function to register the an the plugin page in the tools menu of 
	 * wordpress.
	 * 
	 * @since    1.0.0
	 * @return void
	 */
	public static function add_tools_menu()
	{
		add_management_page(
			__('WHMCS Price Intergration', 'whmcs-pi'),
			WHMCS_PI_NAME,
			'manage_options',
			'whmcs-price-integration/admin/whmcs-pi_admin-display.php',
			''
		);
	}

	/**
	 * Encrypt a field before placing it in the database
	 * 
	 * @since 1.0.0
	 * 
	 * @param string String to encrypt
	 * @return string encrypted string
	 */
	public static function field_encrypt($p_inString) {

		// Use Wordpress "Secure Auth Salt" for our openSSL Encryption method
		$sSalt = SECURE_AUTH_SALT;
		$sSalt = substr(hash('sha256', $sSalt, true), 0, 32);

		// Define the encryption method to be used
		$method = 'aes-256-cbc';
	
		// Create the Initialization Vector
		$iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));

		// Encrypt the field with the Initialization Vector at the end. Place on base64 for easy storage.
		$encrypted = base64_encode(openssl_encrypt($p_inString, $method, $sSalt, OPENSSL_RAW_DATA, $iv) . '|' . $iv);

		// Return the encrypted value
		return $encrypted;
	}

	/**
	 * Decrypt a field that was encrypted before being saved
	 * 
	 * @since 1.0.0
	 * 
	 * @param string String to decrypt
	 * @return string encrypted string
	 */
	public static function field_decrypt($p_inEncryptedString) {

		// Use Wordpress "Secure Auth Salt" for our openSSL Encryption method
		$sSalt = SECURE_AUTH_SALT;
		$sSalt = substr(hash('sha256', $sSalt, true), 0, 32);

		// Define the encryption method to be used
		$method = 'aes-256-cbc';
	
		// Decode the base64 string
		$rawValue = base64_decode($p_inEncryptedString);

		// Seperate the Initialization Vector from the encrypted string
		$explodedValue = explode('|', $rawValue);
		$iv = $explodedValue[1];
		$EncryptedString = $explodedValue[0];

		// Decrypt the string
		$decrypted = openssl_decrypt($EncryptedString, $method, $sSalt, OPENSSL_RAW_DATA, $iv);

		// Return the decrypted value
		return $decrypted;
	}

	/**
	 * Return a domain class object
	 * 
	 * @since 1.0.0
	 * 
	 * @return object encrypted string
	 */
	public static function load_domain_class() {

		// Pull the credential from the database
		$whmcsApiId = self::field_decrypt(get_option('whmcs-pi_api_id'));
		$whmcsApiSecret = self::field_decrypt(get_option('whmcs-pi_api_secret'));
		$whmcsAccessKey = self::field_decrypt(get_option('whmcs-pi_api_accesskey'));
		$WhmcsApiUrl = get_option('whmcs-pi_api_url');

		// Initiate the product class
		$domainObj = new Domains($whmcsApiId , $whmcsApiSecret, $WhmcsApiUrl, $whmcsAccessKey);

		// Return the decrypted value
		return $domainObj;
	}


	/**
	 * Return a product class object
	 * 
	 * @since 1.0.0
	 * 
	 * @return object encrypted string
	 */
	public static function load_product_class() {

		// Pull the credential from the database
		$whmcsApiId = self::field_decrypt(get_option('whmcs-pi_api_id'));
		$whmcsApiSecret = self::field_decrypt(get_option('whmcs-pi_api_secret'));
		$whmcsAccessKey = self::field_decrypt(get_option('whmcs-pi_api_accesskey'));
		$WhmcsApiUrl = get_option('whmcs-pi_api_url');

		// Initiate the product class
		$productObj = new Products($whmcsApiId , $whmcsApiSecret, $WhmcsApiUrl, $whmcsAccessKey);

		// Return the decrypted value
		return $productObj;
	}

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the WHMCS_PI_i18n class in order to set the domain and to register the
     * hook with WordPress.
     *
     * @since    1.0.0
     * @return void
     */
    public static function set_locale()
    {

        /**
         * sub-function that will load the language (i18n) file into the 
         * wordpress admin area
         * 
         * @since    1.0.0
         * @return void
         */
        function WHMCS_PI_load_plugin_textdomain()
        {
            // Define the plugin path
            $plugin_rel_path = dirname(dirname(plugin_basename(__FILE__))) .
                '/i18n';

            // Set the language path for wordPress to find it.
            load_plugin_textdomain('whmcs-pi', false, $plugin_rel_path);
        }

        // Add load the language files upon loading the module
        add_action('plugins_loaded', 'WHMCS_PI_load_plugin_textdomain');
    }  

	/**
	 * Remove the options added by the plugin from the option table in the 
	 * database.
	 * 
	 * @since    1.0.0
	 * @return void
	 */
	public static function uninstall()
	{
		// Remove the WHMCS API Url option
		if (get_option('whmcs-pi_api_url')) {

			delete_option('whmcs-pi_api_url', "");
		}

		// Remove WHMCS API ID option
		if (get_option('whmcs-pi_api_id')) {

			delete_option('whmcs-pi_api_id', "");
		}

		// Remove WHMCS API Secret option
		if (get_option('whmcs-pi_api_secret')) {

			delete_option('whmcs-pi_api_secret', "");
		}

		// Remove the WHMCS API access key option
		if (get_option('whmcs-pi_api_accesskey')) {
            
			delete_option('whmcs-pi_api_accesskey', "");
		}

		// Remove the product cache table
		if (get_option('whmcs-pi_productcache')) {
            
			delete_option('whmcs-pi_productcache', "");
		}

		// Remove the domain cache table
		if (get_option('whmcs-pi_domaincache')) {
            
			delete_option('whmcs-pi_domaincache', "");
		}

	}

}