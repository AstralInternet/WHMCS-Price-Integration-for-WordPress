<?php

/**
 * WHMCS Price Integration — inline price
 *
 * @author            Astral Internet inc.
 * @copyright         Copyright (C) 2021-2026, Astral Internet inc. - support@astralinternet.com
 * @license           http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 *
 * A price inside a sentence, rather than a price standing on its own.
 *
 * Gutenberg has no inline block: the inline image every author knows is not a
 * block at all but a rich text format, and a format is what this file backs.
 * The editor stores a marked <span> in the paragraph, and the server rewrites
 * what that span contains on every render.
 *
 * The text left in the saved content is therefore a fallback, not the source
 * of truth. That is deliberate: a visitor arriving while the plugin is off
 * reads the last known figure instead of a hole in the sentence, and the
 * server overwrites it the moment the plugin is back.
 *
 * @package           WHMCS_PI
 * @since             1.4.0
 */

// If this file is called directly, abort.
defined('ABSPATH') or die('No script kiddies please!');

/**
 * The attributes the format writes, without their "data-whmcs-" prefix.
 *
 * Declared once: the KSES allowance and the tag parser both read this list, so
 * a setting cannot be added to one and forgotten in the other.
 *
 * @since 1.4.0
 * @return array
 */
function whmcs_pi_inline_attribute_names()
{
    return array(
        'kind',
        'tld', 'years', 'part',
        'pid', 'period', 'monthly', 'promo', 'promocode', 'options', 'optionids', 'optionsmin',
        'prefix', 'suffix',
    );
}

/**
 * Load the editor script for the inline format.
 *
 * Kept apart from the block script on purpose. A format is registered against
 * wp.richText, which the block script does not depend on, and neither belongs
 * on the front end.
 *
 * @since 1.4.0
 * @return void
 */
function whmcs_pi_inline_enqueue_editor()
{
    wp_enqueue_script(
        'whmcs-pi-inline-format',
        plugins_url('assets/inline-format.js', WHMCS_PI_FILE),
        array(
            'wp-rich-text',
            'wp-element',
            'wp-components',
            'wp-block-editor',
            'wp-i18n',
            'wp-api-fetch',
            'wp-url',
            'wp-data',
        ),
        whmcs_pi_asset_version('assets/inline-format.js'),
        true
    );

    /**
     * Editor strings live in JavaScript, so the .mo catalogues cannot reach
     * them: wp.i18n reads a JSON file of its own.
     */
    if (function_exists('wp_set_script_translations')) {
        wp_set_script_translations(
            'whmcs-pi-inline-format',
            'whmcs-pi',
            plugin_dir_path(WHMCS_PI_FILE) . 'languages'
        );
    }

    /**
     * The stylesheet is registered against the blocks, which a page carrying
     * only an inline price never loads. Asking for it here keeps the figure
     * looking in the editor the way it will look on the page.
     */
    wp_enqueue_style('whmcs-pi-block-style');
}

add_action('enqueue_block_editor_assets', 'whmcs_pi_inline_enqueue_editor');

/**
 * Let the marked span survive KSES.
 *
 * Anyone without the unfiltered_html capability — an Author, or any role at
 * all on multisite — has their post content filtered on save, and KSES keeps
 * no data-* attribute on a span. Without this the format is stripped back to
 * bare text the first time such an author saves, silently: the price stops
 * updating and the sentence still reads.
 *
 * Only the attributes this plugin writes are allowed, and they carry data,
 * never markup or script.
 *
 * @since 1.4.0
 * @param array  $p_tags    Allowed tags and attributes
 * @param string $p_context Context KSES was called for
 * @return array
 */
function whmcs_pi_inline_allow_attributes($p_tags, $p_context)
{
    if ($p_context !== 'post') {
        return $p_tags;
    }

    if (!isset($p_tags['span']) || !is_array($p_tags['span'])) {
        $p_tags['span'] = array();
    }

    $p_tags['span']['class'] = true;

    foreach (whmcs_pi_inline_attribute_names() as $name) {
        $p_tags['span']['data-whmcs-' . $name] = true;
    }

    return $p_tags;
}

