<?php
/**
 * Tests for Admin — privacy notice methods.
 *
 * @package AI_Email_Spam_Shield
 */

namespace AI_Email_Spam_Shield\Tests;

use AI_Email_Spam_Shield\Admin;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class AdminTest extends TestCase {

	use MockeryPHPUnitIntegration;

	/** @var Admin */
	private Admin $admin;

	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
		// Instantiate without running the constructor (avoids add_action calls).
		$this->admin = ( new \ReflectionClass( Admin::class ) )->newInstanceWithoutConstructor();
	}

	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// render_privacy_notice
	// -------------------------------------------------------------------------

	public function test_render_privacy_notice_outputs_nothing_when_dismissed(): void {
		Functions\expect( 'get_current_user_id' )->once()->andReturn( 1 );
		Functions\expect( 'get_user_meta' )
			->once()
			->with( 1, 'aiess_privacy_notice_dismissed', true )
			->andReturn( 1 );

		ob_start();
		$this->admin->render_privacy_notice();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	public function test_render_privacy_notice_outputs_notice_when_not_dismissed(): void {
		Functions\expect( 'get_current_user_id' )->once()->andReturn( 1 );
		Functions\expect( 'get_user_meta' )
			->once()
			->with( 1, 'aiess_privacy_notice_dismissed', true )
			->andReturn( '' );
		Functions\expect( 'wp_create_nonce' )
			->once()
			->with( 'aiess_dismiss_privacy' )
			->andReturn( 'test_nonce_abc' );
		Functions\stubs( [
			'esc_html_e' => function ( string $t ) { echo $t; },
			'esc_js'     => fn( string $t ) => $t,
		] );

		ob_start();
		$this->admin->render_privacy_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'aiess-privacy-notice', $output );
		$this->assertStringContainsString( 'notice-warning', $output );
		$this->assertStringContainsString( 'aiess_dismiss_privacy_notice', $output );
		$this->assertStringContainsString( 'test_nonce_abc', $output );
	}

	// -------------------------------------------------------------------------
	// handle_dismiss_privacy_notice
	// -------------------------------------------------------------------------

	public function test_handle_dismiss_privacy_notice_saves_user_meta(): void {
		Functions\expect( 'check_ajax_referer' )
			->once()
			->with( 'aiess_dismiss_privacy', 'nonce' );
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( true );
		Functions\expect( 'get_current_user_id' )->once()->andReturn( 42 );
		Functions\expect( 'update_user_meta' )
			->once()
			->with( 42, 'aiess_privacy_notice_dismissed', 1 );
		Functions\expect( 'wp_send_json_success' )->once();

		$this->admin->handle_dismiss_privacy_notice();
	}

	public function test_handle_dismiss_privacy_notice_rejects_unauthorized(): void {
		Functions\expect( 'check_ajax_referer' )
			->once()
			->with( 'aiess_dismiss_privacy', 'nonce' );
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( false );
		Functions\expect( 'wp_send_json_error' )
			->once()
			->with( 'Unauthorized' );

		$this->admin->handle_dismiss_privacy_notice();
	}
}
