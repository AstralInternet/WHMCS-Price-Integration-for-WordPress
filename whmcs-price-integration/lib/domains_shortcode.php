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
    $tldDetail = $domainObj->Get_TLD_Detail($attribute['tld']);

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
    $tldDetail = $domainObj->Get_TLD_Detail($attribute['tld']);

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
    $tldDetail = $domainObj->Get_TLD_Detail($attribute['tld']);

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
    $tldDetail = $domainObj->Get_TLD_Detail($attribute['tld']);

    // return the TLD promo price
    return $tldDetail['promo'];
}

/**
 * Register the WHMCS Shortcode function
 */
add_shortcode('whmcs_domainspromo', 'whmcs_domainspromo_func');


/**
 * Function to return a formated view of each domain and it corresponding categorie
 *
 * The available params are : 
 *   display (tld or category): Will either display alSl the categories or a listr of all the TLDS
 *   bypasscache (default false): Bypass the cache of one hour. The cache is there to prevent overloading the WHMCS server
 */
function whmcs_domainsdisplayall_func($p_atts)
{

    // Parse the given attributes
    $attribute = shortcode_atts(array(
        'bypasscache' => false,
        'display' => 'category',
        'tldbtnclass' => ''
    ), $p_atts);

    // Initiate the product class
    $domainObj = WHMCS_PI_Main::load_domain_class();

    // Fetch the tld Information
    $allTldDetail = $domainObj->Get_Whmcs_TLD_List($attribute['bypasscache']);

    // Temp return result
    $rtn = "<pre>";
    $rtn .= print_r($allTldDetail, true);
    $rtn .= "</pre>";

    // Return the wanted result
    if ($attribute['display'] == 'tld') {
        return whmcs_TLD_To_HTML_Table($allTldDetail, $attribute['tldbtnclass']);
    } else {
        return whmcs_TLD_Category_To_HTML_Ul($allTldDetail);
    }
}

/**
 * Register the WHMCS Shortcode function
 */
add_shortcode('whmcs_domainsdisplayall', 'whmcs_domainsdisplayall_func');


/**
 * Function to return a formated view of each domain and it corresponding categorie
 *
 * The available params are : 
 *   docready (1/0): Will add the custom "docready JS function" for pure JS implemtation
 */
function whmcs_domainsdisplayallJS_func($p_atts)
{

    // Parse the given attributes
    $attribute = shortcode_atts(array(
        'docready' => false
    ), $p_atts);

    // Start the opening JS tag
    $jsScript = "<script>";

    // Add the Doc Ready Script
    if ($attribute['docready'] || $attribute['docready']==1 || $attribute['docready']=='true') {
        $jsScript .= 'function docReady(e){"complete"===document.readyState||"interactive"===document.readyState?setTimeout(e,1):document.addEventListener("DOMContentLoaded",e)}';
    }

    $jsFunc = <<<END
/**
 * wait for document to be ready
 */
docReady(function () {
    whmcsAPI_domainCatClick();
});

/**
 * Function to check for the FAQ open and close
 */
function whmcsAPI_domainCatClick() {

    // Get a list of all displayed category
    var tldCat = document.querySelectorAll("#whmcs_tld_categories_list li");

    // Hide row on each category click
    var toggleCat = function () {

        // Get selected category
        var selCat = this.getAttribute("data-tldgroup");

        // Remove the "selected" class from the group
        for (var i = 0; i < tldCat.length; i++) {
            tldCat[i].classList.remove("selected");
        }

        // Add the "selected class to the current element
        this.classList.add("selected");

        // Get all domain listed
        var allDomain = document.querySelectorAll("#tldgroup tr");

        // Change the row display
        for (var i = 0; i < allDomain.length; i++) {

            // Start by higind the row
            allDomain[i].style.display = 'none';

            // Show rows with corresponding class
            if (allDomain[i].classList.contains(selCat)) {
                allDomain[i].style.display = 'table-row';
            }
        }

    }

    // Add the listener for the click event
    for (var i = 0; i < tldCat.length; i++) {
        tldCat[i].addEventListener('click', toggleCat, false);
    }
}
END;
$jsScript .= $jsFunc;

    // Close the JS tag
    $jsScript .= "</script>";

    // Return the script to be added in the page
    return $jsScript;
}

