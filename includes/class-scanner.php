<?php
/**
 * Scanner — orchestrates AI + rule-based scoring and returns final verdict.
 *
 * @package AI_Email_Spam_Shield
 */

namespace AI_Email_Spam_Shield;

defined( 'ABSPATH' ) || exit;

class Scanner {

    /**
     * Full scan pipeline for one email.
     *
     * @param string $subject  Email subject.
     * @param string $body     Email body.
     * @param string $sender   Sender email address.
     * @param string $ip       Sender IP address.
     * @return array{
     *   ai_score:    float|null,
     *   rule_score:  float,
     *   final_score: float,
     *   blocked:     bool
     * }
     */
    public static function scan( string $subject, string $body, string $sender, string $ip ): array {
        $options     = get_option( 'aiess_settings', array() );
        $threshold   = (float) ( $options['threshold']   ?? 0.80 );
        $ai_weight   = (float) ( $options['ai_weight']   ?? 0.7 );
        $rule_weight = (float) ( $options['rule_weight']  ?? 0.3 );

        // Transient cache — avoid duplicate API calls for identical content.
        $cache_key = 'aiess_scan_' . md5( $subject . $body );
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        // Hard-block check — bypasses AI weight for signals the BERT model misses
        // (e.g. sexual content, darknet phrases). No API call needed.
        if ( Rules_Engine::has_hard_block( $subject, $body ) ) {
            $rule_score = Rules_Engine::score( $subject, $body, $ip );
            $result     = array(
                'ai_score'    => null,
                'hard_blocked' => true,
                'rule_score'  => $rule_score,
                'final_score' => 1.0,
                'blocked'     => true,
            );
            set_transient( $cache_key, $result, 5 * MINUTE_IN_SECONDS );
            return $result;
        }

        // Rule-based score — pure PHP, always available.
        $rule_score = Rules_Engine::score( $subject, $body, $ip );

        // AI score — remote API, may fail/timeout.
        $ai_score = self::fetch_ai_score( $subject, $body );

        // Weighted final score.
        $final_score = self::compute_final_score( $ai_score, $rule_score, $ai_weight, $rule_weight );

        $result = array(
            'ai_score'    => $ai_score,
            'rule_score'  => $rule_score,
            'final_score' => $final_score,
            'blocked'     => self::is_spam( $final_score, $threshold ),
        );

        // Cache result for 5 minutes.
        set_transient( $cache_key, $result, 5 * MINUTE_IN_SECONDS );

        return $result;
    }

    /**
     * Call the AI spam detection microservice.
     *
     * @param string $subject  Email subject.
     * @param string $body     Email body.
     * @return float|null      Spam probability 0–1, or null on failure/timeout.
     */
    public static function fetch_ai_score( string $subject, string $body ): ?float {
        $options = get_option( 'aiess_settings', array() );
        // Env vars (from .env or server config) take precedence over DB settings.
        $api_url = esc_url_raw( getenv( 'AIESS_API_URL' ) ?: ( $options['api_url'] ?? 'http://spam-api:8000/predict' ) );
        $api_key = sanitize_text_field( getenv( 'AIESS_API_KEY' ) ?: ( $options['api_key'] ?? '' ) );

        if ( empty( $api_url ) ) {
            return null;
        }

        $headers = array( 'Content-Type' => 'application/json' );
        if ( ! empty( $api_key ) ) {
            $headers['Authorization'] = 'Bearer ' . $api_key;
        }

        $response = wp_remote_post(
            $api_url,
            array(
                'timeout' => 3,
                'headers' => $headers,
                'body'    => wp_json_encode( array( 'text' => $subject . ' ' . $body ) ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            return null;
        }

        return self::parse_ai_response( wp_remote_retrieve_body( $response ) );
    }

    /**
     * Parse the raw JSON response from the AI API.
     *
     * @param string $body  Raw HTTP response body.
     * @return float|null   Spam probability, or null if response is invalid.
     */
    public static function parse_ai_response( string $body ): ?float {
        $data = json_decode( $body, true );
        if ( ! is_array( $data ) || ! isset( $data['spam_probability'] ) ) {
            return null;
        }
        $prob = (float) $data['spam_probability'];
        return ( $prob >= 0.0 && $prob <= 1.0 ) ? $prob : null;
    }

    /**
     * Apply weighted formula to produce the final score.
     *
     * @param float|null $ai_score    AI probability (null = AI unavailable).
     * @param float      $rule_score  Rule-based score 0–1.
     * @param float      $ai_weight   Weight for AI component.
     * @param float      $rule_weight Weight for rule component.
     * @return float     Final score capped at 1.0.
     */
    public static function compute_final_score(
        ?float $ai_score,
        float $rule_score,
        float $ai_weight,
        float $rule_weight
    ): float {
        if ( null === $ai_score ) {
            // Fail-safe: AI unavailable — use rule score only.
            return min( 1.0, $rule_score );
        }
        return min( 1.0, ( $ai_score * $ai_weight ) + ( $rule_score * $rule_weight ) );
    }

    /**
     * Determine whether a final score meets or exceeds the spam threshold.
     *
     * @param float $final_score  Computed final score.
     * @param float $threshold    Configured spam threshold.
     * @return bool               True if the email should be blocked.
     */
    public static function is_spam( float $final_score, float $threshold ): bool {
        return $final_score >= $threshold;
    }
}
