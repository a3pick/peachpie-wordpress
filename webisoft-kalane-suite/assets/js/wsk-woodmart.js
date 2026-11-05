/*! Webisoft Kalane Suite — اصلاحات Woodmart/Elementor (جایگزین اسنیپت HTML قدیمی) */
(function () {
	'use strict';

	var doc = document;
	var on = (window.WSKTheme && window.WSKTheme.features) || [];

	function qsa(sel, ctx) { return Array.prototype.slice.call((ctx || doc).querySelectorAll(sel)); }

	/* تب‌های المنتور با هاور — بدون jQuery */
	function hoverTabs() {
		doc.addEventListener('mouseover', function (e) {
			var t = e.target.closest ? e.target.closest('.hoverTabs .e-n-tab-title') : null;
			if (!t || t.getAttribute('aria-selected') === 'true') { return; }
			if (!t.contains(e.relatedTarget)) { t.click(); }
		});
	}

	/* H3 عنوان محصول → span (در ظرف‌هایی با کلاس convert-titles-group) */
	var CLS = 'convert-titles-group';
	function convert(container) {
		qsa('h3.wd-entities-title', container).forEach(function (h) {
			var s = doc.createElement('span');
			s.innerHTML = h.innerHTML;
			for (var i = 0; i < h.attributes.length; i++) { s.setAttribute(h.attributes[i].name, h.attributes[i].value); }
			h.parentNode.replaceChild(s, h);
		});
	}
	function convertTitles() {
		function run() { qsa('.' + CLS).forEach(convert); }
		run();
		if (!('MutationObserver' in window)) { return; }
		var pending = false;
		new MutationObserver(function (list) {
			// فقط وقتی نودی که با ظرف مرتبط است اضافه شده (بدون پیمایش کل DOM در هر تغییر)
			var hit = list.some(function (m) {
				return Array.prototype.some.call(m.addedNodes, function (n) {
					return n.nodeType === 1 && (n.classList.contains(CLS) || (n.querySelector && n.querySelector('.' + CLS)) || (n.closest && n.closest('.' + CLS)));
				});
			});
			if (hit && !pending) { pending = true; requestAnimationFrame(function () { pending = false; run(); }); }
		}).observe(doc.body, { childList: true, subtree: true });
	}

	function boot() {
		if (on.indexOf('hoverTabs') !== -1) { hoverTabs(); }
		if (on.indexOf('convertTitles') !== -1) { convertTitles(); }
	}
	if (doc.readyState === 'loading') { doc.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
