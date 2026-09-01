# Changelog

All notable changes to WHMCS Price Integration for WordPress.

## 1.3.0

### Product prices now render

`whmcs_products` never displayed a price: it reported "Pricing is temporarily
unavailable" for every product. `GetProducts()` returns the processed array
built by `_BuildPageInfoArray()` on success, and only a failure comes back as
the raw API object — but the validation demanded an object, so a good response
fell into the failure branch. An array is now the success case.

A response that reports `success` with no product in it is reported separately:
WHMCS answered, so the product id is what needs checking, not the connection.

### A block for product prices

`whmcs_products` covered the shortcode case, but a price on a sales page is
laid out in the editor like everything else around it. **WHMCS product price**
is the block equivalent: product id, billing cycle, options floor, "from"
prefix and label, all from the inspector.

The options floor is **on by default** here, unlike the shortcode. A block
dropped on a sales page is quoting what a customer pays.

Both blocks share one price resolver, so a figure cannot be assembled two
different ways depending on how it was inserted.

### Translations

The product block would have printed "from" and "/month" on a French page:
none of the strings added in this release existed in a catalogue. **52 strings**
translated and added to `fr_CA` and `fr_FR`, catalogues recompiled. That count
includes two from 1.2.0 that had been missed.

### Quoting a price that includes mandatory options

A product price alone is rarely what a customer pays. Where a product carries
configurable options with a mandatory minimum — a number of accounts, a disk
allowance, a database quota — `withoptions` adds the cheapest selectable value
of each, and minimum quantity times unit price for a quantity option.

`options` narrows the count to given option ids, `optionsonly` returns the
floor by itself, and `optionsmin` supplies a minimum for an option WHMCS
reports none for. A reported minimum always wins over a declared one.

No extra API call was needed: `GetProducts` already returned `configoptions`
and the product class already cached them.

Option amounts are quoted per billing cycle, so each is normalised to a monthly
figure before being summed, then scaled by `showmonthlyprice` like the product
price.

### Reading what WHMCS actually returned

`debugoptions` prints every option, the amount found, the field it came from,
and the field names received — the shape of `configoptions` varies between
WHMCS versions. `debugapi` prints the reason a failed call gave instead of the
neutral notice. Both are restricted to users who can manage options.

### Connection test covers products

The settings screen tested `GetTLDPricing` only, and announced a healthy
connection while every product call failed. It now exercises `GetTLDPricing`,
`GetProducts` and `GetPromotions` separately. A refused IP, a refused action
and an unreachable server are named apart, since each needs a different fix.

### Fixes

- `$priceMultiplyer['triennially']` read 34 instead of 36, under-quoting a
  three year term by two months.
- Configurable option types are read as numeric codes as well as words: WHMCS
  sends 1 dropdown, 2 radio, 3 yes/no, 4 quantity.
- The quantity minimum is read from `minqty`, the field `GetProducts` sends,
  in addition to `qtyminimum` and `qtymin`.
- A billing cycle a product is not sold on renders nothing. WHMCS quotes
  -1.00 there as a marker; divided by a number of months it became a small
  negative figure, and with an options floor added it read as a real price.

---

## 1.2.0

### The price block had semantic markup and no stylesheet

The amount, the period and the renewal note ran together in a row, left aligned
under a centred heading, with no hierarchy. The markup had carried the right
classes since 1.0.0 — the stylesheet was simply never shipped.

`assets/block.css` supplies it: large amount, period on the same baseline,
renewal on its own line, discount in an outlined pill. All centred.

The stylesheet is **deliberately colour-free**. The block may sit on a white
page or inside a coloured call to action, so every rule inherits the surrounding
text colour and varies only size, weight and opacity. A hard-coded colour would
break half its placements.

### The label no longer repeats the heading

"A .gold domain" sitting under the heading "Register your .GOLD now" added
nothing. A `showLabel` attribute, **off by default**, now controls it. A label
typed by the author is always honoured.

---

## 1.1.1

The saving was announced as "the first year" whatever length was on display. Under a three year price that described a different offer from the one shown. The wording now follows the length: "Save 33% over 3 years".

---

## 1.1.0

### Multi-year pricing

WHMCS quotes every registration length it sells — `{"1": …, "2": …, "3": …}` —
and the plugin read only the first. A three year discount existed in the API
response and never reached the page; it was visible only at checkout.

Every length is now kept in the cache. The block gains a **Registration length**
selector (1, 2, 3, 5 or 10 years), and both `[whmcs_domainsprice]` and
`[whmcs_domainsrenew]` accept `years="3"`.

A length WHMCS does not sell for a given extension falls back to one year — and
**the wording follows the figure actually shown**, never the one requested. A
price and its period label therefore cannot contradict each other.

### Identical renewal is information, not repetition

