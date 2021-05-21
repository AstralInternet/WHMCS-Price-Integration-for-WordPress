# WHMCS Price Integration for WordPress
Contributors: @astralinternet, @neutrall, @sleyeur
Tags: whmcs, api
Requires at least: 5.0
Tested up to: 5.7
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

## Description

Add some shortcode to into WordPress so one may link WHMCS product prices direcly into his WordPress Installation.

- Information in the database is store with the OpenSSL librairy
- API information must be set from within the "Tools -> WHMCS Price Intergration" menu

The current available shortcode is : 

### whmcs_products

Give the possibility to add product information directly into a wordpress page.

Shortcode attribute : 

- **pid:** The WHMCS pid (integer)
- **period (default is annualy):** monthly, quarterly, semiannually, annually, biennially, triennially
- **productname (default is false):** Return the product name
- **description:** Return the WHMCS product description instead of the regular price
- **setupfee (default is false):** Return the product setup fee.
- **showmonthlyprice (default is true):** Show the monthly price. EX, if the price is 120$/year, the code will return 12$/month
- **promoprice (default is false):** if true, will return the price with the pomotion applied instead of the regular price.
-                                Will return the regular price if there is no promotion price.
- **promodiscount (default false):** If true, will return the promotion discount value instead of the regular price
- **promocode (default false):** If true, will return the promotion code instead of the current price
- **bypasscache (default false):** Bypass the cache of one hour. The cache is there to prevent overloading the WHMCS server
- **class (default empty):** Add a custom class name to the output result
- **whmcsprefix ( default false):** Display the WHMCS define prefix on prices
- **whmcssuffix ( default false):** Display the WHMCS define suffix on prices
- **customprefix(default empty):**  Display a custom prefix (will overide WHMCS prefix)
- **customsuffix(default empty):**  Display a custom suffix (will overide WHMCS suffix)

### whmcs_domainscat

Return the domain category

[whmcs_domainscat tld="com" bypasscache='true"]

Shortcode attribute : 

- **tld:** The domain TLD
- **bypasscache (default false):** Bypass the cache of one hour. The cache is there to prevent overloading the WHMCS server

### whmcs_domainsprice

Return the domain price 

[whmcs_domainsprice tld="com" bypasscache='true"]

Shortcode attribute : 

- **tld:** The domain TLD
- **bypasscache (default false):** Bypass the cache of one hour. The cache is there to prevent overloading the WHMCS server
- 
### whmcs_domainspromo

Return the domain promo price 

[whmcs_domainspromo tld="com" bypasscache='true"]

Shortcode attribute : 

- **tld:** The domain TLD
- **bypasscache (default false):** Bypass the cache of one hour. The cache is there to prevent overloading the WHMCS server

### whmcs_domainsflag

Return the domain flag 

[whmcs_domainsflag tld="com" bypasscache='true"]

Shortcode attribute : 

- **tld:** The domain TLD
- **bypasscache (default false):** Bypass the cache of one hour. The cache is there to prevent overloading the WHMCS server

## Installation

1. Upload the plugin files to the /wp-content/plugins/ directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the ‘Plugins’ screen in WordPress