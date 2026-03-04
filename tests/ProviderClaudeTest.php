<?php
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use AI_Email_Spam_Shield\Provider_Claude;

class ProviderClaudeTest extends TestCase {

    protected function setUp(): void { parent::setUp(); Monkey\setUp(); }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_returns_null_when_no_key(): void {
        $provider = new Provider_Claude( [] );
        $this->assertNull( $provider->get_score( 'Sub', 'Body' ) );
    }

    public function test_returns_score_from_valid_response(): void {
        $response_body = json_encode( [
            'content' => [ [ 'type' => 'text', 'text' => '{"spam_probability": 0.95}' ] ],
        ] );
        Functions\expect( 'wp_json_encode' )->once()->andReturnUsing( 'json_encode' );
        Functions\expect( 'wp_remote_post' )->once()->andReturn( [ 'response' => [ 'code' => 200 ] ] );
        Functions\expect( 'is_wp_error' )->once()->andReturn( false );
        Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
        Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( $response_body );

        $provider = new Provider_Claude( [ 'claude_key' => 'sk-ant-test', 'claude_model' => 'claude-haiku-4-5-20251001' ] );
        $result   = $provider->get_score( 'Free money', 'Click now' );
        $this->assertEqualsWithDelta( 0.95, $result, 0.001 );
    }

    public function test_request_uses_correct_anthropic_headers(): void {
        Functions\expect( 'wp_json_encode' )->once()->andReturnUsing( 'json_encode' );
        Functions\expect( 'wp_remote_post' )
            ->once()
            ->with(
                'https://api.anthropic.com/v1/messages',
                \Mockery::on( function ( $args ) {
                    return isset( $args['headers']['x-api-key'] )
                        && isset( $args['headers']['anthropic-version'] );
                } )
            )
            ->andReturn( new WP_Error() );
        Functions\expect( 'is_wp_error' )->once()->andReturn( true );

        $provider = new Provider_Claude( [ 'claude_key' => 'sk-ant-test' ] );
        $provider->get_score( 'Sub', 'Body' );
        $this->addToAssertionCount( 1 ); // Mockery::on() constraint acts as assertion
    }
}
