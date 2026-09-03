# WHMCS Price Integration for WordPress
Contributors: @astralinternet, @neutrall, @sleyeur
Tags: whmcs, api
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

## Description

Display WHMCS product and domain prices directly inside WordPress pages, through
a Gutenberg block, an inline price inside a sentence, or a shortcode.

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

### WHMCS product price

Renders the live price of a WHMCS product. Unlike the domain block there is no
slug to fall back on: a product is identified by its WHMCS id, listed under
*Products/Services* in the WHMCS admin.

Block settings:

- **Product id:** the WHMCS product id.
- **Billing cycle:** monthly through triennially. A cycle the product is not
  sold on renders nothing.
- **Show the monthly equivalent** (default on): on an annual cycle, divides by
  twelve — how a yearly commitment is normally advertised.
- **Use the promotional price** (default **off** since 1.4.0): a promotional
  price is something an author asks for. Left on by default it published
  whichever promotion WHMCS happened to list first for the product, which on
  a product carrying several can be a code restricted to one client.
- **Promotion code:** prefix of the WHMCS code to price against, such as
  `whfirstterm`. Case sensitive, matched from the start, so `HW-` covers every
  code beginning with it. Empty leaves the choice to WHMCS, in an order that is
  arbitrary — name a code whenever the product carries more than one.
- **Include the options floor** (default on): adds the cheapest selectable
  value of each configurable option. On a product sold with a mandatory account
  count or disk allowance, the bare product price is not what a customer pays.
- **Count only these options** and **Quantity minimums**: the block equivalents
  of the `options` and `optionsmin` shortcode attributes.
- **Label**, **Show the billing period**, **Prefix with "from"**.
- **Currency prefix** and **Currency suffix**: replace the automatic locale
  formatting when the notation has to match figures already written on the
  page. Left empty, the price is formatted for the site locale.

Both blocks offer a **Plain** style, which hands the typography back to the
surrounding element. Use it when the block replaces a hard-coded figure inside
an existing layout; the default style suits a price standing on its own.

Both blocks use block API version 3 and are rendered server side, so no price
is stored in the saved post content where it could go stale unnoticed.

Both also take the editor's usual layout controls — margin, padding, colour,
border and typography — so a price can be spaced and styled like any other
block. Sizes inside the block are relative, so changing the block font size
scales the amount and its period together.

## Inline price

A block is a paragraph of its own, which is the wrong shape for a price quoted
in the middle of a sentence — *"a .ca costs 14,99 $ for the first year"*.
Gutenberg has no inline block, so the plugin adds what the inline image
actually is: a **rich text format**.

Put the caret where the figure belongs, or select the text it should replace,
then pick **WHMCS price** from the paragraph toolbar (the arrow at the end of
the formatting buttons). The popover asks what to quote, and **Apply** writes
the current price into the sentence.

It works anywhere the editor offers formatting: paragraphs, headings, list
items, buttons, table cells, captions.

The popover carries the same settings as the blocks:

- **Price source:** a domain extension, or a product.
- Domain — **Extension** (empty uses the page slug, as in the block),
  **Registration length**, and **Price shown**: the first year or the renewal.
- Product — **Product id**, **Billing cycle**, **Show the monthly
  equivalent**, **Use the promotional price** (off by default) and
  **Promotion code**, **Include the options floor**, **Count only these
  options**, **Quantity minimums**.
- **Currency prefix** and **Currency suffix**, which replace the automatic
  locale formatting exactly as they do in the product block.

A length WHMCS does not sell for the extension renders nothing at all, unlike
the block: the block words its own period line and can say what it really
quoted, while a figure dropped into a sentence somebody else wrote cannot.

### What is stored, and what is live

The paragraph keeps a marked `<span>` carrying the settings, and the figure
inside it is rewritten by the server on every render. Three things follow from
that:

