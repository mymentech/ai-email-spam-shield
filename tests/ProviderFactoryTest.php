<?php
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use AI_Email_Spam_Shield\Provider_Factory;
use AI_Email_Spam_Shield\Provider_Self_Hosted;
use AI_Email_Spam_Shield\Provider_OpenAI;
use AI_Email_Spam_Shield\Provider_Claude;
use AI_Email_Spam_Shield\Provider_Gemini;
use AI_Email_Spam_Shield\Provider_Groq;
use AI_Email_Spam_Shield\Provider_Cohere;
use AI_Email_Spam_Shield\Provider_DeepSeek;
use AI_Email_Spam_Shield\Provider_Ollama;
use AI_Email_Spam_Shield\Provider_OpenAI_Compat;

class ProviderFactoryTest extends TestCase {

    protected function setUp(): void { parent::setUp(); Monkey\setUp(); }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_defaults_to_self_hosted(): void {
        Functions\expect( 'get_option' )->once()->andReturn( [] );
        $provider = Provider_Factory::make();
        $this->assertInstanceOf( Provider_Self_Hosted::class, $provider );
    }

    public function test_returns_openai_provider(): void {
        Functions\expect( 'get_option' )->once()->andReturn( [ 'ai_provider' => 'openai' ] );
        $this->assertInstanceOf( Provider_OpenAI::class, Provider_Factory::make() );
    }

    public function test_returns_claude_provider(): void {
        Functions\expect( 'get_option' )->once()->andReturn( [ 'ai_provider' => 'claude' ] );
        $this->assertInstanceOf( Provider_Claude::class, Provider_Factory::make() );
    }

    public function test_returns_gemini_provider(): void {
        Functions\expect( 'get_option' )->once()->andReturn( [ 'ai_provider' => 'gemini' ] );
        $this->assertInstanceOf( Provider_Gemini::class, Provider_Factory::make() );
    }

    public function test_returns_groq_provider(): void {
        Functions\expect( 'get_option' )->once()->andReturn( [ 'ai_provider' => 'groq' ] );
        $this->assertInstanceOf( Provider_Groq::class, Provider_Factory::make() );
    }

    public function test_returns_cohere_provider(): void {
        Functions\expect( 'get_option' )->once()->andReturn( [ 'ai_provider' => 'cohere' ] );
        $this->assertInstanceOf( Provider_Cohere::class, Provider_Factory::make() );
    }

    public function test_returns_deepseek_provider(): void {
        Functions\expect( 'get_option' )->once()->andReturn( [ 'ai_provider' => 'deepseek' ] );
        $this->assertInstanceOf( Provider_DeepSeek::class, Provider_Factory::make() );
    }

    public function test_returns_ollama_provider(): void {
        Functions\expect( 'get_option' )->once()->andReturn( [ 'ai_provider' => 'ollama' ] );
        $this->assertInstanceOf( Provider_Ollama::class, Provider_Factory::make() );
    }

    public function test_returns_openai_compat_provider(): void {
        Functions\expect( 'get_option' )->once()->andReturn( [ 'ai_provider' => 'openai_compat' ] );
        $this->assertInstanceOf( Provider_OpenAI_Compat::class, Provider_Factory::make() );
    }

    public function test_unknown_provider_defaults_to_self_hosted(): void {
        Functions\expect( 'get_option' )->once()->andReturn( [ 'ai_provider' => 'unknown_future_provider' ] );
        $this->assertInstanceOf( Provider_Self_Hosted::class, Provider_Factory::make() );
    }
}
