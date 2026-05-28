<?php
/**
 * Plugin Name:       BALLKORG Cookie Consent
 * Plugin URI:        https://github.com/ballkorg/ballkorg-cookie-consent
 * Description:       GDPR and 152-FZ compliant cookie consent banner with consent logging, a frontend banner toggle, and lazy analytics.
 * Version:           5.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            BALLKORG
 * Author URI:        https://ballkorg.ru
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ballkorg-cookie-consent
 * Domain Path:       /languages
 *
 * @package BallkorgCookieConsent
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( version_compare( PHP_VERSION, '8.0', '<' ) || version_compare( $GLOBALS['wp_version'] ?? '0', '6.0', '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'BALLKORG Cookie Consent requires PHP 8.0+ and WordPress 6.0+.', 'ballkorg-cookie-consent' )
				. '</p></div>';
		}
	);
	return;
}

define( 'BCC_VERSION',     '5.1.0' );
define( 'BCC_FILE',        __FILE__ );
define( 'BCC_DIR',         plugin_dir_path( __FILE__ ) );
define( 'BCC_URL',         plugin_dir_url( __FILE__ ) );
define( 'BCC_ASSETS_URL',  BCC_URL . 'assets/' );
define( 'BCC_DB_VERSION',  '5.0.0' );

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'BallkorgCookieConsent\\';
		if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$relative = str_replace( [ $prefix, '\\' ], [ '', '/' ], $class );
		$file     = BCC_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';
		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

register_activation_hook(
	__FILE__,
	[ 'BallkorgCookieConsent\\Installer', 'activate' ]
);

register_deactivation_hook(
	__FILE__,
	[ 'BallkorgCookieConsent\\Installer', 'deactivate' ]
);

add_action(
	'plugins_loaded',
	static function (): void {
		BallkorgCookieConsent\Plugin::get_instance()->boot();
	}
);