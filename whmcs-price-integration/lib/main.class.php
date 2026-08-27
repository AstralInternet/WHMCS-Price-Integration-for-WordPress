<?php

/**
 * WHMCS Price Integration
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


class WHMCS_PI_Main
{

	/**
	 * Capability required to configure the plugin.
	 *
	 * @since 1.0.0
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Cron hook used to refresh the WHMCS cache in the background.
	 *
	 * @since 1.0.0
	 */
	const CRON_HOOK = 'whmcs_pi_refresh_cache';

	/**
	 * Marker prefixed to values encrypted with the current scheme, so old and
	 * new formats can live side by side during the upgrade.
	 *
	 * @since 1.0.0
	 */
	const CRYPT_PREFIX = 'v2';

	/**
	 * Upon plugin activation, create the option entries and schedule the
	 * background refresh.
	 *
	 * @since    1.0.0
	 * @return void
	 */
	public static function activate()
	{
		$defaults = array(
			'whmcs-pi_api_url',
			'whmcs-pi_api_id',
			'whmcs-pi_api_secret',
			'whmcs-pi_api_accesskey',
			'whmcs-pi_clientareaurl',
		);

		foreach ($defaults as $option) {
			if (get_option($option) === false) {
				// These are read on a handful of requests only, never autoload.
				add_option($option, '', '', 'no');
			}
		}

		self::schedule_refresh();
	}

	/**
	 * Remove the scheduled refresh when the plugin is switched off.
	 *
	 * Without this the event stays in the cron table and fires against a plugin
	 * that is no longer loaded.
	 *
	 * @since    1.0.0
	 * @return void
	 */
	public static function deactivate()
	{
		$timestamp = wp_next_scheduled(self::CRON_HOOK);

		while ($timestamp) {
			wp_unschedule_event($timestamp, self::CRON_HOOK);
			$timestamp = wp_next_scheduled(self::CRON_HOOK);
		}
	}

	/**
	 * Register the daily cache refresh if it is not already queued.
	 *
	 * @since    1.0.0
	 * @return void
	 */
	public static function schedule_refresh()
	{
		if (!wp_next_scheduled(self::CRON_HOOK)) {
			wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
		}
	}

	/**
	 * Refresh every cache from WHMCS. Runs from cron, never from a page view.
	 *
	 * Pulling on a schedule is what keeps the front end fast: visitors always
	 * read a warm cache, and a slow WHMCS never becomes a slow page.
	 *
	 * @since    1.0.0
	 * @return void
	 */
	public static function refresh_caches()
	{
		if (!get_option('whmcs-pi_api_url') || !get_option('whmcs-pi_api_id')) {
			// Not configured yet, nothing to pull.
			return;
		}

		$domains = self::load_domain_class();
		$domains->Get_Whmcs_TLD_List(true);
	}

	/**
	 * Register the plugin page in the tools menu of WordPress.
	 *
	 * @since    1.0.0
	 * @return void
	 */
	public static function add_tools_menu()
	{
		add_management_page(
			__('WHMCS Price Integration', 'whmcs-pi'),
			WHMCS_PI_NAME,
			self::CAPABILITY,
			'whmcs-price-integration',
			'WHMCS_PI_Main::render_admin_page'
		);
	}

	/**
	 * Render the settings screen.
	 *
	 * The page slug used to be a file path, which loaded the file directly.
	 * Going through a callback lets us assert the capability here as well,
	 * rather than relying on the menu registration alone.
	 *
	 * @since    1.0.0
	 * @return void
	 */
	public static function render_admin_page()
	{
		if (!current_user_can(self::CAPABILITY)) {
			wp_die(esc_html__('You do not have permission to access this page.', 'whmcs-pi'));
		}

		require_once dirname(WHMCS_PI_FILE) . '/admin/whmcs-pi_admin-display.php';
	}

	/**
	 * Derive the encryption key from the WordPress secret salt.
	 *
	 * The salt lives in wp-config.php, so a database dump on its own does not
	 * expose the stored credentials.
	 *
	 * @since 1.0.0
	 * @return string 32 raw bytes
	 */
	private static function crypt_key()
	{
		$salt = '';

		foreach (array('SECURE_AUTH_SALT', 'AUTH_SALT', 'AUTH_KEY') as $constante) {

			if (!defined($constante)) {
				continue;
			}

			$valeur = (string) constant($constante);

			/**
			 * An empty salt, or the placeholder shipped in wp-config-sample.php,
			 * would derive a key from a publicly known string — the encryption
			 * would look real and protect nothing. Refuse both rather than
			 * pretend.
			 */
			if (strlen($valeur) < 16) {
				continue;
			}

			if (stripos($valeur, 'put your unique phrase here') !== false) {
				continue;
			}

			$salt = $valeur;
			break;
		}

		if ($salt === '') {
			return '';
		}

		return substr(hash('sha256', $salt, true), 0, 32);
	}

	/**
	 * Whether a usable encryption key can be derived.
	 *
	 * Surfaced on the settings screen: without it the plugin refuses to store
	 * credentials at all, and the operator needs to know why.
	 *
	 * @since 1.0.1
	 * @return bool
	 */
	public static function has_crypt_key()
	{
		return self::crypt_key() !== '';
	}

	/**
	 * Encrypt a field before placing it in the database
	 *
	 * The initialisation vector is written *before* the ciphertext, at a fixed
	 * length of 16 bytes. The previous scheme joined the two with a "|", which
	 * broke whenever either half happened to contain that byte — roughly one
	 * save in six, silently.
	 *
	 * @since 1.0.0
	 *
	 * @param string $p_inString String to encrypt
	 * @return string Base64 encoded payload, empty string on failure
	 */
	public static function field_encrypt($p_inString)
	{
		if ($p_inString === null || $p_inString === '') {
			return '';
		}

		$key = self::crypt_key();

		// Never store a secret under a key derived from a known public string.
		if ($key === '') {
			return '';
		}

		$iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
		$encrypted = openssl_encrypt($p_inString, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

		if ($encrypted === false) {
			return '';
		}

		return base64_encode(self::CRYPT_PREFIX . $iv . $encrypted);
	}

	/**
	 * Decrypt a field that was encrypted before being saved
	 *
	 * Reads both the current format and the pre-1.0.0 one. The legacy payload
	 * was "ciphertext | iv" with an IV of exactly 16 bytes, so the separator
	 * always sits at length - 17 — position, not a search, recovers it every
	 * time, including the values that used to fail.
	 *
	 * @since 1.0.0
	 *
	 * @param string $p_inEncryptedString Stored value
	 * @return string Decrypted value, empty string when unreadable
	 */
	public static function field_decrypt($p_inEncryptedString)
	{
		if (empty($p_inEncryptedString)) {
			return '';
		}

		$raw = base64_decode($p_inEncryptedString, true);

		if ($raw === false || strlen($raw) < 18) {
			return '';
		}

		$ivLength = openssl_cipher_iv_length('aes-256-cbc');

		if (substr($raw, 0, strlen(self::CRYPT_PREFIX)) === self::CRYPT_PREFIX) {

			$iv = substr($raw, strlen(self::CRYPT_PREFIX), $ivLength);
			$encrypted = substr($raw, strlen(self::CRYPT_PREFIX) + $ivLength);

		} else {

			// Legacy layout: ciphertext . '|' . iv
			$separator = strlen($raw) - ($ivLength + 1);

			if ($separator < 1 || $raw[$separator] !== '|') {
				return '';
			}

			$iv = substr($raw, $separator + 1);
			$encrypted = substr($raw, 0, $separator);
		}

		if (strlen($iv) !== $ivLength) {
			return '';
		}

		$key = self::crypt_key();

		if ($key === '') {
			return '';
		}

		$decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

		return $decrypted === false ? '' : $decrypted;
	}

	/**
	 * Re-encrypt legacy credentials with the current scheme.
	 *
	 * Runs once on upgrade. Values that cannot be read are left untouched
	 * rather than blanked, so nothing is lost if the salt has changed.
	 *
	 * @since 1.0.0
	 * @return int Number of fields migrated
	 */
	public static function migrate_credentials()
	{
		$fields = array('whmcs-pi_api_id', 'whmcs-pi_api_secret', 'whmcs-pi_api_accesskey');
		$migrated = 0;

		foreach ($fields as $field) {

			$stored = get_option($field);

			if (empty($stored)) {
				continue;
			}

			$raw = base64_decode($stored, true);

			// Already on the current format.
			if ($raw !== false && substr($raw, 0, strlen(self::CRYPT_PREFIX)) === self::CRYPT_PREFIX) {
				continue;
			}

			$plain = self::field_decrypt($stored);

			if ($plain !== '') {
				update_option($field, self::field_encrypt($plain), 'no');
				$migrated++;
			}
		}

		return $migrated;
	}

	/**
	 * Return the currency formatted
	 *
	 * Falls back to a plain format when ext-intl is unavailable, which is not
	 * guaranteed on shared hosting.
	 *
	 * @since 1.0.0
	 *
	 * @param float $p_currency number to format
	 * @return string formatted currency
	 */
	public static function format_currency($p_currency)
	{
		$amount = is_numeric($p_currency) ? (float) $p_currency : 0.0;

		/* translators: locale used to format prices, e.g. en-CA or fr-CA */
		$currencyLocale = __('en-CA', 'whmcs-pi');

		if (class_exists('NumberFormatter')) {
			$fmt = new NumberFormatter($currencyLocale, NumberFormatter::CURRENCY);
			$formatted = $fmt->format($amount);

			if ($formatted !== false) {
				return $formatted;
			}
		}

		return '$' . number_format($amount, 2, '.', ' ');
	}

	/**
	 * Return a domain class object
	 *
	 * @since 1.0.0
	 *
	 * @return Domains
	 */
	public static function load_domain_class()
	{
		return new Domains(
			self::field_decrypt(get_option('whmcs-pi_api_id')),
			self::field_decrypt(get_option('whmcs-pi_api_secret')),
			get_option('whmcs-pi_api_url'),
			self::field_decrypt(get_option('whmcs-pi_api_accesskey'))
		);
	}

	/**
	 * Return a product class object
	 *
	 * @since 1.0.0
	 *
	 * @return Products
	 */
	public static function load_product_class()
	{
		return new Products(
			self::field_decrypt(get_option('whmcs-pi_api_id')),
			self::field_decrypt(get_option('whmcs-pi_api_secret')),
			get_option('whmcs-pi_api_url'),
			self::field_decrypt(get_option('whmcs-pi_api_accesskey'))
		);
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * @since    1.0.0
	 * @return void
	 */
	public static function set_locale()
	{
		add_action('plugins_loaded', 'WHMCS_PI_Main::load_textdomain');
	}

	/**
	 * Load the translation files.
	 *
	 * The header used to advertise /i18n while the .mo files sat in
	 * /languages, so the French catalogue never loaded.
	 *
	 * @since    1.0.0
	 * @return void
	 */
	public static function load_textdomain()
	{
		load_plugin_textdomain(
			'whmcs-pi',
			false,
			dirname(plugin_basename(WHMCS_PI_FILE)) . '/languages'
		);
	}

	/**
	 * Remove the options added by the plugin from the option table.
	 *
	 * @since    1.0.0
	 * @return void
	 */
	public static function uninstall()
	{
		global $wpdb;

		$options = array(
			'whmcs-pi_api_url',
			'whmcs-pi_api_id',
			'whmcs-pi_api_secret',
			'whmcs-pi_api_accesskey',
			'whmcs-pi_clientareaurl',
			'whmcs-pi_version',
			'whmcs-domainsTLD',
			'whmcs-pi_pid-promotion',
		);

		foreach ($options as $option) {
			delete_option($option);
		}

		// Product caches are keyed by product id, so they need a pattern sweep.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like('whmcs-pi_pid-') . '%'
			)
		);

		self::deactivate();
	}
}
