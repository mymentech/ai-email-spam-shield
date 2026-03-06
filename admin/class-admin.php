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
		add_action( 'admin_notices',                        array( $this, 'render_privacy_notice' ) );
		add_action( 'wp_ajax_aiess_dismiss_privacy_notice', array( $this, 'handle_dismiss_privacy_notice' ) );
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
		add_submenu_page( 'aiess-dashboard', esc_html__( 'Phrases', 'ai-email-spam-shield' ),      esc_html__( 'Phrases', 'ai-email-spam-shield' ),      'manage_options', 'aiess-phrases',       array( $this, 'page_phrases' ) );
		add_submenu_page( 'aiess-dashboard', esc_html__( 'Logs', 'ai-email-spam-shield' ),         esc_html__( 'Logs', 'ai-email-spam-shield' ),         'manage_options', 'aiess-logs',          array( $this, 'page_logs' ) );
		add_submenu_page( 'aiess-dashboard', esc_html__( 'Test Scanner', 'ai-email-spam-shield' ), esc_html__( 'Test Scanner', 'ai-email-spam-shield' ), 'manage_options', 'aiess-test-scanner',  array( $this, 'page_test_scanner' ) );
	}

	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'aiess' ) === false ) {
			return;
		}
		wp_enqueue_style( 'aiess-admin', AIESS_PLUGIN_URL . 'admin/admin.css', array(), AIESS_VERSION );

		// Chart.js only on the dashboard page.
		if ( strpos( $hook, 'aiess-dashboard' ) !== false || strpos( $hook, 'toplevel_page_aiess-dashboard' ) !== false ) {
			wp_enqueue_script(
				'chartjs',
				'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js',
				array(),
				'4',
				true
			);
		}
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

		register_setting(
			'aiess_phrases_group',
			'aiess_phrases',
			array( 'sanitize_callback' => array( $this, 'sanitize_phrases' ) )
		);
	}

	public function sanitize_phrases( $input ): array {
		$result = array( 'spam' => array(), 'hard_block' => array() );

		foreach ( array( 'spam', 'hard_block' ) as $tier ) {
			$raw   = (array) ( $input[ $tier ] ?? array() );
			$clean = array();
			foreach ( $raw as $phrase ) {
				$phrase = sanitize_text_field( wp_unslash( (string) $phrase ) );
				if ( '' === $phrase ) {
					continue;
				}
				$clean[] = mb_substr( $phrase, 0, 200 );
			}
			$result[ $tier ] = array_slice( array_values( array_unique( $clean ) ), 0, 200 );
		}

		return $result;
	}

	public function sanitize_settings( array $input ): array {
		$valid_providers = [ 'self_hosted', 'openai', 'claude', 'gemini', 'groq', 'cohere', 'deepseek', 'ollama', 'openai_compat' ];
		$provider        = in_array( $input['ai_provider'] ?? '', $valid_providers, true )
			? $input['ai_provider']
			: 'self_hosted';

		return [
			'enabled'             => ! empty( $input['enabled'] ) ? 1 : 0,
			'threshold'           => min( 1.0, max( 0.0, (float) ( $input['threshold'] ?? 0.80 ) ) ),
			'ai_weight'           => min( 1.0, max( 0.0, (float) ( $input['ai_weight'] ?? 0.7 ) ) ),
			'rule_weight'         => min( 1.0, max( 0.0, (float) ( $input['rule_weight'] ?? 0.3 ) ) ),
			// Provider selection
			'ai_provider'         => $provider,
			// Self-hosted
			'self_hosted_url'     => esc_url_raw( $input['self_hosted_url'] ?? '' ),
			'self_hosted_key'     => sanitize_text_field( $input['self_hosted_key'] ?? '' ),
			// OpenAI
			'openai_key'          => sanitize_text_field( $input['openai_key'] ?? '' ),
			'openai_model'        => sanitize_text_field( $input['openai_model'] ?? 'gpt-4o-mini' ),
			// Claude
			'claude_key'          => sanitize_text_field( $input['claude_key'] ?? '' ),
			'claude_model'        => sanitize_text_field( $input['claude_model'] ?? 'claude-haiku-4-5-20251001' ),
			// Gemini
			'gemini_key'          => sanitize_text_field( $input['gemini_key'] ?? '' ),
			'gemini_model'        => sanitize_text_field( $input['gemini_model'] ?? 'gemini-1.5-flash' ),
			// Groq
			'groq_key'            => sanitize_text_field( $input['groq_key'] ?? '' ),
			'groq_model'          => sanitize_text_field( $input['groq_model'] ?? 'llama-3.1-8b-instant' ),
			// Cohere
			'cohere_key'          => sanitize_text_field( $input['cohere_key'] ?? '' ),
			'cohere_model'        => sanitize_text_field( $input['cohere_model'] ?? 'command-r' ),
			// DeepSeek
			'deepseek_key'        => sanitize_text_field( $input['deepseek_key'] ?? '' ),
			'deepseek_model'      => sanitize_text_field( $input['deepseek_model'] ?? 'deepseek-chat' ),
			// Ollama
			'ollama_url'          => esc_url_raw( $input['ollama_url'] ?? 'http://localhost:11434' ),
			'ollama_model'        => sanitize_text_field( $input['ollama_model'] ?? '' ),
			// OpenAI-compatible
			'openai_compat_url'   => esc_url_raw( $input['openai_compat_url'] ?? '' ),
			'openai_compat_key'   => sanitize_text_field( $input['openai_compat_key'] ?? '' ),
			'openai_compat_model' => sanitize_text_field( $input['openai_compat_model'] ?? '' ),
		];
	}

	public function render_privacy_notice(): void {
		if ( get_user_meta( get_current_user_id(), 'aiess_privacy_notice_dismissed', true ) ) {
			return;
		}
		$nonce = wp_create_nonce( 'aiess_dismiss_privacy' );
		?>
		<div id="aiess-privacy-notice" class="notice notice-warning is-dismissible">
			<p>
				<strong><?php esc_html_e( 'AI Email Spam Shield — Privacy Notice', 'ai-email-spam-shield' ); ?></strong><br>
				<?php esc_html_e( 'When using a cloud AI provider, AI Email Spam Shield transmits email subjects and message bodies to the provider\'s API for spam analysis. Email content may leave your server. Please review and update your site\'s privacy policy to inform users of this data processing.', 'ai-email-spam-shield' ); ?>
			</p>
		</div>
		<script>
		(function () {
			document.addEventListener('click', function (e) {
				var btn = e.target.closest('.notice-dismiss');
				if ( btn && btn.closest('#aiess-privacy-notice') ) {
					fetch(ajaxurl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: 'action=aiess_dismiss_privacy_notice&nonce=<?php echo esc_js( $nonce ); ?>'
					}).catch(function () {});
				}
			});
		}());
		</script>
		<?php
	}

	public function handle_dismiss_privacy_notice(): void {
		check_ajax_referer( 'aiess_dismiss_privacy', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
			return;
		}
		update_user_meta( get_current_user_id(), 'aiess_privacy_notice_dismissed', 1 );
		wp_send_json_success();
	}

	// -------------------------------------------------------------------------
	// Pages
	// -------------------------------------------------------------------------

	public function page_dashboard(): void {
		global $wpdb;

		$stats    = Logger::get_stats();
		$daily    = Logger::get_daily_stats( 7 );
		$options  = get_option( 'aiess_settings', array() );
		$provider = $options['ai_provider'] ?? 'self_hosted';

		if ( 'self_hosted' === $provider ) {
			$api_url    = esc_url_raw( $options['self_hosted_url'] ?? 'http://spam-api:8000' );
			$health_url = preg_replace( '/\/predict$/', '/health', rtrim( $api_url, '/' ) );
			$response   = wp_remote_get( $health_url, array( 'timeout' => 3 ) );
			$api_ok     = ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response );
		} else {
			$key_fields = [
				'openai'        => 'openai_key',
				'claude'        => 'claude_key',
				'gemini'        => 'gemini_key',
				'groq'          => 'groq_key',
				'cohere'        => 'cohere_key',
				'deepseek'      => 'deepseek_key',
				'ollama'        => 'ollama_model',
				'openai_compat' => 'openai_compat_url',
			];
			$field  = $key_fields[ $provider ] ?? '';
			$api_ok = ! empty( $options[ $field ] );
		}

		// Overall blocked count for doughnut chart.
		$tbl             = $wpdb->prefix . 'ai_spam_logs';
		$overall_blocked = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl} WHERE blocked = 1" ); // phpcs:ignore
		$overall_allowed = max( 0, $stats['total_scanned'] - $overall_blocked );

		$chart_data = wp_json_encode( array(
			'labels'  => array_column( $daily, 'date' ),
			'scanned' => array_column( $daily, 'scanned' ),
			'blocked' => array_column( $daily, 'blocked' ),
			'overall' => array( $overall_allowed, $overall_blocked ),
		) );
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
					<span class="aiess-stat-label">
						<?php echo 'self_hosted' === $provider
							? esc_html__( 'AI API Status', 'ai-email-spam-shield' )
							: esc_html__( 'AI Provider', 'ai-email-spam-shield' ); ?>
					</span>
				</div>
			</div>

			<div class="aiess-charts-grid">
				<div class="aiess-chart-card">
					<h3><?php esc_html_e( 'Scanned vs Blocked — Last 7 Days', 'ai-email-spam-shield' ); ?></h3>
					<canvas id="aiess-line-chart" height="80"></canvas>
				</div>
				<div class="aiess-chart-card">
					<h3><?php esc_html_e( 'Overall Ratio', 'ai-email-spam-shield' ); ?></h3>
					<canvas id="aiess-doughnut-chart" height="160"></canvas>
				</div>
			</div>
		</div>

		<script>
		(function () {
			var d = <?php echo $chart_data; // wp_json_encode output — safe. ?>;

			new Chart( document.getElementById( 'aiess-line-chart' ), {
				type: 'line',
				data: {
					labels: d.labels,
					datasets: [
						{
							label: '<?php echo esc_js( __( 'Scanned', 'ai-email-spam-shield' ) ); ?>',
							data: d.scanned,
							borderColor: '#6c63ff',
							backgroundColor: 'rgba(108,99,255,.1)',
							tension: 0.3,
							fill: true,
							pointRadius: 4,
						},
						{
							label: '<?php echo esc_js( __( 'Blocked', 'ai-email-spam-shield' ) ); ?>',
							data: d.blocked,
							borderColor: '#ff6584',
							backgroundColor: 'rgba(255,101,132,.08)',
							tension: 0.3,
							fill: true,
							pointRadius: 4,
						}
					]
				},
				options: {
					responsive: true,
					plugins: { legend: { position: 'bottom' } },
					scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
				}
			} );

			new Chart( document.getElementById( 'aiess-doughnut-chart' ), {
				type: 'doughnut',
				data: {
					labels: [
						'<?php echo esc_js( __( 'Allowed', 'ai-email-spam-shield' ) ); ?>',
						'<?php echo esc_js( __( 'Blocked', 'ai-email-spam-shield' ) ); ?>'
					],
					datasets: [ {
						data: d.overall,
						backgroundColor: [ '#43b89c', '#ff6584' ],
						borderWidth: 2,
					} ]
				},
				options: {
					responsive: true,
					plugins: { legend: { position: 'bottom' } },
					cutout: '65%',
				}
			} );
		}());
		</script>
		<?php
	}

	public function page_settings(): void {
		$options  = get_option( 'aiess_settings', array() );
		$provider = $options['ai_provider'] ?? 'self_hosted';
		$providers = [
			'self_hosted'   => __( 'Self-Hosted (BERT microservice)', 'ai-email-spam-shield' ),
			'openai'        => __( 'OpenAI (GPT-4o-mini, GPT-4o, etc.)', 'ai-email-spam-shield' ),
			'claude'        => __( 'Claude (Anthropic)', 'ai-email-spam-shield' ),
			'gemini'        => __( 'Gemini (Google)', 'ai-email-spam-shield' ),
			'groq'          => __( 'Groq (fast inference)', 'ai-email-spam-shield' ),
			'cohere'        => __( 'Cohere', 'ai-email-spam-shield' ),
			'deepseek'      => __( 'DeepSeek', 'ai-email-spam-shield' ),
			'ollama'        => __( 'Ollama (local)', 'ai-email-spam-shield' ),
			'openai_compat' => __( 'OpenAI-Compatible (LM Studio, etc.)', 'ai-email-spam-shield' ),
		];
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
						<th scope="row"><label for="aiess_ai_provider"><?php esc_html_e( 'AI Provider', 'ai-email-spam-shield' ); ?></label></th>
						<td>
							<select id="aiess_ai_provider" name="aiess_settings[ai_provider]">
								<?php foreach ( $providers as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $provider, $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>

				<?php if ( ! get_user_meta( get_current_user_id(), 'aiess_privacy_notice_dismissed', true ) ) : ?>
				<div id="aiess-settings-privacy-notice" class="aiess-inline-notice" style="display:none;">
					<p>
						<strong><?php esc_html_e( 'Privacy Notice:', 'ai-email-spam-shield' ); ?></strong>
						<?php esc_html_e( 'The selected AI provider will receive email subject lines and message bodies for spam scoring. This data is transmitted to an external server. Ensure your privacy policy covers this processing.', 'ai-email-spam-shield' ); ?>
						&nbsp;<a href="#" id="aiess-settings-dismiss-privacy"><?php esc_html_e( 'Dismiss', 'ai-email-spam-shield' ); ?></a>
					</p>
				</div>
				<?php endif; ?>

				<!-- Self-hosted -->
				<table class="form-table aiess-provider-fields" id="aiess-fields-self_hosted" role="presentation">
					<tr><th colspan="2"><strong><?php esc_html_e( 'Self-Hosted Setup', 'ai-email-spam-shield' ); ?></strong></th></tr>
					<tr><td colspan="2"><details><summary><?php esc_html_e( 'Setup Instructions', 'ai-email-spam-shield' ); ?></summary>
					<p class="description"><?php esc_html_e( 'Run the BERT microservice using Docker: clone the plugin repo, copy spam-api/docker-compose-sample.yml, set AIESS_API_KEY, then run: docker-compose up -d spam-api. Requires ~500 MB disk for the model.', 'ai-email-spam-shield' ); ?></p>
					</details></td></tr>
					<tr>
						<th><label for="aiess_self_hosted_url"><?php esc_html_e( 'API URL', 'ai-email-spam-shield' ); ?></label></th>
						<td><input type="url" id="aiess_self_hosted_url" name="aiess_settings[self_hosted_url]"
								   value="<?php echo esc_attr( $options['self_hosted_url'] ?? 'http://spam-api:8000/predict' ); ?>"
								   class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="aiess_self_hosted_key"><?php esc_html_e( 'API Key (optional)', 'ai-email-spam-shield' ); ?></label></th>
						<td><input type="password" id="aiess_self_hosted_key" name="aiess_settings[self_hosted_key]"
								   value="<?php echo esc_attr( $options['self_hosted_key'] ?? '' ); ?>"
								   class="regular-text" autocomplete="new-password"></td>
					</tr>
				</table>

				<!-- OpenAI -->
				<table class="form-table aiess-provider-fields" id="aiess-fields-openai" role="presentation">
					<tr><th colspan="2"><strong><?php esc_html_e( 'OpenAI Setup', 'ai-email-spam-shield' ); ?></strong></th></tr>
					<tr><td colspan="2"><details><summary><?php esc_html_e( 'Setup Instructions', 'ai-email-spam-shield' ); ?></summary>
					<p class="description"><?php esc_html_e( 'Get your API key from platform.openai.com → API Keys. Recommended model: gpt-4o-mini (fast and cheap). Usage is billed per token.', 'ai-email-spam-shield' ); ?></p>
					</details></td></tr>
					<tr>
						<th><label for="aiess_openai_key"><?php esc_html_e( 'API Key', 'ai-email-spam-shield' ); ?></label></th>
						<td><input type="password" id="aiess_openai_key" name="aiess_settings[openai_key]"
								   value="<?php echo esc_attr( $options['openai_key'] ?? '' ); ?>"
								   class="regular-text" autocomplete="new-password"></td>
					</tr>
					<tr>
						<th><label for="aiess_openai_model"><?php esc_html_e( 'Model', 'ai-email-spam-shield' ); ?></label></th>
						<td><input type="text" id="aiess_openai_model" name="aiess_settings[openai_model]"
								   value="<?php echo esc_attr( $options['openai_model'] ?? 'gpt-4o-mini' ); ?>"
								   class="regular-text"></td>
					</tr>
				</table>

				<!-- Claude -->
				<table class="form-table aiess-provider-fields" id="aiess-fields-claude" role="presentation">
					<tr><th colspan="2"><strong><?php esc_html_e( 'Claude (Anthropic) Setup', 'ai-email-spam-shield' ); ?></strong></th></tr>
					<tr><td colspan="2"><details><summary><?php esc_html_e( 'Setup Instructions', 'ai-email-spam-shield' ); ?></summary>
					<p class="description"><?php esc_html_e( 'Get your API key from console.anthropic.com → API Keys. Recommended model: claude-haiku-4-5-20251001 (fastest and most affordable).', 'ai-email-spam-shield' ); ?></p>
					</details></td></tr>
					<tr>
						<th><label for="aiess_claude_key"><?php esc_html_e( 'API Key', 'ai-email-spam-shield' ); ?></label></th>
						<td><input type="password" id="aiess_claude_key" name="aiess_settings[claude_key]"
								   value="<?php echo esc_attr( $options['claude_key'] ?? '' ); ?>"
								   class="regular-text" autocomplete="new-password"></td>
					</tr>
					<tr>
						<th><label for="aiess_claude_model"><?php esc_html_e( 'Model', 'ai-email-spam-shield' ); ?></label></th>
						<td><input type="text" id="aiess_claude_model" name="aiess_settings[claude_model]"
								   value="<?php echo esc_attr( $options['claude_model'] ?? 'claude-haiku-4-5-20251001' ); ?>"
								   class="regular-text"></td>
					</tr>
				</table>

				<!-- Gemini -->
				<table class="form-table aiess-provider-fields" id="aiess-fields-gemini" role="presentation">
					<tr><th colspan="2"><strong><?php esc_html_e( 'Gemini (Google) Setup', 'ai-email-spam-shield' ); ?></strong></th></tr>
					<tr><td colspan="2"><details><summary><?php esc_html_e( 'Setup Instructions', 'ai-email-spam-shield' ); ?></summary>
					<p class="description"><?php esc_html_e( 'Get your API key from aistudio.google.com → Get API key. Recommended model: gemini-1.5-flash. Free tier available.', 'ai-email-spam-shield' ); ?></p>
					</details></td></tr>
					<tr>
						<th><label for="aiess_gemini_key"><?php esc_html_e( 'API Key', 'ai-email-spam-shield' ); ?></label></th>
						<td><input type="password" id="aiess_gemini_key" name="aiess_settings[gemini_key]"
								   value="<?php echo esc_attr( $options['gemini_key'] ?? '' ); ?>"
								   class="regular-text" autocomplete="new-password"></td>
					</tr>
					<tr>
						<th><label for="aiess_gemini_model"><?php esc_html_e( 'Model', 'ai-email-spam-shield' ); ?></label></th>
						<td><input type="text" id="aiess_gemini_model" name="aiess_settings[gemini_model]"
								   value="<?php echo esc_attr( $options['gemini_model'] ?? 'gemini-1.5-flash' ); ?>"
								   class="regular-text"></td>
					</tr>
				</table>

				<!-- Groq -->
				<table class="form-table aiess-provider-fields" id="aiess-fields-groq" role="presentation">
					<tr><th colspan="2"><strong><?php esc_html_e( 'Groq Setup', 'ai-email-spam-shield' ); ?></strong></th></tr>
					<tr><td colspan="2"><details><summary><?php esc_html_e( 'Setup Instructions', 'ai-email-spam-shield' ); ?></summary>
					<p class="description"><?php esc_html_e( 'Get your API key from console.groq.com → API Keys. Groq runs open-source models at very high speed. Recommended: llama-3.1-8b-instant.', 'ai-email-spam-shield' ); ?></p>
					</details></td></tr>
					<tr>
						<th><label for="aiess_groq_key"><?php esc_html_e( 'API Key', 'ai-email-spam-shield' ); ?></label></th>
						<td><input type="password" id="aiess_groq_key" name="aiess_settings[groq_key]"
								   value="<?php echo esc_attr( $options['groq_key'] ?? '' ); ?>"
								   class="regular-text" autocomplete="new-password"></td>
					</tr>
					<tr>
						<th><label for="aiess_groq_model"><?php esc_html_e( 'Model', 'ai-email-spam-shield' ); ?></label></th>
						<td><input type="text" id="aiess_groq_model" name="aiess_settings[groq_model]"
								   value="<?php echo esc_attr( $options['groq_model'] ?? 'llama-3.1-8b-instant' ); ?>"
								   class="regular-text"></td>
					</tr>
				</table>

				<!-- Cohere -->
				<table class="form-table aiess-provider-fields" id="aiess-fields-cohere" role="presentation">
					<tr><th colspan="2"><strong><?php esc_html_e( 'Cohere Setup', 'ai-email-spam-shield' ); ?></strong></th></tr>
					<tr><td colspan="2"><details><summary><?php esc_html_e( 'Setup Instructions', 'ai-email-spam-shield' ); ?></summary>
					<p class="description"><?php esc_html_e( 'Get your API key from dashboard.cohere.com → API Keys. Recommended model: command-r.', 'ai-email-spam-shield' ); ?></p>
					</details></td></tr>
					<tr>
						<th><label for="aiess_cohere_key"><?php esc_html_e( 'API Key', 'ai-email-spam-shield' ); ?></label></th>
						<td><input type="password" id="aiess_cohere_key" name="aiess_settings[cohere_key]"
								   value="<?php echo esc_attr( $options['cohere_key'] ?? '' ); ?>"
								   class="regular-text" autocomplete="new-password"></td>
					</tr>
					<tr>
						<th><label for="aiess_cohere_model"><?php esc_html_e( 'Model', 'ai-email-spam-shield' ); ?></label></th>
						<td><input type="text" id="aiess_cohere_model" name="aiess_settings[cohere_model]"
								   value="<?php echo esc_attr( $options['cohere_model'] ?? 'command-r' ); ?>"
								   class="regular-text"></td>
					</tr>
				</table>

				<!-- DeepSeek -->
				<table class="form-table aiess-provider-fields" id="aiess-fields-deepseek" role="presentation">
					<tr><th colspan="2"><strong><?php esc_html_e( 'DeepSeek Setup', 'ai-email-spam-shield' ); ?></strong></th></tr>
					<tr><td colspan="2"><details><summary><?php esc_html_e( 'Setup Instructions', 'ai-email-spam-shield' ); ?></summary>
					<p class="description"><?php esc_html_e( 'Get your API key from platform.deepseek.com → API Keys. Recommended model: deepseek-chat. Very cost-effective.', 'ai-email-spam-shield' ); ?></p>
					</details></td></tr>
					<tr>
						<th><label for="aiess_deepseek_key"><?php esc_html_e( 'API Key', 'ai-email-spam-shield' ); ?></label></th>
						<td><input type="password" id="aiess_deepseek_key" name="aiess_settings[deepseek_key]"
								   value="<?php echo esc_attr( $options['deepseek_key'] ?? '' ); ?>"
								   class="regular-text" autocomplete="new-password"></td>
					</tr>
					<tr>
						<th><label for="aiess_deepseek_model"><?php esc_html_e( 'Model', 'ai-email-spam-shield' ); ?></label></th>
						<td><input type="text" id="aiess_deepseek_model" name="aiess_settings[deepseek_model]"
								   value="<?php echo esc_attr( $options['deepseek_model'] ?? 'deepseek-chat' ); ?>"
								   class="regular-text"></td>
					</tr>
				</table>

				<!-- Ollama -->
				<table class="form-table aiess-provider-fields" id="aiess-fields-ollama" role="presentation">
					<tr><th colspan="2"><strong><?php esc_html_e( 'Ollama Setup', 'ai-email-spam-shield' ); ?></strong></th></tr>
					<tr><td colspan="2"><details><summary><?php esc_html_e( 'Setup Instructions', 'ai-email-spam-shield' ); ?></summary>
					<p class="description"><?php esc_html_e( 'Install Ollama on your server (ollama.com), then pull a model: ollama pull llama3. Make sure the Ollama API is reachable from your WordPress server. No API key required.', 'ai-email-spam-shield' ); ?></p>
					</details></td></tr>
					<tr>
						<th><label for="aiess_ollama_url"><?php esc_html_e( 'Ollama Base URL', 'ai-email-spam-shield' ); ?></label></th>
						<td><input type="url" id="aiess_ollama_url" name="aiess_settings[ollama_url]"
								   value="<?php echo esc_attr( $options['ollama_url'] ?? 'http://localhost:11434' ); ?>"
								   class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="aiess_ollama_model"><?php esc_html_e( 'Model Name', 'ai-email-spam-shield' ); ?></label></th>
						<td><input type="text" id="aiess_ollama_model" name="aiess_settings[ollama_model]"
								   value="<?php echo esc_attr( $options['ollama_model'] ?? '' ); ?>"
								   class="regular-text" placeholder="e.g. llama3"></td>
					</tr>
				</table>

				<!-- OpenAI-compatible -->
				<table class="form-table aiess-provider-fields" id="aiess-fields-openai_compat" role="presentation">
					<tr><th colspan="2"><strong><?php esc_html_e( 'OpenAI-Compatible Setup', 'ai-email-spam-shield' ); ?></strong></th></tr>
					<tr><td colspan="2"><details><summary><?php esc_html_e( 'Setup Instructions', 'ai-email-spam-shield' ); ?></summary>
					<p class="description"><?php esc_html_e( 'Works with LM Studio, Jan, LocalAI, and any server that implements the OpenAI Chat Completions API. Enter the base URL (e.g. http://localhost:1234/v1) — the plugin appends /chat/completions automatically.', 'ai-email-spam-shield' ); ?></p>
					</details></td></tr>
					<tr>
						<th><label for="aiess_openai_compat_url"><?php esc_html_e( 'Base URL', 'ai-email-spam-shield' ); ?></label></th>
						<td><input type="url" id="aiess_openai_compat_url" name="aiess_settings[openai_compat_url]"
								   value="<?php echo esc_attr( $options['openai_compat_url'] ?? '' ); ?>"
								   class="regular-text" placeholder="http://localhost:1234/v1"></td>
					</tr>
					<tr>
						<th><label for="aiess_openai_compat_key"><?php esc_html_e( 'API Key (optional)', 'ai-email-spam-shield' ); ?></label></th>
						<td><input type="password" id="aiess_openai_compat_key" name="aiess_settings[openai_compat_key]"
								   value="<?php echo esc_attr( $options['openai_compat_key'] ?? '' ); ?>"
								   class="regular-text" autocomplete="new-password"></td>
					</tr>
					<tr>
						<th><label for="aiess_openai_compat_model"><?php esc_html_e( 'Model Name', 'ai-email-spam-shield' ); ?></label></th>
						<td><input type="text" id="aiess_openai_compat_model" name="aiess_settings[openai_compat_model]"
								   value="<?php echo esc_attr( $options['openai_compat_model'] ?? '' ); ?>"
								   class="regular-text" placeholder="e.g. phi-3"></td>
					</tr>
				</table>

				<!-- Scoring weights (always visible) -->
				<div class="aiess-card">
				<table class="form-table" role="presentation">
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
						</td>
					</tr>
				</table>
				</div>

				<?php submit_button( esc_html__( 'Save Settings', 'ai-email-spam-shield' ) ); ?>
			</form>
		</div>

		<script>
		(function () {
			var select     = document.getElementById('aiess_ai_provider');
			var tables     = document.querySelectorAll('.aiess-provider-fields');
			var privNotice = document.getElementById('aiess-settings-privacy-notice');
			var localProvs = ['self_hosted', 'ollama'];

			function showActiveProvider() {
				var val = select.value;
				tables.forEach(function (t) {
					t.style.display = t.id === 'aiess-fields-' + val ? '' : 'none';
				});
				if ( privNotice ) {
					privNotice.style.display = localProvs.indexOf(val) === -1 ? '' : 'none';
				}
			}

			select.addEventListener('change', showActiveProvider);
			showActiveProvider();

			var dismissLink = document.getElementById('aiess-settings-dismiss-privacy');
			if ( dismissLink ) {
				dismissLink.addEventListener('click', function (e) {
					e.preventDefault();
					fetch(ajaxurl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: 'action=aiess_dismiss_privacy_notice&nonce=<?php echo esc_js( wp_create_nonce( 'aiess_dismiss_privacy' ) ); ?>'
					}).then(function () {
						if ( privNotice ) { privNotice.style.display = 'none'; }
					}).catch(function () {
						if ( privNotice ) { privNotice.style.display = 'none'; }
					});
				});
			}
		}());
		</script>
		<?php
	}

	public function page_phrases(): void {
		$phrases             = get_option( 'aiess_phrases', array( 'spam' => array(), 'hard_block' => array() ) );
		$spam_phrases        = (array) ( $phrases['spam']       ?? array() );
		$hard_block_phrases  = (array) ( $phrases['hard_block'] ?? array() );
		?>
		<div class="wrap aiess-wrap">
			<h1><?php esc_html_e( 'AI Spam Shield — Phrase Management', 'ai-email-spam-shield' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Manage custom phrases used by the blocking algorithm. Changes take effect on the next email scan.', 'ai-email-spam-shield' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'aiess_phrases_group' ); ?>

				<div class="aiess-card">
					<div class="aiess-card-header">
						<h2><?php esc_html_e( 'Spam Phrases', 'ai-email-spam-shield' ); ?></h2>
						<p class="description">
							<?php esc_html_e( 'Emails containing these phrases receive a spam score boost (+0.20). They are blocked only if the total score exceeds your threshold.', 'ai-email-spam-shield' ); ?>
						</p>
					</div>
					<div id="aiess-spam-repeater" class="aiess-repeater">
						<?php foreach ( $spam_phrases as $phrase ) : ?>
							<div class="aiess-repeater-row">
								<input type="text"
									   name="aiess_phrases[spam][]"
									   value="<?php echo esc_attr( $phrase ); ?>"
									   class="regular-text"
									   placeholder="<?php esc_attr_e( 'Enter phrase…', 'ai-email-spam-shield' ); ?>">
								<button type="button" class="aiess-remove-row button" aria-label="<?php esc_attr_e( 'Remove', 'ai-email-spam-shield' ); ?>">&#10005;</button>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button aiess-add-row" data-target="aiess-spam-repeater" data-name="aiess_phrases[spam][]">
						+ <?php esc_html_e( 'Add Phrase', 'ai-email-spam-shield' ); ?>
					</button>
				</div>

				<div class="aiess-card">
					<div class="aiess-card-header">
						<h2><?php esc_html_e( 'Hard-Block Phrases', 'ai-email-spam-shield' ); ?></h2>
						<p class="description">
							<?php esc_html_e( 'Emails containing these phrases are blocked immediately regardless of AI score or threshold. Use for zero-tolerance terms.', 'ai-email-spam-shield' ); ?>
						</p>
					</div>
					<div id="aiess-hardblock-repeater" class="aiess-repeater">
						<?php foreach ( $hard_block_phrases as $phrase ) : ?>
							<div class="aiess-repeater-row">
								<input type="text"
									   name="aiess_phrases[hard_block][]"
									   value="<?php echo esc_attr( $phrase ); ?>"
									   class="regular-text"
									   placeholder="<?php esc_attr_e( 'Enter phrase…', 'ai-email-spam-shield' ); ?>">
								<button type="button" class="aiess-remove-row button" aria-label="<?php esc_attr_e( 'Remove', 'ai-email-spam-shield' ); ?>">&#10005;</button>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button aiess-add-row" data-target="aiess-hardblock-repeater" data-name="aiess_phrases[hard_block][]">
						+ <?php esc_html_e( 'Add Phrase', 'ai-email-spam-shield' ); ?>
					</button>
				</div>

				<?php submit_button( esc_html__( 'Save Phrases', 'ai-email-spam-shield' ) ); ?>
			</form>
		</div>

		<script>
		(function () {
			function attachRemove( btn ) {
				btn.addEventListener( 'click', function () {
					btn.closest( '.aiess-repeater-row' ).remove();
				} );
			}

			document.querySelectorAll( '.aiess-add-row' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var repeater = document.getElementById( btn.dataset.target );
					var row      = document.createElement( 'div' );
					row.className = 'aiess-repeater-row';
					row.innerHTML = '<input type="text" name="' + btn.dataset.name + '" class="regular-text" placeholder="<?php echo esc_js( __( 'Enter phrase…', 'ai-email-spam-shield' ) ); ?>">'
					              + '<button type="button" class="aiess-remove-row button" aria-label="Remove">&#10005;</button>';
					repeater.appendChild( row );
					row.querySelector( 'input' ).focus();
					attachRemove( row.querySelector( '.aiess-remove-row' ) );
				} );
			} );

			document.querySelectorAll( '.aiess-remove-row' ).forEach( attachRemove );
		}());
		</script>
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
			return;
		}

		$subject = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
		$body    = sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) );
		$ip      = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );

		$result = Scanner::scan( $subject, $body, '', $ip );
		wp_send_json_success( $result );
	}
}
