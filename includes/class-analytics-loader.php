<?php
declare( strict_types=1 );

namespace BallkorgCookieConsent;

/**
 * Analytics lazy-loading: Google Consent Mode v2 + Yandex Metrika.
 *
 * Architecture:
 * - PHP outputs ONLY the GCM default "denied" state (cache-safe, always denied).
 * - All analytics loading happens 100% client-side in cookie-banner.js,
 *   which reads localStorage after user consent.
 *
 * Removed: output_metrika_placeholder() — <noscript> img fired a request to
 * mc.yandex.ru WITHOUT consent for JS-disabled visitors. Direct violation of
 * 152-FZ Art.9 and ePrivacy Directive. Users without JS cannot consent,
 * therefore no third-party requests should be made for them.
 */
final class Analytics_Loader {

    public function register_hooks(): void {
        // GCM default consent state — safe to cache: always "denied" for every visitor.
        // JS (cookie-banner.js) calls gtag('consent','update',...) after user action.
        add_action( 'wp_head', [ $this, 'output_gcm_defaults' ], 1 );
        // output_metrika_placeholder hook removed intentionally — see class docblock.
    }

    /**
     * Google Consent Mode v2 — default denied state.
     * Must fire BEFORE any GTM/GA script tags (priority 1).
     */
    public function output_gcm_defaults(): void {
        ?>
        <!-- Ballkorg Cookie Consent: Google Consent Mode v2 defaults -->
        <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){ dataLayer.push( arguments ); }

        gtag( 'consent', 'default', {
            ad_storage:              'denied',
            analytics_storage:       'denied',
            functionality_storage:   'granted',
            personalization_storage: 'denied',
            security_storage:        'granted',
            wait_for_update:         500
        } );
        </script>
        <!-- /Ballkorg Cookie Consent -->
        <?php
    }
}