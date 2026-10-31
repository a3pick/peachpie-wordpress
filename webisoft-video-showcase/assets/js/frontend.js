( function () {
	'use strict';

	var connectedDomains = {};

	function injectPreconnect( domains ) {
		( domains || [] ).forEach( function ( domain ) {
			if ( ! domain || connectedDomains[ domain ] ) {
				return;
			}

			connectedDomains[ domain ] = true;

			var link = document.createElement( 'link' );
			link.rel = 'preconnect';
			link.href = domain;
			link.crossOrigin = 'anonymous';
			document.head.appendChild( link );
		} );
	}

	function markLoaded( img ) {
		if ( img.complete ) {
			img.classList.add( 'wvs-loaded' );
		} else {
			img.addEventListener( 'load', function () {
				img.classList.add( 'wvs-loaded' );
			} );
		}
	}

	function initShowcase( root ) {
		var configName = 'WebisoftVideoShowcaseConfig_' + root.id;
		var config = window[ configName ];

		if ( ! config || ! config.items || ! config.items.length ) {
			return;
		}

		injectPreconnect( config.preconnect );

		var grid = root.querySelector( '.wvs-grid' );
		var lightbox = root.querySelector( '.wvs-lightbox' );

		if ( ! grid || ! lightbox ) {
			return;
		}

		grid.querySelectorAll( '.wvs-tile-img' ).forEach( markLoaded );

		var mediaBox = lightbox.querySelector( '.wvs-lb-media' );
		var captionBox = lightbox.querySelector( '.wvs-lb-caption' );
		var linkBox = lightbox.querySelector( '.wvs-lb-link' );
		var closeBtn = lightbox.querySelector( '.wvs-lb-close' );
		var prevBtn = lightbox.querySelector( '.wvs-lb-prev' );
		var nextBtn = lightbox.querySelector( '.wvs-lb-next' );

		var currentIndex = 0;
		var lastFocused = null;

		function buildEmbedUrl( item ) {
			if ( ! config.autoplay ) {
				return item.embedUrl;
			}

			var sep = item.embedUrl.indexOf( '?' ) > -1 ? '&' : '?';
			return item.embedUrl + sep + 'autoplay=1';
		}

		/**
		 * ابعاد جعبه لایت‌باکس/نسبت تصویر آیفریم را بر اساس جهت واقعی ویدیو تنظیم می‌کند —
		 * وگرنه یک ویدیوی افقی (مثلاً وبینار آپارات) توی قاب باریک پیش‌فرض ریلز جا می‌شه و
		 * ریز/با حاشیه سیاه دیده می‌شه.
		 */
		function applyOrientation( key ) {
			lightbox.setAttribute( 'data-orientation', [ 'landscape', 'portrait', 'square' ].indexOf( key ) > -1 ? key : 'landscape' );
		}

		function renderMedia( item ) {
			mediaBox.innerHTML = '';

			if ( 'upload' === item.sourceType && item.fileUrl ) {
				applyOrientation( 'landscape' ); // پیش‌فرض خنثی تا متادیتای واقعی لود بشه

				var video = document.createElement( 'video' );
				video.controls = true;
				video.playsInline = true;
				video.preload = 'metadata';

				if ( item.thumbUrl ) {
					video.poster = item.thumbUrl;
				}

				var source = document.createElement( 'source' );
				source.src = item.fileUrl;
				source.type = 'video/mp4';
				video.appendChild( source );

				video.addEventListener( 'loadedmetadata', function () {
					if ( ! video.videoWidth || ! video.videoHeight ) {
						return;
					}

					var ratio = video.videoWidth / video.videoHeight;
					applyOrientation( ratio >= 1.15 ? 'landscape' : ( ratio <= 0.85 ? 'portrait' : 'square' ) );
				} );

				mediaBox.appendChild( video );

				if ( config.autoplay ) {
					video.autoplay = true;
					video.play().catch( function () {} );
				}
			} else if ( item.embedUrl ) {
				applyOrientation( item.orientation );

				var wrap = document.createElement( 'div' );
				wrap.className = 'wvs-lb-iframe-wrap';

				var iframe = document.createElement( 'iframe' );
				iframe.src = buildEmbedUrl( item );
				iframe.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
				iframe.allowFullscreen = true;
				iframe.loading = 'lazy';
				iframe.referrerPolicy = 'strict-origin-when-cross-origin';

				wrap.appendChild( iframe );
				mediaBox.appendChild( wrap );
			}
		}

		function open( index ) {
			var item = config.items[ index ];

			if ( ! item ) {
				return;
			}

			currentIndex = index;
			lastFocused = document.activeElement;

			renderMedia( item );

			captionBox.textContent = item.caption || '';

			if ( item.link ) {
				linkBox.href = item.link;
				linkBox.textContent = item.title || linkBox.href;
			} else {
				linkBox.removeAttribute( 'href' );
				linkBox.textContent = '';
			}

			lightbox.classList.add( 'is-open' );
			lightbox.setAttribute( 'aria-hidden', 'false' );
			document.body.classList.add( 'wvs-scroll-locked' );

			if ( closeBtn ) {
				closeBtn.focus();
			}
		}

		function close() {
			lightbox.classList.remove( 'is-open' );
			lightbox.setAttribute( 'aria-hidden', 'true' );
			document.body.classList.remove( 'wvs-scroll-locked' );
			mediaBox.innerHTML = '';

			if ( lastFocused && lastFocused.focus ) {
				lastFocused.focus();
			}
		}

		function step( direction ) {
			var len = config.items.length;
			open( ( currentIndex + direction + len ) % len );
		}

		grid.addEventListener( 'click', function ( e ) {
			var tile = e.target.closest( '.wvs-tile' );

			if ( ! tile ) {
				return;
			}

			open( parseInt( tile.getAttribute( 'data-index' ), 10 ) || 0 );
		} );

		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', close );
		}

		if ( prevBtn ) {
			prevBtn.addEventListener( 'click', function () {
				step( -1 );
			} );
		}

		if ( nextBtn ) {
			nextBtn.addEventListener( 'click', function () {
				step( 1 );
			} );
		}

		lightbox.addEventListener( 'click', function ( e ) {
			if ( e.target === lightbox ) {
				close();
			}
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( ! lightbox.classList.contains( 'is-open' ) ) {
				return;
			}

			if ( 'Escape' === e.key ) {
				close();
			} else if ( 'ArrowRight' === e.key ) {
				step( 1 );
			} else if ( 'ArrowLeft' === e.key ) {
				step( -1 );
			}
		} );
	}

	document.querySelectorAll( '.wvs-showcase' ).forEach( initShowcase );
} )();
