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
class Products extends whmcsAPI{

    // Array for the periods
    private $_periods = array('1mois' => 'monthly', '3mois' => 'quarterly', '6mois' => 'semiannually', '1an' => 'annually', '2ans' => 'biennially', '3ans' => 'triennially');
    
    // Array for the number of months per period
    private $_numberMonths = array('monthly' => 1, 'quarterly' => 3, 'semiannually' => 6, 'annually' => 12, 'biennially' => 24, 'triennially' => 36);

    // Properties to search for when parsing the descriptions 
    private $_parseProperties = array(  
                                        'processor' => array('processor', 'processeur', 'cpu'), 
                                        'memory' => array('memory', 'memoire', 'mémoire', 'ram', 'gb ram', 'go ram'), 
                                        'storage' => array('storage', 'stockage', 'gb ssd', 'go ssd'), 
                                        'OS' => array('os', 'operating system', 'systême d\'exploitation', 'systeme d\'exploitation'),
                                        'bandwidth' => array('bandwidth', 'bande passante'),
                                        'email' => array('email accounts', 'comptes courriels', 'email', 'compte courriel'),
                                        'page' => array('page', 'pages'),
                                    );

    
    private $_parseResearchTerms = array('unmetered', 'unlimited', 'illimité', 'illimite', 'sans compteur');

    // Variables used by all our pages
    private $_promotions = null;

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
     * Function that gets a products information
     * 
     * @param int product pid
     * @param string promotion prefix if any (not to use all the promo code)
     * @param boolean Force a refresh even if the page was ran less than one hour agao
     * 
     * @return array Complete product detail
     */
    public function GetProducts($p_pid, $p_promoPrefix = null, $p_forceNew = false) {

        // Define a variable to trigger if can bypass the API CAll
        $passDBCheck = $p_forceNew;

        // Pull out the information from the DB if it is present
        if (!$passDBCheck) {

            $productDBInfo = get_option('whmcs-pi_pid-'.$p_pid);

            // If the db was non existant, we need to proceed to the API call
            if (!$productDBInfo ) {
                $passDBCheck = true;

            } else {

                // Check the timestamp of the product
                $currentTime = microtime(true);

                // If the time diference between the last save is longer then 1 hours, force a refresh
                if (($currentTime - $productDBInfo['timestamp']) > 3600) {
                    $passDBCheck = true;
                }
            }
        }

        /**
         * Make the API call the WHMCS
         */
        if ($passDBCheck) {

            // Prepare the argument for WHMCS
            $apiArg = array('pid' => $p_pid);

            // Make our API call and get the data
            $data = $this->Whmcs_API_Call('GetProducts', $apiArg);
            
            // Check for API Error
            if ($data->result == 'error') {
                
                // Return the data with the error message
                return $data;
            }

            // Get Promotions
            $promotions = $this->_GetProductPromotions($p_pid, $this->GetPromotions($p_forceNew), $p_promoPrefix);

            // clean the product description
            $data = $this->_BuildPageInfoArray($promotions, $data->products->product);

            // Save the content with timestamp to speedup site load time
            $productDBInfo['timestamp'] = microtime(true);
            $productDBInfo['data'] = $data;
            update_option('whmcs-pi_pid-'.$p_pid, $productDBInfo);

        } else {

            // Get the data from the array saved in the DB
            $data = $productDBInfo['data'];
        }

        // Return the raw WHMCS Product Informatin
        return ($data);
    }


    /**
     * function that will return all the current system promotion
     * @return array all WHMCS promotions
     */
    public function GetPromotions($p_forceNew = false) {

        // Return the current promotion in memory if it is set
        if (!is_null($this->_promotions)) {
            return $this->_promotions;
        }

        // Define a variable to trigger if can bypass the API CAll
        $passDBCheck = $p_forceNew;

        // Pull out the information from the DB if it is present
        if (!$passDBCheck) {

            $promoDBInfo = get_option('whmcs-pi_pid-promotion');

            // If the db was non existant, we need to proceed to the API call
            if (!$promoDBInfo ) {
                $passDBCheck = true;

            } else {

                // Check the timestamp of the product
                $currentTime = microtime(true);

                // If the time diference between the last save is longer then 1 hours, force a refresh
                if (($currentTime - $promoDBInfo['timestamp']) > 3600) {
                    $passDBCheck = true;
                }
            }
        }

        // If the file doesn't exist, create it
        if ($passDBCheck) {

            // Make our API call and get the data
            $data = $this->Whmcs_API_Call('GetPromotions');

            // Sort out the promotions
            $data = $this->_OrganizePromotions($data->promotions->promotion);
            
            // Save the content with timestamp to speedup site load time
            $promoDBInfo['timestamp'] = microtime(true);
            $promoDBInfo['data'] = $data;
            update_option('whmcs-pi_pid-promotion', $promoDBInfo);

        } else {

            // The file exist and is valid, load it instead of creating a new one
            $data = $promoDBInfo['data'];
        }

        // Set the promotion variable in case of multiple querys
        $this->_promotions = $data;

        // Return the list of promotions
        return $data;
    }
   
