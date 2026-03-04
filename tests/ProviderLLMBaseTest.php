<?php
use Brain\Monkey;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Provider_LLM_Base shared methods via a minimal concrete stub.
 */
class ProviderLLMBaseTest extends TestCase {

    /** @var \AI_Email_Spam_Shield\Provider_LLM_Base */
    private $provider;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        // Minimal concrete subclass so we can test the shared methods directly.
        $this->provider = new class( [] ) extends \AI_Email_Spam_Shield\Provider_LLM_Base {
            protected function get_endpoint(): string { return ''; }
            protected function get_headers(): array { return []; }
            protected function get_request_body( string $prompt ): array { return []; }
            protected function extract_text( array $data ): ?string { return null; }
            // Expose protected methods for testing.
            public function exposed_build_prompt( string $subject, string $body ): string {
                return $this->build_prompt( $subject, $body );
            }
            public function exposed_parse_json_score( string $text ): ?float {
                return $this->parse_json_score( $text );
            }
        };
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // get_score() with empty endpoint
    // -------------------------------------------------------------------------

    public function test_get_score_returns_null_when_endpoint_is_empty(): void {
        $result = $this->provider->get_score( 'Subject', 'Body' );
        $this->assertNull( $result );
    }

    // -------------------------------------------------------------------------
    // build_prompt()
    // -------------------------------------------------------------------------

    public function test_build_prompt_contains_subject_and_body(): void {
        $prompt = $this->provider->exposed_build_prompt( 'Win money now', 'Click here' );
        $this->assertStringContainsString( 'Subject: Win money now', $prompt );
        $this->assertStringContainsString( 'Body: Click here', $prompt );
    }

    public function test_build_prompt_contains_json_format_instruction(): void {
        $prompt = $this->provider->exposed_build_prompt( 'Sub', 'Body' );
        $this->assertStringContainsString( '{"spam_probability": 0.95}', $prompt );
    }

    public function test_build_prompt_contains_range_instruction(): void {
        $prompt = $this->provider->exposed_build_prompt( 'Sub', 'Body' );
        $this->assertStringContainsString( '0.0 (definitely not spam)', $prompt );
        $this->assertStringContainsString( '1.0 (definitely spam)', $prompt );
    }

    // -------------------------------------------------------------------------
    // parse_json_score()
    // -------------------------------------------------------------------------

    public function test_parse_json_score_returns_float_from_clean_json(): void {
        $result = $this->provider->exposed_parse_json_score( '{"spam_probability": 0.93}' );
        $this->assertEqualsWithDelta( 0.93, $result, 0.001 );
    }

    public function test_parse_json_score_returns_float_from_embedded_json(): void {
        $result = $this->provider->exposed_parse_json_score( 'Sure! Here is the result: {"spam_probability": 0.72} done.' );
        $this->assertEqualsWithDelta( 0.72, $result, 0.001 );
    }

    public function test_parse_json_score_returns_null_for_missing_key(): void {
        $result = $this->provider->exposed_parse_json_score( '{"label": "spam"}' );
        $this->assertNull( $result );
    }

    public function test_parse_json_score_returns_null_for_out_of_range_value(): void {
        $result = $this->provider->exposed_parse_json_score( '{"spam_probability": 1.5}' );
        $this->assertNull( $result );
    }

    public function test_parse_json_score_returns_null_for_invalid_json(): void {
        $result = $this->provider->exposed_parse_json_score( 'not json at all' );
        $this->assertNull( $result );
    }

    public function test_parse_json_score_accepts_boundary_values(): void {
        $this->assertEqualsWithDelta( 0.0, $this->provider->exposed_parse_json_score( '{"spam_probability": 0.0}' ), 0.001 );
        $this->assertEqualsWithDelta( 1.0, $this->provider->exposed_parse_json_score( '{"spam_probability": 1.0}' ), 0.001 );
    }
}
