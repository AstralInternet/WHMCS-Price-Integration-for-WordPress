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
 * Update the API configuration settings
 */
if ( isset($_REQUEST['updateconf']) && isset($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], 'whmcs-pi_update-api-options') ) {
    /**
     * Update the value in the WP options.
     * Value do not need to be cleaned since the "update_options" already prevent from SQL injections
     */
    update_option('whmcs-pi_api_url', $_REQUEST['whmcs-pi-api-url']);
    update_option('whmcs-pi_api_id', WHMCS_PI_Main::field_encrypt($_REQUEST['whmcs-pi-api-id']));
    update_option('whmcs-pi_api_secret', WHMCS_PI_Main::field_encrypt($_REQUEST['whmcs-pi-api-secret']));
    update_option('whmcs-pi_api_accesskey', WHMCS_PI_Main::field_encrypt($_REQUEST['whmcs-pi-api-accesskey']));

    // Send a notice that the options has been updated
    $msg['txt'] = __("API Configuration updated", "whmcs-pi");
    $msg['type'] = 'success';
} 

/**
 * Test if we can access the WHMCS API with the provided credential
 * 
 * @since 1.0.0
 */
if ( isset($_REQUEST['testconnection']) && isset($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], 'whmcs-pi_testconnection') ) {

    /**
     * Load the WHMCS API CLASS
     *
     * @since 1.0.0
     */
    require_once dirname( WHMCS_PI_FILE ) . '/lib/whmcsAPI_call.class.php';
    require_once dirname( WHMCS_PI_FILE ) . '/lib/whmcs-products.class.php';


    // Create a new WHMCS API object to test the connection 
    $whmcsAPI = new whmcsAPI(WHMCS_PI_Main::field_decrypt(get_option('whmcs-pi_api_id')), WHMCS_PI_Main::field_decrypt(get_option('whmcs-pi_api_secret')), get_option('whmcs-pi_api_url'), WHMCS_PI_Main::field_decrypt(get_option('whmcs-pi_api_accesskey')));

    // Create a WHMCS API call to fetch all the domain informatin
    $whmcsTldTestCall = $whmcsAPI->Whmcs_API_Call('GetTLDPricing');

    // Check if the return result is a WHMCS format
    if (property_exists($whmcsTldTestCall, 'result')) {

        // REturn positive message once connection is confirmed
        if ($whmcsTldTestCall->result == 'success') {
            $msg['txt'] = 'WHMCS API is properly configured';
            $msg['type'] = 'success';  
        } else {

            // Different error message if the error is caused for having the wrong credential or
            // having invalid API permission
            if (strpos($whmcsTldTestCall->message, 'Invalid IP') === false) {
                // Return error, invalid credential 
                $msg['txt'] = "Invalid WHMCS API credential";
                $msg['txt'] .= '<pre>'.print_r($whmcsTldTestCall, true).'</pre>';
                $msg['type'] = 'error';
            } else {
                // Return error, invalid access
                // TODO : transfer validation out of this file and check each require permission.
                $msg['txt'] = "Invalid WHMCS API permission";
                $msg['txt'] .= '<pre>'.print_r($whmcsTldTestCall, true).'</pre>';
                $msg['type'] = 'error';
            }
        }
    } else {

        // Return error, wrong WHMCS json format
        $msg['txt'] = "Invalid WHMCS API answerConfiguration";
        $msg['txt'] .= '<pre>Check API URL</pre>';
        $msg['type'] = 'error';
    }
} 

?>

<style>
/* 
The styling has been place in the main display page to reduce the amount of items being loaded each time the backend pages are loaded
 */
