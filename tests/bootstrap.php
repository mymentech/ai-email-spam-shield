<?php
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// WordPress function stubs for unit tests.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/tmp/wordpress/' );
}
if ( ! defined( 'AIESS_VERSION' ) ) {
    define( 'AIESS_VERSION', '1.0.0' );
}
if ( ! defined( 'AIESS_PLUGIN_DIR' ) ) {
    define( 'AIESS_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'AIESS_PLUGIN_FILE' ) ) {
    define( 'AIESS_PLUGIN_FILE', AIESS_PLUGIN_DIR . 'ai-email-spam-shield.php' );
}
if ( ! defined( 'AIESS_TEXT_DOMAIN' ) ) {
    define( 'AIESS_TEXT_DOMAIN', 'ai-email-spam-shield' );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
    define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'WP_DEBUG' ) ) {
    define( 'WP_DEBUG', false );
}
if ( ! defined( 'WP_INSTALLING' ) ) {
    define( 'WP_INSTALLING', false );
}
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
    // Must match the actual plugin location so PUC can identify it as a plugin.
    define( 'WP_PLUGIN_DIR', dirname( __DIR__, 2 ) );
}
if ( ! defined( 'WPMU_PLUGIN_DIR' ) ) {
    define( 'WPMU_PLUGIN_DIR', '/tmp/wordpress/wp-content/mu-plugins' );
}
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
    define( 'WP_CONTENT_DIR', dirname( __DIR__, 3 ) );
}

// Stub WP functions used in plugin classes.
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( string $string, bool $remove_breaks = false ): string {
        $string = strip_tags( $string );
        if ( $remove_breaks ) {
            $string = preg_replace( '/[\r\n\t]+/', ' ', $string );
        }
        return $string;
    }
}
if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( string $key ) { return false; }
}
if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( string $key, $value, int $expiration = 0 ): bool { return true; }
}
if ( ! function_exists( 'wp_parse_url' ) ) {
    function wp_parse_url( string $url, int $component = -1 ) {
        return parse_url( $url, $component );
    }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( string $str ): string {
        return strip_tags( trim( $str ) );
    }
}
if ( ! function_exists( 'sanitize_email' ) ) {
    function sanitize_email( string $email ): string {
        // Match WordPress behaviour: strip invalid characters, lowercase.
        $email = strtolower( trim( $email ) );
        $email = preg_replace( '/[^a-z0-9!#$%&\'*+\/=?^_`{|}~.-@]/', '', $email );
        return $email;
    }
}

// Stubs for PUC (plugin-update-checker) functions called during construction.
if ( ! function_exists( 'plugin_basename' ) ) {
    function plugin_basename( string $file ): string {
        return basename( dirname( $file ) ) . '/' . basename( $file );
    }
}
if ( ! function_exists( 'did_action' ) ) {
    function did_action( string $tag ): int { return 0; }
}
if ( ! function_exists( 'wp_next_scheduled' ) ) {
    function wp_next_scheduled( string $hook, array $args = array() ) { return false; }
}
if ( ! function_exists( 'wp_schedule_event' ) ) {
    function wp_schedule_event( int $timestamp, string $recurrence, string $hook, array $args = array(), bool $wp_error = false ) { return true; }
}
if ( ! function_exists( 'get_locale' ) ) {
    function get_locale(): string { return 'en_US'; }
}
if ( ! function_exists( 'is_admin' ) ) {
    function is_admin(): bool { return false; }
}
if ( ! function_exists( 'load_textdomain' ) ) {
    function load_textdomain( string $domain, string $mofile, string $locale = '' ): bool { return true; }
}
if ( ! function_exists( 'get_option' ) ) {
    function get_option( string $option, $default = false ) { return $default; }
}
if ( ! function_exists( 'wp_http_supports' ) ) {
    function wp_http_supports( array $capabilities = array(), ?string $url = null ): bool { return true; }
}
if ( ! function_exists( 'register_deactivation_hook' ) ) {
    function register_deactivation_hook( string $file, callable $callback ): void {}
}
if ( ! function_exists( 'register_activation_hook' ) ) {
    function register_activation_hook( string $file, callable $callback ): void {}
}
if ( ! function_exists( 'wp_rand' ) ) {
    function wp_rand( int $min = 0, int $max = 0 ): int { return rand( $min, $max ); }
}
if ( ! function_exists( 'wp_unschedule_hook' ) ) {
    function wp_unschedule_hook( string $hook ): int|false { return 0; }
}
if ( ! function_exists( 'get_site_transient' ) ) {
    function get_site_transient( string $transient ) { return false; }
}
if ( ! function_exists( 'set_site_transient' ) ) {
    function set_site_transient( string $transient, $value, int $expiration = 0 ): bool { return true; }
}
if ( ! function_exists( 'delete_site_transient' ) ) {
    function delete_site_transient( string $transient ): bool { return true; }
}

require_once AIESS_PLUGIN_DIR . 'includes/class-rules-engine.php';
require_once AIESS_PLUGIN_DIR . 'includes/class-logger.php';
require_once AIESS_PLUGIN_DIR . 'includes/class-scanner.php';
require_once AIESS_PLUGIN_DIR . 'includes/class-updater.php';
