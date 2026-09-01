<?php

/**
 * WHMCS Price Integration — product price block
 *
 * @author            Astral Internet inc.
 * @copyright         Copyright (C) 2021-2026, Astral Internet inc. - support@astralinternet.com
 * @license           http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 *
 * @package           WHMCS_PI
 * @since             1.3.0
 */

// If this file is called directly, abort.
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Register the product price block.
 *
 * Rendered server side, like the domain block: a price baked into saved post
 * content is a price that goes stale silently on every page carrying it.
 *
 * @since 1.3.0
 * @return void
 */
function whmcs_pi_register_product_block()
{
    if (!function_exists('register_block_type')) {
        return;
    }

    register_block_type('whmcs-pi/product-price', array(
        'api_version'     => 3,
        'style'           => 'whmcs-pi-block-style',
        'title'           => __('WHMCS product price', 'whmcs-pi'),
        'description'     => __('Shows the live price of a WHMCS product, options included.', 'whmcs-pi'),
        'category'        => 'widgets',
        'icon'            => 'cart',
        'keywords'        => array(__('price', 'whmcs-pi'), __('product', 'whmcs-pi'), 'whmcs'),
        'editor_script'   => 'whmcs-pi-block',
        'supports'        => whmcs_pi_block_supports(),
        'attributes'      => array(
            'pid'         => array('type' => 'number', 'default' => 0),
            'period'      => array('type' => 'string',  'default' => 'monthly'),
            'showMonthly' => array('type' => 'boolean', 'default' => true),
            'withOptions' => array('type' => 'boolean', 'default' => true),
            'options'     => array('type' => 'string',  'default' => ''),
            'optionsMin'  => array('type' => 'string',  'default' => ''),
            'promoPrice'  => array('type' => 'boolean', 'default' => true),
            'showPeriod'  => array('type' => 'boolean', 'default' => true),
            'showFrom'    => array('type' => 'boolean', 'default' => false),
            'label'       => array('type' => 'string',  'default' => ''),
        ),
        'render_callback' => 'whmcs_pi_render_product_price',
    ));
}

/**
 * Priority 11: the editor script handle is registered by block.php on the
 * default priority. Depending on load order would work today and break the
 * day the files are required in a different sequence.
 */
add_action('init', 'whmcs_pi_register_product_block', 11);

/**
 * Wording for one billing cycle.
 *
 * @since 1.3.0
 * @param string $p_period  Billing cycle
 * @param bool   $p_monthly Whether the figure shown is a monthly equivalent
 * @return string
 */
function whmcs_pi_product_period_label($p_period, $p_monthly)
{
    if ($p_monthly) {
        return __('/month', 'whmcs-pi');
    }

    $labels = array(
        'monthly'      => __('/month', 'whmcs-pi'),
        'quarterly'    => __('/quarter', 'whmcs-pi'),
        'semiannually' => __('/six months', 'whmcs-pi'),
        'annually'     => __('/year', 'whmcs-pi'),
        'biennially'   => __('/two years', 'whmcs-pi'),
        'triennially'  => __('/three years', 'whmcs-pi'),
    );

    return isset($labels[$p_period]) ? $labels[$p_period] : '';
}

/**
 * Render the block.
 *
 * @since 1.3.0
 * @param array $p_attributes Block attributes
 * @return string HTML, empty when no trustworthy price is available
 */
function whmcs_pi_render_product_price($p_attributes)
{
    $pid = isset($p_attributes['pid']) ? (int) $p_attributes['pid'] : 0;

    if ($pid <= 0) {
        return '';
    }

    $period = isset($p_attributes['period']) ? (string) $p_attributes['period'] : 'monthly';
    $months = whmcs_products_func_cycle_months();

    if (!isset($months[$period])) {
        return '';
    }

    $products = WHMCS_PI_Main::load_product_class();
    $detail = $products->GetProducts($pid);

    // Only a success comes back as an array; the staleness rule lives in the class.
    if (!is_array($detail) || !isset($detail['price'])) {
        return '';
    }

    $monthly = !empty($p_attributes['showMonthly']);

    $price = whmcs_products_func_base_price(
        $detail,
        $period,
        !empty($p_attributes['promoPrice']),
        $monthly
    );

    if ($price === null) {
        return '';
    }

    /**
     * The options floor is on by default here, unlike the shortcode.
     *
     * A block dropped on a sales page is quoting what a customer pays, and on
     * a product sold with a mandatory account count or disk allowance the bare
     * product price is not that figure.
     */
    if (!empty($p_attributes['withOptions'])) {

        $declared = array();
        $raw = isset($p_attributes['optionsMin']) ? (string) $p_attributes['optionsMin'] : '';

        foreach (explode(',', $raw) as $pair) {
            $parts = array_map('trim', explode(':', $pair, 2));
            if (count($parts) === 2 && $parts[0] !== '' && is_numeric($parts[1])) {
                $declared[$parts[0]] = $parts[1];
            }
        }

        $floor = whmcs_products_func_options_floor(
            isset($detail['configOptions']) ? $detail['configOptions'] : null,
            isset($p_attributes['options']) ? (string) $p_attributes['options'] : '',
            $period,
            $declared
        );

        $price += $monthly
            ? $floor['monthly']
            : $floor['monthly'] * $months[$period];
    }

    $lines = array();

    /**
     * "From" belongs on a price built from a floor: what is quoted is the
     * cheapest configuration, not the only one.
     */
    if (!empty($p_attributes['showFrom'])) {
        $lines[] = sprintf(
            '<span class="whmcs-pi-price__from">%s</span>',
            esc_html__('from', 'whmcs-pi')
        );
    }

    $lines[] = sprintf(
        '<span class="whmcs-pi-price__amount">%s</span>',
        esc_html(WHMCS_PI_Main::format_currency($price))
    );

    if (!empty($p_attributes['showPeriod'])) {
        $lines[] = sprintf(
            '<span class="whmcs-pi-price__period">%s</span>',
            esc_html(whmcs_pi_product_period_label($period, $monthly))
        );
    }

    $label = isset($p_attributes['label']) ? trim((string) $p_attributes['label']) : '';

    $header = $label === ''
        ? ''
        : sprintf('<p class="whmcs-pi-price__label">%s</p>', esc_html($label));

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(array('class' => 'whmcs-pi-price'))
        : 'class="whmcs-pi-price"';

    return sprintf(
        '<div %s>%s<p class="whmcs-pi-price__body">%s</p></div>',
        $wrapper,
        $header,
        implode(' ', $lines)
    );
}
