<?php
declare( strict_types=1 );

namespace BallkorgCookieConsent;

/**
 * Append-only file audit log.
 *
 * Anti-pattern removed:
 * - Log at WP_CONTENT_DIR root → publicly accessible via URL.
 * - Now: stored in uploads/bcc-logs/ with .htaccess deny rule.
 * - Filename rotated monthly → automatic size management.
 */
final class File_Logger {

	private const LOG_DIR_NAME = 'bcc-logs';
	private const MAX_LINES    = 10000;

	private string $log_dir;

	public function __construct() {
		$upload          = wp_upload_dir();
		$this->log_dir   = trailingslashit( $upload['basedir'] ) . self::LOG_DIR_NAME;
	}

	public function log( string $consent_type, string $ip_prefix, string $page_url ): void {
		if ( ! $this->ensure_directory() ) {
			return;
		}

		$file  = $this->log_dir . '/consent-' . gmdate( 'Y-m' ) . '.log';
		$entry = sprintf(
			"[%s] type=%s ip=%s page=%s\n",
			gmdate( 'Y-m-d H:i:s' ),
			$consent_type,
			$ip_prefix,
			substr( $page_url, 0, 120 )
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file, $entry, FILE_APPEND | LOCK_EX );
	}

	public function rotate( int $max_days = 30 ): void {
		if ( ! is_dir( $this->log_dir ) ) {
			return;
		}

		$cutoff = strtotime( '-' . $max_days . ' days' );
		foreach ( glob( $this->log_dir . '/*.log' ) ?: [] as $file ) {
			if ( filemtime( $file ) < $cutoff ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				@unlink( $file );
			}
		}
	}

	private function ensure_directory(): bool {
		if ( is_dir( $this->log_dir ) ) {
			return true;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		if ( ! @mkdir( $this->log_dir, 0755, true ) ) {
			return false;
		}

		// Block direct HTTP access — critical security step.
		$htaccess = $this->log_dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $htaccess, "Deny from all\n" );
		}

		// Prevent PHP execution inside the log dir.
		$index = $this->log_dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index, "<?php // Silence is golden.\n" );
		}

		return true;
	}
}