<?php
declare( strict_types=1 );

namespace BallkorgCookieConsent;

/**
 * IP address utilities — anonymization and hashing.
 *
 * GDPR fix: we NEVER store the raw IP.
 * We store:
 *   - ip_hash   → sha256(ip + wp_salt())  for deduplication / fraud detection
 *   - ip_prefix → first 2 octets only     for geo-aggregation
 *
 * Fix applied: IPv6 anonymization now uses inet_pton() instead of explode(':').
 * explode() breaks on compressed addresses like 2001:db8::1 or ::ffff:1.2.3.4.
 * inet_pton() correctly parses ANY valid IPv6 format into raw bytes.
 */
final class IP_Helper {

	/**
	 * Returns the most-likely real client IP from the request headers.
	 * Validates each candidate — never trusts unsanitised header values.
	 */
	public function get_client_ip(): string {
		$headers = [
			'HTTP_CF_CONNECTING_IP',   // Cloudflare (most trusted — set by CF infra)
			'HTTP_TRUE_CLIENT_IP',     // Akamai
			'HTTP_X_FORWARDED_FOR',    // Standard proxy chain (take first)
			'HTTP_X_REAL_IP',          // Nginx common
			'REMOTE_ADDR',             // Direct connection — always last
		];

		foreach ( $headers as $header ) {
			if ( empty( $_SERVER[ $header ] ) ) {
				continue;
			}
			// X-Forwarded-For may contain a comma-separated list; take the first.
			$candidate = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) )[0] );
			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				return $candidate;
			}
		}

		return '0.0.0.0';
	}

	/**
	 * One-way hash of the real IP — allows deduplication without storing PII.
	 */
	public function hash_ip( string $ip ): string {
		return hash( 'sha256', $ip . wp_salt( 'auth' ) );
	}

	/**
	 * Returns the anonymized IP prefix for geo-aggregation display.
	 *
	 * IPv4 result:   "1.2.x.x"
	 * IPv6 result:   "2001:0db8:x:x:x:x:x:x"  (first 2 groups, 32 bits)
	 * Private range: "private"
	 * Loopback:      "local"
	 * Unknown:       "unknown"
	 *
	 * Fix: IPv6 now parsed via inet_pton() — handles compressed notation
	 * like 2001:db8::1, ::1, ::ffff:192.0.2.1 without breaking.
	 */
	public function anonymize( string $ip ): string {

		// --- Loopback / unspecified ----------------------------------------
		if ( in_array( $ip, [ '::1', '127.0.0.1', '0.0.0.0' ], true ) ) {
			return 'local';
		}

		// --- IPv4 -------------------------------------------------------------
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts = explode( '.', $ip );

			// RFC1918 / link-local private ranges.
			if (
				'10' === $parts[0]
				|| ( '172' === $parts[0] && (int) $parts[1] >= 16 && (int) $parts[1] <= 31 )
				|| ( '192' === $parts[0] && '168' === $parts[1] )
				|| ( '169' === $parts[0] && '254' === $parts[1] )
			) {
				return 'private';
			}

			// Public IPv4: keep first two octets only.
			return $parts[0] . '.' . $parts[1] . '.x.x';
		}

		// --- IPv6 -------------------------------------------------------------
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {

			// inet_pton() converts ANY valid IPv6 string (including compressed
			// forms like 2001:db8::1) into a 16-byte binary string.
			// This is the ONLY reliable way to parse compressed IPv6.
			$bin = inet_pton( $ip );

			if ( false === $bin ) {
				// Should never happen after FILTER_VALIDATE_IP passed, but be safe.
				return 'unknown';
			}

			// Convert binary → hex string (32 hex chars = 16 bytes).
			$hex = bin2hex( $bin );

			// Split into 8 groups of 4 hex chars (standard IPv6 notation).
			// e.g. "20010db8000000000000000000000001"
			// → ["2001","0db8","0000","0000","0000","0000","0000","0001"]
			$groups = str_split( $hex, 4 );

			// Check for private/link-local ranges using the first group.
			$first = strtolower( $groups[0] );

			// fe80::/10  link-local
			// fc00::/7   Unique Local (ULA) — includes fd00::/8
			if (
				str_starts_with( $first, 'fe8' )
				|| str_starts_with( $first, 'fea' )
				|| str_starts_with( $first, 'feb' )
				|| str_starts_with( $first, 'fc' )
				|| str_starts_with( $first, 'fd' )
			) {
				return 'private';
			}

			// Keep only first 2 groups (32 bits = network prefix for most ISPs).
			// Result example: "2001:0db8:x:x:x:x:x:x"
			return $groups[0] . ':' . $groups[1] . ':x:x:x:x:x:x';
		}

		return 'unknown';
	}
}