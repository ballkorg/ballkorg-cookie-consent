<?php
declare( strict_types=1 );

namespace BallkorgCookieConsent;

/**
 * Business-logic layer for consent lifecycle.
 *
 * Depends on: Consent_Repository (data), IP_Helper (PII), File_Logger (audit).
 * Anti-pattern removed: business logic mixed directly into AJAX handler and banner.
 */
final class Consent_Service {

	private const RETENTION_DAYS  = 365;
	private const ALLOWED_TYPES   = [ 'accepted_all', 'accepted_stats', 'rejected', 'rejected_stats' ];

	public function __construct(
		private readonly Consent_Repository $repository,
		private readonly IP_Helper          $ip_helper,
		private readonly File_Logger        $logger
	) {}

	/**
	 * Validate consent type string.
	 */
	public function is_valid_type( string $type ): bool {
		return in_array( $type, self::ALLOWED_TYPES, true );
	}

	/**
	 * Record a consent decision.
	 *
	 * @return array{id: int, hash: string}|false
	 */
	public function record_consent(
		string $consent_type,
		string $page_url    = '',
		string $screen_res  = '',
		string $timezone    = '',
	): array|false {
		if ( ! $this->is_valid_type( $consent_type ) ) {
			return false;
		}

		$raw_ip   = $this->ip_helper->get_client_ip();
		$ip_hash  = $this->ip_helper->hash_ip( $raw_ip );
		$ip_prefix = $this->ip_helper->anonymize( $raw_ip );

		// User-agent: truncate to DB column width, never expose raw value in responses.
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] )
			? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 )
			: '';

		$expires_at = gmdate( 'Y-m-d H:i:s', strtotime( '+' . self::RETENTION_DAYS . ' days' ) );

		// Consent hash — links DB record to cookie without exposing PII.
		$consent_hash = hash(
			'sha256',
			$ip_hash . $ua . current_time( 'mysql' ) . $consent_type . wp_salt( 'auth' )
		);

		// Supplementary JSON data — NO raw IP stored here.
		$meta = [
			'consent_type'   => $consent_type,
			'wp_user_id'     => get_current_user_id(),
			'wp_version'     => get_bloginfo( 'version' ),
			'plugin_version' => BCC_VERSION,
			'browser_name'   => $this->detect_browser( $ua ),
			'is_ajax'        => true,
		];

		$row = [
			'ip_hash'      => $ip_hash,
			'ip_prefix'    => $ip_prefix,
			'user_agent'   => $ua,
			'consent_type' => $consent_type,
			'consent_data' => wp_json_encode( $meta, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ),
			'page_url'     => substr( esc_url_raw( $page_url ), 0, 500 ),
			'referer_url'  => isset( $_SERVER['HTTP_REFERER'] )
				? substr( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ), 0, 500 )
				: '',
			'screen_res'   => substr( sanitize_text_field( $screen_res ), 0, 20 ),
			'browser_lang' => isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] )
				? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ), 0, 10 )
				: '',
			'timezone'     => substr( sanitize_text_field( $timezone ), 0, 50 ),
			'do_not_track' => isset( $_SERVER['HTTP_DNT'] ) && '1' === $_SERVER['HTTP_DNT'] ? 1 : 0,
			'consent_hash' => $consent_hash,
			'expires_at'   => $expires_at,
		];

		$id = $this->repository->insert( $row );

		if ( false === $id ) {
			return false;
		}

		$this->logger->log( $consent_type, $ip_prefix, $page_url );

		return [ 'id' => $id, 'hash' => $consent_hash ];
	}

	private function detect_browser( string $ua ): string {
		return match ( true ) {
			str_contains( $ua, 'Edg' )                              => 'Edge',
			str_contains( $ua, 'Chrome' )                           => 'Chrome',
			str_contains( $ua, 'Firefox' )                          => 'Firefox',
			str_contains( $ua, 'Safari' ) && ! str_contains( $ua, 'Chrome' ) => 'Safari',
			str_contains( $ua, 'MSIE' ) || str_contains( $ua, 'Trident' ) => 'IE',
			default                                                 => 'Unknown',
		};
	}

	public function get_retention_days(): int {
		return self::RETENTION_DAYS;
	}
}