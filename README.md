# WHMCS Price Integration for WordPress
Contributors: @astralinternet, @neutrall, @sleyeur
Tags: whmcs, api
Requires at least: 5.8
Tested up to: 5.8
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

## Description

Display WHMCS product and domain prices directly inside WordPress pages, through
a Gutenberg block or a shortcode.

- Prices are pulled from the WHMCS API once a day by WP-Cron and cached, so no
  visitor ever waits on the API.
- API credentials are encrypted at rest with OpenSSL, under a key derived from
  the WordPress secret salts in `wp-config.php`.
- API settings are configured from **Tools → WHMCS Price Integration**.

See [CHANGELOG.md](CHANGELOG.md) for the release history.

## Requirements and setup

The plugin authenticates with **WHMCS API credentials** (`identifier` / `secret`),
created under *Configuration → System Settings → API Credentials*. The legacy
admin username and password authentication is not supported, and no longer exists
in current WHMCS versions.

Three things are worth getting right when you create the credential:

- **Restrict it by IP address** to the WordPress server. This is the protection
  that limits the damage of any credential leak.
- **Grant only the roles the plugin uses:** `GetTLDPricing`, `GetProducts`,
  `GetPromotions`. It needs nothing else.
- **Leave the access key empty.** An access key configured in WHMCS allows calls
  from unlisted IP addresses, which defeats the IP restriction above.

The WHMCS URL must be `https://`. The API identifier and secret travel in the
request body, so a plain-text endpoint is refused rather than silently accepted.

## Installation

1. Upload the plugin files to the `/wp-content/plugins/` directory, or install
   the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the *Plugins* screen in WordPress.
3. Enter the WHMCS URL and API credentials under *Tools → WHMCS Price
   Integration*, then use **Test API connection** to confirm.

## Gutenberg block

**WHMCS domain price** renders the current registration price for a domain
extension, optionally alongside the renewal price and a discount badge.

Block settings:

- **Extension:** the TLD to price. Leave it empty to use the page slug — on a
  post type whose slug is the extension itself, this keeps the price and the page
  in step with no manual entry.
- **Registration length:** 1, 2, 3, 5 or 10 years. A length WHMCS does not sell
  for that extension falls back to one year, and the wording follows the figure
  actually shown.
- **Label:** optional heading shown above the price.
- **Show renewal price** (default on): on new gTLDs the first year is often
  promotional while renewal is markedly higher.
- **Show discount badge** (default on).

The block is rendered server side, so no price is stored in the saved post
content where it could go stale unnoticed.

## Shortcodes

Every shortcode accepts `bypasscache="true"` to skip the daily cache. The cache
exists to avoid overloading the WHMCS server; bypassing it is for testing.

If the cached data has not been refreshed for more than seven days, the
shortcodes render nothing rather than a price that may be wrong.

### whmcs_products

Add product information directly into a WordPress page.

```
[whmcs_products pid="1" period="annually"]
```

Shortcode attributes:

- **pid:** the WHMCS product id (integer)
- **period** (default `annually`): `monthly`, `quarterly`, `semiannually`,
  `annually`, `biennially`, `triennially`
- **productname** (default false): return the product name
- **description:** return the WHMCS product description instead of the price
- **setupfee** (default false): return the product setup fee
- **showmonthlyprice** (default true): show the monthly price — a product at
  $120/year returns $12/month
- **promoprice** (default false): return the price with the promotion applied
  instead of the regular price. Falls back to the regular price when there is no
  promotion
- **promodiscount** (default false): return the promotion discount value instead
  of the price
- **promocode** (default false): return the promotion code instead of the price
- **bypasscache** (default false)
- **class** (default empty): add a custom class name to the output
- **whmcsprefix** (default false): display the WHMCS-defined prefix on prices
- **whmcssuffix** (default false): display the WHMCS-defined suffix on prices
- **customprefix** (default empty): custom prefix, overrides the WHMCS prefix
- **customsuffix** (default empty): custom suffix, overrides the WHMCS suffix

### whmcs_domainsprice

Return the first year registration price of a domain extension.

```
[whmcs_domainsprice tld="com"]
[whmcs_domainsprice tld="ca" years="3"]
```

Attributes: **tld**, **years** (default 1), **bypasscache**

### whmcs_domainsrenew

Return the renewal price of a domain extension. Worth showing next to the
registration price on new gTLDs, where the two often differ substantially.

```
[whmcs_domainsrenew tld="com"]
[whmcs_domainsrenew tld="ca" years="3"]
```

Attributes: **tld**, **years** (default 1), **bypasscache**

### whmcs_domainspromo

Return the promotional discount on a domain extension, as a percentage. Returns
an empty string when the extension is not on promotion.

```
[whmcs_domainspromo tld="com"]
[whmcs_domainspromo tld="com" format="amount"]
```

Attributes:

- **tld:** the domain TLD
- **format** (default `percent`): `percent` or `amount`
- **bypasscache** (default false)

### whmcs_domainscat

Return the domain category.

```
[whmcs_domainscat tld="com"]
```

Attributes: **tld**, **bypasscache**

### whmcs_domainsflag

Return the domain group flag as defined in WHMCS.

```
[whmcs_domainsflag tld="com"]
```

Attributes: **tld**, **bypasscache**

### whmcs_domainsdisplayall

Return a list of every available domain, or a list of each category.

```
[whmcs_domainsdisplayall display="tld"]
```

Attributes:

- **display** (`tld` or `category`): display all the TLDs, or the list of
  categories
- **tldbtnclass:** CSS class added to the TLD buttons
- **bypasscache** (default false)

### whmcs_domainsdisplayallJS

Add a small vanilla JavaScript helper that hides TLDs from the "display all"
shortcode when a category is clicked.

```
[whmcs_domainsdisplayallJS docready="true"]
```

Attributes:

- **docready:** wrap the script in the equivalent of jQuery's
  `$(document).ready()`, in plain JavaScript

## Using a full domain listing with categories

Create a two-column block.

Insert a shortcode block just before the columns with
`[whmcs_domainsdisplayallJS docready="true"]`. This adds the JavaScript needed to
make the two columns interact.

In the first column, insert `[whmcs_domainsdisplayall]` — this lists every
category. In the second, insert `[whmcs_domainsdisplayall display="tld"]` — this
lists every TLD.

TLDs can then be filtered by the selected category. Some CSS is needed to finish
the look.