add_filter('wp_kses_allowed_html', 'whmcs_pi_inline_allow_attributes', 10, 2);

/**
 * Which extension an inline price refers to.
 *
 * Mirrors the block rule: an empty extension falls back to the slug of the
 * post being rendered, so an extension page keeps its price in step with no
 * manual entry. The post is passed explicitly because the editor preview runs
 * over REST, outside the loop, where the global post does not exist.
 *
 * @since 1.4.0
 * @param string $p_tld    Extension as the author typed it
 * @param int    $p_postId Post to fall back on, 0 for the current one
 * @return string Extension without its leading dot, empty when undeterminable
 */
function whmcs_pi_inline_resolve_tld($p_tld, $p_postId = 0)
{
    $tld = trim((string) $p_tld);

    if ($tld !== '') {
        return ltrim(strtolower($tld), '.');
    }

    $post = $p_postId > 0 ? get_post($p_postId) : get_post();

    if (!$post) {
        return '';
    }

    return ltrim(strtolower($post->post_name), '.');
}

/**
 * Turn raw attributes into the exact set the price resolver expects.
 *
 * Every value here arrives from saved post content or from a REST query, so
 * each is constrained to what it is allowed to be rather than trusted.
 *
 * @since 1.4.0
 * @param array $p_raw    Attributes parsed from the span, or from the request
 * @param int   $p_postId Post to resolve an empty extension against
 * @return array
 */
function whmcs_pi_inline_normalise($p_raw, $p_postId = 0)
{
    $read = function ($key, $default = '') use ($p_raw) {
        return isset($p_raw[$key]) ? (string) $p_raw[$key] : $default;
    };

    /**
     * "0" and "false" both have to read as off. An attribute written as
     * data-whmcs-monthly="0" through FILTER_VALIDATE_BOOLEAN is off; the same
     * string through a bare cast would be on, which is the opposite of what an
     * author who cleared the toggle asked for.
     */
    $flag = function ($key, $default) use ($read) {
        return filter_var($read($key, $default ? '1' : '0'), FILTER_VALIDATE_BOOLEAN);
    };

    $kind = $read('kind') === 'product' ? 'product' : 'domain';

    $period = $read('period', 'monthly');
    $months = whmcs_products_func_cycle_months();

    if (!isset($months[$period])) {
        $period = 'monthly';
    }

    /**
     * An affix is written into an HTML attribute and read back by a scanner
     * that stops at the first ">". Stripping tags keeps a stray angle bracket
     * from cutting the span in half, and an affix was never meant to carry
     * markup anyway.
     */
    $affix = function ($key) use ($read) {
        return trim(wp_strip_all_tags($read($key)));
    };

    return array(
        'kind'       => $kind,
        'tld'        => $kind === 'domain'
            ? whmcs_pi_inline_resolve_tld($read('tld'), $p_postId)
            : '',
        'years'      => max(1, (int) $read('years', '1')),
        'part'       => $read('part') === 'renew' ? 'renew' : 'register',
        'pid'        => (int) $read('pid', '0'),
        'period'     => $period,
        'monthly'    => $flag('monthly', true),

        /**
         * Off unless asked for, like the shortcode. On by default it published
         * whichever promotion WHMCS happened to list first for the product,
         * a private code included.
         */
        'promo'      => $flag('promo', false),
        'promocode'  => trim($read('promocode')),
        'options'    => $flag('options', true),
        'optionids'  => $read('optionids'),
        'optionsmin' => $read('optionsmin'),
        'prefix'     => $affix('prefix'),
        'suffix'     => $affix('suffix'),
    );
}

