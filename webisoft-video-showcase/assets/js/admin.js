( function ( $ ) {
	'use strict';

	$( function () {
		initTabs();

		var $wrapper = $( '#wvs-items-wrapper' );

		function template( id ) {
			var el = document.getElementById( id );
			return el ? el.innerHTML : '';
		}

		function nextIndex() {
			return $wrapper.find( '> .wvs-item-row' ).length;
		}

		/* افزودن ویدیوی جدید */
		$( '#wvs-add-item' ).on( 'click', function () {
			var html = template( 'wvs-item-template' ).replace( /__INDEX__/g, nextIndex() );
			$wrapper.append( html );
		} );

		/* حذف */
		$( document ).on( 'click', '.wvs-remove-item', function () {
			$( this ).closest( '.wvs-item-row' ).remove();
			reindex();
		} );

		/* جابجایی با درگ‌اند‌دراپ */
		if ( $.fn.sortable ) {
			$wrapper.sortable( {
				handle: '.wvs-drag-handle',
				update: reindex
			} );
		}

		/* toggle آپلود/لینک خارجی */
		$( document ).on( 'change', '.wvs-source-radio', function () {
			var $row = $( this ).closest( '.wvs-item-row' );
			var value = $( this ).val();
			var isExternal = 'external' === value;

			$row.attr( 'data-source', value );
			$row.find( '.wvs-source-upload' ).toggle( ! isExternal );
			$row.find( '.wvs-source-external' ).toggle( isExternal );

			$row.find( '.wvs-pill' )
				.toggleClass( 'wvs-pill-muted', isExternal )
				.toggleClass( 'wvs-pill-success', ! isExternal )
				.text( isExternal ? 'لینک خارجی' : 'آپلودی' );
		} );

		/* بروزرسانی زنده عنوان بالای هر ردیف حین تایپ */
		$( document ).on( 'input', 'input[name$="[title]"]', function () {
			var $row = $( this ).closest( '.wvs-item-row' );
			var value = $.trim( $( this ).val() );

			if ( value ) {
				$row.find( '.wvs-item-row-title' ).text( value );
			}
		} );

		/* بروزرسانی زنده تصویر کوچک بالای ردیف وقتی کاور تغییر می‌کند */
		function updateRowThumb( $row, url ) {
			var $thumb = $row.find( '.wvs-item-row-thumb' );
			$thumb.find( 'img' ).attr( 'src', url );
			$thumb.toggleClass( 'is-empty', ! url );
		}

		/* آپلودگر تصویر کاور */
		$( document ).on( 'click', '.wvs-pick-thumbnail', function ( e ) {
			e.preventDefault();

			var $btn = $( this );
			var $row = $btn.closest( '.wvs-item-row' );

			var frame = wp.media( {
				title: 'انتخاب تصویر کاور',
				button: { text: 'استفاده' },
				library: { type: 'image' },
				multiple: false
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				var previewUrl = ( attachment.sizes && attachment.sizes.thumbnail ) ? attachment.sizes.thumbnail.url : attachment.url;

				$row.find( '.wvs-field-thumb-id' ).val( attachment.id );
				$row.find( '.wvs-thumb-preview' ).attr( 'src', previewUrl ).show();
				updateRowThumb( $row, previewUrl );
			} );

			frame.open();
		} );

		/* آپلودگر فایل ویدیو */
		$( document ).on( 'click', '.wvs-pick-video', function ( e ) {
			e.preventDefault();

			var $btn = $( this );
			var $row = $btn.closest( '.wvs-item-row' );

			var frame = wp.media( {
				title: 'انتخاب فایل ویدیو',
				button: { text: 'استفاده' },
				library: { type: 'video' },
				multiple: false
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();

				$row.find( '.wvs-field-video-id' ).val( attachment.id );
				$row.find( '.wvs-file-name' ).text( attachment.filename || attachment.url );
			} );

			frame.open();
		} );

		/* بازشماری name attributeها بعد از افزودن/حذف/جابجایی */
		function reindex() {
			$wrapper.find( '> .wvs-item-row' ).each( function ( index ) {
				var $row = $( this );
				var titleValue = $row.find( 'input[name$="[title]"]' ).val();

				if ( ! titleValue ) {
					$row.find( '.wvs-item-row-title' ).text( 'ویدیو ' + ( index + 1 ) );
				}

				$row.find( 'input, select, textarea' ).each( function () {
					var name = $( this ).attr( 'name' );

					if ( ! name ) {
						return;
					}

					name = name.replace( /wvs_items\[\d+\]/, 'wvs_items[' + index + ']' );
					$( this ).attr( 'name', name );
				} );
			} );
		}

		/* تب‌ها سمت کلاینت‌اند (بدون رفرش) — همه تب‌ها داخل یک <form> واحدند، پس با یک submit
		 * ذخیره می‌شن، صرف‌نظر از اینکه کاربر موقع ذخیره کدوم تب رو باز گذاشته. */
		function initTabs() {
			var tabs = document.querySelectorAll( '.wvs-tab' );

			if ( ! tabs.length ) {
				return;
			}

			tabs.forEach( function ( tab ) {
				tab.addEventListener( 'click', function () {
					var target = tab.getAttribute( 'data-tab' );

					document.querySelectorAll( '.wvs-tab' ).forEach( function ( t ) {
						t.classList.remove( 'active' );
					} );
					document.querySelectorAll( '.wvs-panel' ).forEach( function ( p ) {
						p.hidden = true;
					} );

					tab.classList.add( 'active' );

					var panel = document.querySelector( '.wvs-panel[data-panel="' + target + '"]' );

					if ( panel ) {
						panel.hidden = false;
					}
				} );
			} );
		}
	} );
} )( jQuery );
