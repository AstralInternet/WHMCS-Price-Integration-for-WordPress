<?php

/**
 * WHMCS Price Integration — settings screen
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

// Belt and braces: the menu is already gated on this capability.
if (!current_user_can(WHMCS_PI_Main::CAPABILITY)) {
    wp_die(esc_html__('You do not have permission to access this page.', 'whmcs-pi'));
}

$msg = array();

/**
 * Update the API configuration settings
 *
 * Secret fields left blank keep their stored value, so the form can be saved
 * without ever sending the credentials back down to the browser.
 */
if (isset($_POST['updateconf'])
    && isset($_POST['nonce'])
    && wp_verify_nonce(sanitize_key(wp_unslash($_POST['nonce'])), 'whmcs-pi_update-api-options')) {

    $apiUrl = isset($_POST['whmcs-pi-api-url'])
        ? esc_url_raw(trim(wp_unslash($_POST['whmcs-pi-api-url'])))
        : '';

    $clientUrl = isset($_POST['whmcs-pi-client-url'])
        ? esc_url_raw(trim(wp_unslash($_POST['whmcs-pi-client-url'])))
        : '';

    // Refuse a plain-text endpoint at the door: the API credentials travel in
    // the body of every request to it.
    if ($apiUrl !== '' && stripos($apiUrl, 'https://') !== 0) {

        $msg['txt'] = __('The WHMCS URL must start with https:// — the API credentials travel in the request body.', 'whmcs-pi');
        $msg['type'] = 'error';

    } else {
        update_option('whmcs-pi_api_url', $apiUrl, 'no');
    }

    update_option('whmcs-pi_clientareaurl', $clientUrl, 'no');

    $secrets = array(
        'whmcs-pi_api_id'        => 'whmcs-pi-api-id',
        'whmcs-pi_api_secret'    => 'whmcs-pi-api-secret',
        'whmcs-pi_api_accesskey' => 'whmcs-pi-api-accesskey',
    );

    foreach ($secrets as $option => $field) {

        if (!isset($_POST[$field])) {
            continue;
        }

        $value = sanitize_text_field(wp_unslash($_POST[$field]));

        // Blank means "leave it alone", not "erase it".
        if ($value === '') {
            continue;
        }

        update_option($option, WHMCS_PI_Main::field_encrypt($value), 'no');
    }

    $msg['txt'] = __('API configuration updated.', 'whmcs-pi');
    $msg['type'] = 'success';
}

/**
 * Clear the stored credentials.
 *
 * @since 1.0.0
 */
if (isset($_POST['clearcreds'])
    && isset($_POST['nonce'])
    && wp_verify_nonce(sanitize_key(wp_unslash($_POST['nonce'])), 'whmcs-pi_clear-credentials')) {

    foreach (array('whmcs-pi_api_id', 'whmcs-pi_api_secret', 'whmcs-pi_api_accesskey') as $option) {
        update_option($option, '', 'no');
    }

    $msg['txt'] = __('Stored credentials cleared.', 'whmcs-pi');
    $msg['type'] = 'success';
}

/**
 * Turn one failed API call into wording an administrator can act on.
 *
 * The three answers that matter are not interchangeable: a refused IP, a
 * refused action and an unreachable server each need a different fix, and
 * WHMCS phrases only the first two itself.
 *
 * @since 1.3.0
 * @param whmcsAPI $p_api    The client that made the call
 * @param string   $p_action The API action that was attempted
 * @return string Ready to display
 */