When the renewal price matched the registration price, the block printed the
same figure twice. That reads as redundancy, while the fact itself is
reassuring.

The block now renders **"Renews at the same price"** in that case, and the
amount only when the two differ — so the contrast stands out on extensions that
genuinely renew higher.

### Translations

Ten further strings in `fr_CA` and `fr_FR` — lengths, the selector, the new
renewal wording. Catalogues now hold 106 entries.

The cache is purged on upgrade: the stored structure now carries the per-length
prices, which the previous one did not.

---

## 1.0.3

Two findings from verifying a live installation.

### Renewal price is no longer guessed

When WHMCS supplied no renewal price, the code silently fell back to the
registration price. The block therefore rendered "Renewal: X" with exactly the
first year figure, on every extension — that is a claim about money, not a
harmless default.

An absent renewal now stays absent: the block and `[whmcs_domainsrenew]` render
nothing rather than repeating the registration price. A discount is only
computed when both figures are genuinely known.

The cache is purged on version upgrade, otherwise the fallback values already
stored would have masked the fix until the next scheduled refresh.

### French translations

Public output rendered in English on a French language site: none of the strings
added since 1.0.0 existed in any catalogue. **65 strings** translated and added
to `fr_CA` and `fr_FR`, with the `.mo` catalogues recompiled. The 2021
translations are preserved as they were.

The block, the editor panel, the settings screen and the transport messages are
now translated.

---

## 1.0.2

Fixes a defect introduced in 1.0.1 and caught on the first real install: **a
fresh installation displayed no prices at all.**

`Is_Cache_Stale()` treated "never populated" as "too old to display", and both
the shortcodes and the block tested that condition *before* calling the API. An
empty cache therefore read as a stale one: nothing rendered, and nothing
triggered the initial fetch except the scheduled event, an hour after
activation.

The staleness guard now lives inside `Get_Whmcs_TLD_List()`, the only place that
actually knows the cache age. The seven day rule still applies to data that
exists but has gone stale; an absent cache now triggers a first fetch, itself
protected by the fifteen minute back-off.

Workaround on 1.0.1: the **Refresh cache now** button forces the fetch and
unblocks rendering.

### Other

- The block declared its title, category and icon only on the JavaScript side.
  They are now passed to `register_block_type()` as well, so the REST API and
  the inserter report them correctly.

---

## 1.0.1

Security and robustness pass, following a full audit of the boundary between
WordPress and WHMCS. No critical vulnerability was found: there is no path for an
anonymous visitor to reach the WHMCS credentials, execute code, or escalate
privileges, and no SQL injection. The items below are hardening.

### Security

**Output escaping on the grouped display paths.** `whmcs_TLD_Category_To_HTML_Ul()`,
`whmcs_TLD_To_HTML_Table()` and `whmcs_products_func_prepareOutput()` wrote WHMCS
values straight into HTML. TLD names, category names, group flags, prices and the
cart URL are now escaped for their exact context — `esc_attr()` for attributes,
`esc_html()` for text nodes, `esc_url()` for links. Product descriptions go
through `wp_kses_post()` rather than being stripped, since they legitimately
carry simple markup.

**Sanitising on ingestion.** Category names and group flags are passed through
`sanitize_text_field()` in `_ParsePricing()` before being cached. One point of
control protects every consumer at once, present and future, rather than relying
on each display site remembering to escape.

**HTTPS is now required.** `_ResolveEndpoint()` documented a refusal of non-TLS
URLs but only added `https://` when no scheme was present at all; an explicit
`http://` passed through untouched, which made `sslverify` moot and sent the API
identifier and secret over the network in clear text. `http://` is now rejected,
both when building the endpoint and when saving the settings form.

**Shortcode attributes are sanitised.** `tldbtnclass` and `class` reached the
`class` attribute of generated markup unfiltered, so any author able to place a
shortcode controlled it. Both are now reduced through `sanitize_html_class()`.

**The encryption key no longer degrades silently.** It is derived from the
WordPress secret salts; when those were missing — or left at the placeholder
shipped in `wp-config-sample.php` — the key came from a publicly known string and
the encryption protected nothing. The plugin now refuses to store credentials at
all in that situation, and says so on the settings screen.

**WHMCS error messages no longer reach public pages.** WHMCS phrases its own
errors, and that wording can name internal paths or configuration details. Only
administrators see the detail now; everyone else gets a neutral sentence. A null
API response is also treated as a failure rather than a success.

### Availability

**Negative cache.** Between cache expiry and the staleness limit, a page view
could still trigger a blocking API call — and with no memory of failure, every
subsequent view retried. An unreachable WHMCS therefore meant a ten second wait
on every page carrying a price. A fifteen minute back-off is now set after any
failure and cleared on the first success.

