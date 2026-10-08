/* Smart Bulk Product Importer — admin UI (vanilla JS, no dependencies). */
( function () {
	'use strict';

	var $ = function ( sel, ctx ) { return ( ctx || document ).querySelector( sel ); };
	var $$ = function ( sel, ctx ) { return Array.prototype.slice.call( ( ctx || document ).querySelectorAll( sel ) ); };

	function esc( s ) {
		var d = document.createElement( 'div' );
		d.textContent = s == null ? '' : String( s );
		return d.innerHTML;
	}

	function post( action, data ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', SBPI.nonce );
		Object.keys( data ).forEach( function ( k ) { body.append( k, data[ k ] ); } );
		return fetch( SBPI.ajax, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) {
				return r.text().then( function ( t ) {
					try { return JSON.parse( t ); } catch ( e ) {
						throw new Error( 'پاسخ نامعتبر سرور (HTTP ' + r.status + '). لاگ PHP را بررسی کنید.' );
					}
				} );
			} )
			.then( function ( json ) {
				if ( ! json.success ) { throw new Error( ( json.data && json.data.message ) || 'خطای ناشناخته' ); }
				return json.data;
			} );
	}

	/* ---------- History: rollback ---------- */
	$$( '.sbpi-rollback' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			if ( ! window.confirm( 'این نوبت کامل بازگردانی شود؟\n\nمحصولات ساخته‌شده حذف و محصولات به‌روزشده به وضعیت قبل برمی‌گردند. اگر نوبت‌های جدیدتری روی همین محصولات اجرا شده، اول آن‌ها را بازگردانی کنید.' ) ) { return; }
			btn.disabled = true;
			( function step() {
				post( 'sbpi_rollback', { batch: btn.dataset.batch } ).then( function ( d ) {
					btn.textContent = d.remaining ? 'باقی‌مانده: ' + d.remaining : 'بازگردانی شد ✓';
					if ( d.remaining ) { step(); }
				} ).catch( function ( e ) { window.alert( e.message ); btn.disabled = false; } );
			} )();
		} );
	} );

	/* ---------- Price update: apply ---------- */
	var priceBox = $( '#sbpi-prices' );
	var applyBtn = $( '#sbpi-price-apply' );
	if ( priceBox && applyBtn ) {
		applyBtn.addEventListener( 'click', function () {
			if ( ! window.confirm( 'تغییرات قیمت و موجودی اعمال شود؟' ) ) { return; }
			var text = $( '.sbpi-progress-text', priceBox );
			applyBtn.disabled = true;
			( function step( offset ) {
				post( 'sbpi_price_apply', { job: priceBox.dataset.job, offset: offset } ).then( function ( d ) {
					text.textContent = d.offset + ' / ' + d.total;
					if ( d.done ) { text.textContent = '✅ ' + d.total + ' تغییر اعمال شد.'; } else { step( d.offset ); }
				} ).catch( function ( e ) { text.textContent = e.message; applyBtn.disabled = false; } );
			} )( 0 );
		} );
	}


	/* ---------- Shared UI helpers ---------- */
	var fa = function ( n ) { return Number( n ).toLocaleString( 'fa-IR' ); };

	function toast( msg, type ) {
		var box = $( '.sbpi-toasts' );
		if ( ! box ) { return; }
		var el = document.createElement( 'div' );
		el.className = 'sbpi-toast ' + ( type || '' );
		el.textContent = msg;
		box.appendChild( el );
		setTimeout( function () { el.classList.add( 'out' ); }, 4500 );
		setTimeout( function () { el.remove(); }, 5000 );
	}

	function setStep( n ) {
		$$( '#sbpi-steps li' ).forEach( function ( li ) {
			var s = Number( li.dataset.step );
			li.className = s < n ? 'done' : ( s === n ? 'current' : '' );
		} );
	}

	/* ---------- Upload: drag & drop ---------- */
	var upload = $( '#sbpi-upload' );
	if ( upload ) {
		var drop = $( '.sbpi-drop', upload );
		var input = $( '#sbpi-file' );
		var submit = $( 'button[type=submit]', upload );
		var showFile = function () {
			var f = input.files && input.files[0];
			var label = $( '.sbpi-drop-file', drop );
			label.hidden = ! f;
			if ( f ) {
				var ok = /\.(xlsx|csv)$/i.test( f.name );
				label.textContent = ( ok ? '✓ ' : '✕ ' ) + f.name + ' — ' + fa( Math.round( f.size / 1024 ) ) + ' KB' + ( ok ? '' : ' (فقط XLSX یا CSV)' );
				label.className = 'sbpi-drop-file ' + ( ok ? 'ok' : 'bad' );
				submit.disabled = ! ok;
			}
		};
		input.addEventListener( 'change', showFile );
		[ 'dragenter', 'dragover' ].forEach( function ( ev ) {
			drop.addEventListener( ev, function ( e ) { e.preventDefault(); drop.classList.add( 'over' ); } );
		} );
		[ 'dragleave', 'drop' ].forEach( function ( ev ) {
			drop.addEventListener( ev, function ( e ) { e.preventDefault(); drop.classList.remove( 'over' ); } );
		} );
		drop.addEventListener( 'drop', function ( e ) {
			if ( e.dataTransfer.files.length ) { input.files = e.dataTransfer.files; showFile(); }
		} );
		upload.addEventListener( 'submit', function () {
			submit.disabled = true;
			submit.textContent = 'در حال خواندن فایل…';
		} );
	}

	/* ---------- Self-test ---------- */
	var stBtn = $( '#sbpi-selftest' );
	if ( stBtn ) {
		stBtn.addEventListener( 'click', function () {
			var out = $( '#sbpi-selftest-out' );
			stBtn.disabled = true;
			stBtn.classList.add( 'busy' );
			out.innerHTML = '<div class="sbpi-skeleton"></div><div class="sbpi-skeleton"></div>';
			post( 'sbpi_selftest', {} ).then( function ( d ) {
				var bad = d.results.filter( function ( r ) { return r[0] !== 'ok'; } ).length;
				out.innerHTML = '<div class="sbpi-alert ' + ( bad ? 'bad' : 'good' ) + '"><span class="dashicons dashicons-' + ( bad ? 'dismiss' : 'yes-alt' ) + '"></span>' +
					( bad ? fa( bad ) + ' بررسی ناموفق از ' + fa( d.results.length ) + ' — جزئیات را برای پشتیبانی بفرستید.' : 'همه ' + fa( d.results.length ) + ' بررسی موفق بود. افزونه روی این سایت درست کار می‌کند.' ) + '</div>' +
					'<table class="sbpi-env"><tbody>' + d.results.map( function ( r ) {
						return '<tr class="' + r[0] + '"><td class="i">' + ( r[0] === 'ok' ? '✓' : '✕' ) + '</td><th>' + esc( r[1] ) + '</th><td>' + esc( r[2] ) + '</td></tr>';
					} ).join( '' ) + '</tbody></table>';
				toast( bad ? 'خودآزمایی: ' + fa( bad ) + ' مورد ناموفق' : 'خودآزمایی موفق بود', bad ? 'bad' : 'good' );
			} ).catch( function ( e ) {
				out.innerHTML = '<div class="sbpi-alert bad"><span class="dashicons dashicons-dismiss"></span>' + esc( e.message ) + '</div>';
			} ).then( function () { stBtn.disabled = false; stBtn.classList.remove( 'busy' ); } );
		} );
	}

	var form = $( '#sbpi-form' );
	if ( ! form ) { return; }
	var job = form.dataset.job;
	var runBtn = $( '#sbpi-run' );
	var stopBtn = $( '#sbpi-stop' );
	var previewBtn = $( '#sbpi-preview' );
	var status = $( '#sbpi-status' );
	var output = $( '#sbpi-output' );
	var stopped = false;

	/* ---------- Mapping table ---------- */
	function syncRow( tr ) {
		var role = $( '[data-c="role"]', tr ).value;
		var isAttr = role === 'var_attr' || role === 'info_attr' || role === 'split_attr';
		var pick = $( '[data-c="pick"]', tr );
		var slug = $( '[data-c="slug"]', tr );
		$( '[data-c="name"]', tr ).disabled = ! isAttr;
		pick.disabled = ! isAttr;
		slug.disabled = ! isAttr || pick.value !== '';
		slug.hidden = pick.value !== '';
		tr.dataset.role = role;
	}
	$$( '.sbpi-cols tbody tr' ).forEach( function ( tr ) {
		syncRow( tr );
		$( '[data-c="role"]', tr ).addEventListener( 'change', function () { syncRow( tr ); } );
		$( '[data-c="pick"]', tr ).addEventListener( 'change', function () {
			var opt = this.options[ this.selectedIndex ];
			$( '[data-c="slug"]', tr ).value = this.value || '';
			if ( this.value ) { $( '[data-c="name"]', tr ).value = opt.dataset.label; }
			syncRow( tr );
		} );
	} );

	/* Unused virtual columns (section parts) stay folded until asked for. */
	$$( '.sbpi-virtual-toggle' ).forEach( function ( b ) {
		var rows = $$( 'tr.sbpi-virtual', b.closest( '.sbpi-sheet-body' ) );
		if ( ! rows.length ) { b.hidden = true; return; }
		b.textContent += ' — ' + fa( rows.length );
		b.addEventListener( 'click', function () {
			var show = rows[0].hidden;
			rows.forEach( function ( r ) { r.hidden = ! show; } );
			b.classList.toggle( 'open', show );
		} );
	} );

	/* Sheet switches: enabling opens the card, disabling dims it. */
	function syncSheet( box ) {
		var on = $( '[data-f="enabled"]', box ).checked;
		box.classList.toggle( 'off', ! on );
	}
	$$( '.sbpi-sheet[data-sheet]' ).forEach( function ( box ) {
		syncSheet( box );
		$( '[data-f="enabled"]', box ).addEventListener( 'change', function () {
			syncSheet( box );
			box.open = this.checked;
		} );
	} );
	$$( '[data-sheets]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var on = btn.dataset.sheets === 'all';
			$$( '.sbpi-sheet[data-sheet]' ).forEach( function ( box ) {
				$( '[data-f="enabled"]', box ).checked = on;
				box.open = on;
				syncSheet( box );
			} );
			invalidate();
		} );
	} );

	/* Token buttons insert {token} at the cursor of the related input. */
	$$( '.sbpi-tokens' ).forEach( function ( wrap ) {
		var target = $( '[data-f="' + wrap.dataset.target + '"]', wrap.closest( '.sbpi-sheet' ) );
		$$( '.sbpi-token', wrap ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var pos = target.selectionStart != null ? target.selectionStart : target.value.length;
				var v = target.value;
				var tok = b.textContent.trim();
				var pre = v.slice( 0, pos ).replace( /\s*$/, '' );
				target.value = ( pre ? pre + ' ' : '' ) + tok + ( v.slice( pos ) ? ' ' + v.slice( pos ).replace( /^\s*/, '' ) : '' );
				target.focus();
				invalidate();
			} );
		} );
	} );

	/* Settings: vertical navigation between panels (remembered per browser). */
	var snav = $$( '.sbpi-snav button' );
	function showPanel( name ) {
		snav.forEach( function ( b ) { b.classList.toggle( 'active', b.dataset.panel === name ); b.setAttribute( 'aria-selected', b.dataset.panel === name ); } );
		$$( '.sbpi-panel' ).forEach( function ( p ) {
			var on = p.dataset.panel === name;
			p.hidden = ! on;
			p.classList.toggle( 'active', on );
		} );
		try { localStorage.setItem( 'sbpi-panel', name ); } catch ( e ) {}
	}
	snav.forEach( function ( b ) { b.addEventListener( 'click', function () { showPanel( b.dataset.panel ); } ); } );
	try {
		var saved = localStorage.getItem( 'sbpi-panel' );
		if ( saved && $( '.sbpi-panel[data-panel="' + saved + '"]' ) ) { showPanel( saved ); }
	} catch ( e ) {}

	/* Segmented controls: buttons mirror a (hidden) select, so collect() keeps working. */
	$$( 'select.sbpi-seg' ).forEach( function ( sel ) {
		var wrap = document.createElement( 'div' );
		wrap.className = 'sbpi-segmented';
		wrap.setAttribute( 'role', 'radiogroup' );
		Array.prototype.forEach.call( sel.options, function ( o ) {
			var b = document.createElement( 'button' );
			b.type = 'button';
			b.textContent = o.textContent;
			b.setAttribute( 'role', 'radio' );
			var sync = function () {
				$$( 'button', wrap ).forEach( function ( x, i ) {
					var on = sel.options[ i ].selected;
					x.classList.toggle( 'on', on );
					x.setAttribute( 'aria-checked', on );
				} );
			};
			b.addEventListener( 'click', function () {
				sel.value = o.value;
				sel.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				sync();
			} );
			wrap.appendChild( b );
			setTimeout( sync );
		} );
		sel.hidden = true;
		sel.parentNode.insertBefore( wrap, sel );
	} );

	/* Live Google preview for the SEO title template. */
	var serp = $( '.sbpi-serp-live' );
	function renderSerp() {
		if ( ! serp ) { return; }
		var tpl = $( '[data-g="seo_title_tpl"]' ).value || '{title}';
		var site = $( '[data-g="store_name"]' ).value;
		var title = serp.dataset.sample;
		var out = tpl.replace( /\{title\}|\{model\}/g, title ).replace( /\{site\}/g, site ).replace( /\{[^}]+\}/g, '' ).replace( /\s{2,}/g, ' ' ).replace( /[\s|\-–—]+$/, '' ).trim();
		if ( out.length > 65 ) {
			out = tpl.replace( /\{title\}|\{model\}/g, title ).replace( /\{[^}]+\}/g, '' ).replace( /[\s|\-–—]+$/, '' ).trim();
		}
		$( '.t', serp ).textContent = out;
		var l = $( '.sbpi-len', serp );
		l.textContent = fa( out.length ) + ' / ۶۵ کاراکتر';
		l.className = 'sbpi-len ' + ( out.length > 65 ? 'bad' : 'ok' );
	}
	[ 'seo_title_tpl', 'store_name' ].forEach( function ( k ) {
		var el = $( '[data-g="' + k + '"]' );
		if ( el ) { el.addEventListener( 'input', renderSerp ); }
	} );
	renderSerp();

	/* Any change invalidates the last preview. */
	function invalidate() {
		if ( ! runBtn.disabled ) {
			runBtn.disabled = true;
			status.textContent = 'تنظیمات تغییر کرد؛ دوباره «پیش‌نمایش» بگیرید.';
			setStep( 2 );
		}
	}
	/* Search/filters inside the preview are not settings. */
	[ 'input', 'change' ].forEach( function ( ev ) {
		form.addEventListener( ev, function ( e ) {
			if ( ! e.target.closest( '#sbpi-output' ) ) { invalidate(); }
		} );
	} );

	function collect() {
		var settings = { global: {}, sheets: {} };
		$$( '[data-g]' ).forEach( function ( el ) {
			settings.global[ el.dataset.g ] = el.type === 'checkbox' ? ( el.checked ? 1 : 0 ) : el.value;
		} );
		$$( '.sbpi-sheet[data-sheet]' ).forEach( function ( box ) {
			var s = { columns: {} };
			$$( '[data-f]', box ).forEach( function ( el ) {
				s[ el.dataset.f ] = el.type === 'checkbox' ? el.checked : el.value;
			} );
			$$( 'tr[data-col]', box ).forEach( function ( tr ) {
				s.columns[ tr.dataset.col ] = {
					role: $( '[data-c="role"]', tr ).value,
					name: $( '[data-c="name"]', tr ).value,
					slug: $( '[data-c="slug"]', tr ).value,
					split: $( '[data-c="split"]', tr ).checked
				};
			} );
			settings.sheets[ box.dataset.sheet ] = s;
		} );
		return settings;
	}

	function len( s, max ) {
		var n = ( s || '' ).length;
		return '<span class="sbpi-len ' + ( n > max ? 'bad' : 'ok' ) + '">' + fa( n ) + '/' + fa( max ) + '</span>';
	}

	function kpi( label, value, cls ) {
		return '<div class="sbpi-kpi ' + ( cls || '' ) + '"><strong>' + fa( value ) + '</strong><span>' + esc( label ) + '</span></div>';
	}

	/* ---------- Preview ---------- */
	var lastPreview = null;
	var STATUS = { 'new': 'جدید', update: 'به‌روزرسانی', skip: 'رد می‌شود' };

	function productRow( p, i ) {
		return '<tr data-status="' + p.status + '" data-priced="' + ( p.priced ? 1 : 0 ) + '" data-q="' + esc( ( p.title + ' ' + p.category + ' ' + p.slug ).toLowerCase() ) + '">' +
			'<td><span class="sbpi-st sbpi-st-' + p.status + '">' + STATUS[ p.status ] + '</span>' +
			( p.new_vars && p.status === 'update' ? ' <small>+' + fa( p.new_vars ) + ' تنوع جدید</small>' : '' ) +
			'<div class="sbpi-ptitle">' + esc( p.title ) + '</div><code dir="ltr">' + esc( decodeURIComponent( p.slug ) ) + '</code>' +
			'<div class="sbpi-muted-sm">سطرها: ' + esc( p.lines ) + '</div></td>' +
			'<td>' + ( p.type === 'variable' ? 'متغیر · <strong>' + fa( p.variations ) + '</strong> تنوع' : 'ساده' ) + ( p.priced ? '' : '<br><span class="sbpi-len bad">بدون قیمت</span>' ) +
			'<div class="sbpi-muted-sm">' + esc( p.category ) + ' · ' + esc( p.brand ) + '</div>' +
			'<div class="sbpi-muted-sm">' + esc( p.attributes ) + '</div></td>' +
			'<td class="sbpi-serp"><div class="t">' + esc( p.seo_title ) + '</div>' +
			'<div class="d">' + esc( p.seo_desc ) + '</div>' +
			'<div class="k">' + len( p.seo_title, 65 ) + ' ' + len( p.seo_desc, 158 ) + ' 🔑 ' + esc( p.focus ) + '</div>' +
			( p.content ? '<details><summary>توضیحات تولیدشده</summary><div class="sbpi-content" data-i="' + i + '"></div></details>' : '' ) +
			'</td></tr>';
	}

	function applyFilter() {
		var q = ( $( '#sbpi-q' ) || {} ).value || '';
		var f = ( $( '.sbpi-filter.on' ) || { dataset: { f: '' } } ).dataset.f;
		q = q.toLowerCase().trim();
		var shown = 0;
		$$( '.sbpi-preview > tbody > tr' ).forEach( function ( tr ) {
			var ok = ( ! q || tr.dataset.q.indexOf( q ) !== -1 ) &&
				( ! f || ( f === 'noprice' ? tr.dataset.priced === '0' : tr.dataset.status === f ) );
			tr.hidden = ! ok;
			if ( ok ) { shown++; }
		} );
		var c = $( '#sbpi-shown' );
		if ( c ) { c.textContent = fa( shown ) + ' مورد'; }
	}

	previewBtn.addEventListener( 'click', function () {
		var out = $( '#sbpi-result' );
		if ( ! $$( '[data-f="enabled"]' ).some( function ( c ) { return c.checked; } ) ) {
			toast( 'هیچ شیتی انتخاب نشده است.', 'bad' );
			return;
		}
		previewBtn.disabled = true;
		previewBtn.classList.add( 'busy' );
		status.textContent = 'در حال تحلیل فایل…';
		output.hidden = false;
		out.innerHTML = '<div class="sbpi-skeleton"></div><div class="sbpi-skeleton"></div><div class="sbpi-skeleton"></div>';
		post( 'sbpi_preview', { job: job, settings: JSON.stringify( collect() ) } ).then( function ( d ) {
			lastPreview = d;
			var df = d.diff;
			var noPrice = d.products.filter( function ( p ) { return ! p.priced; } ).length;
			var html = '<div class="sbpi-kpis">' +
				kpi( 'محصول', d.count ) +
				kpi( 'تنوع', d.variations ) +
				kpi( 'جدید', df.new, 'good' ) +
				kpi( df.mode === 'skip' ? 'موجود (رد می‌شود)' : 'به‌روزرسانی', df.update, df.mode === 'skip' ? 'warn' : 'info' ) +
				kpi( 'تغییر قیمت', df.pcount, df.pcount ? 'warn' : '' ) +
				kpi( 'بدون قیمت', noPrice, noPrice ? 'bad' : '' ) +
				'</div>';
			if ( d.warnings.length ) {
				html += '<div class="sbpi-alerts">' + d.warnings.map( function ( w ) { return '<div class="sbpi-alert"><span class="dashicons dashicons-warning"></span>' + esc( w ) + '</div>'; } ).join( '' ) + '</div>';
			}
			if ( df.pcount && df.mode !== 'skip' ) {
				html += '<details class="sbpi-box"><summary>تغییرات قیمت — قبلی ← جدید (' + fa( df.pcount ) + ( df.pcount > df.prices.length ? '، ' + fa( df.prices.length ) + ' مورد اول' : '' ) + ')</summary><table class="widefat striped"><tbody>' +
					df.prices.map( function ( p ) {
						var up = Number( p[3] ) > Number( p[2] );
						return '<tr><td>' + esc( p[0] ) + '</td><td>' + esc( p[1] ) + '</td><td>' + esc( p[2] ? fa( p[2] ) : '—' ) + '</td><td><strong class="' + ( up ? 'sbpi-up' : 'sbpi-down' ) + '">' + fa( p[3] ) + ( up ? ' ▲' : ' ▼' ) + '</strong></td></tr>';
					} ).join( '' ) + '</tbody></table></details>';
			}
			html += '<div class="sbpi-toolbar"><input type="search" id="sbpi-q" placeholder="جست‌وجو در نام، دسته یا نامک…" />' +
				'<button type="button" class="sbpi-filter on" data-f="">همه</button>' +
				'<button type="button" class="sbpi-filter" data-f="new">جدید</button>' +
				'<button type="button" class="sbpi-filter" data-f="update">به‌روزرسانی</button>' +
				( noPrice ? '<button type="button" class="sbpi-filter" data-f="noprice">بدون قیمت</button>' : '' ) +
				'<span id="sbpi-shown" class="sbpi-muted-sm"></span></div>';
			html += '<div class="sbpi-table-wrap"><table class="widefat sbpi-preview"><thead><tr><th>محصول</th><th>ساختار</th><th>نمایش در گوگل</th></tr></thead><tbody>' +
				d.products.map( productRow ).join( '' ) + '</tbody></table></div>';
			$( '#sbpi-result' ).innerHTML = html;
			/* Server already ran wp_kses_post on content. */
			$$( '.sbpi-content' ).forEach( function ( el ) { el.innerHTML = d.products[ el.dataset.i ].content; } );
			$( '#sbpi-q' ).addEventListener( 'input', applyFilter );
			$$( '.sbpi-filter' ).forEach( function ( b ) {
				b.addEventListener( 'click', function () {
					$$( '.sbpi-filter' ).forEach( function ( x ) { x.classList.remove( 'on' ); } );
					b.classList.add( 'on' );
					applyFilter();
				} );
			} );
			applyFilter();
			runBtn.disabled = d.count === 0;
			status.innerHTML = d.count
				? 'آماده: <strong>' + fa( d.count ) + '</strong> محصول، <strong>' + fa( d.variations ) + '</strong> تنوع.'
				: 'هیچ محصولی پیدا نشد؛ نقش ستون «نام / مدل» را بررسی کنید.';
			setStep( 3 );
			output.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		} ).catch( function ( e ) {
			out.innerHTML = '<div class="sbpi-alert bad"><span class="dashicons dashicons-dismiss"></span>' + esc( e.message ) + '</div>';
			status.textContent = 'خطا در پیش‌نمایش.';
			toast( e.message, 'bad' );
		} ).then( function () {
			previewBtn.disabled = false;
			previewBtn.classList.remove( 'busy' );
		} );
	} );

	stopBtn.addEventListener( 'click', function () { stopped = true; stopBtn.disabled = true; status.textContent = 'در حال توقف پس از مرحله جاری…'; } );

	/* ---------- Run ---------- */
	runBtn.addEventListener( 'click', function () {
		var count = lastPreview ? lastPreview.count : 0;
		if ( ! window.confirm( 'درون‌ریزی ' + fa( count ) + ' محصول شروع شود؟\n\nپیشنهاد: قبل از اولین اجرا روی سایت اصلی، نسخه پشتیبان بگیرید یا روی Staging تست کنید.' ) ) { return; }
		var log = $( '#sbpi-log' );
		var bar = $( '#sbpi-progress' );
		var retries = 0;
		var started = Date.now();
		var startCursor = null;
		stopped = false;
		runBtn.disabled = true;
		previewBtn.disabled = true;
		stopBtn.hidden = false;
		stopBtn.disabled = false;
		bar.hidden = false;
		$( '#sbpi-log-wrap' ).hidden = false;
		form.classList.add( 'running' );
		setStep( 4 );
		output.scrollIntoView( { behavior: 'smooth', block: 'start' } );

		function finish( msg, ok ) {
			$( '.sbpi-progress-text', bar ).innerHTML = msg;
			$( '.sbpi-eta', bar ).textContent = '';
			stopBtn.hidden = true;
			previewBtn.disabled = false;
			form.classList.remove( 'running' );
			bar.classList.toggle( 'complete', !! ok );
		}

		( function step() {
			if ( stopped ) {
				runBtn.disabled = false;
				runBtn.innerHTML = '<span class="dashicons dashicons-controls-play"></span> ادامه درون‌ریزی';
				status.textContent = 'متوقف شد؛ با «ادامه» از همان نقطه ادامه می‌یابد.';
				return finish( 'متوقف شد.' );
			}
			post( 'sbpi_run', { job: job } ).then( function ( d ) {
				retries = 0;
				if ( startCursor === null ) { startCursor = Math.max( 0, d.cursor - 1 ); }
				if ( d.log.length ) { log.textContent += d.log.join( '\n' ) + '\n'; log.scrollTop = log.scrollHeight; }
				var pct = d.total ? Math.round( d.cursor / d.total * 100 ) : 100;
				$( '.sbpi-bar span', bar ).style.width = pct + '%';
				$( '.sbpi-pct', bar ).textContent = fa( pct ) + '٪';
				var s = d.stats;
				var text = fa( d.cursor ) + ' از ' + fa( d.total ) + ' — ساخته: ' + fa( s.created ) + ' · به‌روز: ' + fa( s.updated ) + ' · رد: ' + fa( s.skipped ) + ( s.errors ? ' · خطا: ' + fa( s.errors ) : '' );
				var doneN = d.cursor - startCursor;
				if ( doneN > 0 && ! d.done ) {
					var sec = Math.round( ( Date.now() - started ) / 1000 / doneN * ( d.total - d.cursor ) );
					$( '.sbpi-eta', bar ).textContent = '≈ ' + ( sec > 90 ? fa( Math.round( sec / 60 ) ) + ' دقیقه' : fa( sec ) + ' ثانیه' ) + ' مانده';
				}
				document.title = fa( pct ) + '٪ — درون‌ریزی';
				if ( d.done ) {
					finish( '✅ ' + text + ' — <a href="' + esc( d.list ) + '">مشاهده محصولات</a>', true );
					status.innerHTML = s.errors ? 'پایان با ' + fa( s.errors ) + ' خطا؛ گزارش را ببینید.' : 'درون‌ریزی با موفقیت تمام شد.';
					toast( s.errors ? 'پایان با خطا — گزارش را بررسی کنید.' : 'درون‌ریزی تمام شد.', s.errors ? 'bad' : 'good' );
					if ( s.errors ) { $( '#sbpi-log-wrap' ).open = true; }
				} else {
					$( '.sbpi-progress-text', bar ).textContent = text;
					status.textContent = 'در حال درون‌ریزی… صفحه را نبندید.';
					step();
				}
			} ).catch( function ( e ) {
				/* Timeouts/502 on shared hosts: the run is resumable, so retry a few times. */
				if ( retries++ < 3 ) {
					log.textContent += '⚠ ' + e.message + ' — تلاش دوباره…\n';
					return setTimeout( step, 3000 );
				}
				runBtn.disabled = false;
				runBtn.innerHTML = '<span class="dashicons dashicons-controls-play"></span> ادامه درون‌ریزی';
				finish( '<span class="sbpi-len bad">' + esc( e.message ) + '</span>' );
				toast( e.message, 'bad' );
			} );
		} )();
	} );

	/* Warn before leaving mid-run. */
	window.addEventListener( 'beforeunload', function ( e ) {
		if ( form.classList.contains( 'running' ) ) { e.preventDefault(); e.returnValue = ''; }
	} );
} )();
