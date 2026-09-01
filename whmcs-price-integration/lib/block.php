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
 * Cache-busting version for one plugin asset.
 *
 * The plugin version alone is not enough: an asset edited between two releases
 * keeps the same URL, so browsers hold the old file. That is not theoretical —
 * a block added to the editor script after the version had already been bumped
 * stayed invisible in the editor while rendering perfectly on the front end,
 * because the browser was still running the previous script.
 *
 * The file modification time is appended, so the URL changes whenever the file
 * does and never otherwise.
 *
 * @since 1.3.0
 * @param string $p_relative Path of the asset inside the plugin folder
 * @return string Version string for wp_register_script() or wp_register_style()
 */
function whmcs_pi_asset_version($p_relative)
{
    $file = plugin_dir_path(WHMCS_PI_FILE) . $p_relative;

    if (!file_exists($file)) {
        return WHMCS_PI_VERSION;
    }

    return WHMCS_PI_VERSION . '.' . filemtime($file);
}

/**
 * Editor controls both price blocks expose.
 *
 * Declaring them is all it takes: WordPress renders the panels and
 * get_block_wrapper_attributes(), which both render callbacks already use,
 * applies the resulting classes and inline styles. Without this the blocks
 * were the only elements on a page an author could not space or restyle.
 *
 * Kept in one function so the two blocks cannot drift apart.
 *
 * @since 1.3.0
 * @return array
 */
function whmcs_pi_block_supports()
{
    return array(

        // No raw HTML editing: the markup is rebuilt server side on every load.
        'html' => false,

        'spacing' => array(
            'margin'                        => true,
            'padding'                       => true,
            '__experimentalDefaultControls' => array('margin' => true),
        ),

        /**
         * Sizes inside the block are all in em, so setting the block font size
         * scales the amount, its period and the notes together rather than
         * knocking them out of proportion.
         */
        'typography' => array(
            'fontSize'                      => true,
            'lineHeight'                    => true,
            'textAlign'                     => true,
            '__experimentalFontFamily'      => true,
            '__experimentalFontWeight'      => true,
            '__experimentalFontStyle'       => true,
            '__experimentalTextTransform'   => true,
            '__experimentalLetterSpacing'   => true,
            '__experimentalDefaultControls' => array('fontSize' => true),
        ),

        // The stylesheet sets no colour of its own, so both inherit cleanly.
        'color' => array(
            'text'       => true,
            'background' => true,
            'gradients'  => true,
            'link'       => false,
        ),

        '__experimentalBorder' => array(
            'color'  => true,
            'radius' => true,
            'style'  => true,
            'width'  => true,
        ),
    );
}

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
        whmcs_pi_asset_version('assets/block-editor.js'),
        true
    );

    /**
     * Editor strings live in JavaScript, so the .mo catalogues cannot reach
     * them: wp.i18n reads a JSON file of its own. Without this call every
     * label in the block inspector stays in English however complete the
     * catalogues are.
     */
    if (function_exists('wp_set_script_translations')) {
        wp_set_script_translations(
            'whmcs-pi-block',
            'whmcs-pi',
            plugin_dir_path(WHMCS_PI_FILE) . 'languages'
        );
    }

    /**
     * Colour-free stylesheet: the block inherits the surrounding text colour,
     * so it stays legible whether it sits on a white page or inside a coloured
     * call to action.
     */
    wp_register_style(
        'whmcs-pi-block-style',
        plugins_url('assets/block.css', WHMCS_PI_FILE),
        array(),
        whmcs_pi_asset_version('assets/block.css')
    );

    register_block_type('whmcs-pi/domain-price', array(
        'api_version'     => 3,
        'style'           => 'whmcs-pi-block-style',
        'title'           => __('WHMCS domain price', 'whmcs-pi'),
        'description'     => __('Shows the live registration and renewal price for a domain extension.', 'whmcs-pi'),
        'category'        => 'widgets',
        'icon'            => 'tag',
        'keywords'        => array(__('price', 'whmcs-pi'), __('domain', 'whmcs-pi'), 'whmcs'),
        'editor_script'   => 'whmcs-pi-block',
        'supports'        => whmcs_pi_block_supports(),
        'attributes'      => array(
            'tld'       => array('type' => 'string', 'default' => ''),
            'years'     => array('type' => 'number', 'default' => 1),
            'showRenew' => array('type' => 'boolean', 'default' => true),
            'showPromo' => array('type' => 'boolean', 'default' => true),
            'label'     => array('type' => 'string', 'default' => ''),
            'showLabel' => array('type' => 'boolean', 'default' => false),
        ),
        'render_callback' => 'whmcs_pi_render_domain_price',
    ));
}

