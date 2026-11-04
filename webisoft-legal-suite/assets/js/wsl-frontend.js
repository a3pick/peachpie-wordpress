/*! Webisoft Legal Suite — Frontend (vanilla JS) */
(function () {
	'use strict';

	var W = window.WSL || { i18n: {}, toc: {} };
	var doc = document;
	var root = doc.documentElement;

	function qs(sel, ctx) { return (ctx || doc).querySelector(sel); }
	function qsa(sel, ctx) { return Array.prototype.slice.call((ctx || doc).querySelectorAll(sel)); }
	function toFa(s) { return String(s).replace(/[0-9]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.charAt(d); }); }

	/* ---------- حالت روشن / تاریک ---------- */
	function currentTheme() { return root.getAttribute('data-wsl-theme') === 'dark' ? 'dark' : 'light'; }

	function syncToggles() {
		var dark = currentTheme() === 'dark';
		qsa('[data-wsl-theme-toggle]').forEach(function (btn) {
			btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
			btn.setAttribute('aria-label', dark ? (W.i18n.toLight || 'Light') : (W.i18n.toDark || 'Dark'));
			btn.title = btn.getAttribute('aria-label');
		});
	}

	function setTheme(t) {
		root.setAttribute('data-wsl-theme', t);
		try { localStorage.setItem('wsl-theme', t); } catch (e) { /* حالت خصوصی مرورگر */ }
		syncToggles();
		doc.dispatchEvent(new CustomEvent('wsl:themechange', { detail: { theme: t } }));
	}

	doc.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('[data-wsl-theme-toggle]') : null;
		if (btn) { e.preventDefault(); setTheme(currentTheme() === 'dark' ? 'light' : 'dark'); }
	});

	window.addEventListener('storage', function (e) {
		if (e.key === 'wsl-theme' && (e.newValue === 'dark' || e.newValue === 'light')) {
			root.setAttribute('data-wsl-theme', e.newValue);
			syncToggles();
		}
	});

	/* ---------- FAQ ---------- */
	function initFaq(el) {
		var single = el.getAttribute('data-single') === '1';
		qsa('.wsl-faq__q', el).forEach(function (btn) {
			btn.addEventListener('click', function () {
				var item = btn.closest('.wsl-faq__item');
				var open = item.classList.contains('is-open');
				if (single) {
					qsa('.wsl-faq__item.is-open', el).forEach(function (o) {
						o.classList.remove('is-open');
						o.querySelector('.wsl-faq__q').setAttribute('aria-expanded', 'false');
					});
				}
				item.classList.toggle('is-open', !open);
				btn.setAttribute('aria-expanded', open ? 'false' : 'true');
			});
		});
	}

	/* ---------- تب‌ها ---------- */
	function initTabs(el) {
		var tabs = qsa('.wsl-tab', el);
		var panels = qsa('.wsl-tabpanel', el);
		function activate(i, focus) {
			tabs.forEach(function (t, j) {
				var on = i === j;
				t.classList.toggle('is-active', on);
				t.setAttribute('aria-selected', on ? 'true' : 'false');
				t.tabIndex = on ? 0 : -1;
				if (panels[j]) { panels[j].classList.toggle('is-active', on); panels[j].hidden = !on; }
			});
			if (focus) { tabs[i].focus(); }
		}
		tabs.forEach(function (t, i) {
			t.addEventListener('click', function () { activate(i, false); });
			t.addEventListener('keydown', function (e) {
				var rtl = getComputedStyle(el).direction === 'rtl';
				var next = (e.key === 'ArrowRight') ? (rtl ? -1 : 1) : (e.key === 'ArrowLeft') ? (rtl ? 1 : -1) : 0;
				if (e.key === 'Home') { e.preventDefault(); activate(0, true); }
				else if (e.key === 'End') { e.preventDefault(); activate(tabs.length - 1, true); }
				else if (next) { e.preventDefault(); activate((i + next + tabs.length) % tabs.length, true); }
			});
		});
	}

	/* ---------- اسلایدر ---------- */
	function initSlider(el) {
		var slides = qsa('.wsl-case', el);
		if (slides.length < 2) { return; }
		var idx = 0, timer = null;
		function show(i) {
			idx = (i + slides.length) % slides.length;
			slides.forEach(function (s, j) {
				var on = j === idx;
				s.classList.toggle('is-active', on);
				if (on) { s.removeAttribute('aria-hidden'); } else { s.setAttribute('aria-hidden', 'true'); }
			});
		}
		var prev = qs('[data-wsl-prev]', el), next = qs('[data-wsl-next]', el);
		if (prev) { prev.addEventListener('click', function () { show(idx - 1); restart(); }); }
		if (next) { next.addEventListener('click', function () { show(idx + 1); restart(); }); }
		var sec = parseInt(el.getAttribute('data-autoplay'), 10);
		function stop() { if (timer) { clearInterval(timer); timer = null; } }
		function start() {
			if (sec > 0 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
				timer = setInterval(function () { show(idx + 1); }, sec * 1000);
			}
		}
		function restart() { stop(); start(); }
		el.addEventListener('mouseenter', stop);
		el.addEventListener('mouseleave', start);
		el.addEventListener('focusin', stop);
		el.addEventListener('focusout', start);
		start();
	}

	/* ---------- آمار ---------- */
	function initStats(el) {
		var nums = qsa('.wsl-stat__num', el);
		var fa = el.getAttribute('data-fa') === '1';
		if (el.getAttribute('data-animate') !== '1' || !('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
		function fmt(n, suffix) { var s = String(n) + suffix; return fa ? toFa(s) : s; }
		nums.forEach(function (n) { n.textContent = fmt(0, n.getAttribute('data-suffix') || ''); });
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (en) {
				if (!en.isIntersecting) { return; }
				io.unobserve(en.target);
				var node = en.target, target = parseInt(node.getAttribute('data-count'), 10) || 0, suffix = node.getAttribute('data-suffix') || '', t0 = null, dur = 1200;
				function step(ts) {
					if (t0 === null) { t0 = ts; }
					var p = Math.min(1, (ts - t0) / dur);
					node.textContent = fmt(Math.round(target * (1 - Math.pow(1 - p, 3))), suffix);
					if (p < 1) { requestAnimationFrame(step); }
				}
				requestAnimationFrame(step);
			});
		}, { threshold: 0.3 });
		nums.forEach(function (n) { io.observe(n); });
	}

	/* ---------- فرم‌ها ---------- */
	function initForm(form) {
		form.addEventListener('submit', function (e) {
			if (!window.fetch || !window.FormData || !W.lead) { return; } // ارسال معمولی (admin-post)
			e.preventDefault();
			var msg = qs('.wsl-form__msg', form);
			msg.className = 'wsl-form__msg';
			msg.textContent = W.i18n.sending || '...';
			form.classList.add('is-busy');
			fetch(W.lead, { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
				.then(function (r) { return r.json().then(function (j) { return { ok: r.ok && j.ok, message: j.message }; }, function () { return { ok: false }; }); })
				.then(function (res) {
					form.classList.remove('is-busy');
					if (res.ok) {
						form.reset();
						msg.className = 'wsl-form__msg is-ok';
						msg.textContent = form.getAttribute('data-success') || '';
					} else {
						msg.className = 'wsl-form__msg is-err';
						msg.textContent = res.message || W.i18n.errGeneric || 'Error';
					}
				})
				.catch(function () {
					form.classList.remove('is-busy');
					msg.className = 'wsl-form__msg is-err';
					msg.textContent = W.i18n.errGeneric || 'Error';
				});
		});
	}

	/* ---------- مفید بود / اشتراک‌گذاری ---------- */
	function initActions(el) {
		var id = el.getAttribute('data-id');
		var like = qs('[data-wsl-like]', el);
		var share = qs('[data-wsl-share]', el);
		var key = 'wsl_liked_' + id;

		function markLiked(btn) {
			btn.classList.add('is-liked');
			var t = qs('.wsl-abtn__t', btn);
			if (t) { t.textContent = W.i18n.liked || 'OK'; }
		}
		try { if (like && localStorage.getItem(key)) { markLiked(like); } } catch (e) { /* noop */ }

		if (like) {
			like.addEventListener('click', function () {
				if (like.classList.contains('is-liked')) { return; }
				markLiked(like);
				try { localStorage.setItem(key, '1'); } catch (e) { /* noop */ }
				var fd = new FormData();
				fd.append('post_id', id);
				fetch(W.like, { method: 'POST', body: fd, credentials: 'same-origin' })
					.then(function (r) { return r.json(); })
					.then(function (j) { var c = qs('[data-wsl-count]', like); if (c && j && j.display) { c.textContent = j.display; } })
					.catch(function () { /* خطای شبکه؛ نمایش ظاهری حفظ می‌شود */ });
			});
		}

		if (share) {
			share.addEventListener('click', function () {
				var url = el.getAttribute('data-url'), title = el.getAttribute('data-title');
				var t = qs('.wsl-abtn__t', share), orig = t ? t.textContent : '';
				function done() {
					if (!t) { return; }
					t.textContent = W.i18n.copied || 'Copied';
					setTimeout(function () { t.textContent = orig; }, 2000);
				}
				if (navigator.share) {
					navigator.share({ title: title, url: url }).catch(function () { /* لغو توسط کاربر */ });
				} else if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(url).then(done, function () { window.prompt('', url); });
				} else {
					var ta = doc.createElement('textarea');
					ta.value = url; ta.style.position = 'fixed'; ta.style.opacity = '0';
					doc.body.appendChild(ta); ta.select();
					try { doc.execCommand('copy'); done(); } catch (e) { window.prompt('', url); }
					doc.body.removeChild(ta);
				}
			});
		}
	}

	/* ---------- فهرست مطالب المنتور ---------- */
	function initToc() {
		if (!W.toc || !W.toc.enabled) { return; }
		qsa('.elementor-widget-table-of-contents').forEach(function (widget) {
			var body = qs('.elementor-toc__body', widget);
			if (!body || qs('.ws-toc-expand-button', widget)) { return; }
			var cs = getComputedStyle(widget);
			var limit = parseFloat(cs.getPropertyValue('--ws-toc-collapsed-height')) || parseFloat(getComputedStyle(root).getPropertyValue('--wsl-toc-h')) || 330;
			if (body.scrollHeight <= limit + 5) { return; }
			widget.classList.add('ws-toc-ready');
			var btn = doc.createElement('button');
			btn.type = 'button';
			btn.className = 'ws-toc-expand-button';
			btn.setAttribute('aria-expanded', 'false');
			btn.textContent = W.toc.open;
			body.insertAdjacentElement('afterend', btn);
			btn.addEventListener('click', function () {
				var open = widget.classList.toggle('ws-toc-is-open');
				if (!open) { body.scrollTop = 0; }
				btn.textContent = open ? W.toc.close : W.toc.open;
				btn.setAttribute('aria-expanded', open ? 'true' : 'false');
			});
		});
	}

	/* ---------- تطبیق هوشمند رنگ‌های المنتور در حالت تاریک ---------- */
	function parseColor(c) {
		var m = /rgba?\(([^)]+)\)/.exec(c || '');
		if (!m) { return null; }
		var p = m[1].split(/[\s,\/]+/).filter(Boolean).map(parseFloat);
		return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 };
	}
	function lum(c) {
		function f(v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }
		return 0.2126 * f(c.r) + 0.7152 * f(c.g) + 0.0722 * f(c.b);
	}
	function effBgLum(el) {
		for (var n = el; n && n.nodeType === 1; n = n.parentElement) {
			var cs = getComputedStyle(n), c = parseColor(cs.backgroundColor);
			if (c && c.a > 0.5) { return lum(c); }
			if (cs.backgroundImage && cs.backgroundImage !== 'none') { return -1; } // پس‌زمینه‌ی تصویر/گرادیان: دست نزن
		}
		var pb = parseColor(getComputedStyle(doc.body).backgroundColor);
		return pb && pb.a > 0.5 ? lum(pb) : 0.02;
	}
	var adaptTimer = null, adapting = false;
	function skip(el) { return el.closest('.wsl, .ws-keep-light, [data-wsl-noadapt], #wpadminbar, .elementor-editor-active'); }
	function adaptAll() {
		if (!W.adapt || currentTheme() !== 'dark' || adapting) { return; }
		adapting = true;
		var bgNodes = qsa('.elementor-element, .elementor-widget-container, .elementor-column-wrap, .e-con, .elementor-section, .elementor-post, .elementor-post__text, .elementor-tab-content, .elementor-accordion-item, .elementor-toggle-item, .elementor-icon-box-wrapper, .elementor-image-box-wrapper, .site-header, .site-footer, header, footer, article');
		var txNodes = qsa('.elementor-widget :is(h1,h2,h3,h4,h5,h6,p,span,li,a,label,div.elementor-heading-title,.elementor-icon-list-text,.elementor-button-text,.elementor-post__title,.elementor-post__excerpt,.elementor-tab-title,.elementor-accordion-title,.elementor-toggle-title,.elementor-icon-box-title,.elementor-icon-box-description,.elementor-image-box-title,.elementor-image-box-description,.elementor-counter-number-wrapper,.elementor-counter-title,.elementor-testimonial-content,.elementor-testimonial-name,.elementor-field-label)');
		var svgNodes = qsa('.elementor-icon svg, .elementor-icon-list-icon svg, .elementor-social-icon svg, .elementor-icon-box-icon svg');
		var i = 0, phase = 0, all = [bgNodes, txNodes, svgNodes];
		function chunk() {
			var list = all[phase], end = Math.min(i + 250, list.length);
			for (; i < end; i++) {
				var el = list[i];
				if (skip(el)) { continue; }
				var cs = getComputedStyle(el);
				if (phase === 0) {
					var bg = parseColor(cs.backgroundColor);
					if (bg && bg.a > 0.5 && (!cs.backgroundImage || cs.backgroundImage === 'none') && lum(bg) > 0.55) { el.classList.add('wsl-ad-bg'); }
					var bw = parseFloat(cs.borderTopWidth) + parseFloat(cs.borderLeftWidth) + parseFloat(cs.borderRightWidth) + parseFloat(cs.borderBottomWidth);
					var bc = parseColor(cs.borderTopColor) || parseColor(cs.borderLeftColor);
					if (bw > 0 && bc && bc.a > 0.3 && lum(bc) > 0.6) { el.classList.add('wsl-ad-bd'); }
				} else if (phase === 1) {
					var tc = parseColor(cs.color);
					if (tc && tc.a > 0.3 && lum(tc) < 0.35) {
						var bl = effBgLum(el);
						if (bl >= 0 && bl < 0.3) { el.classList.add('wsl-ad-tx'); }
					}
				} else {
					var fc = parseColor(cs.fill);
					if (fc && fc.a > 0.3 && lum(fc) < 0.35) {
						var b2 = effBgLum(el.parentElement);
						if (b2 >= 0 && b2 < 0.3) { el.classList.add('wsl-ad-fill'); }
					}
				}
			}
			if (i >= list.length) { phase++; i = 0; }
			if (phase < 3) { (window.requestIdleCallback || function (f) { return setTimeout(f, 16); })(chunk); } else { adapting = false; }
		}
		chunk();
	}
	function scheduleAdapt(delay) {
		if (!W.adapt) { return; }
		clearTimeout(adaptTimer);
		adaptTimer = setTimeout(adaptAll, delay || 150);
	}
	doc.addEventListener('wsl:themechange', function () { scheduleAdapt(30); });
	if (W.adapt && 'MutationObserver' in window) {
		var mo = new MutationObserver(function (muts) {
			for (var k = 0; k < muts.length; k++) { if (muts[k].addedNodes && muts[k].addedNodes.length) { scheduleAdapt(500); return; } }
		});
		doc.addEventListener('DOMContentLoaded', function () { mo.observe(doc.body, { childList: true, subtree: true }); });
	}

	/* ---------- راه‌اندازی ---------- */
	function init(ctx) {
		ctx = ctx || doc;
		var list = qsa('[data-wsl]', ctx);
		if (ctx.nodeType === 1 && ctx.matches && ctx.matches('[data-wsl]')) { list.push(ctx); }
		list.forEach(function (el) {
			if (el.__wslInit) { return; }
			el.__wslInit = true;
			switch (el.getAttribute('data-wsl')) {
				case 'faq': initFaq(el); break;
				case 'tabs': initTabs(el); break;
				case 'slider': initSlider(el); break;
				case 'stats': initStats(el); break;
				case 'form': initForm(el); break;
				case 'actions': initActions(el); break;
			}
		});
		syncToggles();
	}

	function boot() {
		init(doc);
		scheduleAdapt(100);
		initToc();
		setTimeout(initToc, 800);
		setTimeout(initToc, 2500);
	}

	if (doc.readyState === 'loading') { doc.addEventListener('DOMContentLoaded', boot); } else { boot(); }
	window.addEventListener('load', function () { initToc(); scheduleAdapt(100); });
	window.WSLegal = { init: init, setTheme: setTheme, getTheme: currentTheme };

	// المنتور (ویرایشگر / پیش‌نمایش زنده)
	if (window.jQuery) {
		window.jQuery(window).on('elementor/frontend/init', function () {
			if (window.elementorFrontend && window.elementorFrontend.hooks) {
				window.elementorFrontend.hooks.addAction('frontend/element_ready/shortcode.default', function ($el) { init($el[0]); });
			}
		});
	}
})();
