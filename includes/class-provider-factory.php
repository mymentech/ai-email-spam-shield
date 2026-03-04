<?php
/**
 * Provider_Factory — creates the active AI provider from settings.
 *
 * @package AI_Email_Spam_Shield
 */

namespace AI_Email_Spam_Shield;

defined( 'ABSPATH' ) || exit;

class Provider_Factory {

    /**
     * Build and return the configured AI provider instance.
     */
    public static function make(): Provider_Interface {
        $options  = get_option( 'aiess_settings', [] );
        $provider = $options['ai_provider'] ?? 'self_hosted';

        return match ( $provider ) {
            'openai'        => new Provider_OpenAI( $options ),
            'claude'        => new Provider_Claude( $options ),
            'gemini'        => new Provider_Gemini( $options ),
            'groq'          => new Provider_Groq( $options ),
            'cohere'        => new Provider_Cohere( $options ),
            'deepseek'      => new Provider_DeepSeek( $options ),
            'ollama'        => new Provider_Ollama( $options ),
            'openai_compat' => new Provider_OpenAI_Compat( $options ),
            default         => new Provider_Self_Hosted( $options ),
        };
    }
}
