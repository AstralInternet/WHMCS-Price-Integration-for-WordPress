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


/**
 * Function to handle the whmcs_products shortcode
 *
 * The available params are : 
 *   pid : The WHMCS pid (integer)
 *   period (default is annualy): monthly, quarterly, semiannually, annually, biennially, triennially
 *   productname (default is false): Return the product name
 *   description: Return the WHMCS product description instead of the regular price
 *   setupfee (default is false): Return the product setup fee.
 *   showmonthlyprice (default is true): Show the monthly price. EX, if the price is 120$/year, the code will return 12$/month
 *   promoprice (default is false): if true, will return the price with the pomotion applied instead of the regular price.
 *                                  Will return the regular price if there is no promotion price.
 *   promodiscount (default false): If true, will return the promotion discount value instead of the regular price
 *   promocode (default false): If true, will return the promotion code instead of the current price
 *   bypasscache (default false): Bypass the cache of one hour. The cache is there to prevent overloading the WHMCS server
 *   class (default empty): Add a custom class name to the output result
 *   whmcsprefix ( default false): Display the WHMCS define prefix on prices
 *   whmcssuffix ( default false): Display the WHMCS define suffix on prices
 *   customprefix(default empty):  Display a custom prefix (will overide WHMCS prefix)
 *   customsuffix(default empty):  Display a custom suffix (will overide WHMCS suffix)
 */
function whmcs_products_func($p_atts)
{

    // Clean the short code attribute
    $arguments = whmcs_products_func_clean_attribute($p_atts);

    // Validate the PID
    $pidValidation = whmcs_products_func_validade_pid($arguments['pid']);
    if (!$pidValidation['success']) return $pidValidation['msg'];

    // Validate the period
    $periodValidation = whmcs_products_func_validade_period($arguments['period']);
    if (!$periodValidation['success']) return $periodValidation['msg'];

    // Initiate the product class
    $productObj = WHMCS_PI_Main::load_product_class();

    // Fetch the product Information from WHMCS (of the cache saved in the )
    $pidDetail = $productObj->GetProducts($arguments['pid'], null, $arguments['bypasscache']);

    // Validate the API Call
    $apiValidation = whmcs_products_func_validade_api_call($pidDetail);
    if (!$apiValidation['success']) return $apiValidation['msg'];

    // Prepare the prefix for prices output
    $prefix = whmcs_products_func_prepare_prefixsuffixe($arguments, 'prefix');

    // Prepare the suffix for prices output
    $suffix = whmcs_products_func_prepare_prefixsuffixe($arguments, 'suffix');

    // Return the product information if selected
    if ($arguments['description']) {
        return whmcs_products_func_prepareOutput($pidDetail['description'], $arguments['class'], '', '');
    }

    // Return the product name if selected
    if ($arguments['productname']) {
        return whmcs_products_func_prepareOutput($pidDetail['name'], $arguments['class'], '', '');
    }

    // Return the setup fee
    if ($arguments['setupfee']) {

        // Build a period associative array to match the object setup key
        $setupPeriodsArray = array('monthly' => 'msetupfee', 'quarterly' => 'qsetupfee', 'semiannually' => 'ssetupfee', 'annually' => 'asetupfee', 'biennially' => 'bsetupfee', 'triennially' => 'tsetupfee');
        return whmcs_products_func_prepareOutput($pidDetail['price']->$setupPeriodsArray[$arguments['period']], $arguments['class'], $prefix, $suffix);
    }

    // Build a period associative array. Since the product class was first built for a french site.
    $periodsArray = array('monthly' => '1mois', 'quarterly' => '3mois', 'semiannually' => '6mois', 'annually' => '1an', 'biennially' => '2ans', 'triennially' => '3ans');

    // Isolate product pricing for the requested period (for easier readability and maintenance)
    $periodPricing = $pidDetail[$periodsArray[$arguments['period']]];

    // Return the promo code
    if ($arguments['promocode']) {
        return whmcs_products_func_prepareOutput($periodPricing['promo'], $arguments['class'], '', '');
    }

    // Return the promo discount amount/pourc
    if ($arguments['promodiscount']) {
        return whmcs_products_func_prepareOutput($periodPricing['sauver'], $arguments['class'], '', '');
    }

    // Build a period multiplyer array. Used to get the full period price
    $priceMultiplyer = array('monthly' => 1, 'quarterly' => 3, 'semiannually' => 6, 'annually' => 12, 'biennially' => 24, 'triennially' => 34);

    // Return the price with the pomotion applied
    if ($arguments['promoprice']) {

        // Check if there is a promotional price first. With no promo price, regular price will be given
        if ($periodPricing['prix'] > 0) {

            // check for the montly equivalent or full period
            if ($arguments['showmonthlyprice']) {
                $promoPrice = $periodPricing['prix'];
            } else {
                $promoPrice = $periodPricing['prix'] * $priceMultiplyer[$arguments['period']];
            }

            // return the promo price
            return whmcs_products_func_prepareOutput($promoPrice, $arguments['class'], $prefix, $suffix);
        }
    }

    // Return the regular product price
    if ($arguments['showmonthlyprice']) {

        // If no promo was added, the prix will be in the price array
        if (!empty($periodPricing['prixreg'])) {
            $price = $periodPricing['prixreg'];
        } else {
            $price = $periodPricing['prix'];
        }

        return whmcs_products_func_prepareOutput($price, $arguments['class'], $prefix, $suffix);
    } else {

        // Return the full period price
        return whmcs_products_func_prepareOutput($pidDetail['price']->{$arguments['period']}, $arguments['class'], $prefix, $suffix);
    }
}

