<?php
declare( strict_types=1 );

namespace BallkorgCookieConsent;

/**
 * Activation, deactivation, and DB migrations.
 *
 * ВАЖНО (152-ФЗ): данные согласий являются записями об обработке
 * персональных данных. База НЕ удаляется при деактивации плагина.
 * Удаление возможно только через WP Tools → Cookie Logs → кнопку
 * «Удалить все записи» с явным подтверждением администратора.
 */
final class Installer {

	private const TABLE_SUFFIX = 'bcc_cookie_consents';

	public static function activate(): void {
		self::run_migrations();

		if ( ! wp_next_scheduled( 'bcc_cleanup_db_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'bcc_cleanup_db_logs' );
		}
		if ( ! wp_next_scheduled( 'bcc_cleanup_file_logs' ) ) {
			wp_schedule_event( time(), 'weekly', 'bcc_cleanup_file_logs' );
		}

		// Если плагин переустанавливается — таблица уже есть, dbDelta её
		// не тронет. Все ранее сохранённые согласия остаются нетронутыми.
		flush_rewrite_rules();
	}

	/**
	 * Деактивация: отключаем cron, но ДАННЫЕ НЕ ТРОГАЕМ.
	 * Это намеренно — 152-ФЗ требует хранения записей о согласии.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'bcc_cleanup_db_logs' );
		wp_clear_scheduled_hook( 'bcc_cleanup_file_logs' );
		flush_rewrite_rules();
		// Таблица wp_bcc_cookie_consents и все данные сохраняются.
		// Удаление возможно только через явную кнопку в настройках.
	}

	/**
	 * Idempotent — dbDelta добавляет недостающие колонки/индексы,
	 * никогда не удаляет существующие данные.
	 */
	public static function run_migrations(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . self::TABLE_SUFFIX;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id            bigint(20) UNSIGNED  NOT NULL AUTO_INCREMENT,
			ip_hash       varchar(64)          NOT NULL DEFAULT '',
			ip_prefix     varchar(20)          NOT NULL DEFAULT '',
			user_agent    varchar(255)         NOT NULL DEFAULT '',
			consent_type  varchar(50)          NOT NULL DEFAULT '',
			consent_data  longtext             NOT NULL,
			page_url      varchar(500)         NOT NULL DEFAULT '',
			referer_url   varchar(500)                  DEFAULT NULL,
			screen_res    varchar(20)                   DEFAULT NULL,
			browser_lang  varchar(10)                   DEFAULT NULL,
			timezone      varchar(50)                   DEFAULT NULL,
			do_not_track  tinyint(1) UNSIGNED  NOT NULL DEFAULT 0,
			consent_hash  varchar(64)          NOT NULL DEFAULT '',
			created_at    datetime             NOT NULL DEFAULT CURRENT_TIMESTAMP,
			expires_at    datetime             NOT NULL,
			PRIMARY KEY   (id),
			KEY idx_ip_hash     (ip_hash),
			KEY idx_type        (consent_type),
			KEY idx_expires     (expires_at),
			KEY idx_hash        (consent_hash(32)),
			KEY idx_created     (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'bcc_db_version', BCC_DB_VERSION );
	}

	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}
}