/**
 * Format one amount the way the blocks do.
 *
 * An affix supplied by the author replaces the locale formatting: supplying
 * one is a statement that the notation is being controlled from the page, so
 * the amount is rendered plainly rather than through NumberFormatter, which
 * would add a second currency mark.
 *
 * @since 1.4.0
 * @param float  $p_amount Amount to render
 * @param string $p_prefix Author supplied prefix
 * @param string $p_suffix Author supplied suffix
 * @return string
 */
function whmcs_pi_inline_format($p_amount, $p_prefix, $p_suffix)
{
    if ($p_prefix !== '' || $p_suffix !== '') {
        return $p_prefix . number_format((float) $p_amount, 2, '.', '') . $p_suffix;
    }

    return WHMCS_PI_Main::format_currency($p_amount);
}

/**
 * The price one inline span stands for.
 *
 * Both branches go through the helpers the shortcode and the blocks already
 * use, so an inline price and a block price on the same page cannot be
 * assembled two different ways and end up disagreeing.
 *
 * Results are memoised for the request: a paragraph nested in a group is
 * filtered once for the paragraph and again for the group, and a page quoting
 * the same product in three sentences asks three times.
 *
 * @since 1.4.0
 * @param array $p_settings Normalised attributes
 * @return string|null Formatted price, null when nothing can be quoted
 */
function whmcs_pi_inline_price($p_settings)
{
    static $memo = array();

    $key = wp_json_encode($p_settings);

    if ($key !== false && array_key_exists($key, $memo)) {
        return $memo[$key];
    }

    $amount = $p_settings['kind'] === 'product'
        ? whmcs_pi_inline_product_price($p_settings)
        : whmcs_pi_inline_domain_price($p_settings);

    $rendered = $amount === null
        ? null
        : whmcs_pi_inline_format($amount, $p_settings['prefix'], $p_settings['suffix']);

    if ($key !== false) {
        $memo[$key] = $rendered;
    }

    return $rendered;
}

/**
 * Amount for a domain inline price.
 *
 * @since 1.4.0
 * @param array $p_settings Normalised attributes
 * @return float|null
 */
function whmcs_pi_inline_domain_price($p_settings)
{
    if ($p_settings['tld'] === '') {
        return null;
    }

    // The staleness rule is enforced inside the class, which knows the age.
    $domains = WHMCS_PI_Main::load_domain_class();
    $pricing = $domains->Get_TLD_Pricing($p_settings['tld'], $p_settings['years']);

    if (empty($pricing)) {
        return null;
    }

    /**
     * Get_TLD_Pricing() quotes one year when the requested length is not sold.
     * The block can absorb that because it words its own period line; a figure
     * dropped into a sentence the author wrote cannot — "three years for $X"
     * would then describe an offer that does not exist. A length that could
     * not be honoured renders nothing instead.
     */
    if ($pricing['years'] !== $p_settings['years']) {
        return null;
    }

    $amount = $p_settings['part'] === 'renew' ? $pricing['renew'] : $pricing['register'];

    return $amount === null ? null : (float) $amount;
}

/**
 * Amount for a product inline price.
 *
 * @since 1.4.0
 * @param array $p_settings Normalised attributes
 * @return float|null
 */