/**
 * Register the WHMCS Shortcode function
 */
add_shortcode('whmcs_domainsdisplayallJS', 'whmcs_domainsdisplayallJS_func');




########################
### HELPER FUNCTIONS ###
########################

/**
 * Build a HTML list of all the TLD category from WHMCS
 * 
 * @since 1.0.0
 * @param array list of all WHMCS TLD detail
 * @return string (HTML UL block)
 */
function whmcs_TLD_Category_To_HTML_Ul($p_allTldDetail)
{

    // Build the category list
    $htmlList = '<ul id="whmcs_tld_categories_list">';

    // Go through each categorie
    foreach ($p_allTldDetail['categories'] as $tldGroup => $groupTLDs) {

        // Prepare HTML LI class; Select "all" by default
        if ($tldGroup == 'all') {
            $class = 'class="selected" ';
        } else {
            $class = '';
        }

        // replace space by "_" ffrom the tld group for the HTML LI data attribute
        $htmlTldgroup = str_replace(" ", "_", $tldGroup);

        // Build the LI element
        $htmlLI =  '<li ' . $class . 'data-tldgroup="' . $htmlTldgroup . '">' . __($tldGroup, "whmcs-pi-tld-group") . ' (' . count($groupTLDs) . ')</li>';

        // Add HTML LI to the UL element
        $htmlList .= $htmlLI;
    }

    // Close the category list
    $htmlList .= '</ul>';

    // Return the HTML UL List
    return $htmlList;
}

/**
 * Build a HTML table with every TLD from WHMCS
 * 
 * @since 1.0.0
 * @param array list of all WHMCS TLD detail
 * @param string boutton class to att
 * @return string (HTML UL block)
 */
function whmcs_TLD_To_HTML_Table($p_allTldDetail, $p_buttonClass = '')
{

    // Build a table
    $htmlTable = '<table id="tldgroup"><tbody>';

    // Go through each TLD
    foreach ($p_allTldDetail['tlddetail'] as $tldName => $tldDetail) {

        $trClass = 'all ' . $tldDetail['flag'];

        // Build the TLD class for the TR element
        foreach ($tldDetail['categories'] as $category) {
            $trClass .= ' ' . str_replace(" ", "_", $category);
        }

        // Open the row
        $htmlTR = '<tr data-tldname="' . $tldName . '" class="'.$trClass.'">';

        // Add the TLD column
        $htmlTR .= '<td class="table_tld">'.$tldName.'</td>';

        // Add the price and buy column
        $htmlTR .= '<td class="table_tld_cart">';

        // Add the promo price
        if ($tldDetail['promo'] == 1) {
            $htmlTR .= '<span class="prev_price">'.__('Was', "whmcs-pi");
            $htmlTR .= '<span style="text-decoration: line-through;">'.$tldDetail['renew'];
            $htmlTR .= '</span>';
            $htmlTR .= '</span>';
        }

        // Add Current price
        $htmlTR .= '<span class="actual_price">'.$tldDetail['reg_price'].'</span>';

        // Close the cart column
        $htmlTR .= '</td>';

        // Add the register button
        $htmlTR .= '<td><div class="wp-block-button btn '.$p_buttonClass.'" id="'.$tldName.'"><a class="wp-block-button__link" href="'.get_option('whmcs-pi_clientareaurl').'cart.php?a=add&domain=register">'.__('Check Availability', "whmcs-pi").'</a></div></td>';

        // Close the cart column
        $htmlTR .= '</td>';

        // Close the row
        $htmlTR .= '</tr>';

        //Append the row to the table
        $htmlTable .= $htmlTR;
    }

    // Close HTML Table
    $htmlTable .= '</tbody></table>';

    // Return the HTML UL List
    return $htmlTable;
}