- The price is always current, like the blocks.
- The figure visible in the saved content is a **fallback**. A visitor arriving
  while the plugin is switched off reads the last known price instead of a hole
  in the sentence, and the server takes over again the moment it is back.
- Editing that post **while the plugin is deactivated** turns the price into
  ordinary text for good: the editor drops formats it does not know about, and
  saving makes that permanent.

Formatting applied *inside* an inline price — bolding the figure itself, for
instance — is replaced along with the figure. Apply it to the whole
sentence, or to a stretch of text around the price, instead.

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
- **promoprefix** (default empty): price against promotion codes starting with
  this prefix, for instance `promoprefix="whfirstterm"`. Case sensitive.
  Empty lets WHMCS decide which of the product's promotions applies, and the
  order it returns them in is arbitrary. The prefix is part of the cache key,
  so two codes on one product no longer share an entry
- **bypasscache** (default false)
- **class** (default empty): add a custom class name to the output
- **whmcsprefix** (default false): display the WHMCS-defined prefix on prices
- **whmcssuffix** (default false): display the WHMCS-defined suffix on prices
- **customprefix** (default empty): custom prefix, overrides the WHMCS prefix
- **customsuffix** (default empty): custom suffix, overrides the WHMCS suffix
- **withoptions** (default false): add the configurable options floor to the
  returned price
- **options** (default empty): comma separated configurable option ids to count,
  e.g. `options="911,913"`. Empty counts every option on the product. Ignored
  unless `withoptions` is true
- **optionsonly** (default false): return the options floor alone, without the
  product price
- **debugoptions** (default false): print the option structure WHMCS returned.
  Visible only to users who can manage options
- **optionsmin** (default empty): quantity minimums as `id:minimum` pairs, e.g.
  `optionsmin="913:10"`. A minimum reported by WHMCS always wins; this is only
  a fallback for an option it does not report one for
- **raw** (default false): return the bare value with no markup
- **debugapi** (default false): when the call fails, print the reason WHMCS
  gave instead of the neutral notice. Visible only to users who can manage
  options

#### Feeding structured data

A price inside a `<script type="application/ld+json">` block cannot arrive
wrapped in a `<span>`: the JSON would no longer parse. `raw="true"` returns the
value alone.

```
<!-- wp:html -->
<script type="application/ld+json">
{ "@type": "Offer", "priceCurrency": "CAD",
  "price": "[whmcs_products pid="468" period="annually" withoptions="true" raw="true"]" }
</script>
<!-- /wp:html -->
```

Shortcodes are processed inside HTML blocks, so the price is resolved when the
page renders. In raw mode every failure returns an empty string rather than an
error message: a sentence dropped into a JSON-LD document would invalidate the
whole document, which is worse than a missing price.

A billing cycle the product is not sold on renders nothing: WHMCS marks those
with -1.00, which is not an amount.

#### Quoting a "from" price

A product price on its own is rarely what a customer pays. When a product
carries configurable options with a mandatory minimum — a number of accounts, a
disk allowance, a database quota — the real floor is the product plus the
cheapest selectable value of each option.

`withoptions` adds that floor. For a dropdown or a radio it takes the least
expensive value; for a quantity option it takes the minimum quantity times the
unit price, since WHMCS will not accept an order below that minimum.

```
Reseller cPanel, from [whmcs_products pid="468" period="monthly"
                       promoprice="true" withoptions="true"] /month
```

Option prices are quoted per billing cycle, so they are normalised to a monthly
figure before being added, then scaled back by `showmonthlyprice` exactly like
the product price. A cycle read as `annually` and one read as `monthly` yield
the same monthly floor.

An option WHMCS prices at nothing counts as nothing rather than raising an
error: one missing price should not take the whole figure off the page. Run
`debugoptions="true"` once as an administrator to see each option, the amount
found, and which field it came from — the shape of `configoptions` varies
between WHMCS versions, and the dump names the ids needed for `options`.

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
