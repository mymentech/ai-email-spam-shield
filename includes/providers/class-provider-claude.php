<?php
/**
 * Provider_Claude — Anthropic Claude Messages API.
 *
 * @package AI_Email_Spam_Shield
 */

namespace AI_Email_Spam_Shield;

defined( 'ABSPATH' ) || exit;

class Provider_Claude extends Provider_LLM_Base {

    protected function get_endpoint(): string {
        if ( empty( $this->options['claude_key'] ) ) {
            return '';
        }
        return 'https://api.anthropic.com/v1/messages';
    }

    protected function get_headers(): array {
        return [
            'Content-Type'      => 'application/json',
            'x-api-key'         => $this->options['claude_key'] ?? '',
            'anthropic-version' => '2023-06-01',
        ];
    }

    protected function get_request_body( string $prompt ): array {
        return [
            'model'      => $this->options['claude_model'] ?? 'claude-haiku-4-5-20251001',
            'max_tokens' => 50,
            'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
        ];
    }

    protected function extract_text( array $data ): ?string {
        return $data['content'][0]['text'] ?? null;
    }
}
