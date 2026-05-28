<?php
declare( strict_types=1 );

namespace BallkorgCookieConsent;

/**
 * Front-end cookie banner.
 *
 * The banner language is controlled by the plugin setting `bcc_plugin_lang`
 * so it can be independent from the WordPress site language.
 */
final class Banner_Renderer {

	public function register_hooks(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_footer',          [ $this, 'render_banner' ] );
	}

	private function is_banner_enabled(): bool {
		return get_option( 'bcc_banner_enabled', '1' ) === '1';
	}

	public function enqueue_assets(): void {
		if ( ! $this->is_banner_enabled() ) {
			return;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_style(
			'bcc-banner',
			BCC_ASSETS_URL . 'css/cookie-banner.css',
			[],
			BCC_VERSION
		);

		wp_enqueue_script(
			'bcc-banner',
			BCC_ASSETS_URL . 'js/cookie-banner.js',
			[],
			BCC_VERSION,
			true
		);

		$lang = Admin_Page::get_plugin_lang();
		$i18n = $lang === 'ru' ? $this->i18n_ru() : $this->i18n_en();

		wp_localize_script(
			'bcc-banner',
			'bccConfig',
			[
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( Ajax_Handler::get_nonce_action() ),
				'action'        => 'bcc_save_consent',
				'cookieName'    => 'bcc_consent',
				'retentionDays' => 365,
				'metrikaId'     => (string) apply_filters( 'bcc_yandex_metrika_id', '' ),
				'i18n'          => $i18n,
			]
		);
	}

	public function render_banner(): void {
		if ( ! $this->is_banner_enabled() ) {
			return;
		}

		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		$lang       = Admin_Page::get_plugin_lang();
		$i18n       = $lang === 'ru' ? $this->i18n_ru() : $this->i18n_en();
		$policy_url = $this->resolve_policy_url();
		?>
		<div id="bcc-banner"
		     aria-live="polite"
		     role="dialog"
		     aria-label="<?php echo esc_attr( $i18n['ariaLabel'] ); ?>">
			<div class="bcc-container">
				<div class="bcc-content">
					<span class="bcc-icon" aria-hidden="true">🍪</span>
					<div class="bcc-text">
						<p class="bcc-title">
							<?php echo esc_html( $i18n['title'] ); ?>
						</p>
						<p class="bcc-description">
							<?php echo esc_html( $i18n['description'] ); ?>
							<a href="<?php echo esc_url( $policy_url ); ?>" class="bcc-link">
								<?php echo esc_html( $i18n['policyLink'] ); ?>
							</a>
						</p>
					</div>
					<div class="bcc-buttons">
						<button type="button" class="bcc-btn bcc-btn--accept" data-consent="accepted_all">
							<?php echo esc_html( $i18n['acceptAll'] ); ?>
						</button>
						<button type="button" class="bcc-btn bcc-btn--reject" data-consent="rejected">
							<?php echo esc_html( $i18n['reject'] ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/** @return array<string,string> */
	private function i18n_ru(): array {
		return [
			'ariaLabel'    => 'Согласие на использование файлов cookie',
			'title'        => 'Этот сайт использует файлы cookie',
			'description'  => 'Мы используем cookie для корректной работы сайта и улучшения вашего опыта.',
			'policyLink'   => 'Политика cookie',
			'acceptAll'    => 'Принять все',
			'reject'       => 'Отклонить',
			'resetConfirm' => 'Сбросить настройки cookie? Баннер появится снова.',
		];
	}

	/** @return array<string,string> */
	private function i18n_en(): array {
		return [
			'ariaLabel'    => 'Cookie consent',
			'title'        => 'This site uses cookies',
			'description'  => 'We use cookies to keep the site working and to improve your experience.',
			'policyLink'   => 'Cookie Policy',
			'acceptAll'    => 'Accept all',
			'reject'       => 'Reject',
			'resetConfirm' => 'Reset cookie preferences? The banner will reappear.',
		];
	}

	private function resolve_policy_url(): string {
		$url = (string) apply_filters( 'bcc_cookie_policy_url', '' );
		if ( $url ) {
			return $url;
		}

		$page = get_page_by_path( 'cookie-policy' )
			?? get_page_by_path( 'cookie' )
			?? get_page_by_path( 'confidential' );

		if ( $page ) {
			return (string) get_permalink( $page );
		}

		return get_privacy_policy_url() ?: home_url( '/' );
	}
}