function whmcs_pi_admin_explain_failure($p_api, $p_action)
{
    $reason = $p_api->Get_Last_Error();

    // WHMCS says "Invalid IP" when the calling server is not whitelisted.
    // That is a different fix from a wrong secret, so name it plainly.
    if (stripos($reason, 'Invalid IP') !== false) {
        return __('WHMCS refused this server\'s IP address. Add it to the API credential\'s allowed IPs.', 'whmcs-pi');
    }

    /**
     * A role missing the action arrives either as a JSON error naming the
     * permission or as a bare HTTP 403, which carries no wording of its own.
     */
    if (stripos($reason, 'Invalid Permissions') !== false
        || stripos($reason, 'HTTP 403') !== false
        || stripos($reason, 'HTTP 401') !== false) {

        return sprintf(
            /* translators: 1: API action name, 2: reason returned by WHMCS */
            __('WHMCS refused the action %1$s. Add it to the API role used by these credentials, under Setup > Staff Management > API Roles. Reported: %2$s', 'whmcs-pi'),
            $p_action,
            $reason !== '' ? $reason : __('no detail', 'whmcs-pi')
        );
    }

    if ($reason !== '') {
        return sprintf(
            /* translators: 1: API action name, 2: error message returned by WHMCS */
            __('%1$s failed: %2$s', 'whmcs-pi'),
            $p_action,
            $reason
        );
    }

    /* translators: %s: API action name */
    return sprintf(__('%s failed for an unknown reason.', 'whmcs-pi'), $p_action);
}

/**
 * Test if we can reach the WHMCS API with the provided credentials
 *
 * Domains and products are separate API actions and an API role can carry
 * one without the other, so both are exercised rather than just one.
 *
 * @since 1.0.0
 */
if (isset($_POST['testconnection'])
    && isset($_POST['nonce'])
    && wp_verify_nonce(sanitize_key(wp_unslash($_POST['nonce'])), 'whmcs-pi_testconnection')) {

    $whmcsAPI = new whmcsAPI(
        WHMCS_PI_Main::field_decrypt(get_option('whmcs-pi_api_id')),
        WHMCS_PI_Main::field_decrypt(get_option('whmcs-pi_api_secret')),
        get_option('whmcs-pi_api_url'),
        WHMCS_PI_Main::field_decrypt(get_option('whmcs-pi_api_accesskey'))
    );

    $lines = array();
    $failures = 0;

    // --- domains ---------------------------------------------------------
    $test = $whmcsAPI->Whmcs_API_Call('GetTLDPricing');

    if (whmcsAPI::Is_Successful($test)) {
        $count = isset($test->pricing) ? count((array) $test->pricing) : 0;
        $lines[] = sprintf(
            /* translators: %d: number of TLDs returned by WHMCS */
            __('GetTLDPricing: successful, %d extensions returned.', 'whmcs-pi'),
            $count
        );
    } else {
        $failures++;
        $lines[] = whmcs_pi_admin_explain_failure($whmcsAPI, 'GetTLDPricing');
    }

    // --- products --------------------------------------------------------
    $testProducts = $whmcsAPI->Whmcs_API_Call('GetProducts');

    if (whmcsAPI::Is_Successful($testProducts)) {
        $nb = isset($testProducts->products->product)
            ? count((array) $testProducts->products->product)
            : 0;
        $lines[] = sprintf(
            /* translators: %d: number of products returned by WHMCS */
            __('GetProducts: successful, %d products returned.', 'whmcs-pi'),
            $nb
        );
    } else {
        $failures++;
        $lines[] = whmcs_pi_admin_explain_failure($whmcsAPI, 'GetProducts');
    }

    // --- promotions, used by the product shortcode for promo prices ------
    $testPromotions = $whmcsAPI->Whmcs_API_Call('GetPromotions');

    if (whmcsAPI::Is_Successful($testPromotions)) {
        $lines[] = __('GetPromotions: successful.', 'whmcs-pi');
    } else {
        $failures++;
        $lines[] = whmcs_pi_admin_explain_failure($whmcsAPI, 'GetPromotions');
    }

    $msg['txt'] = implode(' | ', $lines);
    $msg['type'] = $failures ? 'error' : 'success';
}

/**
 * Force a cache refresh from the settings screen.
 *
 * @since 1.0.0
 */
