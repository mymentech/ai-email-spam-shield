<?php
use Brain\Monkey;
use PHPUnit\Framework\TestCase;
use AI_Email_Spam_Shield\Rules_Engine;

class RulesEngineTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_clean_message_scores_low(): void {
        $score = Rules_Engine::score( 'Hello there', 'How can I help you today?', '127.0.0.1' );
        $this->assertLessThan( 0.2, $score );
    }

    public function test_detects_too_many_urls(): void {
        $body  = 'Check http://a.com and http://b.com and http://c.com and http://d.com';
        $score = Rules_Engine::score( 'Links', $body, '127.0.0.1' );
        $this->assertGreaterThanOrEqual( 0.25, $score );
    }

    public function test_detects_spam_phrases(): void {
        $body  = 'Buy now and get free money with this crypto investment opportunity!';
        $score = Rules_Engine::score( 'Offer', $body, '127.0.0.1' );
        $this->assertGreaterThanOrEqual( 0.20, $score );
    }

    public function test_detects_short_message(): void {
        $score = Rules_Engine::score( 'Hi', 'Hi', '127.0.0.1' );
        $this->assertGreaterThanOrEqual( 0.15, $score );
    }

    public function test_detects_excessive_uppercase(): void {
        $body  = 'THIS IS A GREAT DEAL YOU SHOULD NOT MISS OUT ON RIGHT NOW PLEASE';
        $score = Rules_Engine::score( 'URGENT', $body, '127.0.0.1' );
        $this->assertGreaterThanOrEqual( 0.15, $score );
    }

    public function test_detects_suspicious_tld(): void {
        $body  = 'Visit http://example.xyz for details';
        $score = Rules_Engine::score( 'Visit', $body, '127.0.0.1' );
        $this->assertGreaterThanOrEqual( 0.20, $score );
    }

    public function test_detects_repeated_chars(): void {
        $body  = 'Amazing deal!!!! act now $$$$ today';
        $score = Rules_Engine::score( 'Deal', $body, '127.0.0.1' );
        $this->assertGreaterThanOrEqual( 0.10, $score );
    }

    public function test_custom_spam_phrase_increases_score(): void {
        Brain\Monkey\Functions\when( 'get_option' )
            ->justReturn( array( 'spam' => array( 'buy my widget' ), 'hard_block' => array() ) );

        $score = Rules_Engine::score( 'offer', 'please buy my widget today', '127.0.0.1' );
        $this->assertGreaterThanOrEqual( 0.20, $score );
    }

    public function test_custom_hard_block_phrase_triggers_has_hard_block(): void {
        Brain\Monkey\Functions\when( 'get_option' )
            ->justReturn( array( 'spam' => array(), 'hard_block' => array( 'forbidden phrase' ) ) );

        $this->assertTrue( Rules_Engine::has_hard_block( 'subject', 'this contains forbidden phrase here' ) );
    }

    public function test_empty_custom_phrases_has_no_effect(): void {
        Brain\Monkey\Functions\when( 'get_option' )
            ->justReturn( array( 'spam' => array(), 'hard_block' => array() ) );

        $score = Rules_Engine::score( 'Hello', 'How can I help you today?', '127.0.0.1' );
        $this->assertLessThan( 0.2, $score );
    }

    public function test_missing_custom_phrases_option_has_no_effect(): void {
        Brain\Monkey\Functions\when( 'get_option' )
            ->justReturn( array() );

        $score = Rules_Engine::score( 'Hello', 'How can I help you today?', '127.0.0.1' );
        $this->assertLessThan( 0.2, $score );
    }

    public function test_score_capped_at_one(): void {
        $body  = 'BUY NOW FREE MONEY CRYPTO http://a.ru http://b.xyz http://c.click http://d.top $$$$ !!!!!';
        $score = Rules_Engine::score( 'URGENT DEAL!!!!', $body, '127.0.0.1' );
        $this->assertLessThanOrEqual( 1.0, $score );
        $this->assertIsFloat( $score );
    }

    public function test_url_count_check_alone(): void {
        $this->assertEquals( 0.25, Rules_Engine::check_url_count( 'http://a.com http://b.com http://c.com http://d.com' ) );
        $this->assertEquals( 0.0,  Rules_Engine::check_url_count( 'Just one http://example.com link' ) );
    }

    public function test_uppercase_ratio_check(): void {
        $this->assertEquals( 0.15, Rules_Engine::check_uppercase_ratio( 'ALL CAPS SHOUTING HERE' ) );
        $this->assertEquals( 0.0,  Rules_Engine::check_uppercase_ratio( 'normal lowercase message here' ) );
    }

    public function test_repeated_chars_check(): void {
        $this->assertEquals( 0.10, Rules_Engine::check_repeated_chars( 'Hello!!!!' ) );
        $this->assertEquals( 0.0,  Rules_Engine::check_repeated_chars( 'Hello!' ) );
    }
}
