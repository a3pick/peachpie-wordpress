/* Webisoft Legal Suite — پنل مدیریت */
(function ($) {
	'use strict';
	var D = window.WSLAdmin || { icons: {}, i18n: {} };
	var counter = 0;

	function svg(key) {
		if (!D.icons[key]) { return ''; }
		return '<svg class="wsl-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="22" height="22" aria-hidden="true">' + D.icons[key] + '</svg>';
	}

	function initColors(ctx) {
		$(ctx).find('.wsl-color').each(function () {
			if (!$(this).closest('.wp-picker-container').length) {
				$(this).wpColorPicker({ defaultColor: $(this).data('default-color') || false });
			}
		});
	}
	initColors(document);

	/* ----- Repeater ----- */
	$(document).on('click', '.wsl-rep__add', function () {
		var $rep = $(this).closest('.wsl-rep');
		var depth = $rep.data('depth');
		var tpl = $rep.children('.wsl-rep__tpl')[0];
		var idx = 'n' + Date.now() + (counter++);
		var html = $.trim(tpl.innerHTML).split('__ROW' + depth + '__').join(idx);
		var $row = $(html).attr('open', 'open');
		$rep.children('.wsl-rep__rows').append($row);
		initColors($row);
		$row.find('input[type=text]:visible:first').trigger('focus');
	});

	$(document).on('click', '.wsl-row .wsl-up, .wsl-row .wsl-down, .wsl-row .wsl-del', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var $row = $(this).closest('.wsl-row');
		if ($(this).hasClass('wsl-up')) { $row.prev('.wsl-row').before($row); }
		else if ($(this).hasClass('wsl-down')) { $row.next('.wsl-row').after($row); }
		else if (window.confirm(D.i18n.del)) { $row.remove(); }
	});

	$(document).on('input', '.wsl-row__body input, .wsl-row__body textarea', function () {
		var $row = $(this).closest('.wsl-row');
		var tf = $row.parent().parent().data('title-field');
		if (!tf || $(this).closest('.wsl-row')[0] !== $row[0]) { return; }
		var name = this.name || '';
		if (name.slice(-(tf.length + 2)) === '[' + tf + ']') {
			var v = $.trim(this.value).replace(/\s+/g, ' ').slice(0, 60);
			$row.children('summary').find('.wsl-row__title').first().text(v || D.i18n.newItem);
		}
	});

	/* ----- انتخاب آیکون ----- */
	var $pop = null;
	function closePop() { if ($pop) { $pop.remove(); $pop = null; } }
	$(document).on('click', '.wsl-icon-pick', function (e) {
		e.preventDefault();
		e.stopPropagation();
		closePop();
		var $btn = $(this), $field = $btn.closest('.wsl-icon-field'), $input = $field.find('.wsl-icon-input');
		$pop = $('<div class="wsl-iconpop" role="dialog"></div>');
		Object.keys(D.icons).forEach(function (k) {
			$('<button type="button" class="wsl-iconpop__item" title="' + k + '"></button>').html(svg(k)).attr('data-key', k).appendTo($pop);
		});
		$('body').append($pop);
		var off = $btn.offset();
		var left = Math.max(10, Math.min(off.left, $(window).width() - $pop.outerWidth() - 10));
		$pop.css({ top: off.top + $btn.outerHeight() + 6, left: left });
		$pop.on('click', '.wsl-iconpop__item', function () {
			var key = $(this).data('key');
			$input.val(key).trigger('input');
			closePop();
		});
	});
	$(document).on('click', function (e) { if ($pop && !$(e.target).closest('.wsl-iconpop').length) { closePop(); } });
	$(document).on('keydown', function (e) { if (e.key === 'Escape') { closePop(); } });
	$(document).on('input', '.wsl-icon-input', function () {
		$(this).closest('.wsl-icon-field').find('.wsl-icon-field__prev').html(svg($.trim(this.value)));
	});

	/* ----- انتخاب تصویر ----- */
	$(document).on('click', '.wsl-img-pick', function (e) {
		e.preventDefault();
		var $field = $(this).closest('.wsl-img-field');
		var frame = wp.media({ title: D.i18n.pick, button: { text: D.i18n.use }, multiple: false, library: { type: 'image' } });
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			var url = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
			$field.find('.wsl-img-input').val(a.id);
			$field.find('.wsl-img-field__prev').html($('<img>', { src: url, alt: '' }));
		});
		frame.open();
	});
	$(document).on('click', '.wsl-img-clear', function (e) {
		e.preventDefault();
		var $field = $(this).closest('.wsl-img-field');
		$field.find('.wsl-img-input').val('');
		$field.find('.wsl-img-field__prev').empty();
	});
	$(document).on('input', '.wsl-img-input', function () {
		var v = $.trim(this.value);
		var $p = $(this).closest('.wsl-img-field').find('.wsl-img-field__prev');
		if (/^https?:\/\//i.test(v)) { $p.html($('<img>', { src: v, alt: '' })); } else if (!/^\d+$/.test(v)) { $p.empty(); }
	});

	/* ----- سایر ----- */
	$(document).on('click', '.wsl-copy', function () {
		var $b = $(this), text = $b.data('copy'), old = $b.text();
		function ok() { $b.text(D.i18n.copied); setTimeout(function () { $b.text(old); }, 1500); }
		if (navigator.clipboard) { navigator.clipboard.writeText(text).then(ok); }
		else { window.prompt('', text); }
	});
	$(document).on('click', '.wsl-reset', function (e) { if (!window.confirm(D.i18n.confirm)) { e.preventDefault(); } });
})(jQuery);