    /**
     * Function that will parse the promotion to return only the one for the current product
     * 
     * @param int pid number of the current product
     * @param array All the system promotions
     * @param string The promotion prefix if any (to prevent using all promo code)
     * 
     * @return array The concerned promotions.
     */
    private function _GetProductPromotions($p_pid, $p_promotions, $p_promoPrefix = null) {

        // Declare our promotions array
        $promotions = array();

        // For each promotion
        foreach($p_promotions as $promoCode => $promotion) {

            // If the promotion applies to the product and the promotino is valid
            if (in_array($p_pid,  explode(',', $promotion['appliesto']))) {
                
                // Check if a prefix was provided
                if (!is_null($p_promoPrefix)) {

                    // Check if code match the prefix
                    if (strrpos($promoCode, $p_promoPrefix) === 0) {

                        // Add the promotion to the array
                        $promotions[$promoCode] = $promotion; 
                    }

                } else {

                   // No prefix, check all promo code : Add the promotion to the array
                    $promotions[$promoCode] = $promotion; 
                }
            }
        }

        // Return the page array
        return $promotions;
    }

    /**
     * Function that build an array usable by the page
     * @param array $p_promotions Promotions that are used to build the array
     * @param array $p_products Products used to build the array
     * @param string $p_defaultOption Default option for the display of the page
     * @return array Complete array with all the informations for the page
     */
    private function _BuildPageInfoArray($p_promotions, $p_products) {
        
        // Declare our storing array
        $pageArray = array();

        // Set our promotion found variable
        $promotionFound = false;

        // Word only with the first product
        $product = $p_products[0];

        // First we add the pid and the default option of one year
        $pageArray['name'] = $product->name;
        $pageArray['pid'] = $product->pid;
        $pageArray['gid'] = $product->gid;
        $pageArray['description'] = $product->description;
        $pageArray['properties'] = $this->_ParseProductDescription($product->description);
        $pageArray['configOptions'] = $product->configoptions;
        $pageArray['price'] = $product->pricing->CAD;
        
        // For each period
        foreach($this->_periods as $periodShort => $periodLong) {

            // Get a string for comparison in the conditions
            $periodLongComparable = strtolower($periodLong);

            // For each promotion
            foreach ($p_promotions as $promotionName => $promotionValue) {
                
                // Set our promotion found variable
                $promotionFound = false;

                // If the promotion is applicable on this product and is applicable for this period of time
                if (in_array($product->pid, explode(',', $promotionValue['appliesto'])) && in_array($periodLongComparable, preg_replace('/-/', '', array_map('strtolower', explode(',', $promotionValue['cycles']))))) {
                    
                    // Set the promotion as found
                    $promotionFound = true;

                    // Set the promotion name
                    $pageArray[$periodShort]['promo'] = $promotionName;
                    
                    // Set the regular price with the value of the pricing / nbMonths
                    $regPrice = strval(number_format($product->pricing->CAD->$periodLongComparable / $this->_numberMonths[$periodLongComparable], 2, '.', ''));
                    $pageArray[$periodShort]['prixreg'] = $regPrice;


                    // If the type of promotion is percentage
                    if (strtolower($promotionValue['type']) == 'percentage') {
                        $pageArray[$periodShort]['sauver'] = strval((int)$promotionValue['value']) . '%';
                        $pageArray[$periodShort]['prix'] = strval(number_format($regPrice * (float)(1 - ($promotionValue['value'] / 100)), 2, ".", ""));
                        
                        // If a value was found, don't check for another
                        break;
                    }
                    // Otherwise if the type is a fixed amount
                    // TODO : Check promotion perdio
                    else if (strtolower($promotionValue['type']) == 'fixed amount') {
                        $promoPrice = strval(floatval($regPrice) - (floatval($promotionValue['value']) / 12)); 
                        $pageArray[$periodShort]['prix'] = $promoPrice;  
                        $pageArray[$periodShort]['sauver'] = strval((int)((1 - floatval($promoPrice) / floatval($regPrice)) * 100)) . '%';
                        
                        // If a value was found, don't check for another
                        break;
                    }
                    // Otherwise if the type is a price override
                    else if (strtolower($promotionValue['type']) == 'price override') {
                        $promoPrice = strval(floatval($promotionValue['value']) / $this->_numberMonths[$periodLongComparable]);
                        $pageArray[$periodShort]['prix'] = $promoPrice;  
                        $pageArray[$periodShort]['sauver'] = strval((int)((1 - floatval($promoPrice) / floatval($regPrice)) * 100)) . '%';

                        // If a value was found, don't check for another
                        break;
                    }

                    // Finally
                    else {
                        $promotionFound = false;
                    }
                }
            }

            // If the promotion was not found
            if (!$promotionFound)  {
                    
                // Set our basic informations
                $pageArray[$periodShort]['promo'] = '';
                $pageArray[$periodShort]['prixreg'] = '';
                $pageArray[$periodShort]['prix'] = strval(number_format($product->pricing->CAD->$periodLongComparable / $this->_numberMonths[$periodLongComparable], 2, '.', ''));
                $pageArray[$periodShort]['sauver'] = '';
            }
        }

        // Return the page array
        return $pageArray;
    }