if (isset($_POST['refreshcache'])
    && isset($_POST['nonce'])
    && wp_verify_nonce(sanitize_key(wp_unslash($_POST['nonce'])), 'whmcs-pi_refresh-cache')) {

    $domains = WHMCS_PI_Main::load_domain_class();
    $result = $domains->Get_Whmcs_TLD_List(true);

    if (!empty($result['tlddetail'])) {
        $msg['txt'] = sprintf(
            /* translators: %d: number of TLDs cached */
            __('Cache refreshed — %d extensions stored.', 'whmcs-pi'),
            count($result['tlddetail'])
        );
        $msg['type'] = 'success';
    } else {
        $msg['txt'] = __('Refresh failed. The previous cache was kept.', 'whmcs-pi');
        $msg['type'] = 'error';
    }
}

// ---------------------------------------------------------------------------
// Current state, for the status panel
// ---------------------------------------------------------------------------
$hasId = WHMCS_PI_Main::field_decrypt(get_option('whmcs-pi_api_id')) !== '';
$hasSecret = WHMCS_PI_Main::field_decrypt(get_option('whmcs-pi_api_secret')) !== '';
$cache = get_option(Domains::CACHE_OPTION);
$cacheCount = (is_array($cache) && isset($cache['data']['tlddetail']))
    ? count($cache['data']['tlddetail'])
    : 0;
$cacheAge = (is_array($cache) && !empty($cache['timestamp']))
    ? human_time_diff((int) $cache['timestamp'], time())
    : null;
$nextRun = wp_next_scheduled(WHMCS_PI_Main::CRON_HOOK);

?>

