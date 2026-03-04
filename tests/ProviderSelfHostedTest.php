<?php
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use AI_Email_Spam_Shield\Provider_Self_Hosted;

class ProviderSelfHostedTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_returns_null_when_no_url_configured(): void {
        // options has no self_hosted_url
        $provider = new Provider_Self_Hosted( [] );
        $result   = $provider->get_score( 'Subject', 'Body' );
        $this->assertNull( $result );
    }

    public function test_returns_null_on_wp_error(): void {
        Functions\expect( 'esc_url_raw' )->once()->andReturnArg( 0 );
        Functions\expect( 'wp_json_encode' )->once()->andReturnUsing( 'json_encode' );
        Functions\expect( 'wp_remote_post' )->once()->andReturn( new WP_Error( 'http_error', 'timeout' ) );
        Functions\expect( 'is_wp_error' )->once()->andReturn( true );

        $provider = new Provider_Self_Hosted( [ 'self_hosted_url' => 'http://spam-api:8000/predict' ] );
        $result   = $provider->get_score( 'Subject', 'Body' );
        $this->assertNull( $result );
    }

    public function test_returns_null_on_non_200(): void {
        Functions\expect( 'esc_url_raw' )->once()->andReturnArg( 0 );
        Functions\expect( 'wp_json_encode' )->once()->andReturnUsing( 'json_encode' );
        Functions\expect( 'wp_remote_post' )->once()->andReturn( [ 'response' => [ 'code' => 503 ] ] );
        Functions\expect( 'is_wp_error' )->once()->andReturn( false );
        Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 503 );

        $provider = new Provider_Self_Hosted( [ 'self_hosted_url' => 'http://spam-api:8000/predict' ] );
        $result   = $provider->get_score( 'Subject', 'Body' );
        $this->assertNull( $result );
    }

    public function test_returns_score_on_valid_response(): void {
        $json = json_encode( [ 'spam_probability' => 0.87 ] );

        Functions\expect( 'esc_url_raw' )->once()->andReturnArg( 0 );
        Functions\expect( 'wp_json_encode' )->once()->andReturnUsing( 'json_encode' );
        Functions\expect( 'wp_remote_post' )->once()->andReturn( [ 'response' => [ 'code' => 200 ] ] );
        Functions\expect( 'is_wp_error' )->once()->andReturn( false );
        Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
        Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( $json );

        $provider = new Provider_Self_Hosted( [ 'self_hosted_url' => 'http://spam-api:8000/predict' ] );
        $result   = $provider->get_score( 'Subject', 'Body' );
        $this->assertEqualsWithDelta( 0.87, $result, 0.001 );
    }

    public function test_bearer_token_is_sent_when_key_is_set(): void {
        $json = json_encode( [ 'spam_probability' => 0.5 ] );

        Functions\expect( 'esc_url_raw' )->once()->andReturnArg( 0 );
        Functions\expect( 'wp_json_encode' )->once()->andReturnUsing( 'json_encode' );
        Functions\expect( 'wp_remote_post' )
            ->once()
            ->with(
                \Mockery::type( 'string' ),
                \Mockery::on( function ( $args ) {
                    return isset( $args['headers']['Authorization'] )
                        && str_starts_with( $args['headers']['Authorization'], 'Bearer ' );
                } )
            )
            ->andReturn( [ 'response' => [ 'code' => 200 ] ] );
        Functions\expect( 'is_wp_error' )->once()->andReturn( false );
        Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
        Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( $json );

        $provider = new Provider_Self_Hosted( [
            'self_hosted_url' => 'http://spam-api:8000/predict',
            'self_hosted_key' => 'secret',
        ] );
        $result = $provider->get_score( 'Subject', 'Body' );
        $this->assertEqualsWithDelta( 0.5, $result, 0.001 );
    }

    public function test_returns_null_when_response_missing_spam_probability_key(): void {
        $json = json_encode( [ 'label' => 'spam' ] ); // no spam_probability key

        Functions\expect( 'esc_url_raw' )->once()->andReturnArg( 0 );
        Functions\expect( 'wp_json_encode' )->once()->andReturnUsing( 'json_encode' );
        Functions\expect( 'wp_remote_post' )->once()->andReturn( [ 'response' => [ 'code' => 200 ] ] );
        Functions\expect( 'is_wp_error' )->once()->andReturn( false );
        Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
        Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( $json );

        $provider = new Provider_Self_Hosted( [ 'self_hosted_url' => 'http://spam-api:8000/predict' ] );
        $this->assertNull( $provider->get_score( 'Subject', 'Body' ) );
    }

    public function test_returns_null_when_spam_probability_out_of_range(): void {
        $json = json_encode( [ 'spam_probability' => 1.5 ] ); // out of range

        Functions\expect( 'esc_url_raw' )->once()->andReturnArg( 0 );
        Functions\expect( 'wp_json_encode' )->once()->andReturnUsing( 'json_encode' );
        Functions\expect( 'wp_remote_post' )->once()->andReturn( [ 'response' => [ 'code' => 200 ] ] );
        Functions\expect( 'is_wp_error' )->once()->andReturn( false );
        Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
        Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( $json );

        $provider = new Provider_Self_Hosted( [ 'self_hosted_url' => 'http://spam-api:8000/predict' ] );
        $this->assertNull( $provider->get_score( 'Subject', 'Body' ) );
    }
}