    /**
     * Function that formats the promotions to only keep the important informations
     * @param string $p_promotions The promotions to be formatted
     * @return array the formatted promotions
     */
    private function _OrganizePromotions($p_promotions) {

        // Instanciate our array
        $formatedPromotions = array();
    
        // For each promotion
        foreach ($p_promotions as  $promotion) {

            // If the promotion is valid in the time period
            if ((strtotime($promotion->startdate) <= strtotime(date('Y-m-d')) || $promotion->startdate == '0000-00-00') && (strtotime($promotion->expirationdate) >= strtotime(date('Y-m-d')) || $promotion->expirationdate == '0000-00-00')) {

                // Add the formatted promotion our array
                $formatedPromotions[$promotion->code] = array("type" => $promotion->type, "value" => $promotion->value, "startdate" => $promotion->startdate, "expirationdate" => $promotion->expirationdate, "cycles" => $promotion->cycles, "appliesto" => $promotion->appliesto);
            } 
        }

        // Return the formatted array 
        return $formatedPromotions;
    }

    /**
     * Function that parses the product description and gets all the properties stored in it
     * @param string $p_description Description of the product to be parsed
     * @return array Array of the properties in the description
     */
    private function _ParseProductDescription($p_description) {

        // Instanciate our storage array
        $propertiesArray = array();

        // Convert our string to an array of words for parsing
        $stringToArray = preg_split('/\r\n|\r|\n/', $p_description);

        // For each line
        for ($lineIndex = 0; $lineIndex < count($stringToArray); $lineIndex++) { 

            // Parse the line to see if it contains a known property
            $parseResult = $this->_ParseLineForProperty($stringToArray[$lineIndex]);

            // If we found something
            if ($parseResult !== null) {

                // Set our property
                $propertiesArray[$parseResult['property']] = $parseResult['value'];
            }
            
        }

        // Return the array of properties
        return $propertiesArray;
    }

    /**
     * Function that parses each line of the description
     * @param string $p_line Line to be parsed
     * @return array|-1|null Array containing the property of the line and its value or -1 if unlimited or null if none
     */
    private function _ParseLineForProperty($p_line) {

        // Get the line for comparison
        $modifiedLine = strtolower($p_line);

        // For each property we could find
        foreach ($this->_parseProperties as $propertyName => $propertyValues) {

            // For each value the property could have
            foreach ($propertyValues as $propertyValue) {

                // If the property is present 
                if (strpos($modifiedLine, $propertyValue) !== false) {
                    
                    // Check for each search term if it is present 
                    foreach ($this->_parseResearchTerms as $searchTerm) {

                        // If we find one of our terms return -1
                        if (strpos($modifiedLine, $searchTerm) !== false)
                        return array('property' => $propertyName, 'value' => -1);
                    }

                    // Get the number in the line without the property
                    if (preg_match('/([0-9]+)/',str_replace($propertyValue, '', $modifiedLine), $lineData) !== 0) {
                    
                        // Return the property name and the property value
                        return array('property' => $propertyName, 'value' => $lineData[1]);
                    }
                }
            }
        }

        // Return that we haven't found anything
        return null;
    }
}