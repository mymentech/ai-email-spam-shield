<?php
/**
 * GitHub auto-updater for AI Email Spam Shield.
 *
 * Uses YahnisElsts/plugin-update-checker to check for new releases
 * on GitHub and enable one-click updates from wp-admin.
 *
 * @package AI_Email_Spam_Shield
 */

namespace AI_Email_Spam_Shield;

defined( 'ABSPATH' ) || exit;

// Parsedown must be loaded before PUC parses GitHub release notes.
require_once AIESS_PLUGIN_DIR . 'lib/plugin-update-checker/vendor/Parsedown.php';
require_once AIESS_PLUGIN_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

class Updater {

    private static ?self $instance = null;

    private function __construct() {
        $checker = PucFactory::buildUpdateChecker(
            'https://github.com/mymentech/ai-email-spam-shield/',
            AIESS_PLUGIN_FILE,
            'ai-email-spam-shield'
        );

        // Tell PUC to use the manually attached release asset zip,
        // not the auto-generated GitHub source archive.
        $checker->getVcsApi()->enableReleaseAssets();
    }

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function reset_instance(): void {
        self::$instance = null;
    }
}
