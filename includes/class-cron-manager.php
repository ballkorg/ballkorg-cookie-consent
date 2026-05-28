<?php
declare( strict_types=1 );

namespace BallkorgCookieConsent;

/**
 * Cron job registration and execution.
 *
 * Anti-pattern removed: cron setup called inside CREATE TABLE function
 * and inside every page-load hook — resulted in redundant wp_next_scheduled()
 * calls (DB query) on every request.
 */
final class Cron_Manager {

	private const HOOK_DB   = 'bcc_cleanup_db_logs';
	private const HOOK_FILE = 'bcc_cleanup_file_logs';

	public function __construct(
		private readonly Consent_Repository $repository,
		private readonly File_Logger        $logger
	) {}

	public function register_hooks(): void {
		add_action( self::HOOK_DB,   [ $this, 'cleanup_db' ] );
		add_action( self::HOOK_FILE, [ $this, 'cleanup_files' ] );
	}

	public function cleanup_db(): void {
		$this->repository->delete_expired( 365 );
	}

	public function cleanup_files(): void {
		$this->logger->rotate( 30 );
	}
}