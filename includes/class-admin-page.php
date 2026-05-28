<?php
declare( strict_types=1 );

namespace BallkorgCookieConsent;

/**
 * Admin UI — Tools → Cookie Logs.
 *
 * Provides:
 * - consent log browsing
 * - CSV export
 * - plugin language selector
 * - frontend banner on/off switch
 * - uninstall data retention switch
 */
final class Admin_Page {

	private const PAGE_SLUG    = 'bcc-cookie-logs';
	private const META_KEY     = 'bcc_notice_dismissed';
	private const PER_PAGE     = 50;
	private const EXPORT_NONCE = 'bcc_export_csv';
	private const SAVE_NONCE   = 'bcc_save_settings';

	public function __construct(
		private readonly Consent_Repository $repository
	) {}

	public function register_hooks(): void {
		add_action( 'admin_menu',                  [ $this, 'add_menu' ] );
		add_action( 'admin_notices',               [ $this, 'system_notice' ] );
		add_action( 'admin_footer',                [ $this, 'dismiss_script' ] );
		add_action( 'wp_ajax_bcc_dismiss_notice',  [ $this, 'ajax_dismiss_notice' ] );
		add_action( 'wp_ajax_bcc_export_csv',      [ $this, 'ajax_export_csv' ] );
		add_action( 'admin_post_bcc_save_settings', [ $this, 'handle_save_settings' ] );
	}

	public function add_menu(): void {
		add_submenu_page(
			'tools.php',
			esc_html__( 'Cookie Consent Logs', 'ballkorg-cookie-consent' ),
			esc_html__( 'Cookie Logs', 'ballkorg-cookie-consent' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	public static function get_plugin_lang(): string {
		$lang = (string) get_option( 'bcc_plugin_lang', 'ru' );
		return in_array( $lang, [ 'ru', 'en' ], true ) ? $lang : 'ru';
	}

	public function handle_save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'ballkorg-cookie-consent' ) );
		}

		check_admin_referer( self::SAVE_NONCE );

		$lang = sanitize_text_field( $_POST['bcc_plugin_lang'] ?? 'ru' );
		if ( ! in_array( $lang, [ 'ru', 'en' ], true ) ) {
			$lang = 'ru';
		}
		update_option( 'bcc_plugin_lang', $lang );

		$banner_enabled = isset( $_POST['bcc_banner_enabled'] ) ? '1' : '0';
		update_option( 'bcc_banner_enabled', $banner_enabled );

		$delete_on_uninstall = isset( $_POST['bcc_delete_data_on_uninstall'] ) ? '1' : '0';
		update_option( 'bcc_delete_data_on_uninstall', $delete_on_uninstall );

