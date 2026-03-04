<?php
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use AI_Email_Spam_Shield\Provider_Gemini;

class ProviderGeminiTest extends TestCase {

    protected function setUp(): void { parent::setUp(); Monkey\setUp(); }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_returns_null_when_no_key(): void {
        $provider = new Provider_Gemini( [] );
        $this->assertNull( $provider->get_score( 'Sub', 'Body' ) );
    }

    public function test_returns_score_from_valid_response(): void {
        $response_body = json_encode( [
            'candidates' => [
                [ 'content' => [ 'parts' => [ [ 'text' => '{"spam_probability": 0.78}' ] ] ] ],
            ],
        ] );
        Functions\expect( 'wp_json_encode' )->once()->andReturnUsing( 'json_encode' );
        Functions\expect( 'wp_remote_post' )->once()->andReturn( [ 'response' => [ 'code' => 200 ] ] );
        Functions\expect( 'is_wp_error' )->once()->andReturn( false );
        Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
        Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( $response_body );

        $provider = new Provider_Gemini( [ 'gemini_key' => 'AIza-test', 'gemini_model' => 'gemini-1.5-flash' ] );
        $result   = $provider->get_score( 'Sub', 'Body' );
        $this->assertEqualsWithDelta( 0.78, $result, 0.001 );
    }

    public function test_endpoint_contains_model_and_api_key(): void {
        Functions\expect( 'wp_json_encode' )->once()->andReturnUsing( 'json_encode' );
        Functions\expect( 'wp_remote_post' )
            ->once()
            ->with(
                \Mockery::on( function ( $url ) {
                    return str_contains( $url, 'gemini-1.5-flash' )
                        && str_contains( $url, 'key=AIza-test' );
                } ),
                \Mockery::any()
            )
            ->andReturn( new WP_Error() );
        Functions\expect( 'is_wp_error' )->once()->andReturn( true );

        $provider = new Provider_Gemini( [ 'gemini_key' => 'AIza-test', 'gemini_model' => 'gemini-1.5-flash' ] );
        $provider->get_score( 'Sub', 'Body' );
        $this->addToAssertionCount( 1 ); // Mockery::on() constraint acts as assertion
    }
}
