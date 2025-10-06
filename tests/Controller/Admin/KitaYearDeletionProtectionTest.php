<?php

namespace App\Tests\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test für den erweiterten Lösch-Schutz von Kita-Jahren
 */
class KitaYearDeletionProtectionTest extends WebTestCase
{
    /**
     * Test: Leeres Jahr kann gelöscht werden
     */
    public function testEmptyYearCanBeDeleted(): void
    {
        $client = static::createClient();
        
        // Als Admin anmelden
        $client->loginUser(
            $client->getContainer()->get('doctrine')->getRepository(\App\Entity\User::class)
                ->findOneBy(['email' => 'admin@kita.local'])
        );
        
        // Neues Test-Jahr erstellen
        $em = $client->getContainer()->get('doctrine')->getManager();
        $kitaYear = new \App\Entity\KitaYear();
        $kitaYear->setStartDate(new \DateTimeImmutable('2099-09-01'));
        $kitaYear->setEndDate(new \DateTimeImmutable('2100-08-31'));
        $kitaYear->setIsActive(false);
        $em->persist($kitaYear);
        $em->flush();
        
        $yearId = $kitaYear->getId();
        
        // Übersicht aufrufen
        $crawler = $client->request('GET', '/admin/kita-year');
        
        $this->assertResponseIsSuccessful();
        
        // Prüfe ob Löschen-Button vorhanden ist (nicht gesperrt)
        $this->assertSelectorExists('form[action*="' . $yearId . '"] button[type="submit"]:contains("Löschen")');
        $this->assertSelectorNotExists('button:contains("🔒 Gesperrt")');
        
        // Jahr löschen
        $client->submitForm('Löschen', [], 'POST');
        
        $this->assertResponseRedirects('/admin/kita-year');
        $client->followRedirect();
        
        // Prüfe Erfolgsmeldung
        $this->assertSelectorTextContains('.alert-success', 'erfolgreich gelöscht');
        
        // Prüfe dass Jahr aus DB entfernt wurde
        $deletedYear = $em->getRepository(\App\Entity\KitaYear::class)->find($yearId);
        $this->assertNull($deletedYear, 'Jahr sollte gelöscht sein');
    }
    
