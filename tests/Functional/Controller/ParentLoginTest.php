<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional Tests für den Parent-Login-Bereich
 * 
 * Prüft:
 * - CSRF-Schutz
 * - Session-Handling
 * - Zugriffskontrolle
 */
class ParentLoginTest extends WebTestCase
{
    // ========== Login-Seite ==========

    public function testLoginPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/parent/login');

        $this->assertResponseIsSuccessful();
    }

    public function testLoginPageContainsCsrfToken(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/parent/login');

        // CSRF-Token sollte im Formular vorhanden sein
        $csrfField = $crawler->filter('input[name="_csrf_token"]');
        $this->assertCount(1, $csrfField, 'Login-Formular sollte CSRF-Token-Feld haben');
    }

    // ========== CSRF-Schutz ==========

    public function testLoginWithoutCsrfTokenIsRejected(): void
    {
        $client = static::createClient();
        
        // POST ohne CSRF-Token
        $client->request('POST', '/parent/login', [
            'party_id' => 1,
            'password' => 'test',
        ]);

        // Sollte Redirect sein (abgelehnt, zurück zum Login)
        $this->assertResponseRedirects('/parent/login');
        $crawler = $client->followRedirect();
        // Flash-Message wird in base.html.twig als div.alert.alert-error gerendert
        $this->assertGreaterThan(
            0,
            $crawler->filter('.alert')->count(),
            'Nach CSRF-Ablehnung sollte eine Flash-Message angezeigt werden'
        );
    }

    public function testLoginWithInvalidCsrfTokenIsRejected(): void
    {
        $client = static::createClient();
        
        $client->request('POST', '/parent/login', [
            'party_id' => 1,
            'password' => 'test',
            '_csrf_token' => 'invalid-token-12345',
        ]);

        $this->assertResponseRedirects('/parent/login');
    }

    // ========== Falsches Passwort ==========

    public function testLoginWithWrongPasswordFails(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/parent/login');

        // Gültigen CSRF-Token holen
        $csrfToken = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        // Finde eine Familie in der DB
        $em = $client->getContainer()->get('doctrine')->getManager();
        $party = $em->getRepository(\App\Entity\Party::class)->findOneBy([]);

        if ($party) {
            $crawler = $client->request('POST', '/parent/login', [
                'party_id' => $party->getId(),
                'password' => 'FALSCHES_PASSWORT',
                '_csrf_token' => $csrfToken,
            ]);

            // Controller rendert Login-Seite erneut mit Flash-Fehler (Status 200)
            $this->assertResponseIsSuccessful();
            $this->assertSelectorExists('.alert.alert-error');
        } else {
            $this->markTestSkipped('Keine Testfamilie in DB vorhanden');
        }
    }

    // ========== Session-Zugriffskontrolle ==========

    public function testAvailabilityPageRedirectsWithoutLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/parent/availability');

        // Sollte zum Login weiterleiten
        $this->assertResponseRedirects('/parent/login');
    }

    public function testLogoutClearsSession(): void
    {
        $client = static::createClient();

        // Simuliere Login-Session
        $em = $client->getContainer()->get('doctrine')->getManager();
        $party = $em->getRepository(\App\Entity\Party::class)->findOneBy([]);
        
        if (!$party) {
            $this->markTestSkipped('Keine Testfamilie in DB');
            return;
        }

        // Setze Session manuell
        $session = $client->getContainer()->get('session.factory')->createSession();
        $session->set('parent_party_id', $party->getId());
        $session->set('parent_login_time', time());
        $session->save();
        $client->getCookieJar()->set(
            new \Symfony\Component\BrowserKit\Cookie($session->getName(), $session->getId())
        );

        // Logout
        $client->request('GET', '/parent/logout');
        $this->assertResponseRedirects('/parent/login');

        // Availability sollte jetzt wieder redirecten
        $client->followRedirect();
        $client->request('GET', '/parent/availability');
        $this->assertResponseRedirects('/parent/login');
    }
}
