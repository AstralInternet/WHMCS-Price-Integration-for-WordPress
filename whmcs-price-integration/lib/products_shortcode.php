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
 * 
 */
// If this file is called directly, abort.
defined('ABSPATH') or die('No script kiddies please!');



/**
 * Function to handle the whmcs_products shortcode
 *
 * The available params are : 
 *   pid : The WHMCS pid (integer)
 *   period (default is annualy): monthly, quarterly, semiannually, annually, biennially, triennially
 *                                A cycle the product is not sold on renders nothing.
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
 *   withoptions (default false): Add the cheapest selectable value of the product configurable
 *                                options to the returned price. A "from" price quoted on a sales
 *                                page normally includes the options a customer cannot avoid, so
 *                                the bare product price understates what is actually payable.
 *   options (default empty): Comma separated list of configurable option IDs to count, for
 *                            instance options="911,913". Empty means every option attached to
 *                            the product. Ignored unless withoptions is true.
 *   optionsonly (default false): Return only the options floor, without the product price. Useful
 *                                to print the breakdown next to the total.
 *   debugoptions (default false): Print the option structure WHMCS actually returned, so its
 *                                 shape can be confirmed against a live account. Visible only to
 *                                 users allowed to manage options; anyone else gets nothing.
 *   optionsmin (default empty): Quantity minimums WHMCS does not report, as id:minimum pairs,
 *                               for instance optionsmin="913:10". Only used when the API gives
 *                               no minimum for that option; a reported minimum always wins.
 *   debugapi (default false): When the API call fails, print the reason WHMCS gave instead of
 *                             the neutral notice. Visible only to users allowed to manage
 *                             options: WHMCS wording can name internal details.
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
    if (!$apiValidation['success']) {

        // Visitors keep the neutral notice; only an administrator who asked
        // for the reason gets it.
        if ($arguments['debugapi'] && current_user_can('manage_options')) {

            $reason = method_exists($productObj, 'Get_Last_Error')
                ? $productObj->Get_Last_Error()
                : '';

            return whmcs_products_func_prepareOutput(
                sprintf(
                    /* translators: 1: WHMCS product id, 2: reason reported by WHMCS */
                    __('GetProducts failed for pid %1$d. WHMCS reported: %2$s', 'whmcs-pi'),
                    $arguments['pid'],
                    $reason !== '' ? $reason : __('no detail recorded', 'whmcs-pi')
                ),
                $arguments['class'], '', '', true
            );
        }

        return $apiValidation['msg'];
    }

    // Prepare the prefix for prices output
    $prefix = whmcs_products_func_prepare_prefixsuffixe($arguments, 'prefix');

    // Prepare the suffix for prices output
    $suffix = whmcs_products_func_prepare_prefixsuffixe($arguments, 'suffix');

    /**
     * Floor of the configurable options, as a monthly amount. Computed only
     * when asked for, then added to whichever price branch runs below.
     */
    $optionsMonthly = 0.0;
    if ($arguments['withoptions'] || $arguments['optionsonly'] || $arguments['debugoptions']) {

        // "913:10,914:5" becomes array('913' => '10', '914' => '5')
        $declaredMinimums = array();
        foreach (explode(',', (string) $arguments['optionsmin']) as $pair) {
            $parts = array_map('trim', explode(':', $pair, 2));
            if (count($parts) === 2 && $parts[0] !== '' && is_numeric($parts[1])) {
                $declaredMinimums[$parts[0]] = $parts[1];
            }
        }

        $floor = whmcs_products_func_options_floor(
            isset($pidDetail['configOptions']) ? $pidDetail['configOptions'] : null,
            $arguments['options'],
            $arguments['period'],
            $declaredMinimums
        );
        $optionsMonthly = $floor['monthly'];

        // The dump answers "what did WHMCS actually send", nothing else.
        if ($arguments['debugoptions']) {
            return whmcs_products_func_options_debug($floor, $arguments['class']);
        }
    }

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
        $setupFee = $pidDetail['price']->$setupPeriodsArray[$arguments['period']];

        if (!whmcs_products_func_is_sellable($setupFee)) {
            return '';
        }

        return whmcs_products_func_prepareOutput($setupFee, $arguments['class'], $prefix, $suffix);
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

    // Months per cycle, to turn a monthly figure into a full period one.
    $priceMultiplyer = whmcs_products_func_cycle_months();

    /**
     * A cycle the product is not sold on carries no price at all.
     *
     * Nothing is rendered in that case, rather than a figure derived from the
     * -1.00 WHMCS uses to mean "not available". Adding an options floor to
     * that would turn the marker into a plausible looking price.
     */
    if (!whmcs_products_func_is_sellable($pidDetail['price']->{$arguments['period']})) {
        return '';
    }

    // Expressed in the same unit as the product price it will sit next to.
    $optionsForOutput = $arguments['showmonthlyprice']
        ? $optionsMonthly
        : $optionsMonthly * $priceMultiplyer[$arguments['period']];

    // Return the options floor on its own, without the product price
    if ($arguments['optionsonly']) {
        return whmcs_products_func_prepareOutput(
            number_format($optionsForOutput, 2, '.', ''),
            $arguments['class'], $prefix, $suffix
        );
    }

    $price = whmcs_products_func_base_price(
        $pidDetail, $arguments['period'], $arguments['promoprice'], $arguments['showmonthlyprice']
    );

    if ($price === null) {
        return '';
    }

    return whmcs_products_func_prepareOutput(
        number_format($price + $optionsForOutput, 2, '.', ''),
        $arguments['class'], $prefix, $suffix
    );
}

