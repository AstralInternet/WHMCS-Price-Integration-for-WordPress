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
 * Requires at least:	3.5.0
 * Requires PHP:		5.3
 *
 * 
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
 * Declare the main plugin file, if not alreay declared
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

// Load the WHMCS Product Class
require_once plugin_dir_path(__FILE__) . 'lib/whmcs-domains.class.php';

// Load the WHMCS Domain Class
require_once plugin_dir_path(__FILE__) . 'lib/whmcs-products.class.php';

// Load the shortcode handling
require_once plugin_dir_path(__FILE__) . 'lib/products_shortcode.php';
require_once plugin_dir_path(__FILE__) . 'lib/domains_shortcode.php';

// Set module local setting
WHMCS_PI_Main::set_locale();

// Register the activation hook
register_activation_hook(__FILE__, 'WHMCS_PI_Main::activate');

// Register the uninstall hook
register_uninstall_hook(__FILE__, 'WHMCS_PI_Main::uninstall');

// Add the WHMCS Menu in the dashboard "options" menu
add_action('admin_menu', 'WHMCS_PI_Main::add_tools_menu');