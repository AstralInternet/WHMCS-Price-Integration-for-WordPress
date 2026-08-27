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

		$age = ($cached !== null && !empty($cache['timestamp']))
			? microtime(true) - $cache['timestamp']
			: null;

		// Warm cache: serve it and touch nothing.
		if (!$p_forceNew && $age !== null && $age <= self::CACHE_TTL) {
			return $cached;
		}

		/**
		 * A recent failure means WHMCS is down or slow. Serving what we have
		 * beats making every visitor wait for the same timeout.
		 */
		if (!$p_forceNew && get_transient(self::BACKOFF_KEY)) {
			return self::_ServeCached($cached, $age);
		}

		$response = $this->Whmcs_API_Call('GetTLDPricing');

		// Anything short of a usable payload: keep what we already have.
		if (!self::Is_Successful($response) || !isset($response->pricing)) {

			set_transient(self::BACKOFF_KEY, 1, self::FAILURE_BACKOFF);

			return self::_ServeCached($cached, $age);
		}

		$parsed = $this->_ParsePricing($response->pricing);

		// An empty parse means WHMCS answered but returned no priced TLD. That
		// is almost always a configuration slip rather than a real catalogue
		// change, so the existing cache wins.
		if (empty($parsed['tlddetail']) && $cached !== null) {
			return self::_ServeCached($cached, $age);
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
	 * Collect every registration length WHMCS quotes for one extension.
	 *
	 * Returns [years => ['register' => float, 'renew' => float]], ordered by
	 * length. Zero and empty amounts are dropped: WHMCS uses them to mean "not
	 * offered", and a zero would otherwise reach a page as a free domain.
	 *
	 * @since 1.1.0
	 * @param object $p_details One entry of the GetTLDPricing payload
	 * @return array
	 */
	private static function _ReadPeriods($p_details)
	{
		$periods = array();

		foreach (array('register', 'renew') as $kind) {

			if (!isset($p_details->$kind)) {
				continue;
			}

			foreach ((array) $p_details->$kind as $years => $amount) {

				if (!ctype_digit((string) $years) || $amount === '' || $amount === null) {
					continue;
				}

				$amount = (float) $amount;

				if ($amount <= 0) {
					continue;
				}

				$periods[(int) $years][$kind] = $amount;
			}
		}

		ksort($periods);

		return $periods;
	}

	/**
	 * Price for one extension over a given number of years.
	 *
	 * When the requested length is not sold, this falls back to one year — and
	 * says so through the returned 'years', so the caller always labels the
	 * figure it actually shows.
	 *
	 * @since 1.1.0
	 * @param string $p_tld Extension, with or without its leading dot
	 * @param int $p_years Requested registration length
	 * @return array Empty when nothing can be quoted
	 */
	public function Get_TLD_Pricing($p_tld, $p_years = 1)
	{
		$detail = $this->Get_TLD_Detail($p_tld);

		if (empty($detail['periods'])) {
			return array();
		}

		$years = max(1, (int) $p_years);

		if (!isset($detail['periods'][$years]['register'])) {
			$years = 1;
		}

		if (!isset($detail['periods'][$years]['register'])) {
			return array();
		}

		$period = $detail['periods'][$years];

		return array(
			'years'    => $years,
			'register' => $period['register'],
			'renew'    => isset($period['renew']) ? $period['renew'] : null,
			'offered'  => array_keys($detail['periods']),
		);
	}

	/**
	 * Hand back cached data, unless it has gone past the staleness limit.
	 *
	 * Callers used to test Is_Cache_Stale() themselves before asking for data.
	 * That read "never cached" as "too old to show" and returned nothing — so a
	 * fresh install rendered blank and never triggered the first fetch. The
	 * decision belongs here, where the age is actually known.
	 *
	 * @since 1.0.2
	 * @param array|null $p_cached Previously cached payload
	 * @param float|null $p_age Age of that payload in seconds
	 * @return array Empty structure when there is nothing safe to show
	 */
	private static function _ServeCached($p_cached, $p_age)
	{
		if ($p_cached === null || $p_age === null || $p_age > self::CACHE_MAX_AGE) {
			return array('categories' => array(), 'tlddetail' => array());
		}

		return $p_cached;
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

			/**
			 * WHMCS quotes every registration length it sells, keyed by number
			 * of years. The previous parser read year one and discarded the
			 * rest, so a multi-year discount never reached the page.
			 */
			$periods = self::_ReadPeriods($details);

			if (!isset($periods[1]['register'])) {
				continue;
			}

			$key = ltrim(strtolower((string) $tld), '.');

			$register = $periods[1]['register'];

			/**
			 * Only record a renewal price when WHMCS actually supplied one.
			 * Falling back to the registration price used to make every
			 * extension look like it renewed at the same rate — on a commercial
			 * page that is not a harmless default, it is a claim about money.
			 */
			$renew = isset($periods[1]['renew']) ? $periods[1]['renew'] : null;
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
				'categories' => $categories,
				'flag'       => $group,
				'promo'      => 0,
				'periods'    => $periods,
			);

			// Absent renewal stays absent: consumers test isset() and render
			// nothing rather than repeating the registration price.
			if ($renew !== null) {
				$entry['renew'] = $renew;
			}

			// First year cheaper than renewal means a promotional rate.
			if ($renew !== null && $register < $renew) {

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
