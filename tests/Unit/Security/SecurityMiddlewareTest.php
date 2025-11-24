<?php

namespace Tests\Unit\Security;

use Tests\TestCase;
use App\Security\SecurityMiddleware;

class SecurityMiddlewareTest extends TestCase
{
    /**
     * Test valid path validation
     */
    public function testValidatePathWithValidPath(): void
    {
        $this->assertEquals('/test', SecurityMiddleware::validatePath('test'));
        $this->assertEquals('/test/folder', SecurityMiddleware::validatePath('/test/folder'));
        $this->assertEquals('/', SecurityMiddleware::validatePath(''));
        $this->assertEquals('/', SecurityMiddleware::validatePath('/'));
    }

    /**
     * Test path traversal detection
     */
    public function testValidatePathBlocksTraversal(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('directory traversal attempt detected');
        SecurityMiddleware::validatePath('../etc/passwd');
    }

    /**
     * Test double dot traversal
     */
    public function testValidatePathBlocksDoubleDot(): void
    {
        $this->expectException(\Exception::class);
        SecurityMiddleware::validatePath('/test/../../../etc/passwd');
    }

    /**
     * Test tilde traversal
     */
    public function testValidatePathBlocksTilde(): void
    {
        $this->expectException(\Exception::class);
        SecurityMiddleware::validatePath('~/etc/passwd');
    }

    /**
     * Test reconstructed traversal
     */
    public function testValidatePathBlocksReconstructedTraversal(): void
    {
        $this->expectException(\Exception::class);
        SecurityMiddleware::validatePath('..../');
    }

    /**
     * Test email validation with valid emails
     */
    public function testValidateEmailWithValidEmails(): void
    {
        $this->assertEquals('test@example.com', SecurityMiddleware::validateEmail('test@example.com'));
        $this->assertEquals('user.name@domain.org', SecurityMiddleware::validateEmail('user.name@domain.org'));
        $this->assertEquals('user+tag@example.com', SecurityMiddleware::validateEmail('user+tag@example.com'));
    }

    /**
     * Test email validation with invalid emails
     */
    public function testValidateEmailWithInvalidEmails(): void
    {
        $this->assertFalse(SecurityMiddleware::validateEmail(''));
        $this->assertFalse(SecurityMiddleware::validateEmail('notanemail'));
        $this->assertFalse(SecurityMiddleware::validateEmail('missing@domain'));
        $this->assertFalse(SecurityMiddleware::validateEmail('double..dot@domain.com'));
    }

    /**
     * Test email validation with too long email
     */
    public function testValidateEmailTooLong(): void
    {
        $longEmail = str_repeat('a', 250) . '@test.com';
        $this->assertFalse(SecurityMiddleware::validateEmail($longEmail));
    }

    /**
     * Test password validation with valid password
     */
    public function testValidatePasswordWithValidPassword(): void
    {
        $this->assertTrue(SecurityMiddleware::validatePassword('Password123'));
        $this->assertTrue(SecurityMiddleware::validatePassword('MyP@ssw0rd'));
    }

    /**
     * Test password validation returns errors
     */
    public function testValidatePasswordReturnsErrors(): void
    {
        $errors = SecurityMiddleware::validatePassword('short', true);
        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
    }

    /**
     * Test password too short
     */
    public function testValidatePasswordTooShort(): void
    {
        $this->assertFalse(SecurityMiddleware::validatePassword('Pass1'));
    }

    /**
     * Test password no uppercase
     */
    public function testValidatePasswordNoUppercase(): void
    {
        $this->assertFalse(SecurityMiddleware::validatePassword('password123'));
    }

    /**
     * Test password no lowercase
     */
    public function testValidatePasswordNoLowercase(): void
    {
        $this->assertFalse(SecurityMiddleware::validatePassword('PASSWORD123'));
    }

    /**
     * Test password no number
     */
    public function testValidatePasswordNoNumber(): void
    {
        $this->assertFalse(SecurityMiddleware::validatePassword('PasswordABC'));
    }

    /**
     * Test token validation
     */
    public function testValidateTokenWithValidTokens(): void
    {
        $this->assertTrue(SecurityMiddleware::validateToken(bin2hex(random_bytes(16))));
        $this->assertTrue(SecurityMiddleware::validateToken(str_repeat('a', 64)));
    }

    /**
     * Test token validation with invalid tokens
     */
    public function testValidateTokenWithInvalidTokens(): void
    {
        $this->assertFalse(SecurityMiddleware::validateToken(''));
        $this->assertFalse(SecurityMiddleware::validateToken('short'));
        $this->assertFalse(SecurityMiddleware::validateToken('not-hex-characters!'));
    }

    /**
     * Test string sanitization
     */
    public function testSanitizeString(): void
    {
        $this->assertEquals('test', SecurityMiddleware::sanitizeString('  test  '));
        $this->assertEquals('test', SecurityMiddleware::sanitizeString("test\0"));
        $this->assertEquals('', SecurityMiddleware::sanitizeString(''));
    }

    /**
     * Test string sanitization max length
     */
    public function testSanitizeStringMaxLength(): void
    {
        $longString = str_repeat('a', 500);
        $sanitized = SecurityMiddleware::sanitizeString($longString, 100);
        $this->assertEquals(100, strlen($sanitized));
    }

    /**
     * Test FTP host validation
     */
    public function testValidateFTPHostWithValidHosts(): void
    {
        $this->assertTrue(SecurityMiddleware::validateFTPHost('ftp.example.com'));
        $this->assertTrue(SecurityMiddleware::validateFTPHost('192.168.1.1'));
        $this->assertTrue(SecurityMiddleware::validateFTPHost('localhost'));
    }

    /**
     * Test FTP host validation with invalid hosts
     */
    public function testValidateFTPHostWithInvalidHosts(): void
    {
        $this->assertFalse(SecurityMiddleware::validateFTPHost(''));
        $this->assertFalse(SecurityMiddleware::validateFTPHost(str_repeat('a', 300)));
    }

    /**
     * Test FTP port validation
     */
    public function testValidateFTPPortWithValidPorts(): void
    {
        $this->assertTrue(SecurityMiddleware::validateFTPPort(21));
        $this->assertTrue(SecurityMiddleware::validateFTPPort(22));
        $this->assertTrue(SecurityMiddleware::validateFTPPort(65535));
    }

    /**
     * Test FTP port validation with invalid ports
     */
    public function testValidateFTPPortWithInvalidPorts(): void
    {
        $this->assertFalse(SecurityMiddleware::validateFTPPort(0));
        $this->assertFalse(SecurityMiddleware::validateFTPPort(-1));
        $this->assertFalse(SecurityMiddleware::validateFTPPort(65536));
        $this->assertFalse(SecurityMiddleware::validateFTPPort('notanumber'));
    }
}
