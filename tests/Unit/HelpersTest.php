<?php

namespace Tests\Unit;

use Tests\TestCase;

class HelpersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure encryption key is set for tests
        $_ENV['APP_ENCRYPTION_KEY'] = 'base64:' . base64_encode(random_bytes(32));
    }

    public function testEnvReturnsValue(): void
    {
        $_ENV['TEST_VAR'] = 'test_value';
        $this->assertEquals('test_value', env('TEST_VAR'));
    }

    public function testEnvReturnsDefaultWhenNotSet(): void
    {
        $this->assertEquals('default', env('NON_EXISTENT_VAR', 'default'));
    }

    public function testEnvReturnsNullWhenNotSetAndNoDefault(): void
    {
        $this->assertNull(env('NON_EXISTENT_VAR_2'));
    }

    public function testEncryptAndDecrypt(): void
    {
        $original = 'sensitive data';
        $encrypted = encrypt($original);

        $this->assertNotEquals($original, $encrypted);

        $decrypted = decrypt($encrypted);
        $this->assertEquals($original, $decrypted);
    }

    public function testEncryptProducesDifferentOutputEachTime(): void
    {
        $data = 'test data';
        $encrypted1 = encrypt($data);
        $encrypted2 = encrypt($data);

        $this->assertNotEquals($encrypted1, $encrypted2);
    }

    public function testEscapeHtml(): void
    {
        $this->assertEquals('&lt;script&gt;', e('<script>'));
        $this->assertEquals('&quot;test&quot;', e('"test"'));
        $this->assertEquals('test &amp; test', e('test & test'));
    }

    public function testEscapeHandlesNull(): void
    {
        $this->assertEquals('', e(null));
    }

    public function testFormatBytes(): void
    {
        $result = formatBytes(0);
        $this->assertStringContainsString('0', $result);

        $result = formatBytes(500);
        $this->assertStringContainsString('500', $result);

        $result = formatBytes(1024);
        $this->assertStringContainsString('1', $result);

        $result = formatBytes(1024 * 1024);
        $this->assertStringContainsString('1', $result);
    }

    public function testGenerateToken(): void
    {
        $token = generateToken(16);
        $this->assertEquals(32, strlen($token)); // 16 bytes = 32 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token);
    }

    public function testGenerateTokenDifferentEachTime(): void
    {
        $token1 = generateToken(16);
        $token2 = generateToken(16);
        $this->assertNotEquals($token1, $token2);
    }

    public function testCsrfTokenGeneration(): void
    {
        // Start a session for CSRF test
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = csrf_token();
        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex chars
    }

    public function testCsrfTokenConsistentInSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        unset($_SESSION['csrf_token']); // Clear any existing token
        $token1 = csrf_token();
        $token2 = csrf_token();

        $this->assertEquals($token1, $token2);
    }
}
