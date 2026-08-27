<?php

/**
 * WHMCS Price Integration
 * 
 * @author            Astral Internet inc.
 * @copyright         Copyright (C) 2021-2026, Astral Internet inc. - support@astralinternet.com
 * @license           http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 * 
 * @wordpress-plugin
 * Plugin Name: 		WHMCS Price Integration
 * Plugin URI:      	https://github.com/AstralInternet/WHMCS-Price-Integration-for-WordPress
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
 * Available shortcodes :
 * [whmcs_domainscat tld="com"]        product category of the extension
 * [whmcs_domainsprice tld="com"]      first year registration price
 * [whmcs_domainsrenew tld="com"]      renewal price
 * [whmcs_domainspromo tld="com"]      discount, as a percentage
 * [whmcs_domainsflag tld="com"]       WHMCS group flag
 *
 * All of them accept bypasscache="true" to skip the daily cache.
 */
// If this file is called directly, abort.
defined('ABSPATH') or die('No script kiddies please!');



/**
 * Read the shared attributes of the single-TLD shortcodes.
 *
 * @since 1.0.0
 * @param array $p_atts Raw shortcode attributes
 * @return array|string Normalised attributes, or an error string to render
 */
function whmcs_domains_parse_atts($p_atts)
{
    $attribute = shortcode_atts(array(
        'tld' => '',
        'bypasscache' => false
    ), $p_atts);

    // Validate the TLD
    if (empty($attribute['tld'])) {
        return whmcs_products_func_prepareOutput(
            __("TLD cannot be empty.", "whmcs-pi"), '', '', '', true
        );
    }

    /**
     * "false", "0" and "no" all have to read as false. The previous code ran
     * boolval() on the literal string 'bypasscache', which is always true, and
     * then dropped the result on the floor.
     */
    $attribute['bypasscache'] = filter_var(
        $attribute['bypasscache'], FILTER_VALIDATE_BOOLEAN
    );

    return $attribute;
}

/**
 * Fetch one TLD, honouring the cache freshness rules.
 *
 * @since 1.0.0
 * @param array $p_attribute Normalised attributes
 * @return array Empty when the TLD is unknown or the cache is too old
 */
function whmcs_domains_fetch($p_attribute)
{
    $domainObj = WHMCS_PI_Main::load_domain_class();

    // A week-old price is worse than no price on a commercial page.
    if ($domainObj->Is_Cache_Stale() && !$p_attribute['bypasscache']) {
        return array();
    }

    return $domainObj->Get_TLD_Detail($p_attribute['tld'], $p_attribute['bypasscache']);
}

/**
 * Function to return a domain category
 *
 * The available params are :
 *   tld : the domain TLD
 *   bypasscache (default false): Bypass the daily cache
 */
