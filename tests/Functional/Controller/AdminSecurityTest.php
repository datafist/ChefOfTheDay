<?php

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional Tests für den Admin-Bereich
 * 
 * Prüft:
 * - Zugangskontrolle (nur ROLE_ADMIN)
 * - CSRF-Schutz auf Admin-Aktionen
 * - Login-Funktionalität
 */
class AdminSecurityTest extends WebTestCase
{
    // ========== Zugangskontrolle ==========

    public function testAdminDashboardRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin');

        // Sollte zum Login weiterleiten
        $this->assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location');
        $this->assertStringContainsString('login', $location);
    }

    public function testAdminDashboardAccessibleForAdmin(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine')->getManager();
        $admin = $em->getRepository(User::class)->findOneBy(['username' => 'admin']);

        if (!$admin) {
            $this->markTestSkipped('Kein Admin-User in DB');
            return;
        }

        $client->loginUser($admin);
        $client->request('GET', '/admin/');

        $this->assertResponseIsSuccessful();
    }

    // ========== Admin Login ==========

    public function testAdminLoginPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
    }

    public function testAdminLoginPageHasCsrfToken(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $csrfField = $crawler->filter('input[name="_csrf_token"]');
        $this->assertCount(1, $csrfField, 'Admin Login sollte CSRF-Token haben');
    }

    // ========== CSRF auf Admin-Aktionen ==========

    public function testGeneratePlanWithoutCsrfFails(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine')->getManager();
        $admin = $em->getRepository(User::class)->findOneBy(['username' => 'admin']);

        if (!$admin) {
            $this->markTestSkipped('Kein Admin-User');
            return;
        }

        $client->loginUser($admin);

        // POST ohne CSRF-Token auf generatePlan
        $client->request('POST', '/admin/generate-plan', [
            'kitaYearId' => 999,
        ]);

        // Sollte Redirect sein (CSRF-Fehler)
        $response = $client->getResponse();
        $this->assertTrue(
            $response->isRedirection() || $response->getStatusCode() >= 400,
            'Generate Plan ohne CSRF sollte abgelehnt werden'
        );
    }

    public function testDeletePlanWithoutCsrfFails(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine')->getManager();
        $admin = $em->getRepository(User::class)->findOneBy(['username' => 'admin']);

        if (!$admin) {
            $this->markTestSkipped('Kein Admin-User');
            return;
        }

        $client->loginUser($admin);

        // POST ohne CSRF-Token
        $client->request('POST', '/admin/delete-plan', [
            'kitaYearId' => 999,
        ]);

        $response = $client->getResponse();
        $this->assertTrue(
            $response->isRedirection() || $response->getStatusCode() >= 400,
            'Delete Plan ohne CSRF sollte abgelehnt werden'
        );
    }

    // ========== Admin-Seiten erreichbar ==========

    public function testKitaYearListAccessibleForAdmin(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine')->getManager();
        $admin = $em->getRepository(User::class)->findOneBy(['username' => 'admin']);

        if (!$admin) {
            $this->markTestSkipped('Kein Admin-User');
            return;
        }

        $client->loginUser($admin);
        $client->request('GET', '/admin/kita-year/');

        $this->assertResponseIsSuccessful();
    }

    public function testFamiliesListAccessibleForAdmin(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine')->getManager();
        $admin = $em->getRepository(User::class)->findOneBy(['username' => 'admin']);

        if (!$admin) {
            $this->markTestSkipped('Kein Admin-User');
            return;
        }

        $client->loginUser($admin);
        $client->request('GET', '/admin/party/');

        $this->assertResponseIsSuccessful();
    }

    public function testCalendarAccessibleForAdmin(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine')->getManager();
        $admin = $em->getRepository(User::class)->findOneBy(['username' => 'admin']);

        if (!$admin) {
            $this->markTestSkipped('Kein Admin-User');
            return;
        }

        $client->loginUser($admin);
        $client->request('GET', '/admin/calendar');

        // Calendar kann redirect sein wenn kein aktives Kita-Jahr existiert
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            $statusCode === 200 || $statusCode === 302,
            sprintf('Calendar sollte 200 oder 302 liefern, bekam %d', $statusCode)
        );
    }
}