function whmcs_pi_inline_product_price($p_settings)
{
    if ($p_settings['pid'] <= 0) {
        return null;
    }

    $products = WHMCS_PI_Main::load_product_class();

    // A prefix names which promotion to price against; empty lets WHMCS order decide.
    $detail = $products->GetProducts(
        $p_settings['pid'],
        $p_settings['promocode'] === '' ? null : $p_settings['promocode']
    );

    // Only a success comes back as an array; the staleness rule lives in the class.
    if (!is_array($detail) || !isset($detail['price'])) {
        return null;
    }

    $price = whmcs_products_func_base_price(
        $detail,
        $p_settings['period'],
        $p_settings['promo'],
        $p_settings['monthly']
    );

    if ($price === null) {
        return null;
    }

    if (!$p_settings['options']) {
        return $price;
    }

    // "913:10,914:5" becomes array('913' => '10', '914' => '5')
    $declared = array();
    foreach (explode(',', $p_settings['optionsmin']) as $pair) {
        $parts = array_map('trim', explode(':', $pair, 2));
        if (count($parts) === 2 && $parts[0] !== '' && is_numeric($parts[1])) {
            $declared[$parts[0]] = $parts[1];
        }
    }

    $floor = whmcs_products_func_options_floor(
        isset($detail['configOptions']) ? $detail['configOptions'] : null,
        $p_settings['optionids'],
        $p_settings['period'],
        $declared
    );

    $months = whmcs_products_func_cycle_months();

    return $price + ($p_settings['monthly']
        ? $floor['monthly']
        : $floor['monthly'] * $months[$p_settings['period']]);
}

/**
 * Read the data-whmcs-* attributes off one opening tag.
 *
 * @since 1.4.0
 * @param string $p_tag The opening <span ...> tag, angle brackets included
 * @return array Attribute name without its prefix, mapped to its value
 */
function whmcs_pi_inline_parse_attributes($p_tag)
{
    $attributes = array();

    if (!preg_match_all('#\bdata-whmcs-([a-z]+)="([^"]*)"#i', $p_tag, $found, PREG_SET_ORDER)) {
        return $attributes;
    }

    foreach ($found as $one) {
        $attributes[strtolower($one[1])] = html_entity_decode(
            $one[2], ENT_QUOTES, get_bloginfo('charset')
        );
    }

    return $attributes;
}

/**
 * Offset of the </span> that closes the span opened before $p_from.
 *
 * A non-greedy match would stop at the first </span>, which is wrong the
 * moment another format nests inside — applying a text colour to an inline
 * price produces exactly that. Depth is counted instead.
 *
 * The scan assumes no ">" inside an attribute value. Nothing this plugin
 * writes carries one, affixes having been stripped on the way in, and the core
 * formats that can nest here write none either.
 *
 * @since 1.4.0
 * @param string $p_html Markup being rewritten
 * @param int    $p_from Offset of the first character inside the span
 * @return int|null Offset of the matching "<", null when the markup is broken
 */
function whmcs_pi_inline_matching_close($p_html, $p_from)
{
    $depth = 1;
    $cursor = $p_from;

    while (preg_match('#</?span\b#i', $p_html, $found, PREG_OFFSET_CAPTURE, $cursor)) {

        $at = $found[0][1];
        $end = strpos($p_html, '>', $at);

        if ($end === false) {
            return null;
        }

        $cursor = $end + 1;

        /**
         * A trailing slash is not treated as self-closing, because a browser
         * does not treat it that way either: <span/> opens a span. Following
         * the parser the markup will actually meet is what keeps the scan and
         * the rendered page in agreement.
         */
        if ($p_html[$at + 1] === '/') {
            $depth--;

            if ($depth === 0) {
                return $at;
            }
        } else {
            $depth++;
        }
    }

    return null;
}

/**
 * Rewrite every inline price in a fragment of markup.
 *
 * The span itself is left exactly as the editor wrote it — only what it holds
 * is replaced, so the settings survive and the figure is rebuilt on the next
 * load. When no trustworthy price comes back the existing text is kept: an
 * empty gap in the middle of a sentence reads as a bug, the last known figure
 * does not.
 *
 * @since 1.4.0
 * @param string $p_content Markup to rewrite
 * @return string
 */
