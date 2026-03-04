<?php
/**
 * Provider_Gemini — Google Gemini generateContent API.
 *
 * @package AI_Email_Spam_Shield
 */

namespace AI_Email_Spam_Shield;

defined( 'ABSPATH' ) || exit;

class Provider_Gemini extends Provider_LLM_Base {

    protected function get_endpoint(): string {
        $key   = $this->options['gemini_key'] ?? '';
        $model = $this->options['gemini_model'] ?? 'gemini-1.5-flash';
        if ( empty( $key ) ) {
            return '';
        }
        return 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $key );
    }

    protected function get_headers(): array {
        return [ 'Content-Type' => 'application/json' ];
    }

    protected function get_request_body( string $prompt ): array {
        return [
            'contents' => [
                [ 'role' => 'user', 'parts' => [ [ 'text' => $prompt ] ] ],
            ],
        ];
    }

    protected function extract_text( array $data ): ?string {
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }
}