/**
 * Product price for one billing cycle, options excluded.
 *
 * Shared by the shortcode and the block so a price cannot be assembled two
 * different ways. The options floor is added by the caller, which knows
 * whether it was asked for.
 *
 * @since 1.3.0
 * @param array  $p_detail  Processed product array from GetProducts()
 * @param string $p_period  Billing cycle
 * @param bool   $p_promo   Prefer the promotional price when one exists
 * @param bool   $p_monthly Monthly equivalent rather than the full period
 * @return float|null Null when the product is not sold on that cycle
 */
function whmcs_products_func_base_price($p_detail, $p_period, $p_promo, $p_monthly)
{
    if (!whmcs_products_func_is_sellable($p_detail['price']->{$p_period})) {
        return null;
    }

    // The product class was first written for a french site.
    $periods = array('monthly' => '1mois', 'quarterly' => '3mois', 'semiannually' => '6mois',
                     'annually' => '1an', 'biennially' => '2ans', 'triennially' => '3ans');
    $pricing = $p_detail[$periods[$p_period]];
    $months = whmcs_products_func_cycle_months();

    // A promotional price only exists when one is configured in WHMCS.
    if ($p_promo && $pricing['prix'] > 0) {
        return $p_monthly
            ? (float) $pricing['prix']
            : (float) $pricing['prix'] * $months[$p_period];
    }

    if (!$p_monthly) {
        return (float) $p_detail['price']->{$p_period};
    }

    return !empty($pricing['prixreg'])
        ? (float) $pricing['prixreg']
        : (float) $pricing['prix'];
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
        'customsuffix' => '',
        'withoptions' => false,
        'options' => '',
        'optionsonly' => false,
        'debugoptions' => false,
        'debugapi' => false,
        'optionsmin' => ''
    ), $p_attr);

    // Define an array of boolean attribute
    $boolAttribute = array(
        'productname', 'description', 'setupfee', 'showmonthlyprice', 'promoprice', 'promodiscount',
        'promocode', 'bypasscache', 'whmcsprefix', 'whmcssuffix', 'withoptions', 'optionsonly',
        'debugoptions', 'debugapi'
    );

    // Convert value into real boolean
    foreach ($boolAttribute as $singleAttribute) {
        // "false" and "0" must read as false; boolval() says true for both.
        $attribute[$singleAttribute] = filter_var(
            $attribute[$singleAttribute], FILTER_VALIDATE_BOOLEAN
        );
    }

    // Make sure the pid is in int format
    $attribute['pid'] = intval($attribute['pid']);

    // Return the cleaned array
    return $attribute;
}

/**
 * Number of months in each WHMCS billing cycle.
 *
 * Kept beside the option code because option prices are quoted per cycle, and
 * everything below reasons in monthly amounts so the two can be added.
 *
 * @return array<string,int>
 */
function whmcs_products_func_cycle_months()
{
    return array(
        'monthly' => 1, 'quarterly' => 3, 'semiannually' => 6,
        'annually' => 12, 'biennially' => 24, 'triennially' => 36,
    );
}

/**
 * Whether a figure returned by WHMCS is a price that can be shown.
 *
 * WHMCS quotes -1.00 for a billing cycle a product is not sold on. It is a
 * marker, not an amount: divided by a number of months it becomes a small
 * negative figure, and any total built on it reads as a real price.
 *
 * @since 1.3.0
 * @param mixed $p_amount Figure as WHMCS returned it
 * @return bool
 */
