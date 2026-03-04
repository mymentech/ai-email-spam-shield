<?php
/**
 * Plugin Name:       AI Email Spam Shield
 * Plugin URI:        https://www.mymentech.com/plugins/ai-email-spam-shield
 * Description:       Hybrid AI + rule-based spam detection for outgoing WordPress emails. Intercepts wp_mail(), Contact Form 7, WPForms, and Gravity Forms submissions.
 * Version:           1.0.1
 * Author:            Mozammel Haque
 * Author URI:        https://www.mymentech.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-email-spam-shield
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Tested up to:      6.7
 *
 * @package AI_Email_Spam_Shield
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'AIESS_VERSION',     '1.0.1' );
define( 'AIESS_PLUGIN_FILE', __FILE__ );
define( 'AIESS_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'AIESS_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'AIESS_TEXT_DOMAIN', 'ai-email-spam-shield' );

// Autoload classes.
require_once AIESS_PLUGIN_DIR . 'includes/class-logger.php';
require_once AIESS_PLUGIN_DIR . 'includes/class-rules-engine.php';
require_once AIESS_PLUGIN_DIR . 'includes/class-scanner.php';
require_once AIESS_PLUGIN_DIR . 'includes/class-core.php';
require_once AIESS_PLUGIN_DIR . 'admin/class-admin.php';
require_once AIESS_PLUGIN_DIR . 'includes/class-updater.php';

// Activation / deactivation hooks.
register_activation_hook( __FILE__, array( 'AI_Email_Spam_Shield\Logger', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AI_Email_Spam_Shield\Core', 'deactivate' ) );

// Boot the plugin.
add_action( 'plugins_loaded', function () {
    AI_Email_Spam_Shield\Core::get_instance();
    AI_Email_Spam_Shield\Updater::get_instance();
    if ( is_admin() ) {
        AI_Email_Spam_Shield\Admin::get_instance();
    }
} );
