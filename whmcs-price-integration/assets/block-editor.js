/**
 * WHMCS Price Integration — editor script for the domain price block.
 *
 * @author            Astral Internet inc.
 * @copyright         Copyright (C) 2021-2026, Astral Internet inc. - support@astralinternet.com
 * @license           http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 *
 * Written in plain ES5 against the wp.* globals so the plugin needs no build
 * step. Adding npm and a bundler to ship one block would cost more to maintain
 * than it saves.
 *
 * @since 1.0.0
 */
(function (blocks, element, components, blockEditor, i18n, serverSideRender) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;

	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;

	/**
	 * Under API version 3 the editor renders inside an iframe and useBlockProps
	 * must land on the element the block actually owns. The inspector controls
	 * are a slot, so they belong beside that element rather than inside it.
	 */
	var Fragment = element.Fragment;

	blocks.registerBlockType('whmcs-pi/domain-price', {
		apiVersion: 3,
		title: __('WHMCS domain price', 'whmcs-pi'),
		description: __(
			'Shows the live registration and renewal price for a domain extension.',
			'whmcs-pi'
		),
		icon: 'tag',
		category: 'widgets',
		keywords: [__('price', 'whmcs-pi'), __('domain', 'whmcs-pi'), 'whmcs'],

		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps ? useBlockProps() : {};

			var controls = el(
				InspectorControls,
				{ key: 'inspector' },
				el(
					components.PanelBody,
					{ title: __('Price settings', 'whmcs-pi'), initialOpen: true },

					el(components.TextControl, {
						label: __('Extension', 'whmcs-pi'),
						help: __(
							'Leave empty to use the page slug. On extension pages that keeps the price and the page in step automatically.',
							'whmcs-pi'
						),
						value: attributes.tld,
						placeholder: __('page slug', 'whmcs-pi'),
						onChange: function (value) {
							setAttributes({ tld: value });
						}
					}),

					el(components.SelectControl, {
						label: __('Registration length', 'whmcs-pi'),
						help: __(
							'Lengths WHMCS does not sell for this extension fall back to one year, and the wording follows what is shown.',
							'whmcs-pi'
						),
						value: String(attributes.years || 1),
						options: [
							{ label: __('1 year', 'whmcs-pi'), value: '1' },
							{ label: __('2 years', 'whmcs-pi'), value: '2' },
							{ label: __('3 years', 'whmcs-pi'), value: '3' },
							{ label: __('5 years', 'whmcs-pi'), value: '5' },
							{ label: __('10 years', 'whmcs-pi'), value: '10' }
						],
						onChange: function (value) {
							setAttributes({ years: parseInt(value, 10) || 1 });
						}
					}),

					el(components.TextControl, {
						label: __('Label', 'whmcs-pi'),
						help: __('Optional heading shown above the price.', 'whmcs-pi'),
						value: attributes.label,
						onChange: function (value) {
							setAttributes({ label: value });
						}
					}),

					el(components.ToggleControl, {
						label: __('Show the label above the price', 'whmcs-pi'),
						help: __(
							'Off by default: the heading above the block usually says the same thing.',
							'whmcs-pi'
						),
						checked: !!attributes.showLabel,
						onChange: function (value) {
							setAttributes({ showLabel: value });
						}
					}),

					el(components.ToggleControl, {
						label: __('Show renewal price', 'whmcs-pi'),
						help: __(
							'Recommended: on new gTLDs the first year is often promotional.',
							'whmcs-pi'
						),
						checked: !!attributes.showRenew,
						onChange: function (value) {
							setAttributes({ showRenew: value });
						}
					}),

					el(components.ToggleControl, {
						label: __('Show discount badge', 'whmcs-pi'),
						checked: !!attributes.showPromo,
						onChange: function (value) {
							setAttributes({ showPromo: value });
						}
					})
				)
			);

			var preview;

			if (serverSideRender) {
				preview = el(serverSideRender, {
					block: 'whmcs-pi/domain-price',
					attributes: attributes,
					// An empty render means no trustworthy price, which is a
					// legitimate outcome rather than an error.
					EmptyResponsePlaceholder: function () {
						return el(
							components.Placeholder,
							{ icon: 'tag', label: __('WHMCS domain price', 'whmcs-pi') },
							__(
								'No price available yet. Check the plugin settings, or refresh the cache.',
								'whmcs-pi'
							)
						);
					}
				});
			} else {
				preview = el(
					components.Placeholder,
					{ icon: 'tag', label: __('WHMCS domain price', 'whmcs-pi') },
					attributes.tld
						? '.' + attributes.tld
						: __('Uses the page slug', 'whmcs-pi')
				);
			}

			return el(Fragment, null, controls, el('div', blockProps, preview));
		},

		// Rendered server side: nothing is stored in the post content, so a
		// price can never go stale inside saved markup.
		save: function () {
			return null;
		}
	});

	/**
	 * WHMCS product price.
	 *
	 * Unlike the domain block there is no slug to fall back on: a product is
	 * identified by its WHMCS id, which the author has to supply. The id is
	 * listed under Products/Services in the WHMCS admin.
	 *
	 * @since 1.3.0
	 */
	blocks.registerBlockType('whmcs-pi/product-price', {
		apiVersion: 3,
		title: __('WHMCS product price', 'whmcs-pi'),
		description: __(
			'Shows the live price of a WHMCS product, options included.',
			'whmcs-pi'
		),
		icon: 'cart',
		category: 'widgets',
		keywords: [__('price', 'whmcs-pi'), __('product', 'whmcs-pi'), 'whmcs'],

		/**
		 * Declared here as well as in PHP.
		 *
		 * The editor normally merges the server definition into a client
		 * registration, but that leaves the block unusable if the merge does
		 * not happen — the editor then reports the block as unsupported even
		 * though it renders correctly on the front end. Declaring them on both
		 * sides removes the dependency; PHP stays the source of truth for
		 * rendering.
		 */
		attributes: {
			pid: { type: 'number', default: 0 },
			period: { type: 'string', default: 'monthly' },
			showMonthly: { type: 'boolean', default: true },
			withOptions: { type: 'boolean', default: true },
			options: { type: 'string', default: '' },
			optionsMin: { type: 'string', default: '' },
			promoPrice: { type: 'boolean', default: false },
			promoCode: { type: 'string', default: '' },
			showPeriod: { type: 'boolean', default: true },
			showFrom: { type: 'boolean', default: false },
			label: { type: 'string', default: '' },
			customPrefix: { type: 'string', default: '' },
			customSuffix: { type: 'string', default: '' }
		},

		supports: {
			html: false,
			spacing: { margin: true, padding: true },
			typography: {
				fontSize: true,
				lineHeight: true,
				textAlign: true,
				__experimentalFontFamily: true,
				__experimentalFontWeight: true,
				__experimentalFontStyle: true,
				__experimentalTextTransform: true,
				__experimentalLetterSpacing: true
			},
			color: { text: true, background: true, gradients: true, link: false },
			__experimentalBorder: { color: true, radius: true, style: true, width: true }
		},

		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps ? useBlockProps() : {};

			var controls = el(
				InspectorControls,
				{ key: 'inspector' },
				el(
					components.PanelBody,
					{ title: __('Product', 'whmcs-pi'), initialOpen: true },

					el(components.TextControl, {
						label: __('Product id', 'whmcs-pi'),
						help: __(
							'The WHMCS product id, listed under Products/Services in the WHMCS admin.',
							'whmcs-pi'
						),
						type: 'number',
						value: attributes.pid ? String(attributes.pid) : '',
						onChange: function (value) {
							setAttributes({ pid: parseInt(value, 10) || 0 });
						}
					}),

					el(components.SelectControl, {
						label: __('Billing cycle', 'whmcs-pi'),
						help: __(
							'A cycle the product is not sold on renders nothing.',
							'whmcs-pi'
						),
						value: attributes.period || 'monthly',
						options: [
							{ label: __('Monthly', 'whmcs-pi'), value: 'monthly' },
							{ label: __('Quarterly', 'whmcs-pi'), value: 'quarterly' },
							{ label: __('Every six months', 'whmcs-pi'), value: 'semiannually' },
							{ label: __('Annually', 'whmcs-pi'), value: 'annually' },
							{ label: __('Every two years', 'whmcs-pi'), value: 'biennially' },
							{ label: __('Every three years', 'whmcs-pi'), value: 'triennially' }
						],
						onChange: function (value) {
							setAttributes({ period: value });
						}
					}),

					el(components.ToggleControl, {
						label: __('Show the monthly equivalent', 'whmcs-pi'),
						help: __(
							'On an annual cycle, divides the price by twelve. This is how a yearly commitment is usually advertised.',
							'whmcs-pi'
						),
						checked: !!attributes.showMonthly,
						onChange: function (value) {
							setAttributes({ showMonthly: value });
						}
					}),

					el(components.ToggleControl, {
						label: __('Use the promotional price', 'whmcs-pi'),
						help: __(
							'Off by default. With no code named below, WHMCS decides which of the product\'s promotions applies and the order is arbitrary, so a private code can end up on a public page.',
							'whmcs-pi'
						),
						checked: !!attributes.promoPrice,
						onChange: function (value) {
							setAttributes({ promoPrice: value });
						}
					}),

					el(components.TextControl, {
						label: __('Promotion code', 'whmcs-pi'),
						help: __(
							'Prefix of the WHMCS promotion code to price against, such as whfirstterm. Case sensitive, and matched from the start, so HW- covers every code beginning with it.',
							'whmcs-pi'
						),
						value: attributes.promoCode,
						disabled: !attributes.promoPrice,
						onChange: function (value) {
							setAttributes({ promoCode: value });
						}
					})
				),

				el(
					components.PanelBody,
					{ title: __('Configurable options', 'whmcs-pi'), initialOpen: false },

					el(components.ToggleControl, {
						label: __('Include the options floor', 'whmcs-pi'),
						help: __(
							'Adds the cheapest selectable value of each option. On a product sold with a mandatory account count or disk allowance, the bare product price is not what a customer pays.',
							'whmcs-pi'
						),
						checked: !!attributes.withOptions,
						onChange: function (value) {
							setAttributes({ withOptions: value });
						}
					}),

					el(components.TextControl, {
						label: __('Count only these options', 'whmcs-pi'),
						help: __(
							'Comma separated option ids, for instance 911,913. Empty counts every option on the product.',
							'whmcs-pi'
						),
						value: attributes.options,
						placeholder: __('all options', 'whmcs-pi'),
						onChange: function (value) {
							setAttributes({ options: value });
						}
					}),

					el(components.TextControl, {
						label: __('Quantity minimums', 'whmcs-pi'),
						help: __(
							'Only for an option WHMCS reports no minimum for, as id:minimum pairs such as 913:10. A reported minimum always wins.',
							'whmcs-pi'
						),
						value: attributes.optionsMin,
						onChange: function (value) {
							setAttributes({ optionsMin: value });
						}
					})
				),

				el(
					components.PanelBody,
					{ title: __('Wording', 'whmcs-pi'), initialOpen: false },

					el(components.TextControl, {
						label: __('Label', 'whmcs-pi'),
						help: __('Optional heading shown above the price.', 'whmcs-pi'),
						value: attributes.label,
						onChange: function (value) {
							setAttributes({ label: value });
						}
					}),

					el(components.ToggleControl, {
						label: __('Show the billing period', 'whmcs-pi'),
						checked: !!attributes.showPeriod,
						onChange: function (value) {
							setAttributes({ showPeriod: value });
						}
					}),

					el(components.TextControl, {
						label: __('Currency prefix', 'whmcs-pi'),
						help: __(
							'Replaces the automatic currency formatting. Use it to match figures already written on the page.',
							'whmcs-pi'
						),
						value: attributes.customPrefix,
						onChange: function (value) {
							setAttributes({ customPrefix: value });
						}
					}),

					el(components.TextControl, {
						label: __('Currency suffix', 'whmcs-pi'),
						value: attributes.customSuffix,
						onChange: function (value) {
							setAttributes({ customSuffix: value });
						}
					}),

					el(components.ToggleControl, {
						label: __('Prefix with "from"', 'whmcs-pi'),
						help: __(
							'Worth turning on when the options floor is included: what is quoted is the cheapest configuration, not the only one.',
							'whmcs-pi'
						),
						checked: !!attributes.showFrom,
						onChange: function (value) {
							setAttributes({ showFrom: value });
						}
					})
				)
			);

			var preview;

			if (!attributes.pid) {
				preview = el(
					components.Placeholder,
					{ icon: 'cart', label: __('WHMCS product price', 'whmcs-pi') },
					__('Enter a WHMCS product id to see the price.', 'whmcs-pi')
				);
			} else if (serverSideRender) {
				preview = el(serverSideRender, {
					block: 'whmcs-pi/product-price',
					attributes: attributes,
					// An empty render means no trustworthy price, which is a
					// legitimate outcome rather than an error.
					EmptyResponsePlaceholder: function () {
						return el(
							components.Placeholder,
							{ icon: 'cart', label: __('WHMCS product price', 'whmcs-pi') },
							__(
								'No price available. Check the product id and the billing cycle, or the plugin settings.',
								'whmcs-pi'
							)
						);
					}
				});
			} else {
				preview = el(
					components.Placeholder,
					{ icon: 'cart', label: __('WHMCS product price', 'whmcs-pi') },
					__('Product', 'whmcs-pi') + ' ' + attributes.pid
				);
			}

			return el(Fragment, null, controls, el('div', blockProps, preview));
		},

		// Rendered server side, for the same reason as the domain block.
		save: function () {
			return null;
		}
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.components,
	window.wp.blockEditor,
	window.wp.i18n,
	window.wp.serverSideRender
);