function whmcs_products_func_is_sellable($p_amount)
{
    return is_numeric($p_amount) && (float) $p_amount >= 0;
}

/**
 * Coerce a WHMCS repeated node into a plain list.
 *
 * A repeated node comes back as a list when it holds several entries, as a
 * single object when it holds one, and sometimes without its wrapping key.
 *
 * @param mixed  $p_node Whatever WHMCS returned
 * @param string $p_key  Name of the wrapping key, e.g. "configoption"
 * @return array Always a list, possibly empty
 */
function whmcs_products_func_as_list($p_node, $p_key)
{
    if (empty($p_node)) {
        return array();
    }

    // Unwrap the container when the key is present.
    if (is_object($p_node) && isset($p_node->$p_key)) {
        $p_node = $p_node->$p_key;
    } elseif (is_array($p_node) && isset($p_node[$p_key])) {
        $p_node = $p_node[$p_key];
    }

    if (is_object($p_node)) {
        // A single entry, handed over without its list.
        return array($p_node);
    }

    return is_array($p_node) ? array_values($p_node) : array();
}

/**
 * Read one field from an object or an associative array indifferently.
 *
 * @param mixed  $p_holder Object or array
 * @param string $p_field  Field name
 * @return mixed|null Null when absent
 */
function whmcs_products_func_field($p_holder, $p_field)
{
    if (is_object($p_holder) && isset($p_holder->$p_field)) {
        return $p_holder->$p_field;
    }
    if (is_array($p_holder) && isset($p_holder[$p_field])) {
        return $p_holder[$p_field];
    }
    return null;
}

/**
 * Monthly cost of a single selectable value of a configurable option.
 *
 * The known shapes are tried in order of trustworthiness: an explicit price
 * for the cycle asked about, then an explicit monthly price, then the looser
 * "recurring" field. The result is normalised to a monthly figure so option
 * costs and product costs can be summed.
 *
 * @param mixed  $p_option     One selectable value
 * @param string $p_periodLong Billing cycle being priced, e.g. "annually"
 * @return array{amount: float, source: string} Source names the field used
 */
function whmcs_products_func_option_value_monthly($p_option, $p_periodLong)
{
    $months = whmcs_products_func_cycle_months();
    $divisor = isset($months[$p_periodLong]) ? (float) $months[$p_periodLong] : 1.0;

    $pricing = whmcs_products_func_field($p_option, 'pricing');
    if ($pricing !== null) {
        $currency = whmcs_products_func_field($pricing, 'CAD');
        if ($currency === null) {
            // Single currency accounts sometimes expose the cycles directly.
            $currency = $pricing;
        }

        $forCycle = whmcs_products_func_field($currency, $p_periodLong);
        if (is_numeric($forCycle) && $divisor > 0) {
            return array('amount' => (float) $forCycle / $divisor,
                         'source' => 'pricing.' . $p_periodLong);
        }

        $monthly = whmcs_products_func_field($currency, 'monthly');
        if (is_numeric($monthly)) {
            return array('amount' => (float) $monthly, 'source' => 'pricing.monthly');
        }
    }

    // Older shapes put the amount straight on the option.
    $recurring = whmcs_products_func_field($p_option, 'recurring');
    if (is_numeric($recurring) && $divisor > 0) {
        return array('amount' => (float) $recurring / $divisor, 'source' => 'recurring');
    }

    $flat = whmcs_products_func_field($p_option, 'monthly');
    if (is_numeric($flat)) {
        return array('amount' => (float) $flat, 'source' => 'monthly');
    }

    return array('amount' => 0.0, 'source' => 'none');
}

/**
 * Cheapest monthly cost a customer cannot avoid on a product configuration.
 *
 * For a dropdown or a radio the floor is its least expensive selectable value.
 * For a quantity option it is the minimum quantity times the unit price, since
 * WHMCS will not let the order go below that minimum.
 *
 * A missing price counts as zero rather than raising an error: one unpriced
 * option should not take the whole figure off the page. The detail array
 * carries what was found, for the debug attribute.
 *
 * @param mixed  $p_configOptions Raw configoptions node from WHMCS
 * @param string $p_filter        Comma separated option IDs, empty for all
 * @param string $p_periodLong    Billing cycle being priced
 * @param array  $p_declared      Quantity minimums keyed by option id, for the
 *                                options WHMCS does not report one for
 * @return array{monthly: float, detail: array, counted: int}
 */
