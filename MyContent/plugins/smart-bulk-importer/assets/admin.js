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
			if ( ! window.confirm( 'همه محصولاتی که در این نوبت ساخته شده‌اند برای همیشه حذف می‌شوند. ادامه می‌دهید؟' ) ) { return; }
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

	var form = $( '#sbpi-form' );
	if ( ! form ) { return; }
	var job = form.dataset.job;
	var runBtn = $( '#sbpi-run' );
	var stopBtn = $( '#sbpi-stop' );
	var stopped = false;

	/* Role-dependent fields: name/slug only matter for attributes. */
	function syncRow( tr ) {
		var role = $( '[data-c="role"]', tr ).value;
		var isAttr = role === 'var_attr' || role === 'info_attr' || role === 'split_attr';
		$( '[data-c="name"]', tr ).disabled = ! isAttr;
		var pick = $( '[data-c="pick"]', tr );
		pick.disabled = ! isAttr;
		$( '[data-c="slug"]', tr ).disabled = ! isAttr || pick.value !== '';
		$( '[data-c="slug"]', tr ).hidden = pick.value !== '';
		tr.classList.toggle( 'sbpi-off', role === 'ignore' );
	}
	$$( '.sbpi-cols tbody tr' ).forEach( function ( tr ) {
		syncRow( tr );
		$( '[data-c="role"]', tr ).addEventListener( 'change', function () { syncRow( tr ); } );
		$( '[data-c="pick"]', tr ).addEventListener( 'change', function () {
			var opt = this.options[ this.selectedIndex ];
			if ( this.value ) {
				$( '[data-c="slug"]', tr ).value = this.value;
				$( '[data-c="name"]', tr ).value = opt.dataset.label;
			} else {
				$( '[data-c="slug"]', tr ).value = '';
			}
			syncRow( tr );
		} );
	} );
	/* Any change invalidates the last preview. */
	form.addEventListener( 'input', function () { runBtn.disabled = true; } );
	form.addEventListener( 'change', function () { runBtn.disabled = true; } );

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
		return '<span class="sbpi-len ' + ( n > max ? 'bad' : 'ok' ) + '">' + n + '/' + max + '</span>';
	}

	$( '#sbpi-preview' ).addEventListener( 'click', function () {
		var btn = this;
		var out = $( '#sbpi-result' );
		btn.disabled = true;
		out.innerHTML = '<p>در حال تحلیل…</p>';
		post( 'sbpi_preview', { job: job, settings: JSON.stringify( collect() ) } ).then( function ( d ) {
			var html = '<div class="sbpi-summary"><strong>' + d.count + '</strong> محصول، <strong>' + d.variations + '</strong> تنوع' +
				( d.seo_plugin ? ' — متای سئو در <strong>' + esc( d.seo_plugin ) + '</strong> ذخیره می‌شود.' : '' ) + '</div>';
			var df = d.diff;
			html += '<div class="sbpi-diff"><span class="sbpi-len ok">جدید: ' + df.new + '</span> ' +
				'<span class="sbpi-len ' + ( df.mode === 'skip' ? 'bad' : 'ok' ) + '">' + ( df.mode === 'skip' ? 'موجود (رد می‌شود): ' : 'موجود (به‌روزرسانی): ' ) + df.update + '</span> ' +
				'<span class="sbpi-len ' + ( df.pcount ? 'bad' : 'ok' ) + '">تغییر قیمت: ' + df.pcount + '</span></div>';
			if ( df.pcount && df.mode !== 'skip' ) {
				html += '<details open><summary>تغییرات قیمت (قبلی ← جدید)' + ( df.pcount > df.prices.length ? ' — ' + df.prices.length + ' مورد اول' : '' ) + '</summary><table class="widefat striped"><tbody>' +
					df.prices.map( function ( p ) {
						var up = Number( p[3] ) > Number( p[2] ) ? '▲' : '▼';
						return '<tr><td>' + esc( p[0] ) + '</td><td>' + esc( p[1] ) + '</td><td>' + esc( p[2] || '—' ) + '</td><td><strong>' + esc( p[3] ) + '</strong> ' + up + '</td></tr>';
					} ).join( '' ) + '</tbody></table></details>';
			}
			if ( d.warnings.length ) {
				html += '<div class="notice notice-warning inline"><ul>' + d.warnings.map( function ( w ) { return '<li>' + esc( w ) + '</li>'; } ).join( '' ) + '</ul></div>';
			}
			html += '<table class="widefat striped sbpi-preview"><thead><tr><th>محصول</th><th>نوع</th><th>دسته / برند</th><th>ویژگی‌ها (★ = متغیر)</th><th>سئو</th></tr></thead><tbody>';
			d.products.forEach( function ( p, i ) {
				html += '<tr>' +
					'<td><span class="sbpi-st sbpi-st-' + p.status + '">' + { 'new': 'جدید', update: 'به‌روزرسانی', skip: 'رد می‌شود' }[ p.status ] + '</span>' +
					( p.new_vars && p.status === 'update' ? ' <small>+' + p.new_vars + ' تنوع جدید</small>' : '' ) +
					'<br><strong>' + esc( p.title ) + '</strong><br><code dir="ltr">' + esc( decodeURIComponent( p.slug ) ) + '</code><br><small>سطرها: ' + esc( p.lines ) + '</small></td>' +
					'<td>' + ( p.type === 'variable' ? 'متغیر<br><strong>' + p.variations + '</strong> تنوع' : 'ساده' ) + ( p.priced ? '' : '<br><span class="sbpi-len bad">بدون قیمت</span>' ) + '</td>' +
					'<td>' + esc( p.category ) + '<br><small>' + esc( p.brand ) + '</small></td>' +
					'<td><small>' + esc( p.attributes ) + '</small></td>' +
					'<td class="sbpi-serp"><div class="t">' + esc( p.seo_title ) + ' ' + len( p.seo_title, 65 ) + '</div>' +
					'<div class="d">' + esc( p.seo_desc ) + ' ' + len( p.seo_desc, 158 ) + '</div>' +
					'<div class="k">🔑 ' + esc( p.focus ) + '</div>' +
					( p.content ? '<details><summary>توضیحات تولیدشده</summary><div class="sbpi-content" data-i="' + i + '"></div></details>' : '' ) +
					'</td></tr>';
			} );
			html += '</tbody></table>';
			out.innerHTML = html;
			/* Server already ran wp_kses_post on content. */
			$$( '.sbpi-content', out ).forEach( function ( el ) { el.innerHTML = d.products[ el.dataset.i ].content; } );
			runBtn.disabled = d.count === 0;
		} ).catch( function ( e ) {
			out.innerHTML = '<div class="notice notice-error inline"><p>' + esc( e.message ) + '</p></div>';
		} ).then( function () { btn.disabled = false; } );
	} );

	stopBtn.addEventListener( 'click', function () { stopped = true; stopBtn.disabled = true; } );

	runBtn.addEventListener( 'click', function () {
		if ( ! window.confirm( 'درون‌ریزی شروع شود؟ (پیشنهاد: قبل از اولین اجرا روی سایت اصلی، نسخه پشتیبان بگیرید یا روی Staging تست کنید.)' ) ) { return; }
		var log = $( '#sbpi-log' );
		var bar = $( '#sbpi-progress' );
		var retries = 0;
		stopped = false;
		runBtn.disabled = true;
		$( '#sbpi-preview' ).disabled = true;
		stopBtn.hidden = false;
		stopBtn.disabled = false;
		log.hidden = false;
		bar.hidden = false;
		log.textContent = '';

		function finish( msg ) {
			$( '.sbpi-progress-text', bar ).innerHTML = msg;
			stopBtn.hidden = true;
			$( '#sbpi-preview' ).disabled = false;
		}

		( function step() {
			if ( stopped ) {
				runBtn.disabled = false;
				runBtn.textContent = '▶ ادامه درون‌ریزی';
				return finish( 'متوقف شد. با «ادامه» از همان نقطه ادامه می‌یابد.' );
			}
			post( 'sbpi_run', { job: job } ).then( function ( d ) {
				retries = 0;
				if ( d.log.length ) { log.textContent += d.log.join( '\n' ) + '\n'; log.scrollTop = log.scrollHeight; }
				var pct = d.total ? Math.round( d.cursor / d.total * 100 ) : 100;
				$( '.sbpi-bar span', bar ).style.width = pct + '%';
				var s = d.stats;
				var text = d.cursor + ' / ' + d.total + ' — ساخته: ' + s.created + ' · به‌روز: ' + s.updated + ' · رد: ' + s.skipped + ' · خطا: ' + s.errors;
				if ( d.done ) {
					finish( '✅ پایان. ' + text + ' — <a href="' + esc( d.list ) + '">مشاهده محصولات</a>' );
				} else {
					$( '.sbpi-progress-text', bar ).textContent = text;
					step();
				}
			} ).catch( function ( e ) {
				/* Timeouts/502 on shared hosts: the run is resumable, so retry a few times. */
				if ( retries++ < 3 ) {
					log.textContent += '⚠ ' + e.message + ' — تلاش دوباره…\n';
					return setTimeout( step, 3000 );
				}
				runBtn.disabled = false;
				runBtn.textContent = '▶ ادامه درون‌ریزی';
				finish( '<span class="sbpi-len bad">' + esc( e.message ) + '</span>' );
			} );
		} )();
	} );
} )();
