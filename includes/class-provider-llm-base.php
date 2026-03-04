<?php
/**
 * Provider_LLM_Base — abstract base for prompt-based LLM providers.
 *
 * Shared logic: builds the classification prompt, POSTs to the API,
 * parses the returned JSON for spam_probability.
 *
 * Subclasses implement:
 *   get_endpoint()              — full API URL (return '' to disable)
 *   get_headers()               — HTTP headers array
 *   get_request_body( $prompt ) — array for wp_json_encode
 *   extract_text( $data )       — pull raw text out of parsed JSON response
 *
 * @package AI_Email_Spam_Shield
 */

namespace AI_Email_Spam_Shield;

defined( 'ABSPATH' ) || exit;

abstract class Provider_LLM_Base implements Provider_Interface {

    protected array $options;

    public function __construct( array $options ) {
        $this->options = $options;
    }

    // -------------------------------------------------------------------------
    // Abstract — implemented by each concrete provider
    // -------------------------------------------------------------------------

    abstract protected function get_endpoint(): string;
    abstract protected function get_headers(): array;
    abstract protected function get_request_body( string $prompt ): array;

    /**
     * Extract the raw text from the provider's decoded JSON response.
     *
     * @param array $data  Decoded response body.
     * @return string|null
     */
    abstract protected function extract_text( array $data ): ?string;

    // -------------------------------------------------------------------------
    // Shared
    // -------------------------------------------------------------------------

    public function get_score( string $subject, string $body ): ?float {
        $endpoint = $this->get_endpoint();
        if ( empty( $endpoint ) ) {
            return null;
        }

        $prompt   = $this->build_prompt( $subject, $body );
        $response = wp_remote_post(
            $endpoint,
            [
                'timeout' => 5,
                'headers' => $this->get_headers(),
                'body'    => wp_json_encode( $this->get_request_body( $prompt ) ),
            ]
        );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            return null;
        }

        $raw  = wp_remote_retrieve_body( $response );
        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) ) {
            return null;
        }

        $text = $this->extract_text( $data );
        if ( null === $text ) {
            return null;
        }

        return $this->parse_json_score( $text );
    }

    protected function build_prompt( string $subject, string $body ): string {
        return 'You are a spam detection system. Analyze this email and respond ONLY with valid JSON in this exact format: {"spam_probability": 0.95}' . "\n"
            . 'Use a value from 0.0 (definitely not spam) to 1.0 (definitely spam).' . "\n\n"
            . 'Subject: ' . $subject . "\n"
            . 'Body: ' . $body;
    }

    /**
     * Extract spam_probability from a JSON string (which may contain surrounding text).
     */
    protected function parse_json_score( string $text ): ?float {
        // Try direct decode first.
        $decoded = json_decode( $text, true );
        if ( is_array( $decoded ) && isset( $decoded['spam_probability'] ) ) {
            $prob = (float) $decoded['spam_probability'];
            return ( $prob >= 0.0 && $prob <= 1.0 ) ? $prob : null;
        }
        // Fall back: find first {...} block.
        if ( preg_match( '/\{[^{}]+\}/', $text, $m ) ) {
            $inner = json_decode( $m[0], true );
            if ( is_array( $inner ) && isset( $inner['spam_probability'] ) ) {
                $prob = (float) $inner['spam_probability'];
                return ( $prob >= 0.0 && $prob <= 1.0 ) ? $prob : null;
            }
        }
        return null;
    }
}
