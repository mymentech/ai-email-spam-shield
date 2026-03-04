<?php
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use AI_Email_Spam_Shield\Provider_OpenAI;
use AI_Email_Spam_Shield\Provider_Groq;
use AI_Email_Spam_Shield\Provider_DeepSeek;

class ProviderOpenAITest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function mock_successful_post( string $content ): void {
        $response_body = json_encode( [
            'choices' => [ [ 'message' => [ 'content' => $content ] ] ],
        ] );
        Functions\expect( 'wp_json_encode' )->once()->andReturnUsing( 'json_encode' );
        Functions\expect( 'wp_remote_post' )->once()->andReturn( [ 'response' => [ 'code' => 200 ] ] );
        Functions\expect( 'is_wp_error' )->once()->andReturn( false );
        Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
        Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( $response_body );
    }

    public function test_openai_returns_null_when_no_key(): void {
        $provider = new Provider_OpenAI( [] );
        $this->assertNull( $provider->get_score( 'Sub', 'Body' ) );
    }

    public function test_openai_returns_score_from_valid_response(): void {
        $this->mock_successful_post( '{"spam_probability": 0.92}' );
        $provider = new Provider_OpenAI( [ 'openai_key' => 'sk-test', 'openai_model' => 'gpt-4o-mini' ] );
        $result   = $provider->get_score( 'Win $1000 NOW', 'Click here FREE' );
        $this->assertEqualsWithDelta( 0.92, $result, 0.001 );
    }

    public function test_openai_returns_null_on_wp_error(): void {
        Functions\expect( 'wp_json_encode' )->once()->andReturnUsing( 'json_encode' );
        Functions\expect( 'wp_remote_post' )->once()->andReturn( new WP_Error() );
        Functions\expect( 'is_wp_error' )->once()->andReturn( true );

        $provider = new Provider_OpenAI( [ 'openai_key' => 'sk-test' ] );
        $this->assertNull( $provider->get_score( 'Sub', 'Body' ) );
    }

    public function test_openai_returns_null_on_non_200(): void {
        Functions\expect( 'wp_json_encode' )->once()->andReturnUsing( 'json_encode' );
        Functions\expect( 'wp_remote_post' )->once()->andReturn( [ 'response' => [ 'code' => 401 ] ] );
        Functions\expect( 'is_wp_error' )->once()->andReturn( false );
        Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 401 );

        $provider = new Provider_OpenAI( [ 'openai_key' => 'sk-bad' ] );
        $this->assertNull( $provider->get_score( 'Sub', 'Body' ) );
    }

    public function test_groq_returns_score_from_valid_response(): void {
        $this->mock_successful_post( '{"spam_probability": 0.75}' );
        $provider = new Provider_Groq( [ 'groq_key' => 'gsk_test', 'groq_model' => 'llama-3.1-8b-instant' ] );
        $result   = $provider->get_score( 'Sub', 'Body' );
        $this->assertEqualsWithDelta( 0.75, $result, 0.001 );
    }

    public function test_deepseek_returns_score_from_valid_response(): void {
        $this->mock_successful_post( '{"spam_probability": 0.60}' );
        $provider = new Provider_DeepSeek( [ 'deepseek_key' => 'ds_test', 'deepseek_model' => 'deepseek-chat' ] );
        $result   = $provider->get_score( 'Sub', 'Body' );
        $this->assertEqualsWithDelta( 0.60, $result, 0.001 );
    }

    public function test_parse_score_handles_embedded_json(): void {
        // LLMs sometimes wrap JSON in text
        $this->mock_successful_post( 'Here is the result: {"spam_probability": 0.88} done.' );
        $provider = new Provider_OpenAI( [ 'openai_key' => 'sk-test' ] );
        $result   = $provider->get_score( 'Sub', 'Body' );
        $this->assertEqualsWithDelta( 0.88, $result, 0.001 );
    }
}
