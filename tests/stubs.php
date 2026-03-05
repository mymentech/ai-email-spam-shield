<?php
/**
 * WordPress function stubs for unit tests.
 *
 * This file must be required AFTER Patchwork is loaded so that Brain\Monkey
 * can intercept any of these stubs when a test calls Functions\expect().
 */

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
if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data, int $options = 0, int $depth = 512 ): string|false {
        return json_encode( $data, $options, $depth );
    }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( string $url, array $protocols = [] ): string {
        return $url;
    }
}
if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ): bool {
        return $thing instanceof \WP_Error;
    }
}
if ( ! function_exists( 'wp_remote_post' ) ) {
    function wp_remote_post( string $url, array $args = [] ) { return new \WP_Error( 'stub', 'Not mocked' ); }
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
    function wp_remote_retrieve_response_code( $response ): int { return 0; }
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
    function wp_remote_retrieve_body( $response ): string { return ''; }
}
if ( ! function_exists( 'get_user_meta' ) ) {
    function get_user_meta( int $user_id, string $key = '', bool $single = false ) { return ''; }
}
if ( ! function_exists( 'update_user_meta' ) ) {
    function update_user_meta( int $user_id, string $key, $value, $prev = '' ) { return true; }
}
if ( ! function_exists( 'get_current_user_id' ) ) {
    function get_current_user_id(): int { return 0; }
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
    function wp_create_nonce( $action = -1 ): string { return 'stub_nonce'; }
}
if ( ! function_exists( 'esc_js' ) ) {
    function esc_js( string $text ): string { return $text; }
}
if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( string $text, string $domain = 'default' ): string { return $text; }
}
if ( ! function_exists( 'esc_html_e' ) ) {
    function esc_html_e( string $text, string $domain = 'default' ): void { echo $text; }
}
if ( ! function_exists( 'check_ajax_referer' ) ) {
    function check_ajax_referer( $action = -1, $query_arg = false, bool $die = true ) { return 1; }
}
if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( string $capability ): bool { return false; }
}
if ( ! function_exists( 'wp_send_json_success' ) ) {
    function wp_send_json_success( $data = null, int $status_code = 200, int $flags = 0 ): void {}
}
if ( ! function_exists( 'wp_send_json_error' ) ) {
    function wp_send_json_error( $data = null, int $status_code = 200, int $flags = 0 ): void {}
}
if ( ! function_exists( 'add_action' ) ) {
    function add_action( string $hook, $callback, int $priority = 10, int $args = 1 ): true { return true; }
}
if ( ! function_exists( 'add_menu_page' ) ) {
    function add_menu_page( ...$args ): string { return ''; }
}
if ( ! function_exists( 'add_submenu_page' ) ) {
    function add_submenu_page( ...$args ): string { return ''; }
}
if ( ! function_exists( 'register_setting' ) ) {
    function register_setting( ...$args ): void {}
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
    function wp_enqueue_style( ...$args ): void {}
}
