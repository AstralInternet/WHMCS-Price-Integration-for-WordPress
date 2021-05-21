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
 * Available shortcode : 
 * [whmcs_domainscat tld="com" bypasscache='true"]
 * [whmcs_domainsprice tld="com" bypasscache='true"]
 * [whmcs_domainspromo tld="com" bypasscache='true"]
 * [whmcs_domainsflag tld="com" bypasscache='true"]
 */


 /**
 * Function to return a domain category
 *
 * The available params are : 
 *   tld : the domain TLD
 *   bypasscache (default false): Bypass the cache of one hour. The cache is there to prevent overloading the WHMCS server
 */
function whmcs_domainscat_func($p_atts)
{
    // Parse the given attributes
    $attribute = shortcode_atts(array(
        'tld' => '',
        'bypasscache' => false
    ), $p_atts);

    // Validate the TLD
    if (empty($attribute['tld'])) {
        $msg = __("TLD cannot be empty.", "whmcs-pi");
        $msg = whmcs_products_func_prepareOutput($msg, '', '', '', true);
        return $msg;
    } 

    // Clean boolean
    $attribute['bypasscache'] =  boolval('bypasscache');

    // Initiate the product class
    $domainObj = WHMCS_PI_Main::load_domain_class();

    // Fetch the tld Information
    $tldDetail = $domainObj->Get_TLD_Detail( $attribute['tld']);

    // return the TLD category
    return $tldDetail['categories'][0];
}

/**
 * Register the WHMCS Shortcode function
 */
add_shortcode('whmcs_domainscat', 'whmcs_domainscat_func');

/**
 * Function to return a domain flag
 *
 * The available params are : 
 *   tld : the domain TLD
 *   bypasscache (default false): Bypass the cache of one hour. The cache is there to prevent overloading the WHMCS server
 */
function whmcs_domainsflag_func($p_atts)
{
    // Parse the given attributes
    $attribute = shortcode_atts(array(
        'tld' => '',
        'bypasscache' => false
    ), $p_atts);

    // Validate the TLD
    if (empty($attribute['tld'])) {
        $msg = __("TLD cannot be empty.", "whmcs-pi");
        $msg = whmcs_products_func_prepareOutput($msg, '', '', '', true);
        return $msg;
    } 

    // Clean boolean
    $attribute['bypasscache'] =  boolval('bypasscache');

    // Initiate the product class
    $domainObj = WHMCS_PI_Main::load_domain_class();

    // Fetch the tld Information
    $tldDetail = $domainObj->Get_TLD_Detail( $attribute['tld']);

    // return the TLD flag
    return $tldDetail['flag'];
}

/**
 * Register the WHMCS Shortcode function
 */
add_shortcode('whmcs_domainsflag', 'whmcs_domainsflag_func');

/**
 * Function to return a domain price
 *
 * The available params are : 
 *   tld : the domain TLD
 *   bypasscache (default false): Bypass the cache of one hour. The cache is there to prevent overloading the WHMCS server
 */
function whmcs_domainsprice_func($p_atts)
{
    // Parse the given attributes
    $attribute = shortcode_atts(array(
        'tld' => '',
        'bypasscache' => false
    ), $p_atts);

    // Validate the TLD
    if (empty($attribute['tld'])) {
        $msg = __("TLD cannot be empty.", "whmcs-pi");
        $msg = whmcs_products_func_prepareOutput($msg, '', '', '', true);
        return $msg;
    } 

    // Clean boolean
    $attribute['bypasscache'] =  boolval('bypasscache');

    // Initiate the product class
    $domainObj = WHMCS_PI_Main::load_domain_class();

    // Fetch the tld Information
    $tldDetail = $domainObj->Get_TLD_Detail( $attribute['tld']);

    // return the TLD price
    return $tldDetail['reg_price'];
}

/**
 * Register the WHMCS Shortcode function
 */
add_shortcode('whmcs_domainsprice', 'whmcs_domainsprice_func');

/**
 * Function to return a domain promo price
 *
 * The available params are : 
 *   tld : the domain TLD
 *   bypasscache (default false): Bypass the cache of one hour. The cache is there to prevent overloading the WHMCS server
 */
function whmcs_domainspromo_func($p_atts)
{
    // Parse the given attributes
    $attribute = shortcode_atts(array(
        'tld' => '',
        'bypasscache' => false
    ), $p_atts);

    // Validate the TLD
    if (empty($attribute['tld'])) {
        $msg = __("TLD cannot be empty.", "whmcs-pi");
        $msg = whmcs_products_func_prepareOutput($msg, '', '', '', true);
        return $msg;
    } 

    // Clean boolean
    $attribute['bypasscache'] =  boolval('bypasscache');

    // Initiate the product class
    $domainObj = WHMCS_PI_Main::load_domain_class();

    // Fetch the tld Information
    $tldDetail = $domainObj->Get_TLD_Detail( $attribute['tld']);

    // return the TLD promo price
    return $tldDetail['promo'];
}

/**
 * Register the WHMCS Shortcode function
 */
add_shortcode('whmcs_domainspromo', 'whmcs_domainspromo_func');

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