function whmcs_domainscat_func($p_atts)
{
    $attribute = whmcs_domains_parse_atts($p_atts);

    if (!is_array($attribute)) {
        return $attribute;
    }

    $tldDetail = whmcs_domains_fetch($attribute);

    if (empty($tldDetail['categories'][0])) {
        return '';
    }

    return esc_html($tldDetail['categories'][0]);
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
 *   bypasscache (default false): Bypass the daily cache
 */
function whmcs_domainsflag_func($p_atts)
{
    $attribute = whmcs_domains_parse_atts($p_atts);

    if (!is_array($attribute)) {
        return $attribute;
    }

    $tldDetail = whmcs_domains_fetch($attribute);

    if (empty($tldDetail['flag'])) {
        return '';
    }

    return esc_html($tldDetail['flag']);
}

/**
 * Register the WHMCS Shortcode function
 */
add_shortcode('whmcs_domainsflag', 'whmcs_domainsflag_func');

/**
 * Function to return the first year registration price of a domain
 *
 * The available params are :
 *   tld : the domain TLD
 *   bypasscache (default false): Bypass the daily cache
 */
function whmcs_domainsprice_func($p_atts)
{
    $attribute = whmcs_domains_parse_atts($p_atts);

    if (!is_array($attribute)) {
        return $attribute;
    }

    $tldDetail = whmcs_domains_fetch($attribute);

    if (!isset($tldDetail['reg_price'])) {
        return '';
    }

    return esc_html(WHMCS_PI_Main::format_currency($tldDetail['reg_price']));
}

/**
 * Register the WHMCS Shortcode function
 */
add_shortcode('whmcs_domainsprice', 'whmcs_domainsprice_func');

/**
 * Function to return the renewal price of a domain
 *
 * On new gTLDs the first year is often promotional while renewal is markedly
 * higher. Showing both is plain honesty, and the client sees it at checkout
 * anyway.
 *
 * The available params are :
 *   tld : the domain TLD
 *   bypasscache (default false): Bypass the daily cache
 *
 * @since 1.0.0
 */
function whmcs_domainsrenew_func($p_atts)
{
    $attribute = whmcs_domains_parse_atts($p_atts);

    if (!is_array($attribute)) {
        return $attribute;
    }

    $tldDetail = whmcs_domains_fetch($attribute);

    if (!isset($tldDetail['renew'])) {
        return '';
    }

    return esc_html(WHMCS_PI_Main::format_currency($tldDetail['renew']));
}

/**
 * Register the WHMCS Shortcode function
 */
add_shortcode('whmcs_domainsrenew', 'whmcs_domainsrenew_func');

/**
 * Function to return the promotional discount on a domain
 *
 * Returns the discount as a percentage, or an empty string when the TLD is not
 * on promotion. The previous version formatted the 0/1 promo flag as currency
 * and rendered "$0.00" or "$1.00".
 *
 * The available params are :
 *   tld : the domain TLD
 *   format (percent|amount): what to render, defaults to percent
 *   bypasscache (default false): Bypass the daily cache
 */
function whmcs_domainspromo_func($p_atts)
{
    $format = 'percent';

    if (is_array($p_atts) && isset($p_atts['format'])) {
        $format = $p_atts['format'] === 'amount' ? 'amount' : 'percent';
    }

    $attribute = whmcs_domains_parse_atts($p_atts);

    if (!is_array($attribute)) {
        return $attribute;
    }

    $tldDetail = whmcs_domains_fetch($attribute);

    if (empty($tldDetail['promo'])) {
        return '';
    }

    if ($format === 'amount' && isset($tldDetail['discount_amount'])) {
        return esc_html(WHMCS_PI_Main::format_currency($tldDetail['discount_amount']));
    }

    if (!isset($tldDetail['discount_pourc'])) {
        return '';
    }

    return esc_html($tldDetail['discount_pourc'] . '%');
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

    // A non-empty string such as "false" reads as true through boolval().
    $attribute['bypasscache'] = filter_var(
        $attribute['bypasscache'], FILTER_VALIDATE_BOOLEAN
    );

    // A shortcode attribute is author-controlled: keep it to a class name.
    $attribute['tldbtnclass'] = implode(' ', array_filter(array_map(
        'sanitize_html_class',
        preg_split('/\s+/', trim((string) $attribute['tldbtnclass']))
    )));

    // Initiate the product class
    $domainObj = WHMCS_PI_Main::load_domain_class();

    // The seven day staleness rule applies to the grouped view as well.
    if ($domainObj->Is_Cache_Stale() && !$attribute['bypasscache']) {
        return '';
    }

    // Fetch the tld Information
    $allTldDetail = $domainObj->Get_Whmcs_TLD_List($attribute['bypasscache']);

    if (empty($allTldDetail['tlddetail'])) {
        return '';
    }

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

        /**
         * The group name comes from WHMCS, a separate system with its own
         * operators. It is data, not markup — escaped for the attribute and for
         * the text node separately, since the two contexts differ.
         */
        $htmlLI = '<li ' . $class . 'data-tldgroup="' . esc_attr($htmlTldgroup) . '">'
            . esc_html(__($tldGroup, "whmcs-pi")) . ' (' . (int) count($groupTLDs) . ')</li>';

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

        /**
         * Every value below originates in WHMCS: the TLD name, the group flag
         * and the category names are all editable by a WHMCS operator. They
         * reach public pages, so they are escaped for their exact context.
         */
        $htmlTR = '<tr data-tldname="' . esc_attr($tldName) . '" class="' . esc_attr($trClass) . '">';

        // Add the TLD column
        $htmlTR .= '<td class="table_tld">.' . esc_html($tldName) . '</td>';

        // Add the price and buy column
        $htmlTR .= '<td class="table_tld_cart">';

        // Add the promo price
        if ($tldDetail['promo'] == 1) {
            $htmlTR .= '<span class="prev_price">' . esc_html__('Was', "whmcs-pi");
            $htmlTR .= '<span style="text-decoration: line-through;">'
                . esc_html(WHMCS_PI_Main::format_currency($tldDetail['renew']));
            $htmlTR .= '</span>';
            $htmlTR .= '</span>';
        }
        
        // Add Current price
        $htmlTR .= '<span class="actual_price">'
            . esc_html(WHMCS_PI_Main::format_currency($tldDetail['reg_price'])) . '</span>';

        // Close the cart column
        $htmlTR .= '</td>';

        /**
         * $p_buttonClass arrives from a shortcode attribute, so any author who
         * can place a shortcode controls it. sanitize_html_class() keeps it to
         * a class name and nothing else.
         */
        $panier = esc_url(trailingslashit(get_option('whmcs-pi_clientareaurl')) . 'cart.php?a=add&domain=register');

        $htmlTR .= '<td><div class="wp-block-button btn ' . esc_attr($p_buttonClass) . '"'
            . ' id="' . esc_attr($tldName) . '">'
            . '<a class="wp-block-button__link" href="' . $panier . '">'
            . esc_html__('Search domain TLD', "whmcs-pi") . '</a></div></td>';

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
