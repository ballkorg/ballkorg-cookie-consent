<?php
declare( strict_types=1 );

namespace BallkorgCookieConsent;

/**
 * WordPress AJAX endpoint wiring.
 *
 * Anti-pattern removed: AJAX handler doing DB inserts, cookie logic,
 * session management, AND nonce checking all in one function.
 * Now: validate → delegate → respond.
 */
final class Ajax_Handler {

	private const ACTION = 'bcc_save_consent';
	private const NONCE  = 'bcc_consent_nonce';

	public function __construct(
		private readonly Consent_Service $service
	) {}

	public function register_hooks(): void {
		add_action( 'wp_ajax_' . self::ACTION,        [ $this, 'handle_save_consent' ] );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, [ $this, 'handle_save_consent' ] );
	}

	public function handle_save_consent(): void {
		// 1. Nonce check — first line of defence.
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error(
				[ 'message' => esc_html__( 'Security check failed.', 'ballkorg-cookie-consent' ) ],
				403
			);
		}

		// 2. Input.
		$consent_type = isset( $_POST['consent_type'] ) ? sanitize_key( wp_unslash( $_POST['consent_type'] ) ) : '';
		$screen_res   = isset( $_POST['screen_res'] )   ? sanitize_text_field( wp_unslash( $_POST['screen_res'] ) ) : '';
		$timezone     = isset( $_POST['timezone'] )     ? sanitize_text_field( wp_unslash( $_POST['timezone'] ) ) : '';
		$page_url     = isset( $_POST['page_url'] )     ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';

		// 3. Business validation.
		if ( ! $this->service->is_valid_type( $consent_type ) ) {
			wp_send_json_error(
				[ 'message' => esc_html__( 'Invalid consent type.', 'ballkorg-cookie-consent' ) ],
				422
			);
		}

		// 4. Delegate to service.
		$result = $this->service->record_consent( $consent_type, $page_url, $screen_res, $timezone );

		if ( false === $result ) {
			wp_send_json_error(
				[ 'message' => esc_html__( 'Failed to save consent.', 'ballkorg-cookie-consent' ) ],
				500
			);
		}

		wp_send_json_success(
			[
				'consent_type' => $consent_type,
				'consent_hash' => $result['hash'],
				'log_id'       => $result['id'],
			]
		);
	}

	/**
	 * Returns the nonce action string — used by Banner_Renderer to output
	 * the nonce into the page without coupling Banner to AJAX internals.
	 */
	public static function get_nonce_action(): string {
		return self::NONCE;
	}
}