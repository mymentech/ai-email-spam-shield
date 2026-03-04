<?php
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use AI_Email_Spam_Shield\Provider_Cohere;
use AI_Email_Spam_Shield\Provider_Ollama;
use AI_Email_Spam_Shield\Provider_OpenAI_Compat;

class ProviderRemainingTest extends TestCase {

    protected function setUp(): void { parent::setUp(); Monkey\setUp(); }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    private function mock_success( string $body ): void {
        Functions\expect( 'wp_json_encode' )->once()->andReturnUsing( 'json_encode' );
        Functions\expect( 'wp_remote_post' )->once()->andReturn( [ 'response' => [ 'code' => 200 ] ] );
        Functions\expect( 'is_wp_error' )->once()->andReturn( false );
        Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
        Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( $body );
    }

    // --- Cohere ---

    public function test_cohere_returns_null_when_no_key(): void {
        $provider = new Provider_Cohere( [] );
        $this->assertNull( $provider->get_score( 'Sub', 'Body' ) );
    }

    public function test_cohere_returns_score_from_valid_response(): void {
        $response_body = json_encode( [
            'message' => [ 'content' => [ [ 'type' => 'text', 'text' => '{"spam_probability": 0.82}' ] ] ],
        ] );
        $this->mock_success( $response_body );

        $provider = new Provider_Cohere( [ 'cohere_key' => 'co-test', 'cohere_model' => 'command-r' ] );
        $result   = $provider->get_score( 'Sub', 'Body' );
        $this->assertEqualsWithDelta( 0.82, $result, 0.001 );
    }

    // --- Ollama ---

    public function test_ollama_returns_null_when_no_model(): void {
        $provider = new Provider_Ollama( [ 'ollama_url' => 'http://localhost:11434' ] );
        $this->assertNull( $provider->get_score( 'Sub', 'Body' ) );
    }

    public function test_ollama_returns_score_from_valid_response(): void {
        $response_body = json_encode( [ 'response' => '{"spam_probability": 0.65}', 'done' => true ] );
        $this->mock_success( $response_body );

        $provider = new Provider_Ollama( [ 'ollama_url' => 'http://localhost:11434', 'ollama_model' => 'llama3' ] );
        $result   = $provider->get_score( 'Sub', 'Body' );
        $this->assertEqualsWithDelta( 0.65, $result, 0.001 );
    }

    // --- OpenAI-compatible ---

    public function test_openai_compat_returns_null_when_no_url(): void {
        $provider = new Provider_OpenAI_Compat( [] );
        $this->assertNull( $provider->get_score( 'Sub', 'Body' ) );
    }

    public function test_openai_compat_returns_score_from_valid_response(): void {
        $response_body = json_encode( [
            'choices' => [ [ 'message' => [ 'content' => '{"spam_probability": 0.70}' ] ] ],
        ] );
        $this->mock_success( $response_body );

        $provider = new Provider_OpenAI_Compat( [
            'openai_compat_url'   => 'http://localhost:1234/v1',
            'openai_compat_key'   => 'local',
            'openai_compat_model' => 'phi-3',
        ] );
        $result = $provider->get_score( 'Sub', 'Body' );
        $this->assertEqualsWithDelta( 0.70, $result, 0.001 );
    }
}
