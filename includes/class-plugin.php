<?php
declare( strict_types=1 );

namespace BallkorgCookieConsent;

/**
 * Central plugin orchestrator — manual DI container + hook registrar.
 *
 * Anti-pattern removed: scattered add_action() calls across a God File.
 * Now all hook wiring lives in one place, making it trivial to audit
 * or stub in tests.
 */
final class Plugin {

	private static ?self $instance = null;

	// Lazily-instantiated services.
	private ?Consent_Repository  $repository        = null;
	private ?Consent_Service     $service           = null;
	private ?Ajax_Handler        $ajax              = null;
	private ?Banner_Renderer     $banner            = null;
	private ?Admin_Page          $admin             = null;
	private ?Cron_Manager        $cron              = null;
	private ?Analytics_Loader    $analytics         = null;

	private function __construct() {}

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire all hooks. Called once on `plugins_loaded`.
	 */
	public function boot(): void {
		load_plugin_textdomain(
			'ballkorg-cookie-consent',
			false,
			dirname( plugin_basename( BCC_FILE ) ) . '/languages'
		);

		// DB version check on every request is cheap — it reads a single option.
		// Only runs the actual migration when the stored version is outdated.
		$this->maybe_run_migrations();

		// Register AJAX handlers (both authed and non-authed).
		$this->get_ajax()->register_hooks();

		// Cron jobs.
		$this->get_cron()->register_hooks();

		if ( is_admin() ) {
			$this->get_admin()->register_hooks();
		} else {
			// Front-end: banner HTML + enqueue assets.
			$this->get_banner()->register_hooks();
			$this->get_analytics()->register_hooks();
		}
	}

	// -------------------------------------------------------------------------
	// Service getters — lazy instantiation with explicit dependencies.
	// -------------------------------------------------------------------------

	public function get_repository(): Consent_Repository {
		if ( null === $this->repository ) {
			$this->repository = new Consent_Repository();
		}
		return $this->repository;
	}

	public function get_service(): Consent_Service {
		if ( null === $this->service ) {
			$this->service = new Consent_Service(
				$this->get_repository(),
				new IP_Helper(),
				new File_Logger()
			);
		}
		return $this->service;
	}

	public function get_ajax(): Ajax_Handler {
		if ( null === $this->ajax ) {
			$this->ajax = new Ajax_Handler( $this->get_service() );
		}
		return $this->ajax;
	}

	public function get_banner(): Banner_Renderer {
		if ( null === $this->banner ) {
			$this->banner = new Banner_Renderer();
		}
		return $this->banner;
	}

	public function get_admin(): Admin_Page {
		if ( null === $this->admin ) {
			$this->admin = new Admin_Page( $this->get_repository() );
		}
		return $this->admin;
	}

	public function get_cron(): Cron_Manager {
		if ( null === $this->cron ) {
			$this->cron = new Cron_Manager( $this->get_repository(), new File_Logger() );
		}
		return $this->cron;
	}

	public function get_analytics(): Analytics_Loader {
		if ( null === $this->analytics ) {
			$this->analytics = new Analytics_Loader();
		}
		return $this->analytics;
	}

	// -------------------------------------------------------------------------
	// Migration guard.
	// -------------------------------------------------------------------------

	private function maybe_run_migrations(): void {
		$stored = get_option( 'bcc_db_version', '0.0.0' );
		if ( version_compare( $stored, BCC_DB_VERSION, '<' ) ) {
			Installer::run_migrations();
		}
	}
}