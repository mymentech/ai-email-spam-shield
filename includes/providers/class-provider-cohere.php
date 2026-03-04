<?php
/**
 * Provider_Cohere — Cohere Chat v2 API.
 *
 * @package AI_Email_Spam_Shield
 */

namespace AI_Email_Spam_Shield;

defined( 'ABSPATH' ) || exit;

class Provider_Cohere extends Provider_LLM_Base {

    protected function get_endpoint(): string {
        if ( empty( $this->options['cohere_key'] ) ) {
            return '';
        }
        return 'https://api.cohere.ai/v2/chat';
    }

    protected function get_headers(): array {
        return [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . ( $this->options['cohere_key'] ?? '' ),
        ];
    }

    protected function get_request_body( string $prompt ): array {
        return [
            'model'    => $this->options['cohere_model'] ?? 'command-r',
            'messages' => [ [ 'role' => 'user', 'content' => $prompt ] ],
        ];
    }

    protected function extract_text( array $data ): ?string {
        return $data['message']['content'][0]['text'] ?? null;
    }
}