		wp_safe_redirect(
			add_query_arg(
				[ 'page' => self::PAGE_SLUG, 'settings-updated' => '1' ],
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'ballkorg-cookie-consent' ) );
		}

		$active_tab = sanitize_text_field( $_GET['tab'] ?? 'logs' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$stats  = $this->repository->get_stats();
		$page   = max( 1, (int) ( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$offset = ( $page - 1 ) * self::PER_PAGE;
		$logs   = $this->repository->get_recent_logs( self::PER_PAGE, $offset );
		$total  = $stats['total'];
		$pages  = (int) ceil( $total / self::PER_PAGE );

		$base_url = admin_url( 'tools.php?page=' . self::PAGE_SLUG );
		?>
		<div class="wrap">
			<h1>
				BALLKORG Cookie Consent
				<span style="font-size:14px;font-weight:400;color:#666;margin-left:8px;">v<?php echo esc_html( BCC_VERSION ); ?></span>
			</h1>

			<?php if ( ! empty( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'ballkorg-cookie-consent' ); ?></p>
				</div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper" style="margin-bottom:20px;">
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'logs', $base_url ) ); ?>"
				   class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Consent Logs', 'ballkorg-cookie-consent' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'settings', $base_url ) ); ?>"
				   class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'ballkorg-cookie-consent' ); ?>
				</a>
			</nav>

			<?php if ( $active_tab === 'settings' ) : ?>
				<?php $this->render_settings_tab(); ?>
			<?php else : ?>
				<?php $this->render_export_button(); ?>
				<?php $this->render_stats( $stats ); ?>
				<?php $this->render_table( $logs ); ?>
				<?php $this->render_pagination( $page, $pages, $total ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_settings_tab(): void {
		$current_lang        = self::get_plugin_lang();
		$banner_enabled      = get_option( 'bcc_banner_enabled', '1' );
		$delete_on_uninstall = get_option( 'bcc_delete_data_on_uninstall', '0' );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="bcc_save_settings">
			<?php wp_nonce_field( self::SAVE_NONCE ); ?>

			<table class="form-table" role="presentation">
				<tbody>

					<tr>
						<th scope="row">
							<label for="bcc_banner_enabled">
								<?php esc_html_e( 'Show cookie banner on the frontend', 'ballkorg-cookie-consent' ); ?>
							</label>
						</th>
						<td>
							<input type="checkbox" id="bcc_banner_enabled"
							       name="bcc_banner_enabled" value="1"
							       <?php checked( $banner_enabled, '1' ); ?>>
							<label for="bcc_banner_enabled">
								<?php esc_html_e( 'Enable the banner for visitors', 'ballkorg-cookie-consent' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Disable this only if you do not want the banner to appear on the site.', 'ballkorg-cookie-consent' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Plugin & banner language', 'ballkorg-cookie-consent' ); ?></label>
						</th>
						<td>
							<fieldset>
								<label style="display:inline-flex;align-items:center;gap:6px;margin-right:20px;">
									<input type="radio" name="bcc_plugin_lang" value="ru"
										<?php checked( $current_lang, 'ru' ); ?>>
									<?php esc_html_e( 'Russian', 'ballkorg-cookie-consent' ); ?>
								</label>
								<label style="display:inline-flex;align-items:center;gap:6px;">
									<input type="radio" name="bcc_plugin_lang" value="en"
										<?php checked( $current_lang, 'en' ); ?>>
									<?php esc_html_e( 'English', 'ballkorg-cookie-consent' ); ?>
								</label>
							</fieldset>
							<p class="description">
								<?php esc_html_e( 'Affects the cookie banner and the admin interface labels.', 'ballkorg-cookie-consent' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="bcc_delete_data_on_uninstall">
								<?php esc_html_e( 'Delete consent data on plugin uninstall', 'ballkorg-cookie-consent' ); ?>
							</label>
						</th>
						<td>
							<input type="checkbox" id="bcc_delete_data_on_uninstall"
							       name="bcc_delete_data_on_uninstall" value="1"
							       <?php checked( $delete_on_uninstall, '1' ); ?>>
							<label for="bcc_delete_data_on_uninstall">
								<?php esc_html_e( 'Delete the consent log table when the plugin is removed', 'ballkorg-cookie-consent' ); ?>
							</label>
							<p class="description" style="color:#c0392b;">
								<?php esc_html_e( 'Leave this off if you need to keep consent logs for compliance purposes.', 'ballkorg-cookie-consent' ); ?>
							</p>
						</td>
					</tr>

				</tbody>
			</table>

			<?php submit_button( __( 'Save settings', 'ballkorg-cookie-consent' ) ); ?>
		</form>
		<?php
	}

	private function render_export_button(): void {
		$url = add_query_arg(
			[
				'action'   => 'bcc_export_csv',
				'_wpnonce' => wp_create_nonce( self::EXPORT_NONCE ),
			],
			admin_url( 'admin-ajax.php' )
		);

		echo '<p>';
		echo '<a href="' . esc_url( $url ) . '" class="button button-secondary">';
		echo '⬇ ' . esc_html__( 'Export all to CSV', 'ballkorg-cookie-consent' );
		echo '</a>';
		echo '</p>';
	}

	public function ajax_export_csv(): void {
		check_admin_referer( self::EXPORT_NONCE );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'ballkorg-cookie-consent' ), 403 );
		}

		$filename = 'cookie-consents-' . gmdate( 'Y-m-d_His' ) . '.csv';

		if ( ob_get_level() ) {
			ob_end_clean();
		}

		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo "\xEF\xBB\xBF";

		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			wp_die( 'Could not open output stream.' );
		}

		fputcsv( $out, [
			'ID', 'Date (UTC)', 'IP prefix (anon)', 'IP hash',
			'Consent type', 'Browser', 'Screen', 'Language',
			'Timezone', 'DNT', 'Page URL', 'Referer URL',
			'Expires (UTC)', 'Consent hash',
		] );

		$batch_size = 200;
		$offset     = 0;

		do {
			$rows = $this->repository->get_recent_logs( $batch_size, $offset );
			foreach ( $rows as $row ) {
				$meta    = json_decode( $row->consent_data ?? '', true ) ?? [];
				$browser = $meta['browser_info']['name'] ?? ( $meta['browser_name'] ?? '' );

				fputcsv( $out, [
					(int) $row->id,
					$row->created_at,
					$row->ip_prefix,
					$row->ip_hash,
					$row->consent_type,
					$browser,
					$row->screen_res   ?? '',
					$row->browser_lang ?? '',
					$row->timezone     ?? '',
					(int) ( $row->do_not_track ?? 0 ),
					$row->page_url,
					$row->referer_url  ?? '',
					$row->expires_at,
					$row->consent_hash,
				] );
			}
			$offset += $batch_size;
		} while ( count( $rows ) === $batch_size );

		fclose( $out );
		exit;
	}

	/** @param array<string,int> $stats */
	private function render_stats( array $stats ): void {
		$rate = $stats['total'] > 0 ? round( ( $stats['accepted'] / $stats['total'] ) * 100, 1 ) : 0;
		?>
		<div style="display:flex;gap:16px;flex-wrap:wrap;margin:20px 0;">
			<?php
			$this->stat_box( (string) $stats['total'],    __( 'Total',    'ballkorg-cookie-consent' ), '' );
			$this->stat_box( (string) $stats['accepted'], __( 'Accepted', 'ballkorg-cookie-consent' ), '#27ae60', $rate . '%' );
			$this->stat_box( (string) $stats['rejected'], __( 'Rejected', 'ballkorg-cookie-consent' ), '#e74c3c' );
			$this->stat_box( (string) $stats['active'],   __( 'Active',   'ballkorg-cookie-consent' ), '#3498db' );
			?>
		</div>
		<?php
	}

	private function stat_box( string $value, string $label, string $color, string $sub = '' ): void {
		$border = $color ? 'border-left:4px solid ' . esc_attr( $color ) . ';' : '';
		echo '<div style="background:#f8f9fa;padding:16px 20px;border-radius:8px;min-width:140px;' . $border . '">';
		echo '<div style="font-size:24px;font-weight:700;' . ( $color ? 'color:' . esc_attr( $color ) . ';' : '' ) . '">' . esc_html( $value ) . '</div>';
		echo '<div style="color:#555;font-size:13px;">' . esc_html( $label ) . '</div>';
		if ( $sub ) {
			echo '<div style="font-size:12px;color:#888;">' . esc_html( $sub ) . '</div>';
		}
		echo '</div>';
	}

	/** @param array<int,object> $logs */
	private function render_table( array $logs ): void {
		?>
		<table class="wp-list-table widefat fixed striped" style="margin-top:20px;">
			<thead>
				<tr>
					<th style="width:50px;"><?php esc_html_e( 'ID', 'ballkorg-cookie-consent' ); ?></th>
					<th style="width:140px;"><?php esc_html_e( 'Date', 'ballkorg-cookie-consent' ); ?></th>
					<th style="width:120px;"><?php esc_html_e( 'IP (anon)', 'ballkorg-cookie-consent' ); ?></th>
					<th style="width:120px;"><?php esc_html_e( 'Decision', 'ballkorg-cookie-consent' ); ?></th>
					<th><?php esc_html_e( 'Browser', 'ballkorg-cookie-consent' ); ?></th>
					<th><?php esc_html_e( 'Page', 'ballkorg-cookie-consent' ); ?></th>
					<th style="width:100px;"><?php esc_html_e( 'Expires', 'ballkorg-cookie-consent' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( $logs ) : ?>
				<?php foreach ( $logs as $row ) : ?>
					<?php
					$meta     = json_decode( $row->consent_data ?? '', true ) ?? [];
					$browser  = $meta['browser_info']['name'] ?? ( $meta['browser_name'] ?? 'Unknown' );
					$accepted = str_starts_with( $row->consent_type, 'accepted' );
					$expires  = new \DateTime( $row->expires_at );
					$now      = new \DateTime();
					$diff     = $now->diff( $expires );
					?>
					<tr>
						<td><?php echo (int) $row->id; ?></td>
						<td>
							<?php echo esc_html( wp_date( 'd.m.Y', strtotime( $row->created_at ) ) ); ?><br>
							<small><?php echo esc_html( wp_date( 'H:i:s', strtotime( $row->created_at ) ) ); ?></small>
						</td>
						<td><code><?php echo esc_html( $row->ip_prefix ); ?></code></td>
						<td>
							<?php if ( $accepted ) : ?>
								<span style="color:#27ae60;font-weight:600;">✓ <?php esc_html_e( 'Accepted', 'ballkorg-cookie-consent' ); ?></span>
							<?php else : ?>
								<span style="color:#e74c3c;font-weight:600;">✗ <?php esc_html_e( 'Rejected', 'ballkorg-cookie-consent' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $browser ); ?></td>
						<td>
							<span title="<?php echo esc_attr( $row->page_url ); ?>">
								<?php echo esc_html( mb_substr( str_replace( [ 'https://', 'http://' ], '', $row->page_url ), 0, 40 ) ); ?>…
							</span>
						</td>
						<td style="<?php echo $diff->invert ? 'color:#e74c3c;' : 'color:#27ae60;'; ?>">
							<?php echo esc_html( wp_date( 'd.m.Y', strtotime( $row->expires_at ) ) ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="7" style="text-align:center;padding:40px;">
						<?php esc_html_e( 'No consent records yet.', 'ballkorg-cookie-consent' ); ?>
					</td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_pagination( int $current, int $total_pages, int $total_rows ): void {
		if ( $total_pages <= 1 ) {
			return;
		}

		$base_url = admin_url( 'tools.php?page=' . self::PAGE_SLUG );

		echo '<div style="margin:16px 0;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">';
		echo '<span style="color:#555;font-size:13px;">';
		echo esc_html( sprintf(
			/* translators: 1: current page, 2: total pages, 3: total rows */
			__( 'Page %1$d of %2$d (%3$d records total)', 'ballkorg-cookie-consent' ),
			$current, $total_pages, $total_rows
		) );
		echo '</span>';

		if ( $current > 1 ) {
			echo '<a href="' . esc_url( add_query_arg( 'paged', $current - 1, $base_url ) ) . '" class="button button-small">← ' . esc_html__( 'Prev', 'ballkorg-cookie-consent' ) . '</a>';
		}

		$window = 2;
		$shown  = [];
		for ( $p = 1; $p <= $total_pages; $p++ ) {
			if ( $p === 1 || $p === $total_pages || abs( $p - $current ) <= $window ) {
				$shown[] = $p;
			}
		}

		$prev = null;
		foreach ( $shown as $p ) {
			if ( null !== $prev && $p - $prev > 1 ) {
				echo '<span style="color:#999;">…</span>';
			}
			if ( $p === $current ) {
				echo '<strong style="padding:4px 8px;background:#0073aa;color:#fff;border-radius:3px;">' . (int) $p . '</strong>';
			} else {
				echo '<a href="' . esc_url( add_query_arg( 'paged', $p, $base_url ) ) . '" class="button button-small">' . (int) $p . '</a>';
			}
			$prev = $p;
		}

		if ( $current < $total_pages ) {
			echo '<a href="' . esc_url( add_query_arg( 'paged', $current + 1, $base_url ) ) . '" class="button button-small">' . esc_html__( 'Next', 'ballkorg-cookie-consent' ) . ' →</a>';
		}

		echo '</div>';
	}

	public function system_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( get_user_meta( get_current_user_id(), self::META_KEY, true ) ) {
			return;
		}

		if ( ! $this->repository->table_exists() ) {
			echo '<div class="notice notice-error is-dismissible bcc-sys-notice"><p>'
				. '<strong>BALLKORG Cookie Consent:</strong> '
				. esc_html__( 'Database table missing. Deactivate and reactivate the plugin.', 'ballkorg-cookie-consent' )
				. '</p></div>';
		}
	}

	public function ajax_dismiss_notice(): void {
		check_ajax_referer( 'bcc_dismiss_notice' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Access denied.' ], 403 );
		}

		$user_id = get_current_user_id();
		if ( $user_id ) {
			update_user_meta( $user_id, self::META_KEY, '1' );
			wp_send_json_success();
		}

		wp_send_json_error();
	}

	public function dismiss_script(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<script>
		( function( $ ) {
			'use strict';
			$( document ).on( 'click', '.bcc-sys-notice .notice-dismiss', function() {
				$.post( ajaxurl, {
					action:      'bcc_dismiss_notice',
					_ajax_nonce: '<?php echo esc_js( wp_create_nonce( 'bcc_dismiss_notice' ) ); ?>'
				} );
			} );
		} )( window.jQuery );
		</script>
		<?php
	}
}