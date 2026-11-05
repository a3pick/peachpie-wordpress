/*! Webisoft Kalane Suite — Frontend (vanilla JS, بدون وابستگی) */
(function () {
	'use strict';

	var W = window.WSK || { track: '', i18n: {} };
	var doc = document;
	var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

	function qs(sel, ctx) { return (ctx || doc).querySelector(sel); }
	function qsa(sel, ctx) { return Array.prototype.slice.call((ctx || doc).querySelectorAll(sel)); }

	/* ---------- حالت روشن / تاریک ---------- */
	var root = doc.documentElement;
	function currentTheme() { return root.getAttribute('data-wsk-theme') === 'dark' ? 'dark' : 'light'; }
	function syncToggles() {
		var dark = currentTheme() === 'dark';
		qsa('[data-wsk-theme-toggle]').forEach(function (b) {
			var l = dark ? (W.i18n.toLight || 'Light') : (W.i18n.toDark || 'Dark');
			b.setAttribute('aria-pressed', dark ? 'true' : 'false');
			b.setAttribute('aria-label', l);
			b.title = l;
		});
	}
	function setTheme(t) {
		root.setAttribute('data-wsk-theme', t);
		try { localStorage.setItem('wsk-theme', t); } catch (e) { /* حالت خصوصی مرورگر */ }
		syncToggles();
		doc.dispatchEvent(new CustomEvent('wsk:themechange', { detail: { theme: t } }));
	}
	if (W.dark) {
		doc.addEventListener('click', function (e) {
			var b = e.target.closest ? e.target.closest('[data-wsk-theme-toggle]') : null;
			if (b) { e.preventDefault(); setTheme(currentTheme() === 'dark' ? 'light' : 'dark'); }
		});
		// همگام‌سازی بین تب‌ها
		window.addEventListener('storage', function (e) {
			if (e.key === 'wsk-theme' && (e.newValue === 'dark' || e.newValue === 'light')) { root.setAttribute('data-wsk-theme', e.newValue); syncToggles(); }
		});
		syncToggles();
	}

	/* ---------- ثبت آمار ---------- */
	function hit(type, key) {
		if (!W.track) { return; }
		var body = JSON.stringify({ type: type, key: String(key) });
		try {
			if (navigator.sendBeacon) {
				if (navigator.sendBeacon(W.track, new Blob([body], { type: 'application/json' }))) { return; }
			}
			fetch(W.track, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body, keepalive: true, credentials: 'omit' });
		} catch (e) { /* آمار نباید تجربه‌ی کاربر را خراب کند */ }
	}

	doc.addEventListener('click', function (e) {
		var a = e.target.closest ? e.target.closest('[data-wsk-hit]') : null;
		if (!a) { return; }
		var p = a.getAttribute('data-wsk-hit').split(':');
		if (p.length === 2) { hit(p[0], p[1]); }
	});

	var pv = qs('[data-wsk-product]');
	if (pv) { hit('product', pv.getAttribute('data-wsk-product')); }

	/* ---------- اسلایدر (scroll-snap + autoplay + درگ ماوس) ---------- */
	function initSlider(root) {
		if (root.__wsk) { return; }
		root.__wsk = true;
		var track = qs('.wsk-track', root);
		if (!track) { return; }
		var rtl = getComputedStyle(track).direction === 'rtl';
		var auto = root.getAttribute('data-autoplay') === '1' && !reduce;
		var delay = Math.max(1500, parseInt(root.getAttribute('data-delay'), 10) || 4000);
		var timer = null, paused = false;

		function atEnd() { return Math.abs(track.scrollLeft) + track.clientWidth >= track.scrollWidth - 4; }
		function step() {
			var first = track.firstElementChild;
			var w = first ? first.getBoundingClientRect().width + 12 : track.clientWidth * 0.8;
			if (track.scrollWidth <= track.clientWidth + 4) { return; }
			if (atEnd()) { track.scrollTo({ left: 0, behavior: 'smooth' }); return; }
			track.scrollBy({ left: (rtl ? -1 : 1) * w, behavior: 'smooth' });
		}
		function start() { if (auto && !timer) { timer = setInterval(function () { if (!paused && !doc.hidden) { step(); } }, delay); } }
		function stop() { if (timer) { clearInterval(timer); timer = null; } }

		if (auto && 'IntersectionObserver' in window) {
			new IntersectionObserver(function (en) { en[0].isIntersecting ? start() : stop(); }, { threshold: 0.2 }).observe(root);
		} else { start(); }

		['mouseenter', 'focusin', 'touchstart'].forEach(function (ev) { root.addEventListener(ev, function () { paused = true; }, { passive: true }); });
		['mouseleave', 'focusout'].forEach(function (ev) { root.addEventListener(ev, function () { paused = false; }); });
		root.addEventListener('touchend', function () { setTimeout(function () { paused = false; }, 3000); }, { passive: true });

		// درگ با ماوس (لمس خودش اسکرول بومی دارد)
		if (window.matchMedia && matchMedia('(pointer: fine)').matches) {
			root.classList.add('has-drag');
			var down = false, sx = 0, sl = 0, moved = false;
			track.addEventListener('pointerdown', function (e) {
				if (e.pointerType !== 'mouse' || e.button !== 0) { return; }
				down = true; moved = false; sx = e.clientX; sl = track.scrollLeft;
			});
			window.addEventListener('pointermove', function (e) {
				if (!down) { return; }
				var dx = e.clientX - sx;
				if (!moved && Math.abs(dx) > 5) { moved = true; root.classList.add('is-dragging'); }
				if (moved) { track.scrollLeft = sl - dx; }
			});
			window.addEventListener('pointerup', function () {
				if (!down) { return; }
				down = false;
				// pointer-events لینک‌ها تا بعد از رها کردن غیرفعال است تا کلیک ناخواسته ثبت نشود.
				setTimeout(function () { root.classList.remove('is-dragging'); }, 0);
			});
		}
	}

	/* ---------- استوری ---------- */
	function initStories(root) {
		var modal = qs('.wsk-modal', root);
		var items = qsa('.wsk-story', root);
		if (!modal || !items.length) { return; }
		var video = qs('.wsk-modal__video', modal);
		var bars = qsa('.wsk-modal__bar i', modal);
		var cur = 0, lastFocus = null;

		function paint() {
			bars.forEach(function (b, i) { b.style.width = i < cur ? '100%' : '0'; });
		}
		function play(i) {
			if (i < 0) { i = 0; }
			if (i >= items.length) { close(); return; }
			cur = i;
			items[i].classList.add('is-viewed');
			paint();
			video.src = items[i].getAttribute('data-video');
			var p = video.play();
			if (p && p.catch) { p.catch(function () { /* autoplay مسدود شد؛ کاربر می‌تواند لمس کند */ }); }
			var h = items[i].getAttribute('data-hit');
			if (h) { var s = h.split(':'); hit(s[0], s[1]); }
		}
		function open(i) {
			lastFocus = doc.activeElement;
			modal.hidden = false;
			// یک فریم صبر تا transition اجرا شود
			requestAnimationFrame(function () { modal.classList.add('is-open'); });
			doc.body.classList.add('wsk-modal-open');
			play(i);
			var c = qs('.wsk-modal__close', modal);
			if (c) { c.focus(); }
		}
		function close() {
			modal.classList.remove('is-open');
			doc.body.classList.remove('wsk-modal-open');
			video.pause();
			video.removeAttribute('src');
			video.load();
			bars.forEach(function (b) { b.style.width = '0'; });
			setTimeout(function () { modal.hidden = true; }, 300);
			if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
		}

		video.addEventListener('timeupdate', function () {
			if (video.duration && bars[cur]) { bars[cur].style.width = (video.currentTime / video.duration * 100) + '%'; }
		});
		video.addEventListener('ended', function () { play(cur + 1); });

		// اسکلت لودینگ تا بارگذاری تصویر
		items.forEach(function (it) {
			var im = qs('img', it);
			function done() { it.classList.remove('is-skeleton'); }
			if (!im || im.complete || im.naturalWidth > 0) { done(); } else { im.addEventListener('load', done); im.addEventListener('error', done); setTimeout(done, 4000); /* شبکه‌ی کند/خطا: اسکلت برای همیشه نماند */ }
		});
		// بار اول: حلقه‌ی چرخان ۶۰۰ms قبل از باز شدن (مثل طراحی اصلی)
		items.forEach(function (it, i) {
			it.addEventListener('click', function () {
				if (!it.classList.contains('is-viewed') && !it.classList.contains('is-loading') && !reduce) {
					it.classList.add('is-loading');
					setTimeout(function () { it.classList.remove('is-loading'); open(i); }, 600);
				} else if (!it.classList.contains('is-loading')) { open(i); }
			});
		});
		// سوایپ داخل استوری
		var tx0 = 0;
		modal.addEventListener('touchstart', function (e) { tx0 = e.changedTouches[0].screenX; }, { passive: true });
		modal.addEventListener('touchend', function (e) {
			var dx = e.changedTouches[0].screenX - tx0;
			if (dx < -40) { play(cur + (getComputedStyle(root).direction === 'rtl' ? -1 : 1)); }
			else if (dx > 40) { play(cur + (getComputedStyle(root).direction === 'rtl' ? 1 : -1)); }
		}, { passive: true });
		var rtl = getComputedStyle(root).direction === 'rtl';
		// در RTL «بعدی» سمت چپ است.
		qs('.wsk-modal__nav--next', modal).addEventListener('click', function () { play(cur + 1); });
		qs('.wsk-modal__nav--prev', modal).addEventListener('click', function () { play(cur - 1); });
		qs('.wsk-modal__close', modal).addEventListener('click', close);
		modal.addEventListener('click', function (e) { if (e.target === modal) { close(); } });
		doc.addEventListener('keydown', function (e) {
			if (modal.hidden) { return; }
			if (e.key === 'Escape') { close(); }
			else if (e.key === 'ArrowLeft') { play(cur + (rtl ? 1 : -1)); }
			else if (e.key === 'ArrowRight') { play(cur + (rtl ? -1 : 1)); }
		});
	}

	/* ---------- دسته‌بندی: اسکلت لودینگ + حرکت بی‌پایان موبایل ---------- */
	function initCats(box) {
		if (box.__wsk) { return; }
		box.__wsk = true;
		qsa('.wsk-cat', box).forEach(function (a) {
			var im = qs('img', a);
			function done() { a.classList.remove('is-skeleton'); }
			if (!im || im.complete || im.naturalWidth > 0) { done(); } else { im.addEventListener('load', done); im.addEventListener('error', done); setTimeout(done, 4000); /* شبکه‌ی کند/خطا: اسکلت برای همیشه نماند */ }
		});
		if (box.getAttribute('data-marquee') !== '1' || reduce) { return; }

		var track = qs('.wsk-cats__track', box), set = qs('.wsk-cats__set', box);
		var mq = window.matchMedia('(max-width: 768px)');
		var setW = 0, x = 0, down = false, sx = 0, px = 0, hover = false, dragged = false, raf = 0;
		var rtl = getComputedStyle(box).direction === 'rtl';
		var dir = rtl ? 1 : -1; // RTL: حرکت به راست؛ LTR: به چپ

		function loop() {
			if (!mq.matches) { return; }
			if (!down && !hover) { x += 0.5 * dir; }
			if (dir > 0) { if (x >= setW * 2) { x -= setW; } else if (x <= 0) { x += setW; } }
			else { if (x <= -setW * 2) { x += setW; } else if (x >= 0) { x -= setW; } }
			track.style.transform = 'translateX(' + x + 'px)';
			raf = requestAnimationFrame(loop);
		}
		function init() {
			cancelAnimationFrame(raf);
			if (mq.matches) {
				setW = set.offsetWidth;
				if (x === 0) { x = dir * setW; }
				loop();
			} else { track.style.transform = ''; x = 0; }
		}
		function gx(e) { return e.touches ? e.touches[0].clientX : e.clientX; }
		function start(e) { if (!mq.matches) { return; } down = true; dragged = false; sx = gx(e); px = x; }
		function move(e) { if (!down) { return; } var d = gx(e) - sx; if (Math.abs(d) > 5) { dragged = true; } x = px + d; }
		function end() { down = false; setTimeout(function () { dragged = false; }, 50); }
		track.addEventListener('touchstart', start, { passive: true });
		track.addEventListener('touchmove', move, { passive: true });
		track.addEventListener('touchend', end);
		track.addEventListener('mousedown', start);
		window.addEventListener('mousemove', move);
		window.addEventListener('mouseup', end);
		track.addEventListener('mouseenter', function () { hover = true; });
		track.addEventListener('mouseleave', function () { hover = false; end(); });
		track.addEventListener('click', function (e) { if (dragged) { e.preventDefault(); } }, true);
		window.addEventListener('resize', init);
		// فقط وقتی در دید است حرکت کند (صرفه‌جویی باتری)
		if ('IntersectionObserver' in window) {
			new IntersectionObserver(function (en) { if (en[0].isIntersecting) { init(); } else { cancelAnimationFrame(raf); } }, { threshold: 0.01 }).observe(box);
		} else { setTimeout(init, 100); }
	}

	function boot() {
		syncToggles();
		qsa('[data-wsk-cats]').forEach(initCats);
		qsa('[data-wsk-slider]').forEach(initSlider);
		qsa('[data-wsk-stories]').forEach(initStories);
	}

	if (doc.readyState === 'loading') { doc.addEventListener('DOMContentLoaded', boot); } else { boot(); }
	// المنتور/آژاکس: محتوای تازه‌اضافه‌شده
	doc.addEventListener('wsk:refresh', boot);
	if (window.jQuery) { jQuery(window).on('elementor/frontend/init', function () { setTimeout(boot, 300); }); }
})();