/**
 * Register the WHMCS Shortcode function
 */
add_shortcode('whmcs_products', 'whmcs_products_func');


/**
 * Function to clean the provided attribute into easyer to use code
 * 
 * @param array shortcode attribut
 * @return array Clean provided attribute
 */
function whmcs_products_func_clean_attribute($p_attr)
{
    $attribute = shortcode_atts(array(
        'pid' => -1,
        'period' => 'annually',
        'productname' => false,
        'description' => false,
        'setupfee' => false,
        'showmonthlyprice' => true,
        'promoprice' => false,
        'promodiscount' => false,
        'promocode' => false,
        'bypasscache' => false,
        'class' => '',
        'whmcsprefix' => false,
        'whmcssuffix' => false,
        'customprefix' => '',
        'customsuffix' => ''
    ), $p_attr);

    // Define an array of boolean attribute
    $boolAttribute = array(
        'productname', 'description', 'setupfee', 'showmonthlyprice', 'promoprice', 'promodiscount',
        'promocode', 'bypasscache', 'whmcsprefix', 'whmcssuffix'
    );

    // Convert value into real boolean
    foreach ($boolAttribute as $singleAttribute) {
        $attribute[$singleAttribute] =  boolval($attribute[$singleAttribute]);
    }

    // Make sure the pid is in int format
    $attribute['pid'] = intval($attribute['pid']);

    // Return the cleaned array
    return $attribute;
}

/**
 * Function to check if the API returned an error
 * 
 * @param array the cleaned shortcode array
 * @param string prefix or suffix
 * @return string The prefix to be user
 */
function whmcs_products_func_prepare_prefixsuffixe($p_arg, $p_presu)
{

    $response = '';
    if ($p_arg['whmcs' . $p_presu]) {
        $response =  $p_arg['price']->prefix;
    }
    if (!empty($p_arg['custom' . $p_presu])) {
        $response = $p_arg['custom' . $p_presu];
    }

    // Return response
    return $response;
}

/**
 * Function to check if the API returned an error
 * 
 * @param object the api call response
 * @return array The validation result en the message if any
 */
function whmcs_products_func_validade_api_call($p_apiResponse)
{

    // Define response array
    $ans = array('success' => true, 'msg' => '');

    // Check for a API call problem
    if (is_object($p_apiResponse)) {
        if (property_exists($p_apiResponse, 'result')) {

            if ($p_apiResponse->result == 'error') {
                $ans['success'] = false;
                $ans['msg'] = __("Error while making the API call : " . $p_apiResponse->message, "whmcs-pi");
                $ans['msg'] = whmcs_products_func_prepareOutput($ans['msg'], '', '', '', true);
            }
        }
    }

    // Return the validation result
    return $ans;
}

/**
 * Function to validate the period provided
 * 
 * @param int the provided period
 * @return array The validation result en the message if any
 */
function whmcs_products_func_validade_period($p_period)
{

    // Define response array
    $ans = array('success' => true, 'msg' => '');

    // Build a valid period type array for validation of the input data
    $validPeriodArray = array('monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially');

    // Validate id the provided period is  valid
    if (!in_array($p_period, $validPeriodArray)) {
        $ans['success'] = false;
        $ans['msg'] = __("Invalid period (<b>" . $p_period . "</b>). Must be one of the following : monthly, quarterly, semiannually, annually, biennially, triennially", "whmcs-pi");
        $ans['msg'] = whmcs_products_func_prepareOutput($ans['msg'], '', '', '', true);
    }

    // Return the validation result
    return $ans;
}

/**
 * Function to validate the PID provided
 * 
 * @param int the provided pid
 * @return array The validation result en the message if any
 */
function whmcs_products_func_validade_pid($p_pid)
{

    // Define response array
    $ans = array('success' => true, 'msg' => '');

    // Make sure the PId is int and above 0
    if ($p_pid <= 0 or !is_int($p_pid)) {
        $ans['success'] = false;
        $ans['msg'] = __("Invalid product ID provided (<b>" . $p_pid . "</b>). Product ID must be an numeric value above 0", "whmcs-pi");
        $ans['msg'] = whmcs_products_func_prepareOutput($ans['msg'], '', '', '', true);
    }

    // Return the validation result
    return $ans;
}

/**
 * Format the response sent back for the shotcode.
 * 
 * @since 1.0.0
 * @param string Message to be return
 * @param string CSS Class to be added to the mesage
 * @param string Prefix string
 * @param string Suffix string
 * @param bool Whether the response is an error or not
 * @return string
 */
function whmcs_products_func_prepareOutput($p_msg, $p_class, $p_prefix = '', $p_suffix = '', $p_isError = false)
{

    // Prepare the class the be added to the return object
    $class = 'class="whmcs_products ' . $p_class . '"';

    // If there is an error, prepare extra styling
    $style = "";
    if ($p_isError) {
        $style = 'style="color:#721c24;background-color: #f8d7da;border: 1px solid #f5c6cb;padding:2px;position:relative;border-radius: .25rem;"';
    }

    // Prepare the response string
    $response = "<span $class $style>$p_prefix$p_msg$p_suffix</span>";

    // Return the response
    return $response;
}
