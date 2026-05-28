/**
 * Ballkorg Cookie Consent — front-end controller.
 *
 * Depends on: bccConfig (injected by wp_localize_script)
 * No jQuery. No external libraries.
 *
 * Architecture:
 *  BccConsent.init()         — boots on DOMContentLoaded
 *  BccConsent.hasConsent()   — reads localStorage + cookie
 *  BccConsent.showBanner()   — CSS class toggle (no setTimeout)
 *  BccConsent.hideBanner()   — CSS class toggle animation
 *  BccConsent.saveConsent()  — persists locally + fires AJAX
 *  BccConsent.reset()        — global reset, exposed on window
 *
 * Fix applied: setTimeout(800) removed.
 * Banner is now hidden via CSS opacity/visibility, not display:none.
 * showBanner() uses requestAnimationFrame for reliable transition trigger.
 */

( function ( config ) {
	'use strict';

	if ( ! config ) {
		return; // Guard: bccConfig not injected — bail silently.
	}

	const COOKIE_NAME = config.cookieName  || 'bcc_consent';
	const ACCEPTED    = [ 'accepted_all', 'accepted_stats' ];

	// -------------------------------------------------------------------------
	// Storage helpers
	// -------------------------------------------------------------------------

	function getCookie( name ) {
		const match = document.cookie.split( '; ' ).find( function ( row ) {
			return row.startsWith( name + '=' );
		} );
		return match ? decodeURIComponent( match.split( '=' )[ 1 ] ) : null;
	}

	function setCookie( name, value, days ) {
		const expires = new Date();
		expires.setDate( expires.getDate() + days );
		document.cookie = name + '=' + encodeURIComponent( value )
			+ '; expires=' + expires.toUTCString()
			+ '; path=/; SameSite=Lax'
			+ ( location.protocol === 'https:' ? '; Secure' : '' );
	}

	function deleteCookie( name ) {
		document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; SameSite=Lax';
	}

	// -------------------------------------------------------------------------
	// Consent state
	// -------------------------------------------------------------------------

	function hasConsent() {
		const ls = localStorage.getItem( COOKIE_NAME );
		if ( ls && ACCEPTED.includes( ls ) ) return true;
		const ck = getCookie( COOKIE_NAME );
		return !! ( ck && ACCEPTED.includes( ck ) );
	}

	function persistLocally( type ) {
		localStorage.setItem( COOKIE_NAME, type );
		localStorage.setItem( COOKIE_NAME + '_date', new Date().toISOString() );
		setCookie( COOKIE_NAME, type, config.retentionDays || 365 );
	}

	function clearLocal() {
		[ COOKIE_NAME, COOKIE_NAME + '_date', COOKIE_NAME + '_hash' ].forEach( function ( key ) {
			localStorage.removeItem( key );
			deleteCookie( key );
		} );
	}

	// -------------------------------------------------------------------------
	// Analytics lazy-loading (Yandex Metrika / Google Consent Mode hook)
	// -------------------------------------------------------------------------

	function maybeLoadAnalytics( consentType ) {
		// Google Consent Mode v2 — update before loading GTM/GA.
		if ( typeof window.gtag === 'function' ) {
			const granted = ACCEPTED.includes( consentType ) ? 'granted' : 'denied';
			window.gtag( 'consent', 'update', {
				ad_storage:              granted,
				analytics_storage:       granted,
				functionality_storage:   'granted',
				personalization_storage: granted,
				security_storage:        'granted',
			} );
		}

		// Yandex Metrika — inject only on accept, only once.
		const metrikaId = config.metrikaId;
		if ( ! metrikaId || ! ACCEPTED.includes( consentType ) ) return;
		if ( window._bccMetrikaLoaded ) return;
		if ( document.querySelector( 'script[src*="mc.yandex.ru/metrika"]' ) ) {
			window._bccMetrikaLoaded = true;
			return;
		}

		const script   = document.createElement( 'script' );
		script.type    = 'text/javascript';
		script.async   = true;
		script.src     = 'https://mc.yandex.ru/metrika/tag.js';
		script.onload  = function () {
			window.ym( metrikaId, 'init', {
				clickmap:            true,
				trackLinks:          true,
				accurateTrackBounce: true,
				webvisor:            true,
			} );
		};
		document.head.appendChild( script );
		window._bccMetrikaLoaded = true;
	}

	// -------------------------------------------------------------------------
	// Banner UI
	// -------------------------------------------------------------------------

	function showBanner( banner ) {
		// Banner is hidden via CSS opacity/visibility (not display:none).
		// requestAnimationFrame ensures the class is added after a paint tick
		// so the CSS transition fires correctly.
		requestAnimationFrame( function () {
			banner.classList.add( 'bcc-banner--visible' );
		} );
	}

	function hideBanner( banner ) {
		banner.classList.remove( 'bcc-banner--visible' );
		// After the CSS transition ends (0.3s), visibility:hidden kicks in
		// automatically via the CSS transition delay — no JS needed here.
	}

	// -------------------------------------------------------------------------
	// AJAX persistence
	// -------------------------------------------------------------------------

	function sendToServer( type, pageUrl, screenRes, tz ) {
		const body = new URLSearchParams( {
			action:       config.action,
			nonce:        config.nonce,
			consent_type: type,
			page_url:     pageUrl,
			screen_res:   screenRes,
			timezone:     tz,
		} );

		fetch( config.ajaxUrl, {
			method:      'POST',
			credentials: 'same-origin',
			headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
			body:        body.toString(),
		} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) {
				if ( data.success && data.data.consent_hash ) {
					localStorage.setItem( COOKIE_NAME + '_hash', data.data.consent_hash );
				}
			} )
			.catch( function ( err ) {
				// Non-fatal — consent is already stored locally.
				if ( window.console && console.warn ) {
					console.warn( '[BCC] Server sync failed:', err );
				}
			} );
	}

	// -------------------------------------------------------------------------
	// Core save flow
	// -------------------------------------------------------------------------

	function saveConsent( type, banner ) {
		hideBanner( banner );
		persistLocally( type );
		maybeLoadAnalytics( type );
		sendToServer(
			type,
			location.href,
			window.screen.width + 'x' + window.screen.height,
			Intl.DateTimeFormat().resolvedOptions().timeZone
		);
	}

	// -------------------------------------------------------------------------
	// Public reset — exposed so theme/shortcode can call window.bccReset()
	// -------------------------------------------------------------------------

	window.bccReset = function ( event ) {
		if ( event ) { event.preventDefault(); }
		if ( ! window.confirm( config.i18n.resetConfirm ) ) return false;

		clearLocal();

		const banner = document.getElementById( 'bcc-banner' );
		if ( banner ) showBanner( banner );

		return false;
	};

	// -------------------------------------------------------------------------
	// Boot
	// -------------------------------------------------------------------------

	document.addEventListener( 'DOMContentLoaded', function () {
		const banner = document.getElementById( 'bcc-banner' );
		if ( ! banner ) return;

		// If consent already given — load analytics silently, no banner shown.
		const existing = localStorage.getItem( COOKIE_NAME ) || getCookie( COOKIE_NAME );
		if ( existing ) {
			maybeLoadAnalytics( existing );
			return;
		}

		// Show banner immediately — CSS transition handles the animation.
		// No setTimeout needed: banner is hidden via opacity/visibility, not display:none.
		showBanner( banner );

		banner.addEventListener( 'click', function ( e ) {
			const btn = e.target.closest( '[data-consent]' );
			if ( btn ) saveConsent( btn.dataset.consent, banner );
		} );
	} );

} )( window.bccConfig );