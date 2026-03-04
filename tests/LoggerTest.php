<?php
/**
 * PHPUnit-discoverable entry point for Logger tests.
 *
 * WordPress naming convention uses test-logger.php (see tests/test-logger.php).
 * PHPUnit 10 requires the file name to match the class name (LoggerTest.php).
 * This file re-declares the same tests under the PHPUnit-compatible name.
 */
use Brain\Monkey;
use PHPUnit\Framework\TestCase;
use AI_Email_Spam_Shield\Logger;

class LoggerTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_prepare_log_data_returns_correct_structure(): void {
        $data = Logger::prepare_log_data(
            subject:     'Test Subject',
            sender:      'test@example.com',
            ai_score:    0.92,
            rule_score:  0.45,
            final_score: 0.77,
            blocked:     true,
            ip:          '127.0.0.1'
        );

        $this->assertIsArray( $data );
        $this->assertArrayHasKey( 'email_subject', $data );
        $this->assertArrayHasKey( 'email_sender', $data );
        $this->assertArrayHasKey( 'ai_score', $data );
        $this->assertArrayHasKey( 'rule_score', $data );
        $this->assertArrayHasKey( 'final_score', $data );
        $this->assertArrayHasKey( 'blocked', $data );
        $this->assertArrayHasKey( 'ip_address', $data );
    }

    public function test_prepare_log_data_sets_blocked_as_integer(): void {
        $data = Logger::prepare_log_data(
            subject:     'Hello',
            sender:      'test@example.com',
            ai_score:    0.9,
            rule_score:  0.4,
            final_score: 0.7,
            blocked:     true,
            ip:          '127.0.0.1'
        );
        $this->assertEquals( 1, $data['blocked'] );
    }

    public function test_prepare_log_data_handles_null_ai_score(): void {
        $data = Logger::prepare_log_data(
            subject:     'Hello',
            sender:      'a@b.com',
            ai_score:    null,
            rule_score:  0.3,
            final_score: 0.3,
            blocked:     false,
            ip:          '10.0.0.1'
        );

        $this->assertNull( $data['ai_score'] );
        $this->assertEquals( 0, $data['blocked'] );
    }

    public function test_prepare_log_data_rounds_scores_to_4_decimal_places(): void {
        $data = Logger::prepare_log_data(
            subject:     'Test',
            sender:      'a@b.com',
            ai_score:    0.123456789,
            rule_score:  0.987654321,
            final_score: 0.555555555,
            blocked:     false,
            ip:          '1.2.3.4'
        );

        $this->assertEquals( 0.1235, $data['ai_score'] );
        $this->assertEquals( 0.9877, $data['rule_score'] );
        $this->assertEquals( 0.5556, $data['final_score'] );
    }

    public function test_prepare_log_data_sanitizes_subject(): void {
        $data = Logger::prepare_log_data(
            subject:     '<script>alert(1)</script>Hello',
            sender:      'test@example.com',
            ai_score:    null,
            rule_score:  0.3,
            final_score: 0.3,
            blocked:     false,
            ip:          '127.0.0.1'
        );

        $this->assertStringNotContainsString( '<script>', $data['email_subject'] );
    }
}