**The staleness rule now covers the grouped view.** `[whmcs_domainsdisplayall]`
bypassed the seven day limit that the other shortcodes respect.

### Robustness

- Two possible divisions by zero in the product price calculation, when the
  billing period is unknown, are now guarded.

### Notes for maintainers

- The `Tested up to` field in `README.md` has not been re-verified; update it
  after testing against your own WordPress version.
- When deploying, restrict the WHMCS API credential by IP address and grant it
  only the three roles the plugin uses: `GetTLDPricing`, `GetProducts`,
  `GetPromotions`. An access key configured in WHMCS bypasses the IP
  restriction, so leave that field empty.

---

## 1.0.0

Full modernisation from 0.1. The plugin could no longer authenticate against
WHMCS 8.x or 9.x, and three defects could affect site availability.

### Fixed — blocking

**API authentication.** Moved from the legacy admin `username` / `password` /
`accesskey` pair to **API credentials** (`identifier` / `secret`). WHMCS removed
the legacy method; the plugin simply could not connect to a current install.

**Credential encryption.** The stored payload joined the ciphertext and the
initialisation vector with a `|`. Both are raw bytes, so either could contain
that character and break the split — measured at **21.4% of saves over 4,000
trials**, failing silently. The symptom was an "invalid credential" message that
came and went, which is a hard thing to diagnose.

The IV is now written as a fixed length prefix, with no separator. Existing
values are migrated automatically on upgrade: the legacy layout put the IV in the
last 16 bytes, so the separator always sat at `length - 17` and every stored
value is recoverable by position — **including the ones the old code could no
longer read**. Nothing needs to be re-entered.

**Request timeout.** cURL was used with no `CURLOPT_TIMEOUT`. Replaced with
`wp_remote_post()` at a 10 second timeout, with explicit TLS verification, so an
unresponsive WHMCS can no longer hang page rendering.

**Cache protection.** A failed API call no longer overwrites cached data. The
previous version wrote the empty result over valid prices, so a single network
hiccup wiped every price on the site until the next successful call. Fixed across
all three caches — TLDs, products and promotions.

### Performance

- Refresh moved to **WP-Cron**, once a day. No visitor waits on the API.
- Cache lifetime raised from 1 hour to 24 hours; domain price lists change once
  or twice a year.
- All cache options are stored with **autoload disabled**. The full TLD table was
  previously loaded into memory on every request to the site, including pages
  that display no price.

### Security

- Explicit `current_user_can()` at the top of the settings screen, in addition to
  the capability declared on the menu.
- `$_POST` instead of `$_REQUEST`, with `sanitize_text_field()` and
  `esc_url_raw()` on input, `esc_attr()` and `esc_html()` on output.
- **Credentials are no longer sent back to the browser.** The fields are of type
  `password` and render empty; leaving one blank keeps the stored value. An
  explicit "clear credentials" action was added.
- `ABSPATH` guards added to the two shortcode files, which had none despite being
  reachable by URL.
- Removed the `print_r()` of the API response from error messages.

### Fixed — functional

- **`[whmcs_domainspromo]` formatted the 0/1 promo flag as currency**, rendering
  "$0.00" or "$1.00". It now returns the discount as a percentage, or an empty
  string when the TLD is not on promotion. Pass `format="amount"` for the amount.
- **`bypasscache` never worked.** `boolval('bypasscache')` evaluated the literal
  string, always true, and the result was never passed on. Replaced with
  `filter_var(..., FILTER_VALIDATE_BOOLEAN)` across every shortcode.
- **Translations never loaded.** The header declared `Domain Path: /i18n` while
  the files live in `languages/`.
- Removed a leftover debug `print_r()` in `whmcs_domainsdisplayall`, computed over
  the whole table on every render and then discarded.
- `NumberFormatter` now falls back cleanly when ext-intl is unavailable.
- Version headers reconciled (`Requires PHP: 7.4`, `Requires at least: 5.8`).
- Uninstall now removes every option, including the TLD cache and the per-product
  caches.
- The scheduled event is unregistered on deactivation.

### Added

**Gutenberg block — "WHMCS domain price".** Server rendered, so no price is baked
into saved post content where it would go stale unnoticed. When the extension
attribute is left empty the block reads the post slug, which keeps the displayed
price in step with the page on any post type whose slug is the extension itself.

**`[whmcs_domainsrenew]`** — the renewal price. On new gTLDs the first year is
often promotional while renewal is markedly higher; showing both alongside is
plain honesty.

**Cache staleness limit.** Past seven days without a successful refresh, the
shortcodes and the block render nothing rather than a price that may be wrong.

**Status panel** on the settings screen: whether credentials are set, how many
extensions are cached, cache age, next scheduled refresh, and a manual refresh
button.

---

## 0.1

Initial release.
