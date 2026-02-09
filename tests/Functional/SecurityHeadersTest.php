<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional Tests für Security Headers
 * 
 * Prüft dass der SecurityHeadersSubscriber alle Headers korrekt setzt.
 */
class SecurityHeadersTest extends WebTestCase
{
    public function testXFrameOptions(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseHeaderSame('X-Frame-Options', 'DENY');
    }

    public function testXContentTypeOptions(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
    }

    public function testReferrerPolicy(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseHeaderSame('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function testPermissionsPolicy(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseHeaderSame('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
    }

    public function testContentSecurityPolicy(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $csp = $client->getResponse()->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp, 'CSP Header sollte gesetzt sein');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
    }

    public function testNoHstsInTestEnv(): void
    {
        // HSTS sollte nur in Produktion gesetzt werden
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertFalse(
            $client->getResponse()->headers->has('Strict-Transport-Security'),
            'HSTS sollte in Test-Umgebung nicht gesetzt sein'
        );
    }

    public function testSecurityHeadersOnAdminLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseHeaderSame('X-Frame-Options', 'DENY');
        $this->assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
    }

    public function testSecurityHeadersOnParentLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/parent/login');

        $this->assertResponseHeaderSame('X-Frame-Options', 'DENY');
        $this->assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
    }

    public function testSecurityHeadersOn404Page(): void
    {
        $client = static::createClient();
        $client->request('GET', '/nonexistent-path');

        // Security Headers sollten auch bei 404 gesetzt sein
        $this->assertResponseHeaderSame('X-Frame-Options', 'DENY');
        $this->assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
    }
}
