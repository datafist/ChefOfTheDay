<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Fügt Security-Headers zu allen HTTP-Responses hinzu.
 * 
 * Diese Headers schützen gegen:
 * - XSS (Content-Security-Policy, X-XSS-Protection)
 * - Clickjacking (X-Frame-Options)
 * - MIME-Type-Sniffing (X-Content-Type-Options)
 * - Referrer-Leaking (Referrer-Policy)
 * - Unsichere Verbindungen (Strict-Transport-Security)
 */
class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly string $appEnv,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();

        // Kein Clickjacking zulassen
        $response->headers->set('X-Frame-Options', 'DENY');

        // MIME-Type-Sniffing verhindern
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Referrer einschränken
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions Policy (keine Kamera, Mikrofon, Geolocation etc.)
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // HSTS nur in Produktion (erzwingt HTTPS für 1 Jahr)
        if ($this->appEnv === 'prod') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // CSP: Inline-Styles und -Scripts erlauben (nötig für Twig-Templates mit Inline-JS),
        // aber externe Quellen blockieren
        $csp = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; form-action 'self'; frame-ancestors 'none'";
        $response->headers->set('Content-Security-Policy', $csp);
    }
}
