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


/**
 * Class that handles the domains pricing coming from WHMCS.
 */
class Domains extends whmcsAPI
{

	/**
	 * Option holding the cached TLD table.
	 *
	 * @since 1.0.0
	 */
	const CACHE_OPTION = 'whmcs-domainsTLD';

	/**
	 * How long the cache stays warm. Refreshed daily by cron; a page view only
	 * pulls from WHMCS when the cache is missing entirely.
	 *
	 * Domain price lists change once or twice a year — the previous one hour
	 * window meant twenty-four needless API calls a day.
	 *
	 * @since 1.0.0
	 */
	const CACHE_TTL = DAY_IN_SECONDS;

	/**
	 * Past this age the cached prices are considered untrustworthy and the
	 * shortcodes render nothing. Showing no price beats showing a wrong one.
	 *
	 * @since 1.0.0
	 */
	const CACHE_MAX_AGE = 7 * DAY_IN_SECONDS;

	/**
	 * Back-off applied after a failed call, so an unreachable WHMCS is not
	 * retried on every single page view. Without it a WHMCS outage turns into
	 * a ten second wait on every page that displays a price.
	 *
	 * @since 1.0.1
	 */
	const FAILURE_BACKOFF = 15 * MINUTE_IN_SECONDS;

	/**
	 * Transient guarding the back-off window.
	 *
	 * @since 1.0.1
	 */
	const BACKOFF_KEY = 'whmcs_pi_api_backoff';

	/**
	 * Constructor
	 * @param string $p_apiID Id used to login to the API
	 * @param string $p_apiSecret Secret used to login to the API
	 * @param string $p_apiUrl Url to connect to the API
	 * @param string $p_apiKey Key used to connect to the API
	 */
	public function __construct($p_apiID, $p_apiSecret, $p_apiUrl, $p_apiKey)
	{
		parent::__construct($p_apiID, $p_apiSecret, $p_apiUrl, $p_apiKey);
	}

	/**
	 * function to return TLD categories
	 *
	 * @param boolean $p_forceNew Force a new API query
	 * @return array the TLD categories, empty when unavailable
	 */
	public function Get_TLD_Categories($p_forceNew = false)
	{
		$tldsInfo = $this->Get_Whmcs_TLD_List($p_forceNew);

		return isset($tldsInfo['categories']) ? $tldsInfo['categories'] : array();
	}

	/**
	 * function to return TLD detailed information
	 *
	 * @param string $p_tld the TLD to retrieve the info for
	 * @param boolean $p_forceNew Force a new API query
	 * @return array the TLD information, empty array when unknown
	 */
	public function Get_TLD_Detail($p_tld, $p_forceNew = false)
	{
		$tld = ltrim(strtolower(trim((string) $p_tld)), '.');

		if ($tld === '') {
			return array();
		}

		$tldsInfo = $this->Get_Whmcs_TLD_List($p_forceNew);

		if (!isset($tldsInfo['tlddetail']) || !array_key_exists($tld, $tldsInfo['tlddetail'])) {
			return array();
		}

		return $tldsInfo['tlddetail'][$tld];
	}

	/**
	 * Age of the cached table, in seconds.
	 *
	 * @since 1.0.0
	 * @return float|null Null when nothing has ever been cached.
	 */
	public function Get_Cache_Age()
	{
		$cache = get_option(self::CACHE_OPTION);

		if (!is_array($cache) || empty($cache['timestamp'])) {
			return null;
		}

		return microtime(true) - $cache['timestamp'];
	}

