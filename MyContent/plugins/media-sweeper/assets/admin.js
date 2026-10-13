/* Media Sweeper Pro – admin UI (vanilla JS, no innerHTML with data) */
(function () {
	'use strict';

	var root = document.getElementById('msw-app');
	if (!root || !window.MSW) { return; }
	var CFG = window.MSW;

	/* ------------------------------------------------------------ helpers */

	function h(tag, props) {
		var el = document.createElement(tag);
		props = props || {};
		Object.keys(props).forEach(function (k) {
			var v = props[k];
			if (v === null || v === undefined || v === false) { return; }
			if (k === 'class') { el.className = v; }
			else if (k.indexOf('on') === 0) { el.addEventListener(k.slice(2).toLowerCase(), v); }
			else if (k === 'value' || k === 'checked' || k === 'selected' || k === 'disabled') { el[k] = v; }
			else if (v === true) { el.setAttribute(k, ''); }
			else { el.setAttribute(k, v); }
		});
		for (var i = 2; i < arguments.length; i++) { add(el, arguments[i]); }
		return el;
	}
	function add(el, c) {
		if (c === null || c === undefined || c === false) { return; }
		if (Array.isArray(c)) { c.forEach(function (x) { add(el, x); }); return; }
		el.appendChild(c.nodeType ? c : document.createTextNode(String(c)));
	}
	function clear(el) { while (el.firstChild) { el.removeChild(el.firstChild); } return el; }
	function num(n) { return Number(n || 0).toLocaleString(); }

	function params(obj, prefix, out) {
		out = out || new URLSearchParams();
		Object.keys(obj).forEach(function (k) {
			var v = obj[k], key = prefix ? prefix + '[' + k + ']' : k;
			if (v === undefined || v === null) { return; }
			if (Array.isArray(v)) { v.forEach(function (x) { out.append(key + '[]', x); }); }
			else if (typeof v === 'object') { params(v, key, out); }
			else if (typeof v === 'boolean') { out.append(key, v ? '1' : '0'); }
			else { out.append(key, v); }
		});
		return out;
	}

	function api(action, data) {
		var body = params(Object.assign({ action: 'msw', nonce: CFG.nonce, do: action }, data || {}));
		return fetch(CFG.ajax, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.text(); })
			.then(function (t) {
				var j;
				try { j = JSON.parse(t); } catch (e) { throw new Error('پاسخ نامعتبر از سرور (احتمالاً خطای PHP یا محدودیت زمان): ' + t.slice(0, 160)); }
				if (!j.success) { throw new Error((j.data && j.data.message) || 'خطا'); }
				return j.data;
			});
	}

	function toast(msg, kind) {
		var t = h('div', { class: 'msw-toast ' + (kind || '') }, msg);
		document.body.appendChild(t);
		setTimeout(function () { t.classList.add('show'); }, 10);
		setTimeout(function () { t.classList.remove('show'); setTimeout(function () { t.remove(); }, 300); }, kind === 'err' ? 7000 : 3500);
	}

	var ICON = { image: '🖼️', video: '🎬', audio: '🎵', doc: '📄' };
	var TABS = [
		['dashboard', 'داشبورد'],
		['unused', 'بدون استفاده'],
		['orphans', 'فایل‌های یتیم'],
		['duplicates', 'تکراری‌ها'],
		['broken', 'لینک‌های خراب'],
		['quarantine', 'قرنطینه'],
		['webp', 'WebP و بهینه‌سازی'],
		['settings', 'تنظیمات'],
		['log', 'گزارش']
	];

	var state = { tab: 'dashboard', dash: null };
	var view = null;     // current tab container
	var navEl = null;

	/* --------------------------------------------------------- job / progress */

	function jobBox(opts) {
		// opts: {onDone, title}
		var bar = h('div', { class: 'msw-bar-fill' });
		var label = h('div', { class: 'msw-job-label' }, 'در حال شروع…');
		var detail = h('div', { class: 'msw-job-detail' });
		var counts = h('div', { class: 'msw-job-counts' });
		var cancel = h('button', { class: 'button', type: 'button' }, 'لغو');
		var box = h('div', { class: 'msw-job' },
			h('div', { class: 'msw-job-head' }, h('strong', null, opts.title || 'در حال انجام…'), cancel),
			h('div', { class: 'msw-bar' }, bar), label, detail, counts,
			h('p', { class: 'description' }, 'این صفحه را باز نگه دارید؛ در صورت بستن، عملیات از همان نقطه با ورود دوباره ادامه می‌یابد.'));
		var stopped = false;
		cancel.addEventListener('click', function () {
			cancel.disabled = true;
			api('job_cancel').then(function () { stopped = true; });
		});
		function paint(s) {
			bar.style.width = (s.pct || 0) + '%';
			label.textContent = (s.pct || 0) + '٪ — ' + (s.label || '');
			detail.textContent = s.detail || '';
			var c = s.counts || {}, parts = [];
			if (c.total) { parts.push('کل: ' + num(c.total)); }
			if (c.converted !== undefined) { parts.push('تبدیل‌شده: ' + num(c.converted)); }
			if (c.skipped) { parts.push('ردشده: ' + num(c.skipped)); }
			if (c.failed) { parts.push('ناموفق: ' + num(c.failed)); }
			if (c.saved) { parts.push('صرفه‌جویی: ' + (c.saved / 1048576).toFixed(1) + ' MB'); }
			if (c.rewritten) { parts.push('ردیف اصلاح‌شده: ' + num(c.rewritten)); }
			counts.textContent = parts.join(' | ');
		}
		function loop() {
			if (stopped) { return finish({ status: 'cancelled' }); }
			api('job_tick').then(function (s) {
				paint(s);
				if (s.status === 'running') { setTimeout(loop, 150); } else { finish(s); }
			}).catch(function (e) {
				label.textContent = 'خطای ارتباط: ' + e.message + ' — تلاش مجدد…';
				setTimeout(loop, 3000);
			});
		}
		function finish(s) {
			if (s.status === 'done') { toast('عملیات با موفقیت پایان یافت.', 'ok'); }
			else if (s.status === 'cancelled') { toast('عملیات لغو شد.', 'warn'); }
			else { toast('عملیات متوقف شد: ' + (s.msg || s.status), 'err'); }
			if (opts.onDone) { opts.onDone(s); }
		}
		box.start = function (initial) { if (initial) { paint(initial); } loop(); };
		return box;
	}

	/* ----------------------------------------------------------- bulk ops */

	function runOp(op, ids, done, chunkSize) {
		chunkSize = chunkSize || 15;
		var total = ids.length, ok = 0, bytes = 0, failed = [], pos = 0;
		var bar = h('div', { class: 'msw-bar-fill' });
		var msg = h('div', null, '0 / ' + num(total));
		var ov = h('div', { class: 'msw-overlay' }, h('div', { class: 'msw-modal small' },
			h('h3', null, 'در حال اجرا…'), h('div', { class: 'msw-bar' }, bar), msg));
		document.body.appendChild(ov);
		function next() {
			if (pos >= total) {
				ov.remove();
				var text = num(ok) + ' مورد انجام شد' + (bytes ? ' (' + (bytes / 1048576).toFixed(1) + ' MB)' : '') + '.';
				if (failed.length) { text += ' ' + num(failed.length) + ' مورد ناموفق/ردشده: ' + failed.slice(0, 3).map(function (f) { return f.msg; }).join(' | '); }
				toast(text, failed.length ? 'warn' : 'ok');
				return done && done();
			}
			var part = ids.slice(pos, pos + chunkSize);
			api('act', { op: op, ids: part }).then(function (r) {
				ok += r.ok; bytes += r.bytes || 0; failed = failed.concat(r.failed || []);
				pos += chunkSize;
				var p = Math.min(100, Math.round(pos / total * 100));
				bar.style.width = p + '%';
				msg.textContent = num(Math.min(pos, total)) + ' / ' + num(total);
				next();
			}).catch(function (e) {
				ov.remove();
				toast(e.message, 'err');
				if (done) { done(); }
			});
		}
		next();
	}

	/* -------------------------------------------------------- lightbox */

	function lightbox(items, index, hooks) {
		var i = index;
		var ov = h('div', { class: 'msw-overlay msw-lb' });
		function key(e) {
			if (e.key === 'Escape') { close(); }
			var next = CFG.rtl ? 'ArrowLeft' : 'ArrowRight', prev = CFG.rtl ? 'ArrowRight' : 'ArrowLeft';
			if (e.key === next) { go(1); } else if (e.key === prev) { go(-1); }
		}
		function close() { document.removeEventListener('keydown', key); ov.remove(); }
		function go(d) { var n = i + d; if (n >= 0 && n < items.length) { i = n; paint(); } }
		function row(k, v, ltr) { return v ? h('tr', null, h('th', null, k), h('td', ltr ? { dir: 'ltr', style: 'text-align:start' } : null, v)) : null; }
		function paint() {
			var it = items[i];
			clear(ov);
			var media;
			if (it.type === 'video' && it.url) { media = h('video', { src: it.url, controls: true, preload: 'metadata', class: 'msw-lb-media' }); }
			else if (it.type === 'audio' && it.url) { media = h('audio', { src: it.url, controls: true, class: 'msw-lb-audio' }); }
			else if (it.type === 'image' && (it.url || it.thumb)) { media = h('img', { src: it.url || it.thumb, class: 'msw-lb-media', alt: '' }); }
			else { media = h('div', { class: 'msw-lb-icon' }, ICON[it.type] || '📄'); }
			var usageBox = h('div', { class: 'msw-usage' });
			var rows = h('table', { class: 'msw-meta' },
				row('نام', it.title || it.file), row('فایل', it.rel, true), row('نوع', it.mime || it.type),
				row('حجم (با تمام سایزها)', it.size), row('ابعاد', it.w ? it.w + '×' + it.h : ''), row('تاریخ', it.date),
				it.missing ? row('وضعیت', 'فایل روی دیسک وجود ندارد') : null,
				it.used_in ? h('tr', null, h('th', null, 'استفاده در'), h('td', null, it.used_in.link ? h('a', { href: it.used_in.link, target: '_blank', rel: 'noopener' }, it.used_in.label) : it.used_in.label)) : null,
				it.parent ? h('tr', null, h('th', null, 'آپلود شده در'), h('td', null, h('a', { href: it.parent.link, target: '_blank', rel: 'noopener' }, it.parent.title || ('#' + it.parent.id)))) : null);
			var actions = h('div', { class: 'msw-lb-actions' });
			if (it.id && it.status === 'unused' && hooks && hooks.actions) {
				hooks.actions.forEach(function (a) {
					actions.appendChild(h('button', { class: 'button ' + (a.cls || ''), type: 'button', onClick: function () { close(); a.fn([it]); } }, a.label));
				});
			}
			if (it.id) {
				actions.appendChild(h('button', { class: 'button', type: 'button', onClick: function (e) {
					e.target.disabled = true;
					clear(usageBox).appendChild(h('em', null, 'در حال جست‌وجو در دیتابیس…'));
					api('usage', { id: it.id }).then(function (r) {
						clear(usageBox);
						if (!r.usage.length) { usageBox.appendChild(h('p', { class: 'ok' }, '✔ در جست‌وجوی زنده هیچ ارجاعی پیدا نشد.')); return; }
						usageBox.appendChild(h('p', { class: 'warn' }, '⚠ ارجاع‌های احتمالی پیدا شد (قبل از حذف بررسی کنید):'));
						usageBox.appendChild(h('ul', null, r.usage.map(function (u) {
							return h('li', null, u.where + ': ', u.link ? h('a', { href: u.link, target: '_blank', rel: 'noopener' }, u.label) : u.label);
						})));
					}).catch(function (er) { clear(usageBox).appendChild(h('p', { class: 'warn' }, er.message)); });
				} }, '🔍 بررسی زنده ارجاع‌ها'));
				if (it.edit) { actions.appendChild(h('a', { class: 'button', href: it.edit, target: '_blank', rel: 'noopener' }, 'ویرایش در کتابخانه')); }
			}
			if (it.url) { actions.appendChild(h('a', { class: 'button', href: it.url, target: '_blank', rel: 'noopener' }, 'باز کردن فایل')); }
			ov.appendChild(h('div', { class: 'msw-lb-box' },
				h('button', { class: 'msw-lb-x', type: 'button', onClick: close, 'aria-label': 'بستن' }, '✕'),
				h('button', { class: 'msw-lb-nav prev', type: 'button', onClick: function () { go(-1); }, disabled: i === 0 }, CFG.rtl ? '›' : '‹'),
				h('button', { class: 'msw-lb-nav next', type: 'button', onClick: function () { go(1); }, disabled: i === items.length - 1 }, CFG.rtl ? '‹' : '›'),
				h('div', { class: 'msw-lb-stage' }, media),
				h('div', { class: 'msw-lb-side' }, rows, actions, usageBox, h('div', { class: 'msw-lb-count' }, (i + 1) + ' / ' + items.length))));
		}
		ov.addEventListener('click', function (e) { if (e.target === ov) { close(); } });
		document.addEventListener('keydown', key);
		document.body.appendChild(ov);
		paint();
	}

	/* ------------------------------------------------------ media browser */

	/**
	 * Generic grid with filters, selection, pagination and bulk actions.
	 * cfg: { tab, idOf(item), card(item), bulk:[{label, op, cls, confirm, danger}], filters:fn(browser)->nodes, hint }
	 */
	function browser(cfg) {
		var B = { f: Object.assign({ page: 1 }, cfg.defaults || {}), sel: {}, allMatching: false, data: null };
		var wrap = h('div', { class: 'msw-browser' });
		var bar = h('div', { class: 'msw-filters' });
		var info = h('div', { class: 'msw-info' });
		var tools = h('div', { class: 'msw-tools' });
		var grid = h('div', { class: 'msw-grid' });
		var pager = h('div', { class: 'msw-pager' });
		wrap.appendChild(bar); wrap.appendChild(info); wrap.appendChild(tools); wrap.appendChild(grid); wrap.appendChild(pager);

		function selIds() { return Object.keys(B.sel); }
		function reload(keepPage) {
			if (!keepPage) { B.f.page = 1; }
			clear(grid).appendChild(h('p', { class: 'msw-loading' }, 'در حال بارگذاری…'));
			api('list', { tab: cfg.tab, f: B.f }).then(function (d) { B.data = d; if (cfg.onData) { cfg.onData(d, B); } paint(); })
				.catch(function (e) { clear(grid).appendChild(h('p', { class: 'warn' }, e.message)); });
		}
		function setSel(id, on) { if (on) { B.sel[id] = true; } else { delete B.sel[id]; B.allMatching = false; } paintTools(); }

		function paintFilters() {
			clear(bar);
			cfg.filters(B).forEach(function (n) { bar.appendChild(n); });
		}
		function paintTools() {
			clear(tools);
			var d = B.data; if (!d) { return; }
			var n = B.allMatching ? d.total : selIds().length;
			tools.appendChild(h('label', { class: 'msw-check-all' }, h('input', { type: 'checkbox', checked: d.items.length > 0 && d.items.every(function (it) { return B.sel[cfg.idOf(it)]; }), onChange: function (e) {
				d.items.forEach(function (it) { if (e.target.checked) { B.sel[cfg.idOf(it)] = true; } else { delete B.sel[cfg.idOf(it)]; } });
				B.allMatching = false; paintGridChecks(); paintTools();
			} }), ' انتخاب همین صفحه'));
			if (d.total > d.items.length) {
				tools.appendChild(h('button', { class: 'button-link', type: 'button', onClick: function () { B.allMatching = true; d.items.forEach(function (it) { B.sel[cfg.idOf(it)] = true; }); paintGridChecks(); paintTools(); } }, 'انتخاب هر ' + num(d.total) + ' مورد این فیلتر'));
			}
			tools.appendChild(h('span', { class: 'msw-selcount' }, n ? num(n) + ' مورد انتخاب شد' : ''));
			(cfg.bulk || []).forEach(function (b) {
				tools.appendChild(h('button', { class: 'button ' + (b.cls || ''), type: 'button', disabled: n === 0, onClick: function () { doBulk(b); } }, b.label));
			});
			if (cfg.exportable) {
				tools.appendChild(h('a', { class: 'button', href: CFG.ajax + '?' + params({ action: 'msw', do: 'export', nonce: CFG.nonce, tab: cfg.tab, f: B.f }).toString() }, '⬇ CSV'));
			}
		}
		function fetchIds() {
			if (!B.allMatching) { return Promise.resolve(selIds()); }
			return api('ids', { tab: cfg.tab, f: B.f }).then(function (r) { return r.ids; });
		}
		function doBulk(b) {
			fetchIds().then(function (ids) {
				if (!ids.length) { return; }
				if (b.confirm && !window.confirm(b.confirm.replace('{n}', num(ids.length)))) { return; }
				if (b.typed) {
					var t = window.prompt('این کار برگشت‌پذیر نیست. برای تأیید، عبارت DELETE را تایپ کنید:');
					if (t !== 'DELETE') { return; }
				}
				runOp(b.op, ids, function () { B.sel = {}; B.allMatching = false; reload(true); refreshDash(); });
			});
		}
		function paintGridChecks() {
			Array.prototype.forEach.call(grid.querySelectorAll('input.msw-cb'), function (cb) { cb.checked = !!B.sel[cb.getAttribute('data-id')]; cb.closest('.msw-card').classList.toggle('sel', cb.checked); });
		}
		function singleAction(items, b) {
			if (b.confirm && !window.confirm(b.confirm.replace('{n}', num(items.length)))) { return; }
			if (b.typed && window.prompt('برای تأیید، عبارت DELETE را تایپ کنید:') !== 'DELETE') { return; }
			runOp(b.op, items.map(cfg.idOf), function () { reload(true); refreshDash(); });
		}
		function paint() {
			var d = B.data;
			paintFilters();
			clear(info);
			info.appendChild(h('span', null, num(d.total) + ' مورد', d.total_size ? ' — ' + d.total_size : ''));
			if (cfg.hint) { info.appendChild(h('span', { class: 'msw-hint' }, cfg.hint)); }
			clear(grid);
			if (!d.items.length) { grid.appendChild(h('p', { class: 'msw-empty' }, cfg.empty || 'موردی یافت نشد.')); }
			d.items.forEach(function (it, idx) {
				var id = cfg.idOf(it);
				var cb = h('input', { type: 'checkbox', class: 'msw-cb', 'data-id': id, checked: !!B.sel[id], onChange: function (e) { setSel(id, e.target.checked); e.target.closest('.msw-card').classList.toggle('sel', e.target.checked); } });
				var card = cfg.card(it, function () {
					lightbox(d.items.map(cfg.lbItem || function (x) { return x; }), idx, { actions: (cfg.bulk || []).filter(function (b) { return b.single; }).map(function (b) { return { label: b.label, cls: b.cls, fn: function (items) { singleAction(items, b); } }; }) });
				});
				card.insertBefore(cb, card.firstChild);
				if (B.sel[id]) { card.classList.add('sel'); }
				grid.appendChild(card);
			});
			paintTools();
			clear(pager);
			if (d.pages > 1) {
				pager.appendChild(h('button', { class: 'button', disabled: d.page <= 1, onClick: function () { B.f.page = d.page - 1; reload(true); } }, 'قبلی'));
				pager.appendChild(h('span', null, ' صفحه ' + num(d.page) + ' از ' + num(d.pages) + ' '));
				pager.appendChild(h('button', { class: 'button', disabled: d.page >= d.pages, onClick: function () { B.f.page = d.page + 1; reload(true); } }, 'بعدی'));
			}
		}
		B.el = wrap; B.reload = reload; B.paintFilters = paintFilters;
		reload();
		return B;
	}

	function selectEl(options, value, onChange) {
		return h('select', { onChange: function (e) { onChange(e.target.value); } }, options.map(function (o) {
			return h('option', { value: o[0], selected: String(o[0]) === String(value) }, o[1]);
		}));
	}
	function debounce(fn, ms) { var t; return function () { var a = arguments, c = this; clearTimeout(t); t = setTimeout(function () { fn.apply(c, a); }, ms); }; }

	function mediaCard(it, open) {
		var thumb = it.thumb
			? h('img', { src: it.thumb, loading: 'lazy', alt: '', onError: function (e) { e.target.replaceWith(h('span', { class: 'msw-ico' }, ICON[it.type] || '📄')); } })
			: (it.type === 'video' && it.url
				? h('video', { src: it.url + '#t=0.5', preload: 'metadata', muted: true })
				: h('span', { class: 'msw-ico' }, ICON[it.type] || '📄'));
		var badges = h('div', { class: 'msw-badges' },
			h('span', { class: 'msw-b' }, it.size),
			it.locked ? h('span', { class: 'msw-b ' + (it.locked === 'protected' ? 'prot' : 'rec') }, it.locked === 'protected' ? '🔒 محافظت‌شده' : '🕒 جدید') : null,
			it.missing ? h('span', { class: 'msw-b bad' }, 'فایل ندارد') : null,
			it.status === 'used' ? h('span', { class: 'msw-b ok' }, 'در حال استفاده') : null);
		return h('div', { class: 'msw-card' },
			h('button', { class: 'msw-thumb', type: 'button', onClick: open, title: 'پیش‌نمایش' }, thumb, h('span', { class: 'msw-type' }, ICON[it.type] || '')),
			badges,
			h('div', { class: 'msw-name', title: it.rel }, it.file || it.title),
			h('div', { class: 'msw-sub' }, [it.w ? it.w + '×' + it.h : '', it.date].filter(Boolean).join(' • ')));
	}

	/* ------------------------------------------------------------- tabs */

	function refreshDash() { return api('dashboard').then(function (d) { state.dash = d; return d; }); }

	function stat(label, value, sub, cls) {
		return h('div', { class: 'msw-stat ' + (cls || '') }, h('div', { class: 'msw-stat-v' }, value), h('div', { class: 'msw-stat-l' }, label), sub ? h('div', { class: 'msw-stat-s' }, sub) : null);
	}

	function tabDashboard(el) {
		clear(el).appendChild(h('p', { class: 'msw-loading' }, 'در حال بارگذاری…'));
		refreshDash().then(function (d) {
			clear(el);
			var s = d.scan, caps = d.caps;
			var box = h('div', { class: 'msw-scanbox' });
			var optOrph = h('input', { type: 'checkbox', checked: !!CFG.settings.scan_orphans });
			var optDup = h('input', { type: 'checkbox', checked: !!CFG.settings.detect_duplicates });
			var optBg = h('input', { type: 'checkbox' });
			function startScan() {
				clear(box);
				var jb = jobBox({ title: 'اسکن کامل سایت', onDone: function () { tabDashboard(el); } });
				box.appendChild(jb);
				api('job_start', { type: 'scan', opts: { orphans: optOrph.checked, duplicates: optDup.checked, bg: optBg.checked } })
					.then(function (st) { jb.start(st); }).catch(function (e) { toast(e.message, 'err'); tabDashboard(el); });
			}
			function resume() {
				clear(box);
				var jb = jobBox({ title: d.job.type === 'scan' ? 'اسکن کامل سایت' : 'تبدیل WebP', onDone: function () { tabDashboard(el); } });
				box.appendChild(jb); jb.start(d.job);
			}
			if (d.job && d.job.status === 'running') { resume(); }
			else {
				box.appendChild(h('div', { class: 'msw-scan-actions' },
					h('button', { class: 'button button-primary button-hero', type: 'button', onClick: startScan }, s.time ? '🔄 اسکن مجدد' : '🔍 شروع اسکن'),
					h('div', { class: 'msw-scan-opts' },
						h('label', null, optOrph, ' جست‌وجوی فایل‌های یتیم در پوشه uploads'),
						h('label', null, optDup, ' تشخیص فایل‌های تکراری (کندتر)'),
						h('label', null, optBg, ' اجرا در پس‌زمینه (WP-Cron) هم ادامه یابد'))));
				if (d.job && (d.job.status === 'error' || d.job.status === 'cancelled')) { box.appendChild(h('p', { class: 'warn' }, 'آخرین عملیات: ' + d.job.status + ' ' + (d.job.msg || ''))); }
			}
			var warn = [];
			if (!caps.engine) { warn.push('هیچ موتور WebP (Imagick با پشتیبانی WebP یا GD با imagewebp) روی سرور فعال نیست؛ تبدیل WebP کار نخواهد کرد. با هاستینگ تماس بگیرید.'); }
			el.appendChild(h('div', { class: 'msw-hero' },
				h('div', null, h('h2', null, 'پاک‌سازی و بهینه‌سازی رسانه'),
					h('p', null, s.time ? 'آخرین اسکن: ' + s.time + ' (' + s.ago + ' پیش، ' + s.duration + ' ثانیه)' : 'هنوز اسکنی انجام نشده است. ابتدا اسکن را اجرا کنید.')),
				box));
			warn.forEach(function (w) { el.appendChild(h('div', { class: 'msw-notice warn' }, w)); });
			if (s.time) {
				el.appendChild(h('div', { class: 'msw-stats' },
					stat('بدون استفاده', num(s.unused), s.unused_size + ' قابل آزادسازی', 'primary'),
					stat('فایل‌های یتیم', num(s.orphans), s.orphan_size + ' خارج از کتابخانه'),
					stat('کل رسانه‌ها', num(s.total), s.total_size),
					stat('در حال استفاده', num(s.used), s.locked ? num(s.locked) + ' محافظت‌شده/جدید' : ''),
					stat('در قرنطینه', num(d.quarantine.count), d.quarantine.size),
					stat('صرفه‌جویی WebP', d.webp.saved, num(d.webp.count) + ' تصویر'),
					stat('فضای خالی هاست', d.free || '—', ''),
					stat('فایل مفقود', num(s.missing), 'رکورد دارد، فایل ندارد'),
					stat('لینک خراب', num(s.broken), 'ارجاع به فایل ناموجود')));
				el.appendChild(h('div', { class: 'msw-quick' },
					h('button', { class: 'button', onClick: function () { go('unused'); } }, 'مشاهدهٔ ' + num(s.unused) + ' مورد بدون استفاده'),
					h('button', { class: 'button', onClick: function () { go('orphans'); } }, 'فایل‌های یتیم'),
					h('button', { class: 'button', onClick: function () { go('webp'); } }, 'تبدیل به WebP')));
			}
			el.appendChild(h('details', { class: 'msw-how' }, h('summary', null, 'این ابزار چطور کار می‌کند؟ (مهم)'),
				h('ul', null,
					h('li', null, 'اسکن، «همه جا» را می‌گردد: محتوای نوشته‌ها، متای المنتور/ووکامرس/ACF، ویجت‌ها، تنظیمات، جدول‌های افزونه‌ها و فایل‌های قالب (لینک‌های مستقیم و شناسهٔ پیوست).'),
					h('li', null, 'هیچ‌چیز بدون تأیید حذف نمی‌شود. «قرنطینه» فایل‌ها را فقط جابه‌جا می‌کند و هر زمان قابل بازگردانی است.'),
					h('li', null, 'رسانه‌های جدید (پیش‌فرض ۱۴ روز) و موارد «محافظت‌شده» هرگز پیشنهاد حذف نمی‌شوند.'),
					h('li', null, 'ارجاع‌هایی که هیچ‌جای سایت ثبت نشده‌اند (مثلاً لینک مستقیم در ایمیل، شبکه‌های اجتماعی یا سایت‌های دیگر) قابل تشخیص نیستند؛ قبل از حذف دائمی از سرچ‌کنسول (گزارش تصاویر) و ترافیک 404 مطمئن شوید.'),
					h('li', null, 'پیش از هر عملیات بزرگ، بک‌آپ کامل (فایل + دیتابیس) بگیرید.'))));
		}).catch(function (e) { clear(el).appendChild(h('p', { class: 'warn' }, e.message)); });
	}

	function mediaBrowserCfg(tab) {
		return {
			tab: tab, idOf: function (it) { return it.id; }, card: mediaCard, exportable: true,
			defaults: { status: 'unused', orderby: 'bytes', order: 'desc', per_page: 48 },
			empty: 'موردی با این فیلتر یافت نشد. اگر اسکن نکرده‌اید، از داشبورد اسکن را اجرا کنید.',
			bulk: [
				{ label: '📦 انتقال به قرنطینه', op: 'quarantine', cls: 'button-primary', single: true, confirm: '{n} مورد به قرنطینه منتقل شود؟ (قابل بازگردانی)' },
				{ label: '🔒 محافظت', op: 'protect' },
				{ label: '🗑 حذف دائمی', op: 'delete', cls: 'msw-danger', single: true, typed: true, confirm: '{n} مورد برای همیشه حذف شود؟' }
			]
		};
	}

	function tabUnused(el) {
		var cfg = mediaBrowserCfg('unused');
		cfg.filters = function (B) {
			var f = B.f, years = (B.data && B.data.years) || [];
			var q = h('input', { type: 'search', placeholder: 'جست‌وجوی نام فایل یا عنوان…', value: f.q || '', onInput: debounce(function (e) { f.q = e.target.value; B.reload(); }, 400) });
			return [
				selectEl([['unused', 'بدون استفاده'], ['used', 'در حال استفاده'], ['skipped', 'خارج از محدودهٔ اسکن']], f.status, function (v) { f.status = v; f.locked = ''; B.reload(); }),
				selectEl([['', 'همه نوع'], ['image', 'تصویر'], ['video', 'ویدئو'], ['audio', 'صدا'], ['doc', 'سند']], f.type || '', function (v) { f.type = v; B.reload(); }),
				selectEl([['', 'جدید/محافظت‌شده پنهان'], ['any', 'نمایش همه'], ['protected', 'فقط محافظت‌شده'], ['recent', 'فقط جدیدها']], f.locked || '', function (v) { f.locked = v; B.reload(); }),
				selectEl([['', 'همه سال‌ها']].concat(years.map(function (y) { return [y, y]; })), f.year || '', function (v) { f.year = v; B.reload(); }),
				h('input', { type: 'number', min: '0', step: '0.1', placeholder: 'حداقل MB', value: f.min_mb || '', class: 'small-text', onInput: debounce(function (e) { f.min_mb = e.target.value; B.reload(); }, 500) }),
				h('input', { type: 'date', title: 'آپلودشده قبل از', value: f.before || '', onChange: function (e) { f.before = e.target.value; B.reload(); } }),
				selectEl([['bytes:desc', 'بزرگ‌ترین'], ['bytes:asc', 'کوچک‌ترین'], ['uploaded:asc', 'قدیمی‌ترین'], ['uploaded:desc', 'جدیدترین'], ['name:asc', 'نام']], (f.orderby || 'bytes') + ':' + (f.order || 'desc'), function (v) { var p = v.split(':'); f.orderby = p[0]; f.order = p[1]; B.reload(); }),
				h('label', null, h('input', { type: 'checkbox', checked: !!f.missing, onChange: function (e) { f.missing = e.target.checked; B.reload(); } }), ' فقط فایل‌های مفقود'),
				q
			];
		};
		clear(el).appendChild(h('div', { class: 'msw-notice' }, 'روی هر تصویر کلیک کنید تا پیش‌نمایش بزرگ (ویدئو/صدا قابل پخش) و جزئیات را ببینید. برای بررسی دوباره از «بررسی زنده ارجاع‌ها» استفاده کنید. توصیه: ابتدا «قرنطینه»، بعد از چند روز بررسی سایت، حذف دائمی.'));
		el.appendChild(browser(cfg).el);
	}

	function tabOrphans(el) {
		var cfg = {
			tab: 'orphans', idOf: function (it) { return it.hash; }, exportable: true,
			defaults: { status: 'orphan', orderby: 'bytes', order: 'desc', per_page: 48 },
			empty: 'فایل یتیمی یافت نشد (یا اسکن با گزینهٔ فایل‌های یتیم انجام نشده).',
			hint: 'فایل‌هایی داخل uploads که در کتابخانهٔ رسانه ثبت نیستند و جایی هم به آن‌ها ارجاع داده نشده است.',
			card: function (it, open) {
				var thumb = it.thumb ? h('img', { src: it.thumb, loading: 'lazy', alt: '' }) : h('span', { class: 'msw-ico' }, ICON[it.type] || '📄');
				return h('div', { class: 'msw-card' }, h('button', { class: 'msw-thumb', type: 'button', onClick: open }, thumb, h('span', { class: 'msw-type' }, ICON[it.type] || '')),
					h('div', { class: 'msw-badges' }, h('span', { class: 'msw-b' }, it.size)),
					h('div', { class: 'msw-name', title: it.rel }, it.file), h('div', { class: 'msw-sub' }, it.rel));
			},
			lbItem: function (it) { return { id: 0, status: '', title: it.file, rel: it.rel, url: it.url, thumb: it.thumb, type: it.type, size: it.size, date: it.date, mime: '' }; },
			bulk: [
				{ label: '📦 قرنطینه', op: 'orphan_quarantine', cls: 'button-primary', confirm: '{n} فایل یتیم به قرنطینه برود؟' },
				{ label: '🗑 حذف دائمی', op: 'orphan_delete', cls: 'msw-danger', typed: true, confirm: '{n} فایل برای همیشه حذف شود؟' }
			]
		};
		cfg.filters = function (B) {
			var f = B.f;
			var isQ = f.status === 'quarantined';
			cfg.bulk = isQ
				? [{ label: '↩ بازگردانی', op: 'orphan_restore', cls: 'button-primary' }, { label: '🗑 حذف دائمی', op: 'orphan_purge', cls: 'msw-danger', typed: true, confirm: '{n} فایل برای همیشه حذف شود؟' }]
				: [{ label: '📦 قرنطینه', op: 'orphan_quarantine', cls: 'button-primary', confirm: '{n} فایل یتیم به قرنطینه برود؟' }, { label: '🗑 حذف دائمی', op: 'orphan_delete', cls: 'msw-danger', typed: true, confirm: '{n} فایل برای همیشه حذف شود؟' }];
			return [
				selectEl([['orphan', 'یتیم‌ها'], ['quarantined', 'قرنطینهٔ یتیم‌ها']], f.status, function (v) { f.status = v; B.reload(); }),
				selectEl([['bytes:desc', 'بزرگ‌ترین'], ['bytes:asc', 'کوچک‌ترین'], ['mtime:asc', 'قدیمی‌ترین'], ['name:asc', 'نام']], (f.orderby || 'bytes') + ':' + (f.order || 'desc'), function (v) { var p = v.split(':'); f.orderby = p[0]; f.order = p[1]; B.reload(); }),
				h('input', { type: 'search', placeholder: 'جست‌وجوی مسیر…', value: f.q || '', onInput: debounce(function (e) { f.q = e.target.value; B.reload(); }, 400) })
			];
		};
		clear(el).appendChild(h('div', { class: 'msw-notice' }, 'فایل‌های یتیم معمولاً باقی‌ماندهٔ افزونه‌های قدیمی، آپلود دستی FTP یا تصاویر حذف‌شده‌اند. نسخه‌های ساخته‌شده توسط افزونه‌های WebP (foo.jpg.webp) به‌عنوان یتیم شناسایی نمی‌شوند.'));
		el.appendChild(browser(cfg).el);
	}

	function tabQuarantine(el) {
		var cfg = {
			tab: 'quarantine', idOf: function (it) { return it.id; }, defaults: { per_page: 48 },
			empty: 'قرنطینه خالی است.',
			card: function (it, open) {
				return h('div', { class: 'msw-card' }, h('button', { class: 'msw-thumb', type: 'button', onClick: open }, h('span', { class: 'msw-ico' }, ICON[it.type] || '📄')),
					h('div', { class: 'msw-badges' }, h('span', { class: 'msw-b' }, it.size), it.days_left !== null ? h('span', { class: 'msw-b ' + (it.days_left < 4 ? 'bad' : '') }, it.days_left + ' روز تا حذف') : null),
					h('div', { class: 'msw-name' }, it.file || it.title), h('div', { class: 'msw-sub' }, it.date));
			},
			lbItem: function (it) { return { id: 0, title: it.title, rel: it.file, type: it.type, size: it.size, date: it.date, status: '' }; },
			bulk: [
				{ label: '↩ بازگردانی', op: 'restore', cls: 'button-primary' },
				{ label: '🗑 حذف دائمی', op: 'purge', cls: 'msw-danger', typed: true, confirm: '{n} مورد برای همیشه حذف شود؟' }
			],
			hint: CFG.qdays ? 'موارد پس از ' + CFG.qdays + ' روز به‌صورت خودکار حذف می‌شوند.' : 'حذف خودکار غیرفعال است.',
			filters: function (B) { return [h('span', { class: 'description' }, 'برای انتخاب همهٔ موارد، «انتخاب همین صفحه» یا «انتخاب هر … مورد» را بزنید.')]; }
		};
		clear(el).appendChild(browser(cfg).el);
	}

	function tabDuplicates(el) {
		var page = 1;
		function load() {
			clear(el).appendChild(h('p', { class: 'msw-loading' }, 'در حال بارگذاری…'));
			api('list', { tab: 'duplicates', f: { page: page } }).then(function (d) {
				clear(el);
				el.appendChild(h('div', { class: 'msw-notice' }, d.total ? num(d.total) + ' گروه فایل تکراری؛ تا ' + d.wasted_size + ' فضای قابل صرفه‌جویی. فایل‌های تکراری «بدون استفاده» را می‌توانید قرنطینه کنید؛ آن‌هایی که استفاده می‌شوند حذف نمی‌شوند (ادغام خودکار ارجاع‌ها عمداً انجام نمی‌شود).' : 'گروه تکراری یافت نشد. اگر اسکن را بدون گزینهٔ «تشخیص تکراری‌ها» انجام داده‌اید، دوباره اسکن کنید.'));
				d.groups.forEach(function (g) {
					var row = h('div', { class: 'msw-grid dup' });
					var unused = g.items.filter(function (i) { return i.status === 'unused' && !i.locked; });
					g.items.forEach(function (it, idx) { row.appendChild(mediaCard(it, function () { lightbox(g.items, idx, { actions: [{ label: '📦 قرنطینه', cls: 'button-primary', fn: function (items) { runOp('quarantine', items.map(function (x) { return x.id; }), load); } }] }); })); });
					el.appendChild(h('section', { class: 'msw-dupgroup' },
						h('header', null, h('strong', null, g.count + ' نسخه یکسان'), ' — ' + g.size, unused.length ? h('button', { class: 'button', onClick: function () {
							if (!window.confirm(unused.length + ' نسخهٔ بدون استفاده به قرنطینه برود؟')) { return; }
							runOp('quarantine', unused.map(function (x) { return x.id; }), load);
						} }, '📦 قرنطینهٔ نسخه‌های بدون استفاده (' + unused.length + ')') : null), row));
				});
				if (d.pages > 1) {
					el.appendChild(h('div', { class: 'msw-pager' },
						h('button', { class: 'button', disabled: page <= 1, onClick: function () { page--; load(); } }, 'قبلی'), ' صفحه ' + page + ' از ' + d.pages + ' ',
						h('button', { class: 'button', disabled: page >= d.pages, onClick: function () { page++; load(); } }, 'بعدی')));
				}
			}).catch(function (e) { clear(el).appendChild(h('p', { class: 'warn' }, e.message)); });
		}
		load();
	}

	function tabBroken(el) {
		var page = 1, q = '';
		function load() {
			api('list', { tab: 'broken', f: { page: page, q: q } }).then(function (d) {
				clear(el);
				el.appendChild(h('div', { class: 'msw-notice' }, 'ارجاع‌هایی در سایت به فایل‌هایی در uploads که روی دیسک وجود ندارند (تصویر شکسته). اگر رسانه‌ها روی CDN/S3 هستند ممکن است نادرست باشد. ' + num(d.total) + ' مورد.'));
				el.appendChild(h('div', { class: 'msw-filters' }, h('input', { type: 'search', value: q, placeholder: 'جست‌وجوی مسیر…', onInput: debounce(function (e) { q = e.target.value; page = 1; load(); }, 400) })));
				var body = h('tbody');
				d.items.forEach(function (it) {
					body.appendChild(h('tr', null, h('td', { dir: 'ltr', style: 'text-align:start' }, it.rel), h('td', null, it.link ? h('a', { href: it.link, target: '_blank', rel: 'noopener' }, it.where) : it.where)));
				});
				el.appendChild(h('table', { class: 'widefat striped msw-table' }, h('thead', null, h('tr', null, h('th', null, 'فایل مفقود'), h('th', null, 'ارجاع در'))), body));
				if (d.pages > 1) {
					el.appendChild(h('div', { class: 'msw-pager' }, h('button', { class: 'button', disabled: page <= 1, onClick: function () { page--; load(); } }, 'قبلی'), ' ' + page + ' / ' + d.pages + ' ', h('button', { class: 'button', disabled: page >= d.pages, onClick: function () { page++; load(); } }, 'بعدی')));
				}
			}).catch(function (e) { clear(el).appendChild(h('p', { class: 'warn' }, e.message)); });
		}
		clear(el).appendChild(h('p', { class: 'msw-loading' }, 'در حال بارگذاری…'));
		load();
	}

	function tabWebp(el) {
		clear(el).appendChild(h('p', { class: 'msw-loading' }, 'در حال بارگذاری…'));
		var scope = CFG.settings.webp_scope || 'used';
		refreshDash().then(function (d) {
			clear(el);
			var caps = d.caps, s = CFG.settings;
			var jobArea = h('div');
			var simArea = h('div');
			var listArea = h('div');
			var status = '';
			function loadList(page) {
				clear(listArea).appendChild(h('p', { class: 'msw-loading' }, 'در حال بارگذاری…'));
				api('list', { tab: 'converted', f: { status: status, page: page || 1 } }).then(function (r) {
					clear(listArea);
					var sm = r.summary;
					listArea.appendChild(h('div', { class: 'msw-stats compact' },
						stat('تبدیل‌شده', num(sm.converted), sm.saved + ' صرفه‌جویی', 'primary'),
						stat('ردشده', num(sm.skipped), 'کوچک‌تر نشد / سازگار نبود'),
						stat('ناموفق', num(sm.failed), ''),
						stat('منتظر حذف اصل', num(sm.pending_originals), 'فایل‌های اصلی نگه‌داشته‌شده')));
					if (sm.pending_originals) {
						listArea.appendChild(h('p', null, h('button', { class: 'button', onClick: function () {
							if (!window.confirm('تبدیل WebP همهٔ تصاویری که اصل‌شان هنوز نگه‌داشته شده به JPG/PNG برگردد و لینک‌ها اصلاح شود؟')) { return; }
							clear(jobArea);
							var jb = jobBox({ title: 'بازگردانی به JPG/PNG', onDone: function () { loadList(1); } });
							jobArea.appendChild(jb);
							api('job_start', { type: 'revert', opts: {} }).then(function (st) { jb.start(st); }).catch(function (e) { clear(jobArea); toast(e.message, 'err'); });
						} }, '↩ بازگردانی به JPG/PNG (تا قبل از حذف اصل‌ها)')));
						listArea.appendChild(h('p', null, h('button', { class: 'button msw-danger', onClick: function () {
							if (!window.confirm('فایل‌های اصلی JPG/PNG ' + sm.pending_originals + ' تصویر همین حالا حذف شود؟ بعد از آن بازگشت ممکن نیست. ابتدا سایت را در چند صفحه بررسی کنید.')) { return; }
							api('act', { op: 'purge_originals' }).then(function (x) { toast(x.ok + ' تصویر پاک‌سازی شد.', 'ok'); loadList(1); }).catch(function (e) { toast(e.message, 'err'); });
						} }, '🗑 حذف فایل‌های اصلی همین حالا')));
					}
					listArea.appendChild(h('div', { class: 'msw-filters' }, selectEl([['', 'همه'], ['reverted', 'بازگردانده‌شده'], ['done', 'تبدیل‌شده (در انتظار حذف اصل)'], ['deleted', 'تبدیل‌شده (اصل حذف شد)'], ['skipped', 'ردشده'], ['failed', 'ناموفق'], ['error', 'خطای بررسی نهایی']], status, function (v) { status = v; loadList(1); })));
					var tb = h('table', { class: 'widefat striped msw-table' }, h('thead', null, h('tr', null, ['#', 'عنوان', 'وضعیت', 'قبل', 'بعد', 'صرفه‌جویی', 'توضیح'].map(function (x) { return h('th', null, x); }))));
					var body = h('tbody');
					r.items.forEach(function (it) {
						body.appendChild(h('tr', null, h('td', null, h('a', { href: it.edit, target: '_blank', rel: 'noopener' }, it.id)), h('td', null, it.title), h('td', null, ({ done: 'تبدیل شد', deleted: 'تبدیل شد (اصل حذف شد)', reverted: 'بازگردانده شد', reverting: 'در حال بازگردانی', skipped: 'ردشد', failed: 'ناموفق', error: 'خطای بررسی نهایی', converted: 'در حال اصلاح لینک' }[it.status] || it.status) + (it.delete_after ? ' (حذف اصل: ' + it.delete_after + ')' : '')), h('td', null, it.before), h('td', null, it.after), h('td', null, it.pct ? it.pct + '٪ (' + it.saved + ')' : '—'), h('td', null, it.reason)));
					});
					tb.appendChild(body);
					listArea.appendChild(tb);
					if (r.pages > 1) {
						listArea.appendChild(h('div', { class: 'msw-pager' }, h('button', { class: 'button', disabled: r.page <= 1, onClick: function () { loadList(r.page - 1); } }, 'قبلی'), ' ' + r.page + ' / ' + r.pages + ' ', h('button', { class: 'button', disabled: r.page >= r.pages, onClick: function () { loadList(r.page + 1); } }, 'بعدی')));
					}
				});
			}
			function start(retry) {
				if (!window.confirm('تبدیل شروع شود؟\n\n• ابتدا فایل‌های WebP ساخته و بررسی می‌شوند.\n• سپس تمام لینک‌ها در دیتابیس اصلاح می‌شوند.\n• فایل‌های اصلی ' + (s.webp_retention_days > 0 ? 'تا ' + s.webp_retention_days + ' روز نگه‌داشته و بعد حذف می‌شوند' : 'پس از بررسی نهایی بلافاصله حذف می‌شوند') + '.\n\nآیا بک‌آپ کامل دارید؟')) { return; }
				clear(jobArea);
				var jb = jobBox({ title: 'تبدیل به WebP', onDone: function () { loadList(1); refreshDash(); } });
				jobArea.appendChild(jb);
				api('job_start', { type: 'convert', opts: { scope: scope, retry: !!retry } }).then(function (st) { jb.start(st); }).catch(function (e) { clear(jobArea); toast(e.message, 'err'); });
			}
			el.appendChild(h('div', { class: 'msw-hero' }, h('div', null, h('h2', null, 'تبدیل امن JPG / PNG به WebP'),
				h('p', null, 'موتور فعال: ', h('strong', null, caps.engine ? (caps.engine === 'imagick' ? 'Imagick' : 'GD') : 'هیچ‌کدام'), caps.lossless ? ' • پشتیبانی Lossless' : '', caps.exif ? '' : ' • افزونهٔ EXIF غیرفعال (چرخش خودکار JPEG محدود)', ' • حافظه PHP: ' + caps.memory)),
				h('div', { class: 'msw-webp-actions' },
					h('div', { class: 'msw-scope' },
						h('label', null, h('input', { type: 'radio', name: 'msw-scope', checked: scope === 'used', onChange: function () { scope = 'used'; } }), ' فقط عکس‌های در حال استفاده (نیازمند اسکن)'),
						h('label', null, h('input', { type: 'radio', name: 'msw-scope', checked: scope === 'all', onChange: function () { scope = 'all'; } }), ' همهٔ عکس‌های کتابخانه')),
					h('button', { class: 'button button-primary button-hero', disabled: !caps.engine, onClick: function () { start(false); } }, '⚡ شروع تبدیل'),
					h('button', { class: 'button', disabled: !caps.engine, onClick: function () { start(true); } }, 'تلاش دوباره برای ناموفق/ردشده‌ها'),
					h('button', { class: 'button', disabled: !caps.engine, onClick: function () {
						clear(simArea).appendChild(h('em', null, 'در حال آزمایش روی چند تصویر نمونه (بدون تغییر در سایت)…'));
						api('sim').then(function (r) {
							clear(simArea);
							if (r.error) { simArea.appendChild(h('p', { class: 'warn' }, r.error)); return; }
							var tot0 = 0, tot1 = 0;
							var t = h('table', { class: 'widefat striped' }, h('thead', null, h('tr', null, ['فایل', 'قبل', 'بعد', 'کاهش'].map(function (x) { return h('th', null, x); }))), h('tbody', null, r.items.map(function (x) {
								tot0 += x.before; if (x.after) { tot1 += x.after; } else { tot1 += x.before; }
								return h('tr', null, h('td', null, x.file), h('td', null, (x.before / 1024).toFixed(0) + ' KB'), h('td', null, x.error ? x.error : (x.after / 1024).toFixed(0) + ' KB'), h('td', null, x.after ? Math.round((1 - x.after / x.before) * 100) + '٪' : '—'));
							})));
							simArea.appendChild(t);
							if (tot0) { simArea.appendChild(h('p', null, 'میانگین کاهش نمونه: ' + Math.round((1 - tot1 / tot0) * 100) + '٪ (فقط تخمین)')); }
						}).catch(function (e) { clear(simArea).appendChild(h('p', { class: 'warn' }, e.message)); });
					} }, '🧪 آزمایش روی چند نمونه'))));
			el.appendChild(simArea);
			var maxpx = h('input', { type: 'number', value: 2560, min: 800, step: 10, class: 'small-text' });
			var dropOrig = h('input', { type: 'checkbox', checked: true });
			var optArea = h('div');
			el.appendChild(h('div', { class: 'msw-hero' }, h('div', null, h('h2', null, 'کوچک‌سازی اصل‌های سنگین'), h('p', null, 'نام فایل‌ها تغییر نمی‌کند، پس لینکی نمی‌شکند. حذف original_image نسخهٔ بزرگ‌تر از -scaled را پاک می‌کند (بازسازی از اصل بعداً ممکن نیست).')),
				h('div', { class: 'msw-scope' }, h('label', null, 'حداکثر ابعاد (px): ', maxpx), h('label', null, dropOrig, ' حذف original_image نسخه‌های -scaled')),
				h('button', { class: 'button button-primary', onClick: function () {
					if (!window.confirm('اصل‌های سنگین کوچک شوند؟ این کار برگشت‌پذیر نیست. بک‌آپ دارید؟')) { return; }
					clear(optArea);
					var jb = jobBox({ title: 'کوچک‌سازی اصل‌ها', onDone: function () { refreshDash(); } });
					optArea.appendChild(jb);
					api('job_start', { type: 'optimize', opts: { max_px: maxpx.value, drop_original: dropOrig.checked } }).then(function (st) { jb.start(st); }).catch(function (e) { clear(optArea); toast(e.message, 'err'); });
				} }, '📉 اجرا')));
			el.appendChild(optArea);
			el.appendChild(h('div', { class: 'msw-notice' }, 'امنیت: هر فایل جدید ابتدا ساخته و اعتبارسنجی می‌شود؛ تا پایان اصلاح لینک‌ها فایل اصلی دست‌نخورده می‌ماند؛ تصاویری که WebP آن‌ها کوچک‌تر نشود، متحرک باشند، روی ذخیره‌ساز خارجی باشند یا در فایل‌های قالب لینک مستقیم داشته باشند، رد می‌شوند. کیفیت و سایر گزینه‌ها در «تنظیمات».'));
			el.appendChild(jobArea);
			if (d.job && d.job.status === 'running' && d.job.type === 'convert') {
				var jb = jobBox({ title: 'تبدیل به WebP', onDone: function () { loadList(1); } }); jobArea.appendChild(jb); jb.start(d.job);
			}
			el.appendChild(h('h3', null, 'نتایج تبدیل'));
			el.appendChild(listArea);
			loadList(1);
		}).catch(function (e) { clear(el).appendChild(h('p', { class: 'warn' }, e.message)); });
	}

	var SCHEMA = [
		['اسکن', [
			['scan_types', 'checks', 'انواع رسانه در اسکن', { image: 'تصویر', video: 'ویدئو', audio: 'صدا', doc: 'سند (PDF، Office، ZIP)' }],
			['protect_days', 'number', 'محافظت از رسانه‌های جدیدتر از (روز)', 'هرگز پیشنهاد حذف نمی‌شوند؛ ۰ = غیرفعال'],
			['parent_used', 'bool', 'پیوست‌شده به نوشتهٔ منتشرشده = در حال استفاده', 'محافظه‌کارانه؛ اما ممکن است تصاویر آپلودشده و حذف‌شده از متن را «استفاده‌شده» نگه دارد'],
			['include_revisions', 'bool', 'ارجاع‌های داخل پیش‌نویس‌های قدیمی (Revision) هم حساب شود'],
			['deep_tables', 'bool', 'جدول‌های سفارشی افزونه‌ها هم بررسی شوند (اسلایدرها، گالری‌ها…)'],
			['exclude_tables', 'textarea', 'جدول‌های مستثنا (هر خط یا با کاما، بدون پیشوند)'],
			['aggressive_meta', 'bool', 'حالت سخت‌گیر: هر عدد داخل متا که با شناسهٔ پیوست برابر باشد «استفاده‌شده» حساب شود', 'ایمن‌تر ولی ممکن است موارد بیشتری را نادرست «استفاده‌شده» نشان دهد'],
			['weak_basename', 'bool', 'تطبیق بر اساس نام فایل (محافظه‌کارانه)', 'اگر نام فایل جایی دیده شود، «استفاده‌شده» حساب می‌شود (توصیه: روشن)'],
			['scan_theme_files', 'bool', 'فایل‌های قالب/قالب فرزند برای لینک‌های ثابت بررسی شوند'],
			['scan_orphans', 'bool', 'فایل‌های یتیم داخل uploads هم جست‌وجو شود'],
			['orphan_all_dirs', 'bool', 'همهٔ زیرپوشه‌های uploads (نه فقط سال/ماه) برای یتیم‌ها بررسی شود', 'احتیاط: پوشهٔ افزونه‌ها هم دیده می‌شود'],
			['detect_duplicates', 'bool', 'تشخیص تکراری‌ها با هش فایل (کندتر)']
		]],
		['قرنطینه', [
			['quarantine_days', 'number', 'حذف خودکار قرنطینه پس از (روز)', '۰ = هرگز خودکار حذف نشود']
		]],
		['تبدیل WebP', [
			['webp_engine', 'select', 'موتور تبدیل', { auto: 'خودکار (Imagick سپس GD)', imagick: 'Imagick', gd: 'GD' }],
			['webp_jpeg_quality', 'number', 'کیفیت JPEG→WebP (۳۰ تا ۱۰۰)', 'پیشنهاد ۸۰–۸۵'],
			['webp_png_mode', 'select', 'حالت PNG', { lossless: 'Lossless (بدون افت کیفیت، پیشنهادی)', lossy: 'Lossy (حجم کمتر)' }],
			['webp_png_quality', 'number', 'کیفیت PNG در حالت Lossy'],
			['webp_min_saving', 'number', 'حداقل کاهش حجم لازم برای تبدیل (٪)', 'اگر کمتر باشد همان فایل اصلی می‌ماند'],
			['webp_scope', 'select', 'محدودهٔ پیش‌فرض', { used: 'فقط عکس‌های در حال استفاده', all: 'همهٔ عکس‌ها' }],
			['webp_skip_theme', 'bool', 'عکس‌هایی که در فایل‌های قالب لینک مستقیم دارند تبدیل نشوند', 'توصیه: روشن (این لینک‌ها خودکار قابل اصلاح نیستند)'],
			['webp_originals', 'bool', 'نسخهٔ original_image (قبل از -scaled) هم تبدیل شود'],
			['webp_retention_days', 'number', 'نگه‌داری فایل‌های اصلی پس از تبدیل (روز)', '۰ = بلافاصله بعد از بررسی نهایی حذف شود. پیشنهاد: ۷ روز برای اطمینان'],
			['webp_on_upload', 'bool', 'تصاویر جدید هنگام آپلود خودکار WebP شوند']
		]],
		['خودکارسازی', [
			['auto_scan', 'select', 'اسکن زمان‌بندی‌شده', { off: 'خاموش', weekly: 'هفتگی (شب)', monthly: 'ماهانه (شب)' }],
			['notify_email', 'text', 'ایمیل گزارش اسکن (خالی = بدون ایمیل)']
		]],
		['حذف افزونه', [
			['remove_data', 'bool', 'هنگام حذف افزونه، تمام جدول‌ها و تنظیمات پاک شود', 'آیتم‌های قرنطینه قبل از آن بازگردانده می‌شوند']
		]]
	];

	function tabSettings(el) {
		var cur = JSON.parse(JSON.stringify(CFG.settings));
		clear(el);
		var form = h('form', { class: 'msw-form', onSubmit: function (e) {
			e.preventDefault();
			api('settings_save', { s: cur }).then(function (r) { CFG.settings = r; toast('تنظیمات ذخیره شد.', 'ok'); }).catch(function (er) { toast(er.message, 'err'); });
		} });
		SCHEMA.forEach(function (sec) {
			var fs = h('fieldset', null, h('legend', null, sec[0]));
			sec[1].forEach(function (f) {
				var key = f[0], type = f[1], label = f[2], extra = f[3], input;
				if (type === 'bool') { input = h('input', { type: 'checkbox', checked: !!cur[key], onChange: function (e) { cur[key] = e.target.checked ? 1 : 0; } }); fs.appendChild(h('div', { class: 'msw-field' }, h('label', null, input, ' ' + label), typeof extra === 'string' ? h('p', { class: 'description' }, extra) : null)); return; }
				if (type === 'checks') {
					input = h('div', { class: 'msw-checks' }, Object.keys(extra).map(function (k) {
						return h('label', null, h('input', { type: 'checkbox', checked: (cur[key] || []).indexOf(k) !== -1, onChange: function (e) { var a = cur[key] || []; a = a.filter(function (x) { return x !== k; }); if (e.target.checked) { a.push(k); } cur[key] = a; } }), ' ' + extra[k]);
					}));
					fs.appendChild(h('div', { class: 'msw-field' }, h('label', null, label), input)); return;
				}
				if (type === 'select') { input = selectEl(Object.keys(extra).map(function (k) { return [k, extra[k]]; }), cur[key], function (v) { cur[key] = v; }); }
				else if (type === 'textarea') { input = h('textarea', { rows: 3, class: 'large-text', onInput: function (e) { cur[key] = e.target.value; } }, cur[key] || ''); }
				else { input = h('input', { type: type === 'number' ? 'number' : 'text', value: cur[key], class: 'regular-text', onInput: function (e) { cur[key] = e.target.value; } }); }
				fs.appendChild(h('div', { class: 'msw-field' }, h('label', null, label), input, typeof extra === 'string' ? h('p', { class: 'description' }, extra) : null));
			});
			form.appendChild(fs);
		});
		form.appendChild(h('p', null, h('button', { class: 'button button-primary', type: 'submit' }, 'ذخیرهٔ تنظیمات')));
		el.appendChild(form);
	}

	function tabLog(el) {
		clear(el).appendChild(h('p', { class: 'msw-loading' }, 'در حال بارگذاری…'));
		api('log').then(function (r) {
			clear(el);
			el.appendChild(h('p', null, h('button', { class: 'button', onClick: function () { api('log_clear').then(function () { tabLog(el); }); } }, 'پاک کردن گزارش')));
			var body = h('tbody');
			r.items.forEach(function (l) { body.appendChild(h('tr', { class: 'lvl-' + l.level }, h('td', null, l.time), h('td', null, l.level), h('td', null, l.msg))); });
			el.appendChild(h('table', { class: 'widefat striped msw-table' }, h('thead', null, h('tr', null, h('th', null, 'زمان'), h('th', null, 'سطح'), h('th', null, 'پیام'))), body));
		});
	}

	/* ------------------------------------------------------------- shell */

	var RENDER = { dashboard: tabDashboard, unused: tabUnused, orphans: tabOrphans, duplicates: tabDuplicates, broken: tabBroken, quarantine: tabQuarantine, webp: tabWebp, settings: tabSettings, log: tabLog };

	function go(tab) {
		state.tab = tab;
		try { history.replaceState(null, '', '#' + tab); } catch (e) { /* ignore */ }
		Array.prototype.forEach.call(navEl.children, function (a) { a.classList.toggle('nav-tab-active', a.getAttribute('data-tab') === tab); });
		RENDER[tab](clear(view));
	}

	navEl = h('nav', { class: 'nav-tab-wrapper msw-nav' }, TABS.map(function (t) {
		return h('a', { href: '#' + t[0], 'data-tab': t[0], class: 'nav-tab', onClick: function (e) { e.preventDefault(); go(t[0]); } }, t[1]);
	}));
	view = h('div', { class: 'msw-view' });
	clear(root);
	root.appendChild(h('h1', null, 'Media Sweeper Pro ', h('span', { class: 'msw-ver' }, 'v' + CFG.version)));
	root.appendChild(navEl);
	root.appendChild(view);
	var initial = (location.hash || '').replace('#', '');
	go(RENDER[initial] ? initial : 'dashboard');
})();