<style>
    /*
The styling has been placed in the main display page to reduce the amount of items being loaded each time the backend pages are loaded
 */
    .whmcs-pi { max-width: 1000px; margin: 0 auto; padding: 0 20px; position: relative }
    .whmcs-pi h1 { font-size: 36px; line-height: 1.1; border-left: 4px solid #ef6c45; font-weight: lighter; padding: 0 0 0 30px }
    .whmcs-pi p { font-size: 14px }
    .whmcs-pi .white_box { background-color: #fff; border: 1px solid #ccc; padding: 20px; margin: 20px 0 }
    .whmcs-pi #whmcs-pi-message { padding: .75rem 1.25rem; font-size: 16px; margin: 20px 0; border-radius: 3px }
    .whmcs-pi .success { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb }
    .whmcs-pi .error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb }
    .whmcs-pi label { display: block; margin-bottom: 14px }
    .whmcs-pi label span { display: inline-block; width: 210px; text-align: right; padding-right: 12px; vertical-align: middle }
    .whmcs-pi label input[type=text], .whmcs-pi label input[type=password] { width: 420px }
    .whmcs-pi .hint { color: #666; font-size: 12px; margin: 4px 0 0 222px }
    .whmcs-pi .status { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px }
    .whmcs-pi .status div { background: #f6f7f7; border: 1px solid #dcdcde; padding: 12px 14px }
    .whmcs-pi .status b { display: block; font-size: 20px; margin-bottom: 2px }
    .whmcs-pi .actions form { display: inline-block; margin-right: 10px }
</style>

<div class="whmcs-pi">

    <h1><?php echo esc_html(WHMCS_PI_NAME); ?></h1>

    <?php if (!empty($msg)) : ?>
        <div id="whmcs-pi-message" class="<?php echo esc_attr($msg['type']); ?>">
            <?php echo esc_html($msg['txt']); ?>
        </div>
    <?php endif; ?>

    <div class="white_box">
        <h2><?php esc_html_e('Status', 'whmcs-pi'); ?></h2>
        <div class="status">
            <div>
                <b><?php echo $hasId && $hasSecret ? esc_html__('Set', 'whmcs-pi') : esc_html__('Missing', 'whmcs-pi'); ?></b>
                <?php esc_html_e('API credentials', 'whmcs-pi'); ?>
            </div>
            <?php if (!WHMCS_PI_Main::has_crypt_key()) : ?>
                <div style="border-color:#d63638;color:#721c24">
                    <b><?php esc_html_e('No key', 'whmcs-pi'); ?></b>
                    <?php esc_html_e('wp-config.php has no usable salt — credentials cannot be stored safely.', 'whmcs-pi'); ?>
                </div>
            <?php endif; ?>
            <div>
                <b><?php echo esc_html((string) $cacheCount); ?></b>
                <?php esc_html_e('extensions cached', 'whmcs-pi'); ?>
            </div>
            <div>
                <b><?php echo $cacheAge ? esc_html($cacheAge) : esc_html__('never', 'whmcs-pi'); ?></b>
                <?php esc_html_e('since last refresh', 'whmcs-pi'); ?>
            </div>
            <div>
                <b><?php echo $nextRun ? esc_html(human_time_diff(time(), $nextRun)) : esc_html__('not scheduled', 'whmcs-pi'); ?></b>
                <?php esc_html_e('until next refresh', 'whmcs-pi'); ?>
            </div>
        </div>
    </div>

    <div class="white_box">
        <h2><?php esc_html_e('API configuration', 'whmcs-pi'); ?></h2>
        <p><?php esc_html_e('Create dedicated API credentials in WHMCS under Configuration > System Settings > API Credentials. Restrict them by IP address and grant only the pricing related roles.', 'whmcs-pi'); ?></p>

        <form method="post">
            <input type="hidden" name="updateconf" value="1">
            <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('whmcs-pi_update-api-options')); ?>">

            <label>
                <span><?php esc_html_e('WHMCS URL', 'whmcs-pi'); ?></span>
                <input type="text" name="whmcs-pi-api-url"
                       value="<?php echo esc_attr(get_option('whmcs-pi_api_url')); ?>"
                       placeholder="https://clients.example.com">
            </label>
            <p class="hint"><?php esc_html_e('Client area root. "/includes/api.php" is appended automatically.', 'whmcs-pi'); ?></p>

            <label>
                <span><?php esc_html_e('Client area URL', 'whmcs-pi'); ?></span>
                <input type="text" name="whmcs-pi-client-url"
                       value="<?php echo esc_attr(get_option('whmcs-pi_clientareaurl')); ?>">
            </label>

            <label>
                <span><?php esc_html_e('API identifier', 'whmcs-pi'); ?></span>
                <input type="password" name="whmcs-pi-api-id" autocomplete="off"
                       placeholder="<?php echo $hasId ? esc_attr__('unchanged', 'whmcs-pi') : ''; ?>">
            </label>

            <label>
                <span><?php esc_html_e('API secret', 'whmcs-pi'); ?></span>
                <input type="password" name="whmcs-pi-api-secret" autocomplete="off"
                       placeholder="<?php echo $hasSecret ? esc_attr__('unchanged', 'whmcs-pi') : ''; ?>">
            </label>

            <label>
                <span><?php esc_html_e('Access key (optional)', 'whmcs-pi'); ?></span>
                <input type="password" name="whmcs-pi-api-accesskey" autocomplete="off">
            </label>
            <p class="hint"><?php esc_html_e('Stored credentials are never sent back to the browser. Leave a field blank to keep its current value.', 'whmcs-pi'); ?></p>

            <p><input type="submit" class="button button-primary" value="<?php esc_attr_e('Save configuration', 'whmcs-pi'); ?>"></p>
        </form>
    </div>

    <div class="white_box actions">
        <h2><?php esc_html_e('Maintenance', 'whmcs-pi'); ?></h2>

        <form method="post">
            <input type="hidden" name="testconnection" value="1">
            <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('whmcs-pi_testconnection')); ?>">
            <input type="submit" class="button" value="<?php esc_attr_e('Test API connection', 'whmcs-pi'); ?>">
        </form>

        <form method="post">
            <input type="hidden" name="refreshcache" value="1">
            <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('whmcs-pi_refresh-cache')); ?>">
            <input type="submit" class="button" value="<?php esc_attr_e('Refresh cache now', 'whmcs-pi'); ?>">
        </form>

        <form method="post" onsubmit="return confirm('<?php echo esc_js(__('Clear the stored API credentials?', 'whmcs-pi')); ?>');">
            <input type="hidden" name="clearcreds" value="1">
            <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('whmcs-pi_clear-credentials')); ?>">
            <input type="submit" class="button" value="<?php esc_attr_e('Clear credentials', 'whmcs-pi'); ?>">
        </form>
    </div>

</div>