    /**
     * Test: Zukünftiges Jahr mit Verfügbarkeiten kann NICHT gelöscht werden
     */
    public function testFutureYearWithAvailabilitiesCannotBeDeleted(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine')->getManager();
        
        // Als Admin anmelden
        $client->loginUser(
            $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'admin@kita.local'])
        );
        
        // Finde aktives Jahr um zukünftiges Jahr zu erstellen
        $activeYear = $em->getRepository(\App\Entity\KitaYear::class)
            ->findOneBy(['isActive' => true]);
        
        $this->assertNotNull($activeYear);
        
        $futureStartYear = (int)$activeYear->getStartDate()->format('Y') + 1;
        
        // Zukünftiges Test-Jahr und Test-Familie erstellen
        $futureYear = new \App\Entity\KitaYear();
        $futureYear->setStartDate(new \DateTimeImmutable($futureStartYear . '-09-01'));
        $futureYear->setEndDate(new \DateTimeImmutable(($futureStartYear + 1) . '-08-31'));
        $futureYear->setIsActive(false);
        $em->persist($futureYear);
        
        $party = new \App\Entity\Party();
        $party->setName('Test Familie Zukunft');
        $party->setIsSingleParent(false);
        $party->setContactEmail('test-future@test.de');
        $em->persist($party);
        
        // Verfügbarkeit eintragen
        $availability = new \App\Entity\Availability();
        $availability->setParty($party);
        $availability->setKitaYear($futureYear);
        $availability->setAvailableDates([$futureStartYear . '-10-15', $futureStartYear . '-10-22']);
        $em->persist($availability);
        
        $em->flush();
        $yearId = $futureYear->getId();
        
        // Übersicht aufrufen
        $crawler = $client->request('GET', '/admin/kita-year');
        
        $this->assertResponseIsSuccessful();
        
        // Prüfe dass Button gesperrt ist
        $this->assertSelectorExists('button:contains("🔒 Gesperrt")[disabled]');
        
        // Prüfe Fehlermeldung unter Button
        $this->assertSelectorTextContains('small', 'Eltern haben bereits Verfügbarkeiten eingetragen');
        $this->assertSelectorTextContains('small', '1 Einträge'); // 1 Availability-Eintrag
        
        // Cleanup: Jahr und Daten löschen (direkt in DB für Test)
        $em->remove($availability);
        $em->remove($party);
        $em->remove($futureYear);
        $em->flush();
    }
    
    /**
     * Test: Vorjahr MIT Verfügbarkeiten kann gelöscht werden wenn Folgeplan existiert
     */
    public function testPastYearWithAvailabilitiesCanBeDeletedIfCurrentPlanExists(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine')->getManager();
        
        // Als Admin anmelden
        $client->loginUser(
            $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'admin@kita.local'])
        );
        
        // Aktives Jahr holen
        $activeYear = $em->getRepository(\App\Entity\KitaYear::class)
            ->findOneBy(['isActive' => true]);
        
        $this->assertNotNull($activeYear);
        
        // Prüfe ob aktives Jahr einen Plan hat
        $activePlanExists = $em->getRepository(\App\Entity\CookingAssignment::class)
            ->count(['kitaYear' => $activeYear]) > 0;
        
        if (!$activePlanExists) {
            $this->markTestSkipped('Aktives Jahr hat noch keinen Plan - Test kann nicht durchgeführt werden');
        }
        
        $pastStartYear = (int)$activeYear->getStartDate()->format('Y') - 1;
        
        // Vorjahr erstellen
        $pastYear = new \App\Entity\KitaYear();
        $pastYear->setStartDate(new \DateTimeImmutable($pastStartYear . '-09-01'));
        $pastYear->setEndDate(new \DateTimeImmutable(($pastStartYear + 1) . '-08-31'));
        $pastYear->setIsActive(false);
        $em->persist($pastYear);
        
        $party = new \App\Entity\Party();
        $party->setName('Test Familie Vergangenheit');
        $party->setIsSingleParent(false);
        $party->setContactEmail('test-past@test.de');
        $em->persist($party);
        
        // Verfügbarkeit für Vorjahr eintragen
        $availability = new \App\Entity\Availability();
        $availability->setParty($party);
        $availability->setKitaYear($pastYear);
        $availability->setAvailableDates([$pastStartYear . '-10-15', $pastStartYear . '-10-22']);
        $em->persist($availability);
        
        $em->flush();
        $yearId = $pastYear->getId();
        
        // Übersicht aufrufen
        $crawler = $client->request('GET', '/admin/kita-year');
        
        $this->assertResponseIsSuccessful();
        
        // Prüfe dass Löschen-Button vorhanden ist (NICHT gesperrt, trotz Verfügbarkeiten!)
        $this->assertSelectorExists('form[action*="' . $yearId . '"] button[type="submit"]:contains("Löschen")');
        
        // Jahr löschen (sollte funktionieren)
        $client->request('POST', '/admin/kita-year/' . $yearId, [
            '_token' => $crawler->filter('form[action*="' . $yearId . '"] input[name="_token"]')->attr('value'),
        ]);
        
        $this->assertResponseRedirects('/admin/kita-year');
        $client->followRedirect();
        
        // Prüfe Erfolgsmeldung
        $this->assertSelectorTextContains('.alert-success', 'erfolgreich gelöscht');
        
        // Cleanup Party (Jahr und Availability wurden bereits gelöscht)
        $em->remove($party);
        $em->flush();
    }
    
    /**
     * Test: Manipulierter POST-Request wird abgelehnt
     */
    public function testManipulatedDeleteRequestIsRejected(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine')->getManager();
        
        // Als Admin anmelden
        $client->loginUser(
            $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'admin@kita.local'])
        );
        
        // Test-Jahr mit Verfügbarkeit erstellen
        $kitaYear = new \App\Entity\KitaYear();
        $kitaYear->setStartDate(new \DateTimeImmutable('2097-09-01'));
        $kitaYear->setEndDate(new \DateTimeImmutable('2098-08-31'));
        $kitaYear->setIsActive(false);
        $em->persist($kitaYear);
        
        $party = new \App\Entity\Party();
        $party->setName('Test Familie 2');
        $party->setIsSingleParent(false);
        $party->setContactEmail('test2@test.de');
        $em->persist($party);
        
        $availability = new \App\Entity\Availability();
        $availability->setParty($party);
        $availability->setKitaYear($kitaYear);
        $availability->setAvailableDates(['2097-11-10']);
        $em->persist($availability);
        
        $em->flush();
        $yearId = $kitaYear->getId();
        
        // Versuche trotzdem zu löschen (manipulierter Request)
        $crawler = $client->request('GET', '/admin/kita-year');
        $csrfToken = $crawler->filter('input[name="_token"]')->first()->attr('value');
        
        $client->request('POST', '/admin/kita-year/' . $yearId, [
            '_token' => $csrfToken,
        ]);
        
        $this->assertResponseRedirects('/admin/kita-year');
        $client->followRedirect();
        
        // Prüfe Fehlermeldung
        $this->assertSelectorExists('.alert-error');
        $this->assertSelectorTextContains('.alert-error', 'kann nicht gelöscht werden');
        $this->assertSelectorTextContains('.alert-error', 'Verfügbarkeits-Einträge');
        
        // Prüfe dass Jahr NICHT gelöscht wurde
        $stillExists = $em->getRepository(\App\Entity\KitaYear::class)->find($yearId);
        $this->assertNotNull($stillExists, 'Jahr sollte noch existieren');
        
        // Cleanup
        $em->remove($availability);
        $em->remove($party);
        $em->remove($kitaYear);
        $em->flush();
    }
    
    /**
     * Test: Aktives Jahr kann niemals gelöscht werden
     */
    public function testActiveYearCannotBeDeleted(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine')->getManager();
        
        // Als Admin anmelden
        $client->loginUser(
            $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'admin@kita.local'])
        );
        
        // Finde aktives Jahr
        $activeYear = $em->getRepository(\App\Entity\KitaYear::class)
            ->findOneBy(['isActive' => true]);
        
        $this->assertNotNull($activeYear, 'Es sollte ein aktives Jahr geben');
        
        // Übersicht aufrufen
        $crawler = $client->request('GET', '/admin/kita-year');
        
        $this->assertResponseIsSuccessful();
        
        // Prüfe dass für aktives Jahr Button gesperrt ist
        $this->assertSelectorExists('button:contains("🔒 Gesperrt")[disabled]');
        $this->assertSelectorTextContains('small', 'Aktives Jahr kann nicht gelöscht werden');
    }
}
