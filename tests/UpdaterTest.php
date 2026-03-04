<?php
use Brain\Monkey;
use PHPUnit\Framework\TestCase;
use AI_Email_Spam_Shield\Updater;

class UpdaterTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Updater::reset_instance();
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_updater_class_exists(): void {
        $this->assertTrue( class_exists( Updater::class ) );
    }

    public function test_get_instance_returns_updater(): void {
        $instance = Updater::get_instance();
        $this->assertInstanceOf( Updater::class, $instance );
    }

    public function test_get_instance_returns_same_object(): void {
        $a = Updater::get_instance();
        $b = Updater::get_instance();
        $this->assertSame( $a, $b );
    }
}
