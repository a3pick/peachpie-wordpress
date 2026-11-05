/* Webisoft Kalane Suite — پنل مدیریت */
(function ($) {
	'use strict';
	var D = window.WSKAdmin || { i18n: {} };
	var counter = 0;

	function initColors(ctx) {
		$(ctx).find('.wsk-color').each(function () {
			if (!$(this).closest('.wp-picker-container').length) {
				$(this).wpColorPicker({ defaultColor: $(this).data('default-color') || false });
			}
		});
	}
	initColors(document);

	/* ----- Repeater ----- */
	$(document).on('click', '.wsk-rep__add', function () {
		var $rep = $(this).closest('.wsk-rep');
		var depth = $rep.data('depth');
		var tpl = $rep.children('.wsk-rep__tpl')[0];
		var idx = 'n' + Date.now() + (counter++);
		var html = $.trim(tpl.innerHTML).split('__ROW' + depth + '__').join(idx);
		var $row = $(html).attr('open', 'open');
		$rep.children('.wsk-rep__rows').append($row);
		initColors($row);
		$row.find('input[type=text]:visible:first').trigger('focus');
	});

	$(document).on('click', '.wsk-row .wsk-up, .wsk-row .wsk-down, .wsk-row .wsk-del', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var $row = $(this).closest('.wsk-row');
		if ($(this).hasClass('wsk-up')) { $row.prev('.wsk-row').before($row); }
		else if ($(this).hasClass('wsk-down')) { $row.next('.wsk-row').after($row); }
		else if (window.confirm(D.i18n.del)) { $row.remove(); }
	});

	$(document).on('input', '.wsk-row__body input, .wsk-row__body textarea', function () {
		var $row = $(this).closest('.wsk-row');
		var tf = $row.parent().parent().data('title-field');
		if (!tf || $(this).closest('.wsk-row')[0] !== $row[0]) { return; }
		var name = this.name || '';
		if (name.slice(-(tf.length + 2)) === '[' + tf + ']') {
			var v = $.trim(this.value).replace(/\s+/g, ' ').slice(0, 60);
			$row.children('summary').find('.wsk-row__title').first().text(v || D.i18n.newItem);
		}
	});

	/* ----- انتخاب تصویر ----- */
	$(document).on('click', '.wsk-img-pick', function (e) {
		e.preventDefault();
		var $field = $(this).closest('.wsk-img-field');
		var kind = $(this).data('media') === 'video' ? 'video' : 'image';
		var frame = wp.media({ title: kind === 'video' ? D.i18n.pickVideo : D.i18n.pick, button: { text: D.i18n.use }, multiple: false, library: { type: kind } });
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			if (kind === 'video') {
				$field.find('.wsk-img-input').val(a.url).trigger('input');
				return;
			}
			var url = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
			$field.find('.wsk-img-input').val(a.id);
			$field.find('.wsk-img-field__prev').html($('<img>', { src: url, alt: '' }));
		});
		frame.open();
	});
	$(document).on('click', '.wsk-img-clear', function (e) {
		e.preventDefault();
		var $field = $(this).closest('.wsk-img-field');
		$field.find('.wsk-img-input').val('');
		$field.find('.wsk-img-field__prev').empty();
	});
	$(document).on('input', '.wsk-img-input', function () {
		var v = $.trim(this.value);
		var $p = $(this).closest('.wsk-img-field').find('.wsk-img-field__prev');
		if (/^https?:\/\//i.test(v)) { $p.html($('<img>', { src: v, alt: '' })); } else if (!/^\d+$/.test(v)) { $p.empty(); }
	});

	/* ----- سایر ----- */
	$(document).on('click', '.wsk-copy', function () {
		var $b = $(this), text = $b.data('copy'), old = $b.text();
		function ok() { $b.text(D.i18n.copied); setTimeout(function () { $b.text(old); }, 1500); }
		if (navigator.clipboard) { navigator.clipboard.writeText(text).then(ok); }
		else { window.prompt('', text); }
	});
	$(document).on('click', '.wsk-reset', function (e) { if (!window.confirm(D.i18n.confirm)) { e.preventDefault(); } });
})(jQuery);
