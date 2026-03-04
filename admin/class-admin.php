<?php
/**
 * Admin — admin menu, settings, logs table, and test scanner.
 *
 * @package AI_Email_Spam_Shield
 */

namespace AI_Email_Spam_Shield;

defined( 'ABSPATH' ) || exit;

class Admin {

	private static ?self $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu',    array( $this, 'register_menu' ) );
		add_action( 'admin_init',    array( $this, 'register_settings' ) );
		add_action( 'admin_post_aiess_clear_logs', array( $this, 'handle_clear_logs' ) );
		add_action( 'wp_ajax_aiess_test_scan',     array( $this, 'handle_test_scan' ) );
		add_action( 'admin_enqueue_scripts',       array( $this, 'enqueue_assets' ) );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	public function register_menu(): void {
		add_menu_page(
			esc_html__( 'AI Spam Shield', 'ai-email-spam-shield' ),
			esc_html__( 'AI Spam Shield', 'ai-email-spam-shield' ),
			'manage_options',
			'aiess-dashboard',
			array( $this, 'page_dashboard' ),
			'dashicons-shield',
			81
		);
		add_submenu_page( 'aiess-dashboard', esc_html__( 'Dashboard', 'ai-email-spam-shield' ),    esc_html__( 'Dashboard', 'ai-email-spam-shield' ),    'manage_options', 'aiess-dashboard',     array( $this, 'page_dashboard' ) );
		add_submenu_page( 'aiess-dashboard', esc_html__( 'Settings', 'ai-email-spam-shield' ),     esc_html__( 'Settings', 'ai-email-spam-shield' ),     'manage_options', 'aiess-settings',      array( $this, 'page_settings' ) );
		add_submenu_page( 'aiess-dashboard', esc_html__( 'Logs', 'ai-email-spam-shield' ),         esc_html__( 'Logs', 'ai-email-spam-shield' ),         'manage_options', 'aiess-logs',          array( $this, 'page_logs' ) );
		add_submenu_page( 'aiess-dashboard', esc_html__( 'Test Scanner', 'ai-email-spam-shield' ), esc_html__( 'Test Scanner', 'ai-email-spam-shield' ), 'manage_options', 'aiess-test-scanner',  array( $this, 'page_test_scanner' ) );
	}

	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'aiess' ) === false ) {
			return;
		}
		wp_enqueue_style( 'aiess-admin', AIESS_PLUGIN_URL . 'admin/admin.css', array(), AIESS_VERSION );
	}

	// -------------------------------------------------------------------------
	// Settings API
	// -------------------------------------------------------------------------

	public function register_settings(): void {
		register_setting(
			'aiess_settings_group',
			'aiess_settings',
			array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) )
		);
	}

	public function sanitize_settings( array $input ): array {
		return array(
			'enabled'     => ! empty( $input['enabled'] ) ? 1 : 0,
			'api_url'     => esc_url_raw( $input['api_url'] ?? '' ),
			'api_key'     => sanitize_text_field( $input['api_key'] ?? '' ),
			'threshold'   => min( 1.0, max( 0.0, (float) ( $input['threshold'] ?? 0.80 ) ) ),
			'ai_weight'   => min( 1.0, max( 0.0, (float) ( $input['ai_weight'] ?? 0.7 ) ) ),
			'rule_weight' => min( 1.0, max( 0.0, (float) ( $input['rule_weight'] ?? 0.3 ) ) ),
		);
	}

	// -------------------------------------------------------------------------
	// Pages
	// -------------------------------------------------------------------------

	public function page_dashboard(): void {
		$stats   = Logger::get_stats();
		$options = get_option( 'aiess_settings', array() );
		$api_url = esc_url_raw( $options['api_url'] ?? 'http://spam-api:8000' );
		// Ping the /health endpoint instead of /predict.
		$health_url = preg_replace( '/\/predict$/', '/health', rtrim( $api_url, '/' ) );
		$response   = wp_remote_get( $health_url, array( 'timeout' => 3 ) );
		$api_ok     = ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response );
		?>
		<div class="wrap aiess-wrap">
			<h1><?php esc_html_e( 'AI Spam Shield — Dashboard', 'ai-email-spam-shield' ); ?></h1>
			<p class="aiess-brand">by <a href="https://www.mymentech.com" target="_blank">MymenTech</a></p>

			<div class="aiess-stats-grid">
				<div class="aiess-stat-card">
					<span class="aiess-stat-number"><?php echo esc_html( number_format( $stats['total_scanned'] ) ); ?></span>
					<span class="aiess-stat-label"><?php esc_html_e( 'Total Scanned', 'ai-email-spam-shield' ); ?></span>
				</div>
				<div class="aiess-stat-card">
					<span class="aiess-stat-number"><?php echo esc_html( $stats['blocked_today'] ); ?></span>
					<span class="aiess-stat-label"><?php esc_html_e( 'Blocked Today', 'ai-email-spam-shield' ); ?></span>
				</div>
				<div class="aiess-stat-card">
					<span class="aiess-stat-number"><?php echo esc_html( $stats['blocked_week'] ); ?></span>
					<span class="aiess-stat-label"><?php esc_html_e( 'Blocked This Week', 'ai-email-spam-shield' ); ?></span>
				</div>
				<div class="aiess-stat-card <?php echo $api_ok ? 'status-ok' : 'status-error'; ?>">
					<span class="aiess-stat-number"><?php echo $api_ok ? '&#10003;' : '&#10007;'; ?></span>
					<span class="aiess-stat-label"><?php esc_html_e( 'AI API Status', 'ai-email-spam-shield' ); ?></span>
				</div>
			</div>
		</div>
		<?php
	}

	public function page_settings(): void {
		$options = get_option( 'aiess_settings', array() );
		?>
		<div class="wrap aiess-wrap">
			<h1><?php esc_html_e( 'AI Spam Shield — Settings', 'ai-email-spam-shield' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'aiess_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Scanning', 'ai-email-spam-shield' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="aiess_settings[enabled]" value="1" <?php checked( ! empty( $options['enabled'] ) ); ?>>
								<?php esc_html_e( 'Intercept and scan outgoing emails', 'ai-email-spam-shield' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aiess_api_url"><?php esc_html_e( 'API URL', 'ai-email-spam-shield' ); ?></label></th>
						<td>
							<input type="url" id="aiess_api_url" name="aiess_settings[api_url]"
								   value="<?php echo esc_attr( $options['api_url'] ?? 'http://spam-api:8000/predict' ); ?>"
								   class="regular-text">
							<p class="description"><?php esc_html_e( 'URL of the FastAPI spam detection service. Default: http://spam-api:8000/predict', 'ai-email-spam-shield' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aiess_api_key"><?php esc_html_e( 'API Key (optional)', 'ai-email-spam-shield' ); ?></label></th>
						<td>
							<input type="password" id="aiess_api_key" name="aiess_settings[api_key]"
								   value="<?php echo esc_attr( $options['api_key'] ?? '' ); ?>"
								   class="regular-text" autocomplete="new-password">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aiess_threshold"><?php esc_html_e( 'Spam Threshold', 'ai-email-spam-shield' ); ?></label></th>
						<td>
							<input type="number" id="aiess_threshold" name="aiess_settings[threshold]"
								   value="<?php echo esc_attr( $options['threshold'] ?? '0.80' ); ?>"
								   min="0" max="1" step="0.01" class="small-text">
							<p class="description"><?php esc_html_e( 'Emails with a final score ≥ this value are blocked. (0.0–1.0, default 0.80)', 'ai-email-spam-shield' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Score Weights', 'ai-email-spam-shield' ); ?></th>
						<td>
							<label>
								<?php esc_html_e( 'AI Weight:', 'ai-email-spam-shield' ); ?>
								<input type="number" name="aiess_settings[ai_weight]"
									   value="<?php echo esc_attr( $options['ai_weight'] ?? '0.7' ); ?>"
									   min="0" max="1" step="0.1" class="small-text">
							</label>
							&nbsp;&nbsp;
							<label>
								<?php esc_html_e( 'Rule Weight:', 'ai-email-spam-shield' ); ?>
								<input type="number" name="aiess_settings[rule_weight]"
									   value="<?php echo esc_attr( $options['rule_weight'] ?? '0.3' ); ?>"
									   min="0" max="1" step="0.1" class="small-text">
							</label>
							<p class="description"><?php esc_html_e( 'Weights for the final score formula: (AI × AI Weight) + (Rules × Rule Weight). Should sum to 1.0.', 'ai-email-spam-shield' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( esc_html__( 'Save Settings', 'ai-email-spam-shield' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function page_logs(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$current_page = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$filter       = sanitize_key( $_GET['filter'] ?? '' );
		// phpcs:enable
		$result      = Logger::get_logs( $current_page, 20, $filter ?: null );
		$total_pages = (int) ceil( $result['total'] / 20 );
		?>
		<div class="wrap aiess-wrap">
			<h1><?php esc_html_e( 'AI Spam Shield — Logs', 'ai-email-spam-shield' ); ?></h1>

			<div class="aiess-filter-bar">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aiess-logs' ) ); ?>"
				   class="button <?php echo empty( $filter ) ? 'button-primary' : ''; ?>">
				   <?php esc_html_e( 'All', 'ai-email-spam-shield' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aiess-logs&filter=blocked' ) ); ?>"
				   class="button <?php echo 'blocked' === $filter ? 'button-primary' : ''; ?>">
				   <?php esc_html_e( 'Blocked', 'ai-email-spam-shield' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aiess-logs&filter=allowed' ) ); ?>"
				   class="button <?php echo 'allowed' === $filter ? 'button-primary' : ''; ?>">
				   <?php esc_html_e( 'Allowed', 'ai-email-spam-shield' ); ?>
				</a>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
					  style="display:inline-block;margin-left:20px;"
					  onsubmit="return confirm('<?php esc_attr_e( 'Delete all logs? This cannot be undone.', 'ai-email-spam-shield' ); ?>')">
					<input type="hidden" name="action" value="aiess_clear_logs">
					<?php wp_nonce_field( 'aiess_clear_logs', 'aiess_nonce' ); ?>
					<button type="submit" class="button button-secondary" style="color:#cc0000;">
						<?php esc_html_e( 'Clear All Logs', 'ai-email-spam-shield' ); ?>
					</button>
				</form>
			</div>

			<table class="wp-list-table widefat fixed striped aiess-logs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'ai-email-spam-shield' ); ?></th>
						<th><?php esc_html_e( 'Sender', 'ai-email-spam-shield' ); ?></th>
						<th><?php esc_html_e( 'Subject', 'ai-email-spam-shield' ); ?></th>
						<th><?php esc_html_e( 'AI Score', 'ai-email-spam-shield' ); ?></th>
						<th><?php esc_html_e( 'Rule Score', 'ai-email-spam-shield' ); ?></th>
						<th><?php esc_html_e( 'Final Score', 'ai-email-spam-shield' ); ?></th>
						<th><?php esc_html_e( 'Status', 'ai-email-spam-shield' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $result['rows'] ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'No log entries found.', 'ai-email-spam-shield' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $result['rows'] as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['created_at'] ); ?></td>
								<td><?php echo esc_html( $row['email_sender'] ); ?></td>
								<td><?php echo esc_html( wp_trim_words( $row['email_subject'], 8 ) ); ?></td>
								<td><?php echo null !== $row['ai_score'] ? esc_html( number_format( (float) $row['ai_score'], 3 ) ) : '<em>N/A</em>'; ?></td>
								<td><?php echo esc_html( number_format( (float) $row['rule_score'], 3 ) ); ?></td>
								<td><?php echo esc_html( number_format( (float) $row['final_score'], 3 ) ); ?></td>
								<td>
									<?php if ( $row['blocked'] ) : ?>
										<span class="aiess-badge aiess-badge-blocked"><?php esc_html_e( 'BLOCKED', 'ai-email-spam-shield' ); ?></span>
									<?php else : ?>
										<span class="aiess-badge aiess-badge-allowed"><?php esc_html_e( 'ALLOWED', 'ai-email-spam-shield' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						echo paginate_links( array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'current'   => $current_page,
							'total'     => $total_pages,
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
						) );
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function page_test_scanner(): void {
		?>
		<div class="wrap aiess-wrap">
			<h1><?php esc_html_e( 'AI Spam Shield — Test Scanner', 'ai-email-spam-shield' ); ?></h1>
			<p><?php esc_html_e( 'Paste a sample email to run a live hybrid scan and see the score breakdown.', 'ai-email-spam-shield' ); ?></p>

			<table class="form-table">
				<tr>
					<th><label for="aiess-test-subject"><?php esc_html_e( 'Subject', 'ai-email-spam-shield' ); ?></label></th>
					<td><input type="text" id="aiess-test-subject" class="regular-text" placeholder="<?php esc_attr_e( 'Email subject...', 'ai-email-spam-shield' ); ?>"></td>
				</tr>
				<tr>
					<th><label for="aiess-test-body"><?php esc_html_e( 'Message Body', 'ai-email-spam-shield' ); ?></label></th>
					<td><textarea id="aiess-test-body" rows="8" class="large-text" placeholder="<?php esc_attr_e( 'Email message body...', 'ai-email-spam-shield' ); ?>"></textarea></td>
				</tr>
			</table>

			<button id="aiess-run-scan" class="button button-primary">
				<?php esc_html_e( 'Run Scan', 'ai-email-spam-shield' ); ?>
			</button>

			<div id="aiess-scan-result" style="margin-top:20px;display:none;">
				<h3><?php esc_html_e( 'Scan Results', 'ai-email-spam-shield' ); ?></h3>
				<table class="widefat" style="max-width:500px;">
					<tr><th><?php esc_html_e( 'AI Score', 'ai-email-spam-shield' ); ?></th><td id="aiess-r-ai">&#8212;</td></tr>
					<tr><th><?php esc_html_e( 'Rule Score', 'ai-email-spam-shield' ); ?></th><td id="aiess-r-rule">&#8212;</td></tr>
					<tr><th><?php esc_html_e( 'Final Score', 'ai-email-spam-shield' ); ?></th><td id="aiess-r-final">&#8212;</td></tr>
					<tr><th><?php esc_html_e( 'Verdict', 'ai-email-spam-shield' ); ?></th><td id="aiess-r-verdict">&#8212;</td></tr>
				</table>
			</div>

			<script>
			document.getElementById('aiess-run-scan').addEventListener('click', function () {
				var subject = document.getElementById('aiess-test-subject').value;
				var body    = document.getElementById('aiess-test-body').value;
				var btn     = this;
				btn.disabled    = true;
				btn.textContent = '<?php esc_html_e( 'Scanning...', 'ai-email-spam-shield' ); ?>';

				fetch(ajaxurl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: new URLSearchParams({
						action:  'aiess_test_scan',
						nonce:   '<?php echo esc_js( wp_create_nonce( 'aiess_test_scan' ) ); ?>',
						subject: subject,
						body:    body
					})
				})
				.then(function(r) { return r.json(); })
				.then(function(data) {
					if ( data.success ) {
						var r = data.data;
						var aiText = r.ai_score !== null ? parseFloat(r.ai_score).toFixed(3) + ' (via API)' : ( r.hard_blocked ? 'N/A (hard-block — AI skipped)' : 'N/A (API unavailable)' );
					document.getElementById('aiess-r-ai').textContent    = aiText;
						document.getElementById('aiess-r-rule').textContent  = parseFloat(r.rule_score).toFixed(3) + ' (local rules)';
						document.getElementById('aiess-r-final').textContent = parseFloat(r.final_score).toFixed(3);
						var verdict = document.getElementById('aiess-r-verdict');
						verdict.textContent      = r.blocked ? 'SPAM (would be blocked)' : 'ALLOWED';
						verdict.style.color      = r.blocked ? '#cc0000' : '#007700';
						verdict.style.fontWeight = 'bold';
						document.getElementById('aiess-scan-result').style.display = 'block';
					}
					btn.disabled    = false;
					btn.textContent = '<?php esc_html_e( 'Run Scan', 'ai-email-spam-shield' ); ?>';
				});
			});
			</script>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// AJAX / POST handlers
	// -------------------------------------------------------------------------

	public function handle_clear_logs(): void {
		check_admin_referer( 'aiess_clear_logs', 'aiess_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'ai-email-spam-shield' ) );
		}
		Logger::clear_all();
		wp_safe_redirect( admin_url( 'admin.php?page=aiess-logs&cleared=1' ) );
		exit;
	}

	public function handle_test_scan(): void {
		check_ajax_referer( 'aiess_test_scan', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$subject = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
		$body    = sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) );
		$ip      = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );

		$result = Scanner::scan( $subject, $body, '', $ip );
		wp_send_json_success( $result );
	}
}
