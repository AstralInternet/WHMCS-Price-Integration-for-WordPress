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
 * Description:			Display WHMCS product and domain prices inside WordPress pages, through a Gutenberg block or a shortcode.
 * Version:         	1.3.0
 * Author:				Astral Internet inc.
 * Author URI:			https://www.astralinternet.com/fr
 * License:				GPL v3
 * License URI:			http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: 		whmcs-pi
 * Domain Path:     	/languages
 * Requires at least:	5.8
 * Requires PHP:		7.4
 */

// If this file is called directly, abort.
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Store the plugin name.
 *
 * @since 1.0.0
 */
define('WHMCS_PI_NAME', 'WHMCS Price Integration');

/**
 * Current plugin version, used to trigger the upgrade routine.
 *
 * @since 1.0.0
 */
define('WHMCS_PI_VERSION', '1.3.0');

/**
 * Declare the main plugin file, if not already declared
 *
 * @since 1.0.0
 */
if (!defined('WHMCS_PI_FILE')) {
    define('WHMCS_PI_FILE', __FILE__);
}

/**
 * Include the core plugin class WHMCS_PI_Main
 *
 * @since 1.0.0
 */
require_once plugin_dir_path(__FILE__) . 'lib/main.class.php';

// Load the WHMCS API Class
require_once plugin_dir_path(__FILE__) . 'lib/whmcsAPI_call.class.php';

// Load the WHMCS Domain Class
require_once plugin_dir_path(__FILE__) . 'lib/whmcs-domains.class.php';

// Load the WHMCS Product Class
require_once plugin_dir_path(__FILE__) . 'lib/whmcs-products.class.php';

// Load the shortcode handling
require_once plugin_dir_path(__FILE__) . 'lib/products_shortcode.php';
require_once plugin_dir_path(__FILE__) . 'lib/domains_shortcode.php';

// Load the Gutenberg blocks. The product block reuses the pricing helpers
// defined in products_shortcode.php, required above.
require_once plugin_dir_path(__FILE__) . 'lib/block.php';
require_once plugin_dir_path(__FILE__) . 'lib/product-block.php';

// Set module local setting
WHMCS_PI_Main::set_locale();

// Register the activation hook
register_activation_hook(__FILE__, 'WHMCS_PI_Main::activate');

// Release the scheduled refresh when the plugin is switched off
register_deactivation_hook(__FILE__, 'WHMCS_PI_Main::deactivate');

// Register the uninstall hook
register_uninstall_hook(__FILE__, 'WHMCS_PI_Main::uninstall');

// Add the WHMCS Menu in the dashboard "tools" menu
add_action('admin_menu', 'WHMCS_PI_Main::add_tools_menu');

// Background refresh of the WHMCS caches, so no visitor ever waits on the API
add_action(WHMCS_PI_Main::CRON_HOOK, 'WHMCS_PI_Main::refresh_caches');

/**
 * Run the upgrade routine once per version change.
 *
 * Credentials saved before 1.0.0 use an encryption format that broke roughly
 * one save in six; they are re-encrypted here rather than asking anyone to
 * type them again.
 *
 * @since 1.0.0
 * @return void
 */
function whmcs_pi_maybe_upgrade()
{
    if (get_option('whmcs-pi_version') === WHMCS_PI_VERSION) {
        return;
    }

    WHMCS_PI_Main::migrate_credentials();
    WHMCS_PI_Main::schedule_refresh();

    /**
     * Drop the cached pricing table so it is rebuilt with the current parser.
     * An upgrade that changes how the payload is read must not keep serving
     * data shaped by the previous one.
     */
    delete_option('whmcs-domainsTLD');

    update_option('whmcs-pi_version', WHMCS_PI_VERSION, 'no');
}

add_action('admin_init', 'whmcs_pi_maybe_upgrade');
