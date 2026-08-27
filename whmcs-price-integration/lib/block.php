<?php

/**
 * WHMCS Price Integration — Gutenberg block
 *
 * @author            Astral Internet inc.
 * @copyright         Copyright (C) 2021-2026, Astral Internet inc. - support@astralinternet.com
 * @license           http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 *
 * @package           WHMCS_PI
 * @since             1.0.0
 */

// If this file is called directly, abort.
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Register the domain price block.
 *
 * Rendered server side so the price is never baked into the saved post
 * content: a price baked into saved markup is a price that goes stale
 * silently, on every page that carries it.
 *
 * @since 1.0.0
 * @return void
 */
function whmcs_pi_register_block()
{
    if (!function_exists('register_block_type')) {
        return;
    }

    wp_register_script(
        'whmcs-pi-block',
        plugins_url('assets/block-editor.js', WHMCS_PI_FILE),
        array(
            'wp-blocks',
            'wp-element',
            'wp-components',
            'wp-block-editor',
            'wp-i18n',
            'wp-server-side-render',
        ),
        WHMCS_PI_VERSION,
        true
    );

    register_block_type('whmcs-pi/domain-price', array(
        'api_version'     => 2,
        'editor_script'   => 'whmcs-pi-block',
        'attributes'      => array(
            'tld'       => array('type' => 'string', 'default' => ''),
            'showRenew' => array('type' => 'boolean', 'default' => true),
            'showPromo' => array('type' => 'boolean', 'default' => true),
            'label'     => array('type' => 'string', 'default' => ''),
        ),
        'render_callback' => 'whmcs_pi_render_domain_price',
    ));
}

add_action('init', 'whmcs_pi_register_block');

/**
 * Resolve which TLD a block instance refers to.
 *
 * When the attribute is left empty the post slug is used. On the
 * post type whose slug is the extension itself, the price can never drift
 * away from the page it sits on.
 *
 * @since 1.0.0
 * @param array $p_attributes Block attributes
 * @return string TLD without its leading dot, empty when undeterminable
 */
function whmcs_pi_resolve_tld($p_attributes)
{
    if (!empty($p_attributes['tld'])) {
        return ltrim(strtolower(trim($p_attributes['tld'])), '.');
    }

    $post = get_post();

    if (!$post) {
        return '';
    }

    return ltrim(strtolower($post->post_name), '.');
}

/**
 * Render the block.
 *
 * @since 1.0.0
 * @param array $p_attributes Block attributes
 * @return string HTML, empty when no trustworthy price is available
 */
function whmcs_pi_render_domain_price($p_attributes)
{
    $tld = whmcs_pi_resolve_tld($p_attributes);

    if ($tld === '') {
        return '';
    }

    $domains = WHMCS_PI_Main::load_domain_class();

    // Nothing at all beats a price that may be a week out of date.
    if ($domains->Is_Cache_Stale()) {
        return '';
    }

    $detail = $domains->Get_TLD_Detail($tld);

    if (!isset($detail['reg_price'])) {
        return '';
    }

    $label = !empty($p_attributes['label'])
        ? $p_attributes['label']
        /* translators: %s: domain extension, e.g. .pizza */
        : sprintf(__('A .%s domain', 'whmcs-pi'), $tld);

    $lines = array();

    $lines[] = sprintf(
        '<span class="whmcs-pi-price__amount">%s</span> <span class="whmcs-pi-price__period">%s</span>',
        esc_html(WHMCS_PI_Main::format_currency($detail['reg_price'])),
        esc_html__('for the first year', 'whmcs-pi')
    );

    // Showing renewal next to the first year is plain honesty on new gTLDs,
    // where the introductory rate is often well below the renewal.
    if (!empty($p_attributes['showRenew']) && isset($detail['renew'])) {
        $lines[] = sprintf(
            '<span class="whmcs-pi-price__renew">%s %s</span>',
            esc_html__('Renewal:', 'whmcs-pi'),
            esc_html(WHMCS_PI_Main::format_currency($detail['renew']))
        );
    }

    if (!empty($p_attributes['showPromo']) && !empty($detail['promo']) && isset($detail['discount_pourc'])) {
        $lines[] = sprintf(
            '<span class="whmcs-pi-price__promo">%s</span>',
            sprintf(
                /* translators: %d: discount percentage */
                esc_html__('Save %d%% the first year', 'whmcs-pi'),
                (int) $detail['discount_pourc']
            )
        );
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(array('class' => 'whmcs-pi-price'))
        : 'class="whmcs-pi-price"';

    return sprintf(
        '<div %s><p class="whmcs-pi-price__label">%s</p><p class="whmcs-pi-price__body">%s</p></div>',
        $wrapper,
        esc_html($label),
        implode(' ', $lines)
    );
}
