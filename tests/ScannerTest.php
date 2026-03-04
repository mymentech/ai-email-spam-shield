<?php
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use AI_Email_Spam_Shield\Scanner;

class ScannerTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_weighted_score_with_ai(): void {
        // (0.9 * 0.7) + (0.4 * 0.3) = 0.63 + 0.12 = 0.75
        $final = Scanner::compute_final_score( 0.9, 0.4, 0.7, 0.3 );
        $this->assertEqualsWithDelta( 0.75, $final, 0.001 );
    }

    public function test_fallback_score_without_ai(): void {
        // Fail-safe: AI unavailable, use rule score only
        $final = Scanner::compute_final_score( null, 0.6, 0.7, 0.3 );
        $this->assertEqualsWithDelta( 0.6, $final, 0.001 );
    }

    public function test_score_above_threshold_is_spam(): void {
        $this->assertTrue( Scanner::is_spam( 0.85, 0.80 ) );
    }

    public function test_score_below_threshold_is_not_spam(): void {
        $this->assertFalse( Scanner::is_spam( 0.75, 0.80 ) );
    }

    public function test_score_equal_to_threshold_is_spam(): void {
        $this->assertTrue( Scanner::is_spam( 0.80, 0.80 ) );
    }

    public function test_parse_ai_response_valid(): void {
        $body   = json_encode( array( 'spam_probability' => 0.92, 'label' => 'spam' ) );
        $result = Scanner::parse_ai_response( $body );
        $this->assertEqualsWithDelta( 0.92, $result, 0.001 );
    }

    public function test_parse_ai_response_invalid_json_returns_null(): void {
        $result = Scanner::parse_ai_response( 'not json at all' );
        $this->assertNull( $result );
    }

    public function test_parse_ai_response_missing_key_returns_null(): void {
        $body   = json_encode( array( 'label' => 'spam' ) );
        $result = Scanner::parse_ai_response( $body );
        $this->assertNull( $result );
    }

    public function test_parse_ai_response_out_of_range_returns_null(): void {
        $body   = json_encode( array( 'spam_probability' => 1.5, 'label' => 'spam' ) );
        $result = Scanner::parse_ai_response( $body );
        $this->assertNull( $result );
    }

    public function test_compute_final_score_capped_at_one(): void {
        $final = Scanner::compute_final_score( 1.0, 1.0, 0.7, 0.3 );
        $this->assertLessThanOrEqual( 1.0, $final );
    }

    public function test_compute_final_score_zero_inputs(): void {
        $final = Scanner::compute_final_score( 0.0, 0.0, 0.7, 0.3 );
        $this->assertEqualsWithDelta( 0.0, $final, 0.001 );
    }
}
