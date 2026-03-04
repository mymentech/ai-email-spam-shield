<?php
/**
 * Provider_DeepSeek — DeepSeek API (OpenAI-compatible).
 *
 * @package AI_Email_Spam_Shield
 */

namespace AI_Email_Spam_Shield;

defined( 'ABSPATH' ) || exit;

class Provider_DeepSeek extends Provider_LLM_Base {

    protected function get_endpoint(): string {
        if ( empty( $this->options['deepseek_key'] ) ) {
            return '';
        }
        return 'https://api.deepseek.com/v1/chat/completions';
    }

    protected function get_headers(): array {
        return [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . ( $this->options['deepseek_key'] ?? '' ),
        ];
    }

    protected function get_request_body( string $prompt ): array {
        return [
            'model'           => $this->options['deepseek_model'] ?? 'deepseek-chat',
            'messages'        => [ [ 'role' => 'user', 'content' => $prompt ] ],
            'response_format' => [ 'type' => 'json_object' ],
            'max_tokens'      => 50,
        ];
    }

    protected function extract_text( array $data ): ?string {
        return $data['choices'][0]['message']['content'] ?? null;
    }
}
