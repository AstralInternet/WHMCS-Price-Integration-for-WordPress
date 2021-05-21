
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
 * Requires at least:	5.0.0
 * Requires PHP:		7.2
 *
 * 
 */

 // If this file is called directly, abort.
 defined('ABSPATH') or die('No script kiddies please!');


/**
 * Class that handles the products and domains from whcms
 */
class Domains extends whmcsAPI{


    /**
     * Constructor
     * @param string $p_apiID Id used to login to the API
     * @param string $p_apiSecret Secret used to login to the API
     * @param string $p_apiUrl Url to connect to the API
     * @param string $p_apiKey Key used to connect to the API
     */
    public function __construct($p_apiID, $p_apiSecret, $p_apiUrl, $p_apiKey) {
        
        // Assign all our properties
        $this->_apiID = $p_apiID;
        $this->_apiSecret = $p_apiSecret;
        $this->_apiUrl = $p_apiUrl;
        $this->_apiKey = $p_apiKey;
    }

    /**
	 * Function that will call the WHMCS API and return the complete list of TLD in the system
	 * It will also update the WordPress Database
	 * @param array whmcs api name, key and url
	 * @return array Array containing all the information about all the tlds
	 */
	public static function Update_Whmcs_TLD_List($p_whmcsApiConfig)
	{
		// Fetch WHMCS domain list and prices
		$apiValues['action'] = 'GetTLDPricing';
		$whmcsApiTld = Whmcs_API_Call($p_whmcsApiConfig, $apiValues);

		// Build a usable array for the display
		foreach ($whmcsApiTld->pricing as $tld => $details) {

			// Proceed only if we got a TLD with a price
			if ($details->register->{'1'} > 0) {

				// Get the detail per TLD
				$whmcsTLD['tlddetail'][$tld]['reg_price'] = $details->register->{'1'};
				$whmcsTLD['tlddetail'][$tld]['categories'] = $details->categories;
				$whmcsTLD['tlddetail'][$tld]['flag'] = $details->group;
				$whmcsTLD['tlddetail'][$tld]['renew'] = $details->renew->{'1'};

				// Set promotion variable in the array
				if ($whmcsTLD['tlddetail'][$tld]['reg_price'] < $whmcsTLD['tlddetail'][$tld]['renew'] ) {

					// Get discount amount 
					$whmcsTLD['tlddetail'][$tld]['discount_amount'] = $whmcsTLD['tlddetail'][$tld]['renew']  - $whmcsTLD['tlddetail'][$tld]['reg_price'];

					// div / 0 protection
					if ($whmcsTLD['tlddetail'][$tld]['renew'] > 0 ) {

						// Get discount pourcentage
						$whmcsTLD['tlddetail'][$tld]['discount_pourc'] =  round((1 - ($whmcsTLD['tlddetail'][$tld]['reg_price']/$whmcsTLD['tlddetail'][$tld]['renew'])) * 100, 0);
					}

					// set promo trigger
					$whmcsTLD['tlddetail'][$tld]['promo'] = 1;
				} else {
					// set promo trigger
					$whmcsTLD['tlddetail'][$tld]['promo'] = 0;
				}

				// Add all tld in the "All" category
				$whmcsTLD['categories']['all'][] = $tld;
				// Create categories for each TLD
				foreach ($details->categories as $category) {
					$whmcsTLD['categories'][$category][] = $tld;
				}
				// Create categorie by flag if available
				if ($details->group != '') {
					$whmcsTLD['categories'][$details->group][] = $tld;
				}
			}
		}

		// Save the output into a file
		file_put_contents(DOMAINS_DATAFILES, serialize($whmcsTLD));

		// Return the list of domain in a usable format
		return $whmcsTLD;
	}

}