add_action('init', 'whmcs_pi_register_block');

/**
 * Resolve which TLD a block instance refers to.
 *
 * When the attribute is left empty the post slug is used. On a post type whose
 * slug is the extension itself, the price can never drift away from the page it
 * sits on.
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

    // The staleness rule is enforced inside the class, which knows the age.
    $domains = WHMCS_PI_Main::load_domain_class();

    $years = isset($p_attributes['years']) ? (int) $p_attributes['years'] : 1;
    $pricing = $domains->Get_TLD_Pricing($tld, $years);

    if (empty($pricing)) {
        return '';
    }

    /**
     * Always label the figure actually shown. When the requested length is not
     * sold, Get_TLD_Pricing() quotes one year and says so — the wording below
     * follows the returned length, never the requested one.
     */
    $years = $pricing['years'];

    $period = $years === 1
        ? __('for the first year', 'whmcs-pi')
        /* translators: %d: number of years */
        : sprintf(__('for %d years', 'whmcs-pi'), $years);

    /**
     * The generated label repeats whatever heading sits above the block, so it
     * is off by default. A label typed by the author is always honoured.
     */
    if (!empty($p_attributes['label'])) {
        $label = $p_attributes['label'];
    } elseif (!empty($p_attributes['showLabel'])) {
        /* translators: %s: domain extension, e.g. .pizza */
        $label = sprintf(__('A .%s domain', 'whmcs-pi'), $tld);
    } else {
        $label = '';
    }

    $lines = array();

    $lines[] = sprintf(
        '<span class="whmcs-pi-price__amount">%s</span> <span class="whmcs-pi-price__period">%s</span>',
        esc_html(WHMCS_PI_Main::format_currency($pricing['register'])),
        esc_html($period)
    );

    /**
     * Showing renewal next to the first year is plain honesty on new gTLDs,
     * where the introductory rate is often well below the renewal. When the two
     * match, repeating the figure adds nothing — but saying so does, because
     * "no increase at renewal" is the reassuring half of the same fact.
     */
    if (!empty($p_attributes['showRenew']) && $pricing['renew'] !== null) {

        $identical = abs($pricing['renew'] - $pricing['register']) < 0.005;

        $lines[] = sprintf(
            '<span class="whmcs-pi-price__renew">%s</span>',
            $identical
                ? esc_html__('Renews at the same price', 'whmcs-pi')
                : esc_html(sprintf(
                    /* translators: %s: formatted renewal price */
                    __('Renewal: %s', 'whmcs-pi'),
                    WHMCS_PI_Main::format_currency($pricing['renew'])
                ))
        );
    }

    if (!empty($p_attributes['showPromo']) && $pricing['renew'] !== null
        && $pricing['register'] < $pricing['renew']) {

        $discount = (int) round((1 - ($pricing['register'] / $pricing['renew'])) * 100, 0);

        if ($discount > 0) {

            /**
             * The saving applies to the length being quoted. Saying "the first
             * year" under a three year price would describe a different offer
             * from the one shown.
             */
            $saving = $years === 1
                /* translators: %d: discount percentage */
                ? sprintf(esc_html__('Save %d%% the first year', 'whmcs-pi'), $discount)
                : sprintf(
                    /* translators: 1: discount percentage, 2: number of years */
                    esc_html__('Save %1$d%% over %2$d years', 'whmcs-pi'),
                    $discount,
                    $years
                );

            $lines[] = sprintf('<span class="whmcs-pi-price__promo">%s</span>', $saving);
        }
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(array('class' => 'whmcs-pi-price'))
        : 'class="whmcs-pi-price"';

    $entete = $label === ''
        ? ''
        : sprintf('<p class="whmcs-pi-price__label">%s</p>', esc_html($label));

    return sprintf(
        '<div %s>%s<p class="whmcs-pi-price__body">%s</p></div>',
        $wrapper,
        $entete,
        implode(' ', $lines)
    );
}