function whmcs_products_func_options_floor($p_configOptions, $p_filter, $p_periodLong, $p_declared = array())
{
    $wanted = array_filter(array_map('trim', explode(',', (string) $p_filter)), 'strlen');
    $total = 0.0;
    $detail = array();

    foreach (whmcs_products_func_as_list($p_configOptions, 'configoption') as $option) {

        $id = (string) whmcs_products_func_field($option, 'id');
        if ($wanted && !in_array($id, $wanted, true)) {
            continue;
        }

        /**
         * WHMCS reports the type as a numeric code: 1 dropdown, 2 radio,
         * 3 yes/no, 4 quantity. Some versions send the word, so both are read.
         */
        $type = strtolower((string) whmcs_products_func_field($option, 'type'));
        $isQuantity = ($type === 'quantity' || $type === '4');

        $values = whmcs_products_func_as_list(
            whmcs_products_func_field($option, 'options'), 'option'
        );

        $floor = null;
        $source = 'none';

        if ($isQuantity) {

            // One selectable value carrying a unit price and a minimum.
            $unit = $values ? whmcs_products_func_option_value_monthly($values[0], $p_periodLong)
                            : whmcs_products_func_option_value_monthly($option, $p_periodLong);

            /**
             * GetProducts sends the minimum as minqty; the other spellings
             * appear in older responses. It sits on the option in some
             * versions and on its single value in others.
             */
            $min = null;
            $foundIn = '';
            foreach (array('minqty', 'qtyminimum', 'qtymin') as $key) {
                if ($min === null) {
                    $min = whmcs_products_func_field($option, $key);
                    if ($min !== null) {
                        $foundIn = $key;
                    }
                }
                if ($min === null && $values) {
                    $min = whmcs_products_func_field($values[0], $key);
                    if ($min !== null) {
                        $foundIn = $key . ' on value';
                    }
                }
            }

            // Last resort when WHMCS reports no minimum at all. A reported
            // minimum always wins over a declared one.
            if (!is_numeric($min) && isset($p_declared[$id])) {
                $min = $p_declared[$id];
                $foundIn = 'declared in shortcode';
            }

            $quantity = is_numeric($min) ? (float) $min : 1.0;
            $floor = $unit['amount'] * $quantity;
            $source = sprintf('%s x %s', $unit['source'],
                is_numeric($min)
                    ? sprintf('minimum %s (%s)', $quantity, $foundIn)
                    : 'NO MINIMUM FOUND, assumed 1');

        } else {

            // Dropdown, radio or yes/no: the cheapest value that can be picked.
            foreach ($values as $value) {
                $priced = whmcs_products_func_option_value_monthly($value, $p_periodLong);
                if ($floor === null || $priced['amount'] < $floor) {
                    $floor = $priced['amount'];
                    $source = $priced['source'];
                }
            }
        }

        if ($floor === null) {
            $floor = 0.0;
        }

        $total += $floor;

        // Field names only, for the debug dump: a name is what identifies a
        // key read from the wrong place.
        $optionKeys = is_object($option) ? array_keys(get_object_vars($option))
                                   : (is_array($option) ? array_keys($option) : array());
        $valueKeys = array();
        if ($values) {
            $premier = $values[0];
            $valueKeys = is_object($premier) ? array_keys(get_object_vars($premier))
                                              : (is_array($premier) ? array_keys($premier) : array());
        }

        $detail[] = array(
            'id' => $id,
            'name' => (string) whmcs_products_func_field($option, 'name'),
            'type' => $type,
            'values' => count($values),
            'monthly' => round($floor, 2),
            'source' => $source,
            'keys' => $optionKeys,
            'valuekeys' => $valueKeys,
        );
    }

    return array('monthly' => $total, 'detail' => $detail, 'counted' => count($detail));
}

/**
 * Readable dump of what WHMCS returned for the options, for an administrator.
 *
 * The shape of configoptions varies between WHMCS versions. This prints what
 * was received and which field each amount came from. Restricted to users who
 * can manage options: it names internal identifiers.
 *
 * @param array  $p_floor Result of whmcs_products_func_options_floor()
 * @param string $p_class Extra CSS class from the shortcode
 * @return string HTML, empty for anyone not allowed to see it
 */
