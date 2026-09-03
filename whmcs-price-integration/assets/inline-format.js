/**
 * WHMCS Price Integration — inline price format.
 *
 * @author            Astral Internet inc.
 * @copyright         Copyright (C) 2021-2026, Astral Internet inc. - support@astralinternet.com
 * @license           http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 *
 * Gutenberg has no inline block. The inline image every author knows is a rich
 * text format, and so is this: a marked <span> applied to a run of text inside
 * a paragraph, a heading, a list item or a button.
 *
 * The price is fetched from the server and written into the document as
 * ordinary text, so the author sees the real figure while writing the
 * sentence around it. The server rewrites that text on every render, so what
 * is saved is a fallback rather than the source of truth.
 *
 * Written in plain ES5 against the wp.* globals, like the block script, so the
 * plugin still needs no build step.
 *
 * @since 1.4.0
 */
(function (richText, element, components, blockEditor, i18n, apiFetch, url, data) {
	'use strict';

	// A WordPress too old to expose the rich text API simply gets no format.
	if (!richText || !richText.registerFormatType) {
		return;
	}

	var el = element.createElement;
	var __ = i18n.__;
	var useState = element.useState;

	var NAME = 'whmcs-pi/price';

	/**
	 * Settings the format is registered with.
	 *
	 * Held in a variable because useAnchor() needs the very same object to work
	 * out where the popover belongs.
	 */
	var settings = {
		title: __('WHMCS price', 'whmcs-pi'),
		tagName: 'span',
		className: 'whmcs-pi-inline',

		/**
		 * Every setting travels in a data attribute of its own. Rich text
		 * keeps only the attributes declared here, so this list and the PHP
		 * one have to agree.
		 */
		attributes: {
			kind: 'data-whmcs-kind',
			tld: 'data-whmcs-tld',
			years: 'data-whmcs-years',
			part: 'data-whmcs-part',
			pid: 'data-whmcs-pid',
			period: 'data-whmcs-period',
			monthly: 'data-whmcs-monthly',
			promo: 'data-whmcs-promo',
			promocode: 'data-whmcs-promocode',
			options: 'data-whmcs-options',
			optionids: 'data-whmcs-optionids',
			optionsmin: 'data-whmcs-optionsmin',
			prefix: 'data-whmcs-prefix',
			suffix: 'data-whmcs-suffix'
		}
	};

	var PERIODS = [
		{ label: __('Monthly', 'whmcs-pi'), value: 'monthly' },
		{ label: __('Quarterly', 'whmcs-pi'), value: 'quarterly' },
		{ label: __('Every six months', 'whmcs-pi'), value: 'semiannually' },
		{ label: __('Annually', 'whmcs-pi'), value: 'annually' },
		{ label: __('Every two years', 'whmcs-pi'), value: 'biennially' },
		{ label: __('Every three years', 'whmcs-pi'), value: 'triennially' }
	];

	var YEARS = [
		{ label: __('1 year', 'whmcs-pi'), value: '1' },
		{ label: __('2 years', 'whmcs-pi'), value: '2' },
		{ label: __('3 years', 'whmcs-pi'), value: '3' },
		{ label: __('5 years', 'whmcs-pi'), value: '5' },
		{ label: __('10 years', 'whmcs-pi'), value: '10' }
	];

	/**
	 * Copy of the popover state with one or more fields changed.
	 *
	 * Written out rather than reaching for Object.assign, so the file stays the
	 * plain ES5 its header promises and still needs no build step.
	 *
	 * @param {Object} form   Current state
	 * @param {Object} change Fields to overwrite
	 * @return {Object}
	 */
	function merge(form, change) {
		var out = {};
		var key;

		for (key in form) {
			if (Object.prototype.hasOwnProperty.call(form, key)) {
				out[key] = form[key];
			}
		}

		for (key in change) {
			if (Object.prototype.hasOwnProperty.call(change, key)) {
				out[key] = change[key];
			}
		}

		return out;
	}

	/**
	 * Read one stored flag.
	 *
	 * An attribute that was never written keeps the default; "0" written by an
	 * author who cleared the toggle has to read as off, which a bare truthiness
	 * test on the string would get backwards.
	 *
	 * @param {string|undefined} raw      Stored attribute
	 * @param {boolean}          fallback Value to use when nothing was stored
	 * @return {boolean}
	 */
	function flag(raw, fallback) {
		if (raw === undefined || raw === '') {
			return fallback;
		}

		return raw !== '0' && raw !== 'false';
	}

	/**
	 * Keep an author supplied affix free of angle brackets.
	 *
	 * The value ends up inside an HTML attribute that the server reads back
	 * with a scanner stopping at the first ">". An affix carrying one would cut
	 * the span in half, and an affix was never meant to carry markup.
	 *
	 * @param {string} raw Value as typed
	 * @return {string}
	 */
	function affix(raw) {
		return String(raw === undefined ? '' : raw).replace(/[<>]/g, '').trim();
	}

	/**
	 * Turn the popover state into the attributes to store.
	 *
	 * Only what the chosen source actually uses is written: a domain price
	 * carrying a billing cycle would be noise in the markup and a puzzle to
	 * read six months later.
	 *
	 * @param {Object} form Popover state
	 * @return {Object}
	 */
	function buildAttributes(form) {
		var out = { kind: form.kind };

		if (form.kind === 'product') {
			out.pid = String(parseInt(form.pid, 10) || 0);
			out.period = form.period;

			// Both states matter, so a flag is always written for a product.
			out.monthly = form.monthly ? '1' : '0';
			out.promo = form.promo ? '1' : '0';
			out.options = form.options ? '1' : '0';

			if (String(form.promocode).trim()) {
				out.promocode = String(form.promocode).trim();
			}

			if (String(form.optionids).trim()) {
				out.optionids = String(form.optionids).trim();
			}

			if (String(form.optionsmin).trim()) {
				out.optionsmin = String(form.optionsmin).trim();
			}
		} else {
			// Left empty on purpose: the server then falls back to the page slug.
			if (String(form.tld).trim()) {
				out.tld = String(form.tld).trim();
			}

			out.years = String(parseInt(form.years, 10) || 1);
			out.part = form.part === 'renew' ? 'renew' : 'register';
		}

		if (affix(form.prefix)) {
			out.prefix = affix(form.prefix);
		}

		if (affix(form.suffix)) {
			out.suffix = affix(form.suffix);
		}

		return out;
	}

	/**
	 * Fill the popover from what is already stored on the span.
	 *
	 * @param {Object} stored Attributes of the active format, if any
	 * @return {Object}
	 */
	function seedForm(stored) {
		return {
			kind: stored.kind === 'product' ? 'product' : 'domain',
			tld: stored.tld || '',
			years: stored.years || '1',
			part: stored.part === 'renew' ? 'renew' : 'register',
			pid: stored.pid || '',
			period: stored.period || 'monthly',
			monthly: flag(stored.monthly, true),
			promo: flag(stored.promo, false),
			promocode: stored.promocode || '',
			options: flag(stored.options, true),
			optionids: stored.optionids || '',
			optionsmin: stored.optionsmin || '',
			prefix: stored.prefix || '',
			suffix: stored.suffix || ''
		};
	}

	/**
	 * Whether one character of the record carries this format.
	 *
	 * @param {Array|undefined} list Formats on a single character
	 * @return {boolean}
	 */
	function covered(list) {
		if (!list) {
			return false;
		}

		for (var i = 0; i < list.length; i++) {
			if (list[i].type === NAME) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The stretch of text the next write should replace.
	 *
	 * Editing an existing price replaces the whole formatted run, never the
	 * part of it that happens to be selected: half a price left behind with a
	 * new one beside it is not something an author can have meant.
	 *
	 * With no format under the caret the current selection is used, which for
	 * a collapsed caret is an insertion point.
	 *
	 * @param {Object} value Rich text record
	 * @return {Object|null} start and end offsets, null when there is no selection
	 */
	function targetRange(value) {
		var start = value.start;

		if (start === undefined) {
			return null;
		}

		var formats = value.formats || [];
		var index = -1;

		if (covered(formats[start])) {
			index = start;
		} else if (start > 0 && covered(formats[start - 1])) {
			index = start - 1;
		}

		if (index === -1) {
			return { start: start, end: value.end === undefined ? start : value.end };
		}

		var from = index;
		while (from > 0 && covered(formats[from - 1])) {
			from--;
		}

		var to = index + 1;
		while (to < formats.length && covered(formats[to])) {
			to++;
		}

		return { start: from, end: to };
	}

	/**
	 * Post being edited, so the server can resolve an empty extension.
	 *
	 * The site editor has no core/editor post, which is a normal state rather
	 * than an error: the extension then has to be typed.
	 *
	 * @return {number}
	 */
	function currentPostId() {
		var store = data && data.select ? data.select('core/editor') : null;

		if (!store || !store.getCurrentPostId) {
			return 0;
		}

		return store.getCurrentPostId() || 0;
	}

	/**
	 * Ask the server what these settings are worth.
	 *
	 * The figure is never computed here. The rules — promotional prices, the
	 * options floor, the monthly equivalent, the staleness limit — live in PHP
	 * and a second implementation would drift away from them.
	 *
	 * @param {Object} attributes Attributes about to be stored
	 * @return {Promise<string>} Formatted price, empty when none is available
	 */
	function fetchPrice(attributes) {
		var query = { post: currentPostId() };

		Object.keys(attributes).forEach(function (key) {
			query[key] = attributes[key];
		});

		return apiFetch({ path: url.addQueryArgs('/whmcs-pi/v1/price', query) })
			.then(function (response) {
				return response && response.price ? response.price : '';
			});
	}

	/**
	 * Toolbar button and settings popover.
	 *
	 * @param {Object} props Format edit props
	 * @return {Object}
	 */
	function Edit(props) {
		var value = props.value;
		var onChange = props.onChange;
		var contentRef = props.contentRef;

		var openState = useState(false);
		var isOpen = openState[0];
		var setOpen = openState[1];

		var formState = useState(null);
		var form = formState[0];
		var setForm = formState[1];

		var busyState = useState(false);
		var isBusy = busyState[0];
		var setBusy = busyState[1];

		var noticeState = useState('');
		var notice = noticeState[0];
		var setNotice = noticeState[1];

		/**
		 * useAnchor keeps the popover pinned to the formatted text rather than
		 * to the toolbar, which matters once the editor scrolls. It arrived in
		 * WordPress 6.1; without it the popover still opens, just unanchored.
		 */
		var anchor = richText.useAnchor
			? richText.useAnchor({
				editableContentElement: contentRef ? contentRef.current : null,
				settings: settings
			})
			: undefined;

		var active = richText.getActiveFormat(value, NAME);
		var stored = (active && active.attributes) || {};

		function update(change) {
			setForm(merge(form, change));
		}

		function open() {
			setForm(seedForm(stored));
			setNotice('');
			setOpen(true);
		}

		function close() {
			setOpen(false);
			setBusy(false);
		}

		function remove() {
			onChange(richText.removeFormat(value, NAME));
			close();
		}

		function apply() {
			var attributes = buildAttributes(form);
			var range = targetRange(value);

			if (!range) {
				close();
				return;
			}

			setBusy(true);
			setNotice('');

			fetchPrice(attributes).then(function (price) {
				setBusy(false);

				/**
				 * Nothing came back, but there may already be a figure in the
				 * document — a cache gone stale, an extension pulled from the
				 * catalogue. Keeping it beats blanking a sentence, and the
				 * settings are stored either way so the price returns on its
				 * own once WHMCS answers again.
				 */
				var text = price || richText.getTextContent(
					richText.slice(value, range.start, range.end)
				);

				if (!text) {
					setNotice(__('No price is available for these settings.', 'whmcs-pi'));
					return;
				}

				var replacement = richText.applyFormat(
					richText.create({ text: text }),
					{ type: NAME, attributes: attributes },
					0,
					text.length
				);

				onChange(richText.insert(value, replacement, range.start, range.end));
				close();
			}).catch(function () {
				setBusy(false);
				setNotice(__('No price is available for these settings.', 'whmcs-pi'));
			});
		}

		var button = el(blockEditor.RichTextToolbarButton, {
			icon: 'tag',
			title: settings.title,
			isActive: props.isActive,
			onClick: open
		});

		if (!isOpen || !form) {
			return button;
		}

		var fields = [
			el(components.SelectControl, {
				key: 'kind',
				label: __('Price source', 'whmcs-pi'),
				value: form.kind,
				options: [
					{ label: __('Domain extension', 'whmcs-pi'), value: 'domain' },
					{ label: __('Product', 'whmcs-pi'), value: 'product' }
				],
				onChange: function (next) {
					update({ kind: next });
				}
			})
		];

		if (form.kind === 'domain') {

			fields.push(el(components.TextControl, {
				key: 'tld',
				label: __('Extension', 'whmcs-pi'),
				help: __(
					'Leave empty to use the page slug. On extension pages that keeps the price and the page in step automatically.',
					'whmcs-pi'
				),
				placeholder: __('page slug', 'whmcs-pi'),
				value: form.tld,
				onChange: function (next) {
					update({ tld: next });
				}
			}));

			fields.push(el(components.SelectControl, {
				key: 'years',
				label: __('Registration length', 'whmcs-pi'),
				help: __(
					'A length WHMCS does not sell for this extension renders nothing, since the surrounding sentence would then describe an offer that does not exist.',
					'whmcs-pi'
				),
				value: String(form.years),
				options: YEARS,
				onChange: function (next) {
					update({ years: next });
				}
			}));

			fields.push(el(components.SelectControl, {
				key: 'part',
				label: __('Price shown', 'whmcs-pi'),
				value: form.part,
				options: [
					{ label: __('First year', 'whmcs-pi'), value: 'register' },
					{ label: __('Renewal', 'whmcs-pi'), value: 'renew' }
				],
				onChange: function (next) {
					update({ part: next });
				}
			}));

		} else {

			fields.push(el(components.TextControl, {
				key: 'pid',
				label: __('Product id', 'whmcs-pi'),
				help: __(
					'The WHMCS product id, listed under Products/Services in the WHMCS admin.',
					'whmcs-pi'
				),
				type: 'number',
				value: form.pid ? String(form.pid) : '',
				onChange: function (next) {
					update({ pid: next });
				}
			}));

			fields.push(el(components.SelectControl, {
				key: 'period',
				label: __('Billing cycle', 'whmcs-pi'),
				help: __('A cycle the product is not sold on renders nothing.', 'whmcs-pi'),
				value: form.period,
				options: PERIODS,
				onChange: function (next) {
					update({ period: next });
				}
			}));

			fields.push(el(components.ToggleControl, {
				key: 'monthly',
				label: __('Show the monthly equivalent', 'whmcs-pi'),
				checked: form.monthly,
				onChange: function (next) {
					update({ monthly: next });
				}
			}));

			fields.push(el(components.ToggleControl, {
				key: 'promo',
				label: __('Use the promotional price', 'whmcs-pi'),
				help: __(
					'Off by default. With no code named below, WHMCS decides which of the product\'s promotions applies and the order is arbitrary, so a private code can end up on a public page.',
					'whmcs-pi'
				),
				checked: form.promo,
				onChange: function (next) {
					update({ promo: next });
				}
			}));

			fields.push(el(components.TextControl, {
				key: 'promocode',
				label: __('Promotion code', 'whmcs-pi'),
				help: __(
					'Prefix of the WHMCS promotion code to price against, such as whfirstterm. Case sensitive, and matched from the start, so HW- covers every code beginning with it.',
					'whmcs-pi'
				),
				value: form.promocode,
				disabled: !form.promo,
				onChange: function (next) {
					update({ promocode: next });
				}
			}));

			fields.push(el(components.ToggleControl, {
				key: 'options',
				label: __('Include the options floor', 'whmcs-pi'),
				help: __(
					'Adds the cheapest selectable value of each option. On a product sold with a mandatory account count or disk allowance, the bare product price is not what a customer pays.',
					'whmcs-pi'
				),
				checked: form.options,
				onChange: function (next) {
					update({ options: next });
				}
			}));

			fields.push(el(components.TextControl, {
				key: 'optionids',
				label: __('Count only these options', 'whmcs-pi'),
				placeholder: __('all options', 'whmcs-pi'),
				value: form.optionids,
				onChange: function (next) {
					update({ optionids: next });
				}
			}));

			fields.push(el(components.TextControl, {
				key: 'optionsmin',
				label: __('Quantity minimums', 'whmcs-pi'),
				help: __(
					'Only for an option WHMCS reports no minimum for, as id:minimum pairs such as 913:10. A reported minimum always wins.',
					'whmcs-pi'
				),
				value: form.optionsmin,
				onChange: function (next) {
					update({ optionsmin: next });
				}
			}));
		}

		fields.push(el(components.TextControl, {
			key: 'prefix',
			label: __('Currency prefix', 'whmcs-pi'),
			help: __(
				'Replaces the automatic currency formatting. Use it to match figures already written on the page.',
				'whmcs-pi'
			),
			value: form.prefix,
			onChange: function (next) {
				update({ prefix: next });
			}
		}));

		fields.push(el(components.TextControl, {
			key: 'suffix',
			label: __('Currency suffix', 'whmcs-pi'),
			value: form.suffix,
			onChange: function (next) {
				update({ suffix: next });
			}
		}));

		if (notice) {
			fields.push(el(
				components.Notice,
				{ key: 'notice', status: 'warning', isDismissible: false },
				notice
			));
		}

		fields.push(el(
			'div',
			{
				key: 'actions',
				style: { display: 'flex', gap: '8px', marginTop: '12px' }
			},
			el(
				components.Button,
				{ variant: 'primary', isPrimary: true, disabled: isBusy, onClick: apply },
				__('Apply', 'whmcs-pi')
			),
			props.isActive
				? el(
					components.Button,
					{ variant: 'tertiary', isTertiary: true, disabled: isBusy, onClick: remove },
					__('Remove', 'whmcs-pi')
				)
				: null,
			isBusy ? el(components.Spinner, null) : null
		));

		/**
		 * The panel is sized here rather than in a stylesheet: a dozen lines of
		 * CSS shipped to every visitor for a panel only the editor ever shows
		 * is a poor trade.
		 */
		var panel = el(
			'div',
			{
				style: {
					padding: '16px',
					width: '280px',
					maxHeight: '60vh',
					overflowY: 'auto'
				}
			},
			fields
		);

		return el(
			element.Fragment,
			null,
			button,
			el(
				components.Popover,
				{
					anchor: anchor,
					placement: 'bottom',
					onClose: close,
					focusOnMount: 'firstElement'
				},
				panel
			)
		);
	}

	settings.edit = Edit;

	richText.registerFormatType(NAME, settings);
})(
	window.wp.richText,
	window.wp.element,
	window.wp.components,
	window.wp.blockEditor,
	window.wp.i18n,
	window.wp.apiFetch,
	window.wp.url,
	window.wp.data
);
