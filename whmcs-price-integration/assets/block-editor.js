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

	blocks.registerBlockType('whmcs-pi/domain-price', {
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

					el(components.TextControl, {
						label: __('Label', 'whmcs-pi'),
						help: __('Optional heading shown above the price.', 'whmcs-pi'),
						value: attributes.label,
						onChange: function (value) {
							setAttributes({ label: value });
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

			return el('div', blockProps, [controls, preview]);
		},

		// Rendered server side: nothing is stored in the post content, so a
		// price can never go stale inside saved markup.
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