function whmcs_products_func_options_debug($p_floor, $p_class)
{
    if (!current_user_can('manage_options')) {
        return '';
    }

    if (!$p_floor['counted']) {
        return whmcs_products_func_prepareOutput(
            __('No configurable option was returned for this product.', 'whmcs-pi'),
            $p_class, '', '', true
        );
    }

    $lines = array();
    foreach ($p_floor['detail'] as $d) {
        $lines[] = sprintf(
            'id %s — %s (%s, %d value(s)) : %s /month, read from %s',
            $d['id'], $d['name'], $d['type'], $d['values'],
            number_format($d['monthly'], 2, '.', ''), $d['source']
        );

        // Field names as WHMCS sent them: what to check when an amount
        // looks wrong.
        if (!empty($d['keys'])) {
            $lines[] = '      option keys : ' . implode(', ', $d['keys']);
        }
        if (!empty($d['valuekeys'])) {
            $lines[] = '      value keys  : ' . implode(', ', $d['valuekeys']);
        }
    }
    $lines[] = sprintf('TOTAL floor : %s /month',
        number_format($p_floor['monthly'], 2, '.', ''));

    return '<pre class="' . esc_attr(trim('whmcs_products_debug ' . $p_class)) . '">'
        . esc_html(implode("\n", $lines)) . '</pre>';
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

    /**
     * A successful fetch is an array: GetProducts() returns the processed
     * array built by _BuildPageInfoArray(). Only a failure comes back as the
     * raw API object, or as null when the call could not be completed.
     */
    if (is_array($p_apiResponse)) {
        return $ans;
    }

    // A null response means the call could not be completed at all. Treating
    // that as success let the caller walk into a missing payload.
    if (!is_object($p_apiResponse)) {

        $ans['success'] = false;
        $ans['msg'] = whmcs_products_func_prepareOutput(
            __('Pricing is temporarily unavailable.', 'whmcs-pi'), '', '', '', true
        );

        return $ans;
    }

    /**
     * "success" with no product means the pid does not exist, or is not
     * visible to these credentials. WHMCS did answer, so this is not a
     * connection problem and must not be phrased as one.
     */
    if (isset($p_apiResponse->result) && $p_apiResponse->result === 'success') {

        $ans['success'] = false;
        $texte = current_user_can('manage_options')
            ? __('WHMCS answered but returned no product for this pid. Check the product id.', 'whmcs-pi')
            : __('Pricing is temporarily unavailable.', 'whmcs-pi');

        $ans['msg'] = whmcs_products_func_prepareOutput($texte, '', '', '', true);

        return $ans;
    }

    // Check for a API call problem
    if (property_exists($p_apiResponse, 'result') && $p_apiResponse->result == 'error') {

        $ans['success'] = false;

        $detail = isset($p_apiResponse->message) ? (string) $p_apiResponse->message : '';

        /**
         * WHMCS phrases its own errors, and that wording can name internal
         * paths or configuration details. This string lands on a public page,
         * so only an administrator sees the detail — everyone else gets a
         * neutral sentence.
         */
        if ($detail !== '' && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[whmcs-pi] WHMCS API error: ' . $detail);
        }

        if ($detail !== '' && current_user_can('manage_options')) {
            /* translators: %s: error message returned by WHMCS */
            $texte = sprintf(__('WHMCS API error: %s', 'whmcs-pi'), $detail);
        } else {
            $texte = __('Pricing is temporarily unavailable.', 'whmcs-pi');
        }

        $ans['msg'] = whmcs_products_func_prepareOutput($texte, '', '', '', true);
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

    /**
     * The class may come from a shortcode attribute, the message and its
     * affixes from WHMCS. Each is escaped for its own context: attribute for
     * the class, wp_kses_post for the message since product descriptions
     * legitimately carry simple markup, plain text for the affixes.
     */
    $classes = implode(' ', array_filter(array_map(
        'sanitize_html_class',
        preg_split('/\s+/', trim((string) $p_class))
    )));

    $class = 'class="' . esc_attr(trim('whmcs_products ' . $classes)) . '"';

    // If there is an error, prepare extra styling
    $style = "";
    if ($p_isError) {
        $style = 'style="color:#721c24;background-color: #f8d7da;border: 1px solid #f5c6cb;padding:2px;position:relative;border-radius: .25rem;"';
    }

    // Prepare the response string
    $response = '<span ' . $class . ' ' . $style . '>'
        . esc_html($p_prefix)
        . wp_kses_post($p_msg)
        . esc_html($p_suffix)
        . '</span>';

    // Return the response
    return $response;
}
