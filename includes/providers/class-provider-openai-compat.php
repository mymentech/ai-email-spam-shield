<?php
/**
 * Provider_OpenAI_Compat — any OpenAI-compatible API endpoint.
 *
 * @package AI_Email_Spam_Shield
 */

namespace AI_Email_Spam_Shield;

defined( 'ABSPATH' ) || exit;

class Provider_OpenAI_Compat extends Provider_LLM_Base {

    protected function get_endpoint(): string {
        $base = rtrim( $this->options['openai_compat_url'] ?? '', '/' );
        if ( empty( $base ) ) {
            return '';
        }
        return $base . '/chat/completions';
    }

    protected function get_headers(): array {
        $headers = [ 'Content-Type' => 'application/json' ];
        $key     = $this->options['openai_compat_key'] ?? '';
        if ( ! empty( $key ) ) {
            $headers['Authorization'] = 'Bearer ' . $key;
        }
        return $headers;
    }

    protected function get_request_body( string $prompt ): array {
        return [
            'model'      => $this->options['openai_compat_model'] ?? '',
            'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
            'max_tokens' => 50,
        ];
    }

    protected function extract_text( array $data ): ?string {
        return $data['choices'][0]['message']['content'] ?? null;
    }
}
