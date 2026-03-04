<?php
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Constants (safe to define before Patchwork — they are not interceptable anyway).
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

// WP_Error class stub (must be a class, not a function — defined before stubs.php is fine).
if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        public string $code;
        public string $message;
        public function __construct( string $code = '', string $message = '' ) {
            $this->code    = $code;
            $this->message = $message;
        }
        public function get_error_message(): string { return $this->message; }
    }
}

// Patchwork MUST be loaded before any WP function stubs so Brain\Monkey can intercept them.
require_once dirname( __DIR__ ) . '/vendor/antecedent/patchwork/Patchwork.php';

// WP function stubs — loaded through Patchwork's stream wrapper so they are interceptable.
require_once __DIR__ . '/stubs.php';

// Plugin classes.
require_once AIESS_PLUGIN_DIR . 'includes/class-rules-engine.php';
require_once AIESS_PLUGIN_DIR . 'includes/class-logger.php';
require_once AIESS_PLUGIN_DIR . 'includes/class-scanner.php';
require_once AIESS_PLUGIN_DIR . 'includes/class-updater.php';
require_once AIESS_PLUGIN_DIR . 'includes/class-provider-interface.php';
require_once AIESS_PLUGIN_DIR . 'includes/providers/class-provider-self-hosted.php';
