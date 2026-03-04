<?php
/**
 * Provider_Self_Hosted — calls the local FastAPI BERT microservice.
 *
 * @package AI_Email_Spam_Shield
 */

namespace AI_Email_Spam_Shield;

defined( 'ABSPATH' ) || exit;

class Provider_Self_Hosted implements Provider_Interface {

    private array $options;

    public function __construct( array $options ) {
        $this->options = $options;
    }

    public function get_score( string $subject, string $body ): ?float {
        $url = esc_url_raw( $this->options['self_hosted_url'] ?? '' );
        if ( empty( $url ) ) {
            return null;
        }

        $key     = sanitize_text_field( $this->options['self_hosted_key'] ?? '' );
        $headers = [ 'Content-Type' => 'application/json' ];
        if ( ! empty( $key ) ) {
            $headers['Authorization'] = 'Bearer ' . $key;
        }

        $response = wp_remote_post(
            $url,
            [
                'timeout' => 5,
                'headers' => $headers,
                'body'    => wp_json_encode( [ 'text' => $subject . ' ' . $body ] ),
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
        if ( ! is_array( $data ) || ! isset( $data['spam_probability'] ) ) {
            return null;
        }
        $prob = (float) $data['spam_probability'];
        return ( $prob >= 0.0 && $prob <= 1.0 ) ? $prob : null;
    }
}
