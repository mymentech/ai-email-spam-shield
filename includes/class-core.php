<?php
/**
 * Core — registers all WordPress hooks, honeypot injection, and cron scheduling.
 *
 * @package AI_Email_Spam_Shield
 */

namespace AI_Email_Spam_Shield;

defined( 'ABSPATH' ) || exit;

class Core {

	/** Singleton instance. */
	private static ?self $instance = null;

	/**
	 * Get or create the singleton instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor — register all hooks here.
	 */
	private function __construct() {
		self::load_dotenv();
		$this->register_hooks();
	}

	/**
	 * Load AIESS_* variables from a .env file in the plugin directory.
	 * Only sets a variable if it is not already defined in the environment,
	 * so server-level / Docker env vars always take precedence.
	 */
	public static function load_dotenv(): void {
		$env_file = AIESS_PLUGIN_DIR . '.env';
		if ( ! is_readable( $env_file ) ) {
			return;
		}
		$lines = file( $env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( str_starts_with( $line, '#' ) || ! str_contains( $line, '=' ) ) {
				continue;
			}
			[ $key, $value ] = explode( '=', $line, 2 );
			$key   = trim( $key );
			$value = trim( $value );
			// Only set variables prefixed AIESS_ and only when not already set.
			if ( str_starts_with( $key, 'AIESS_' ) && false === getenv( $key ) ) {
				putenv( "{$key}={$value}" );
			}
		}
	}

	/**
	 * Register all WordPress action and filter hooks.
	 */
	private function register_hooks(): void {
		// Load plugin translations.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// WP-Cron: prune logs daily.
		add_action( 'aiess_prune_logs', array( Logger::class, 'prune_old_logs' ) );

		// Inject universal honeypot field on all frontend pages.
		add_action( 'wp_footer', array( $this, 'inject_honeypot' ) );

		// Ensure cron is scheduled after WordPress is fully loaded.
		add_action( 'wp_loaded', array( $this, 'maybe_schedule_cron' ) );

		// Only intercept emails if scanning is enabled.
		$options = get_option( 'aiess_settings', array() );
		if ( ! empty( $options['enabled'] ) ) {
			// pre_wp_mail short-circuits wp_mail() when a non-null value is returned,
			// preventing delivery. The wp_mail filter cannot block emails.
			add_filter( 'pre_wp_mail', array( $this, 'intercept_email' ), 10, 2 );
		}
	}

	/**
	 * Load the plugin text domain for translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			AIESS_TEXT_DOMAIN,
			false,
			dirname( plugin_basename( AIESS_PLUGIN_FILE ) ) . '/languages/'
		);
	}

	/**
	 * pre_wp_mail short-circuit filter — intercept every outgoing email before delivery.
	 *
	 * Returning false blocks the email (wp_mail() returns false immediately).
	 * Returning null lets WordPress proceed normally.
	 *
	 * @param null|false $result  Current short-circuit value (null by default).
	 * @param array      $atts   wp_mail() arguments: to, subject, message, headers, attachments.
	 * @return null|false
	 */
	public function intercept_email( $result, array $atts ): ?bool {
		// Honeypot check — bot auto-block, no scoring overhead.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['_aiess_honeypot'] ) ) {
			return false;
		}

		$subject = $atts['subject'] ?? '';
		$body    = $atts['message'] ?? '';
		$sender  = $this->extract_sender( $atts['headers'] ?? array() );
		$ip      = $this->get_sender_ip();

		// Run the hybrid scan.
		$scan = Scanner::scan( $subject, $body, $sender, $ip );

		// Log the decision.
		$log_data = Logger::prepare_log_data(
			subject:     $subject,
			sender:      $sender,
			ai_score:    $scan['ai_score'],
			rule_score:  $scan['rule_score'],
			final_score: $scan['final_score'],
			blocked:     $scan['blocked'],
			ip:          $ip
		);
		Logger::insert( $log_data );

		// Return false to block (short-circuits wp_mail), null to allow.
		return $scan['blocked'] ? false : null;
	}

	/**
	 * Extract sender email from wp_mail headers array or string.
	 *
	 * @param array|string $headers  Email headers.
	 * @return string                Sender email or empty string.
	 */
	private function extract_sender( array|string $headers ): string {
		if ( is_string( $headers ) ) {
			$headers = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
		}
		foreach ( (array) $headers as $header ) {
			if ( stripos( trim( $header ), 'from:' ) === 0 ) {
				preg_match( '/[\w.+\-]+@[\w\-]+\.[a-z.]{2,}/i', $header, $m );
				return isset( $m[0] ) ? sanitize_email( $m[0] ) : '';
			}
		}
		return '';
	}

	/**
	 * Get the real client IP, respecting X-Forwarded-For for proxies.
	 *
	 * @return string  Single sanitized IP address.
	 */
	public function get_sender_ip(): string {
		$forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
		if ( ! empty( $forwarded ) ) {
			// Take the first IP from a potentially comma-separated proxy chain.
			$parts = explode( ',', $forwarded );
			return sanitize_text_field( trim( $parts[0] ) );
		}
		return sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
	}

	/**
	 * Inject a universal hidden honeypot field into all frontend pages.
	 * Bots that fill this field are auto-blocked in intercept_email().
	 */
	public function inject_honeypot(): void {
		echo '<div style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;" aria-hidden="true">';
		echo '<input type="text" name="_aiess_honeypot" tabindex="-1" autocomplete="off" value="">';
		echo '</div>';
	}

	/**
	 * Ensure the daily log-pruning cron event is scheduled.
	 */
	public function maybe_schedule_cron(): void {
		if ( ! wp_next_scheduled( 'aiess_prune_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'aiess_prune_logs' );
		}
	}

	/**
	 * Plugin deactivation: clear the scheduled cron event.
	 */
	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( 'aiess_prune_logs' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'aiess_prune_logs' );
		}
	}
}
