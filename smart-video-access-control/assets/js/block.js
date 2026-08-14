(function (blocks, element, components, blockEditor, i18n) {
	'use strict';
	var el = element.createElement;
	blocks.registerBlockType('svac/protected-video', {
		apiVersion: 2,
		title: i18n.__('ویدیوی محافظت‌شده', 'smart-video-access-control'),
		icon: 'video-alt3',
		category: 'embed',
		attributes: { videoId: { type: 'integer', default: 0 } },
		edit: function (props) {
			return el('div', { className: 'svac-block-editor' },
				el(blockEditor.InspectorControls, {}, el(components.PanelBody, { title: i18n.__('تنظیمات ویدیو', 'smart-video-access-control') },
					el(components.TextControl, {
						label: i18n.__('شناسه ویدیوی محافظت‌شده', 'smart-video-access-control'),
						type: 'number',
						value: props.attributes.videoId || '',
						onChange: function (value) { props.setAttributes({ videoId: parseInt(value, 10) || 0 }); }
					})
				)),
				el('p', {}, props.attributes.videoId ?
					i18n.sprintf(i18n.__('ویدیوی محافظت‌شده #%d در بخش عمومی بر اساس قوانین دسترسی نمایش داده می‌شود.', 'smart-video-access-control'), props.attributes.videoId) :
					i18n.__('یک شناسه ویدیوی محافظت‌شده وارد کنید.', 'smart-video-access-control'))
			);
		},
		save: function () { return null; }
	});
}(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.i18n));