.whmcs-pi {max-width: 1000px; margin: 0 auto;transition: all ease 0.3s;padding:0 20px; position:relative}
.whmcs-pi .success {color: #155724; background-color: #b6ecc3; border:1px solid #c3e6cb}
.whmcs-pi #whmcs-pi-message {padding: .75rem 1.25rem; font-size: 20px; text-align: center; display:flex;position:relative;flex-direction: column;}
.whmcs-pi h1 {font-size: 36px;line-height: 1.1; border-left: 4px solid #ef6c45;font-weight: lighter;padding: 0 0 0 50px;}
.whmcs-pi p {text-align:justify; font-size: 14px;}
.whmcs-pi .flex_base {display:flex;justify-content:flex-start;align-items:center;}
.whmcs-pi .white_box {background-color: #fff; border: 1px solid #ccc; padding: 15px;}
.whmcs-pi .white_box {display: flex; flex-direction: column; align-items: center; margin: 20px 0;}
.whmcs-pi .options_grp {width: 90%; text-align:left; padding:10px 0; align-items: baseline;}
.whmcs-pi .options_grp .config_form {min-width: 50px;}
.whmcs-pi .options_grp .config_form input {width: 400px;}
.whmcs-pi .options_grp .config_form label {margin-bottom: 1em;display: block;}
.whmcs-pi .options_grp .config_form span {display: inline-block;width: 200px;text-align: right;}
#whmcs-pi-close {position: absolute; top: 2px; right: 2px; height: 18px; width: 18px; z-index: 4; cursor: pointer; font-size: 16px;}
#whmcs-pi-close:hover { background-color: #b0d2a8;}

</style>

<div class="whmcs-pi">
    <div class="flex_base" style="justify-content:space-between">
        <h1><?=__("WHMCS Price Integration", "whmcs-pi");?></h1>
    </div>

    <?php
    /**
     * Display a notice message
     */
    if (isset($msg)) {
    ?>
    <div id="whmcs-pi-message" class="<?=$msg['type']?>">
        <div id="whmcs-pi-close" onClick="removeDiv()">&#x274E;</div>
        <div style="display:block;z-index:2;">
            <?=$msg['txt']?>
        </div>
    </div>
    <?php 
    }

/**
 * Start the display for the configuration options for the WHMCS API
 */
    ?>
    <div class="white_box">
        <h2><?=__("Module Configuration", "whmcs-pi");?></h2>
        <p><?=__("Enter your WHMCS API information below", "whmcs-pi");?></p>

        <div class="options_grp flex_base">
            <div class="config_form">
                <form method="post">
                    <input type="hidden" name="updateconf" value="1">
                    <input type="hidden" name="nonce" value="<?=wp_create_nonce('whmcs-pi_update-api-options')?>">
                    <label for="whmcs-pi-api-url">
                        <span><?_e("WHMCS API URL address :", "whmcs-pi");?></span>
                        <input type="text" name="whmcs-pi-api-url" value="<?=get_option('whmcs-pi_api_url')?>"> 
                    </label>
                    <label for="whmcs-pi-api-id">
                    <span><?_e("WHMCS API ID :", "whmcs-pi");?></span>
                        <input type="text" name="whmcs-pi-api-id" value="<?=WHMCS_PI_Main::field_decrypt(get_option('whmcs-pi_api_id'))?>"> 
                    </label>
                    <label for="whmcs-pi-api-secret">
                    <span><?_e("WHMCS API secret key:", "whmcs-pi");?></span>
                        <input type="text" name="whmcs-pi-api-secret" value="<?=WHMCS_PI_Main::field_decrypt(get_option('whmcs-pi_api_secret'))?>"> 
                    </label>
                    <label for="whmcs-pi-api-accesskey">
                    <span><?_e("WHMCS API access key:", "whmcs-pi");?></span>
                        <input type="text" name="whmcs-pi-api-accesskey" value="<?=WHMCS_PI_Main::field_decrypt(get_option('whmcs-pi_api_accesskey'))?>"> 
                    </label>
                    <input type="submit" value="<?_e("Save Configuration", "whmcs-pi");?>">
                </form>
            </div>
        </div>
    </div>
    <?php
    /**
     * Start the display for the test button
     */
    ?>
    <div class="white_box">
        <h2><?=__("Test the API connection", "whmcs-pi");?></h2>

        <div class="options_grp flex_base">
            <div class="config_form">
                <form method="post">
                    <input type="hidden" name="testconnection" value="1">
                    <input type="hidden" name="nonce" value="<?=wp_create_nonce('whmcs-pi_testconnection')?>">
                    <input type="submit" value="<?_e("Test API connection", "whmcs-pi");?>">
                </form>
            </div>
        </div>
    </div>  


</div>
<script> 

// Fade the "success" message once the cache has been purged.
var popupBloc = document.getElementById("wsa-message");
var popupMessage = document.getElementById("wsa-progress");
var popupMessageSize = popupBloc.offsetWidth - 4;
popupMessage.style.borderRightWidth = popupMessageSize + "px";

function removeDiv(){
    jQuery('#wsa-message').remove();
}
</script>