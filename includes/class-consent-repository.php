<?php
declare( strict_types=1 );

namespace BallkorgCookieConsent;

/**
 * Data-access layer for consent records.
 *
 * Anti-pattern removed: raw SQL queries scattered across admin, cron, and AJAX handlers.
 * Now: single source of truth for all DB operations.
 * All queries use $wpdb->prepare() — no string interpolation with user data.
 */
final class Consent_Repository {

	private string $table;

	public function __construct() {
		$this->table = Installer::get_table_name();
	}

	/**
	 * Persist a new consent record.
	 *
	 * @param array<string, mixed> $data Validated, sanitized data from Consent_Service.
	 * @return int|false  Inserted row ID or false on failure.
	 */
	public function insert( array $data ): int|false {
		global $wpdb;

		$result = $wpdb->insert(
			$this->table,
			[
				'ip_hash'      => $data['ip_hash'],
				'ip_prefix'    => $data['ip_prefix'],
				'user_agent'   => $data['user_agent'],
				'consent_type' => $data['consent_type'],
				'consent_data' => $data['consent_data'],
				'page_url'     => $data['page_url'],
				'referer_url'  => $data['referer_url'],
				'screen_res'   => $data['screen_res'],
				'browser_lang' => $data['browser_lang'],
				'timezone'     => $data['timezone'],
				'do_not_track' => $data['do_not_track'],
				'consent_hash' => $data['consent_hash'],
				'expires_at'   => $data['expires_at'],
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
		);

		if ( false === $result ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[BCC] DB insert error: ' . $wpdb->last_error );
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Check whether a valid (non-expired, accepted) consent exists for an IP hash.
	 */
	public function has_valid_consent( string $ip_hash ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->table}
				 WHERE ip_hash = %s
				   AND consent_type IN ('accepted_all', 'accepted_stats')
				   AND expires_at > NOW()
				 LIMIT 1",
				$ip_hash
			)
		);

		return null !== $result;
	}

	/**
	 * Fetch paginated log rows for the admin page.
	 *
	 * @return array<int, object>
	 */
	public function get_recent_logs( int $limit = 50, int $offset = 0 ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		) ?: [];
	}

	/** @return array<string, int> */
	public function get_stats(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			"SELECT
				COUNT(*) AS total,
				SUM( consent_type LIKE 'accepted%' ) AS accepted,
				SUM( consent_type LIKE 'rejected%' ) AS rejected,
				SUM( expires_at > NOW() )             AS active
			 FROM {$this->table}"
		);

		return [
			'total'    => (int) ( $row->total    ?? 0 ),
			'accepted' => (int) ( $row->accepted ?? 0 ),
			'rejected' => (int) ( $row->rejected ?? 0 ),
			'active'   => (int) ( $row->active   ?? 0 ),
		];
	}

	/**
	 * Delete records older than $days days (cron cleanup).
	 */
	public function delete_expired( int $days ): int|false {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);
	}

	public function table_exists(): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table )
		) === $this->table;
	}
}