function whmcs_pi_inline_rewrite($p_content)
{
    if (!is_string($p_content) || strpos($p_content, 'data-whmcs-kind') === false) {
        return $p_content;
    }

    $out = '';
    $offset = 0;
    $styled = false;

    while (preg_match(
        '#<span\b[^>]*\bdata-whmcs-kind="[^"]*"[^>]*>#i',
        $p_content,
        $found,
        PREG_OFFSET_CAPTURE,
        $offset
    )) {

        $tag = $found[0][0];
        $inner = $found[0][1] + strlen($tag);
        $close = whmcs_pi_inline_matching_close($p_content, $inner);

        // Markup this scanner cannot follow is left alone rather than mangled.
        if ($close === null) {
            break;
        }

        $price = whmcs_pi_inline_price(
            whmcs_pi_inline_normalise(whmcs_pi_inline_parse_attributes($tag))
        );

        $out .= substr($p_content, $offset, $inner - $offset);
        $out .= $price === null
            ? substr($p_content, $inner, $close - $inner)
            : esc_html($price);

        $offset = $close;
        $styled = true;
    }

    /**
     * Asked for only once a price has actually been written, so a page with no
     * inline price never carries the stylesheet. Styles enqueued this late are
     * printed in the footer, which is soon enough for a rule that only sets
     * figure spacing.
     */
    if ($styled && !wp_style_is('whmcs-pi-block-style', 'enqueued')) {
        wp_enqueue_style('whmcs-pi-block-style');
    }

    return $out . substr($p_content, $offset);
}

/**
 * render_block rather than the_content alone, so the substitution also reaches
 * template parts, query loops and site editor templates, where the_content
 * never runs. A block nested in a group is then filtered twice, once for
 * itself and once inside its parent — harmless, and the memoised price makes
 * the second pass free.
 *
 * @since 1.4.0
 */
add_filter('render_block', 'whmcs_pi_inline_rewrite', 10, 1);

/**
 * Priority 12 puts this after do_blocks() and do_shortcode(), so a classic
 * editor post — which render_block never sees — is covered too.
 *
 * @since 1.4.0
 */
add_filter('the_content', 'whmcs_pi_inline_rewrite', 12);
add_filter('widget_text_content', 'whmcs_pi_inline_rewrite', 12);

/**
 * Preview endpoint for the editor.
 *
 * The format writes the current price straight into the document text, so the
 * editor needs the same figure the front end will render. Asking the server
 * for it keeps one pricing path rather than reimplementing the rules in
 * JavaScript, where they would drift.
 *
 * @since 1.4.0
 * @return void
 */
function whmcs_pi_inline_register_rest()
{
    register_rest_route('whmcs-pi/v1', '/price', array(
        'methods'  => 'GET',
        'callback' => 'whmcs_pi_inline_rest_price',

        /**
         * Whoever can write a post can already place the block that quotes the
         * same figure, so this exposes nothing new. It is not public either:
         * the endpoint reaches WHMCS data, which has no business being
         * readable by an anonymous request.
         */
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },

        'args' => array(
            'post' => array(
                'type'              => 'integer',
                'default'           => 0,
                'sanitize_callback' => 'absint',
            ),
        ),
    ));
}

add_action('rest_api_init', 'whmcs_pi_inline_register_rest');

/**
 * Answer the preview endpoint.
 *
 * @since 1.4.0
 * @param WP_REST_Request $p_request Incoming request
 * @return WP_REST_Response
 */
function whmcs_pi_inline_rest_price($p_request)
{
    $raw = array();

    foreach (whmcs_pi_inline_attribute_names() as $name) {
        $value = $p_request->get_param($name);

        if ($value !== null) {
            $raw[$name] = is_scalar($value) ? (string) $value : '';
        }
    }

    $settings = whmcs_pi_inline_normalise($raw, (int) $p_request->get_param('post'));
    $price = whmcs_pi_inline_price($settings);

    /**
     * "No price" is a legitimate answer, not a failure: an extension WHMCS
     * does not sell, a cycle a product is not offered on, a cache that has
     * gone stale. The editor says so in its own words rather than surfacing
     * an HTTP error the author cannot act on.
     */
    return rest_ensure_response(array(
        'price' => $price === null ? '' : $price,
    ));
}