	/**
	 * Whether the cache is too old to be shown to a visitor.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function Is_Cache_Stale()
	{
		$age = $this->Get_Cache_Age();

		return $age === null || $age > self::CACHE_MAX_AGE;
	}

	/**
	 * Return the complete list of TLDs, from cache or from the API.
	 *
	 * A failed API call never overwrites the cache. The previous version wrote
	 * the empty result straight over the good data, so a single network hiccup
	 * wiped every price on the site until the next successful call.
	 *
	 * @param boolean $p_forceNew Skip the cache and pull from WHMCS
	 * @return array Array containing all the information about all the TLDs
	 */
	public function Get_Whmcs_TLD_List($p_forceNew = false)
	{
		$cache = get_option(self::CACHE_OPTION);
		$cached = (is_array($cache) && isset($cache['data']) && is_array($cache['data']))
			? $cache['data']
			: null;

		if (!$p_forceNew && $cached !== null) {

			$age = microtime(true) - (isset($cache['timestamp']) ? $cache['timestamp'] : 0);

			if ($age <= self::CACHE_TTL) {
				return $cached;
			}
		}

		/**
		 * A recent failure means WHMCS is down or slow. Serving the stale cache
		 * beats making every visitor wait for the same timeout.
		 */
		if (!$p_forceNew && get_transient(self::BACKOFF_KEY)) {
			return $cached !== null ? $cached : array('categories' => array(), 'tlddetail' => array());
		}

		$response = $this->Whmcs_API_Call('GetTLDPricing');

		// Anything short of a usable payload: keep what we already have.
		if (!self::Is_Successful($response) || !isset($response->pricing)) {

			set_transient(self::BACKOFF_KEY, 1, self::FAILURE_BACKOFF);

			if ($cached !== null) {
				return $cached;
			}

			return array('categories' => array(), 'tlddetail' => array());
		}

		$parsed = $this->_ParsePricing($response->pricing);

		// An empty parse means WHMCS answered but returned no priced TLD. That
		// is almost always a configuration slip rather than a real catalogue
		// change, so the existing cache wins.
		if (empty($parsed['tlddetail']) && $cached !== null) {
			return $cached;
		}

		// A good answer clears any pending back-off.
		delete_transient(self::BACKOFF_KEY);

		update_option(
			self::CACHE_OPTION,
			array('timestamp' => microtime(true), 'data' => $parsed),
			'no' // never autoload: this table holds several hundred TLDs
		);

		return $parsed;
	}

	/**
	 * Turn the WHMCS pricing payload into the array the shortcodes expect.
	 *
	 * @since 1.0.0
	 * @param object $p_pricing The "pricing" node of a GetTLDPricing response
	 * @return array
	 */
	private function _ParsePricing($p_pricing)
	{
		$whmcsTLD = array('categories' => array(), 'tlddetail' => array());

		foreach ($p_pricing as $tld => $details) {

			if (!isset($details->register->{'1'}) || !($details->register->{'1'} > 0)) {
				continue;
			}

			$key = ltrim(strtolower((string) $tld), '.');

			$register = (float) $details->register->{'1'};
			$renew = isset($details->renew->{'1'}) ? (float) $details->renew->{'1'} : $register;
			/**
			 * Sanitise on the way in rather than at every display site. WHMCS is a
			 * separate system with its own operators; treating its output as
			 * untrusted text here protects every consumer at once, including any
			 * future one.
			 */
			$categories = isset($details->categories) ? (array) $details->categories : array();
			$categories = array_values(array_filter(array_map(
				function ($c) { return sanitize_text_field((string) $c); },
				$categories
			)));

			$group = isset($details->group) ? sanitize_text_field((string) $details->group) : '';

			$entry = array(
				'reg_price'  => $register,
				'renew'      => $renew,
				'categories' => $categories,
				'flag'       => $group,
				'promo'      => 0,
			);

			// First year cheaper than renewal means a promotional rate.
			if ($register < $renew) {

				$entry['promo'] = 1;
				$entry['discount_amount'] = $renew - $register;

				if ($renew > 0) {
					$entry['discount_pourc'] = (int) round((1 - ($register / $renew)) * 100, 0);
				}
			}

			$whmcsTLD['tlddetail'][$key] = $entry;
			$whmcsTLD['categories']['all'][] = $key;

			foreach ($categories as $category) {
				$whmcsTLD['categories'][$category][] = $key;
			}

			if ($group !== '') {
				$whmcsTLD['categories'][$group][] = $key;
			}
		}

		return $whmcsTLD;
	}
}
