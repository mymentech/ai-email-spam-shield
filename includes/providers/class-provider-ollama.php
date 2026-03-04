<?php
/**
 * Provider_Ollama — Ollama local inference API.
 *
 * @package AI_Email_Spam_Shield
 */

namespace AI_Email_Spam_Shield;

defined( 'ABSPATH' ) || exit;

class Provider_Ollama extends Provider_LLM_Base {

    protected function get_endpoint(): string {
        $model = $this->options['ollama_model'] ?? '';
        if ( empty( $model ) ) {
            return '';
        }
        $base = rtrim( $this->options['ollama_url'] ?? 'http://localhost:11434', '/' );
        return $base . '/api/generate';
    }

    protected function get_headers(): array {
        return [ 'Content-Type' => 'application/json' ];
    }

    protected function get_request_body( string $prompt ): array {
        return [
            'model'  => $this->options['ollama_model'] ?? '',
            'prompt' => $prompt,
            'stream' => false,
            'format' => 'json',
        ];
    }

    protected function extract_text( array $data ): ?string {
        return $data['response'] ?? null;
    }
}
