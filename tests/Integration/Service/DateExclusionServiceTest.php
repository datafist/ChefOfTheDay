<?php

namespace App\Tests\Integration\Service;

use App\Entity\Holiday;
use App\Entity\KitaYear;
use App\Entity\Vacation;
use App\Service\DateExclusionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration Tests für den DateExclusionService
 * 
 * Testet die korrekte Ermittlung ausgeschlossener Tage:
 * - Wochenenden
 * - Feiertage
 * - Ferien
 * - Priorität der Gründe
 */
class DateExclusionServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private DateExclusionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->service = self::getContainer()->get(DateExclusionService::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }

    private function createTestYear(): KitaYear
    {
        $kitaYear = new KitaYear();
        $kitaYear->setStartDate(new \DateTimeImmutable('2087-09-01'));
        $kitaYear->setEndDate(new \DateTimeImmutable('2088-08-31'));
        $kitaYear->setIsActive(false);
        $this->em->persist($kitaYear);
        $this->em->flush();
        return $kitaYear;
    }

    private function cleanupYear(KitaYear $kitaYear): void
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement('DELETE FROM holidays WHERE kita_year_id = ?', [$kitaYear->getId()]);
        $conn->executeStatement('DELETE FROM vacations WHERE kita_year_id = ?', [$kitaYear->getId()]);
        $conn->executeStatement('DELETE FROM kita_years WHERE id = ?', [$kitaYear->getId()]);
        $this->em->clear();
    }

    // ========== Wochenenden ==========

    public function testWeekendsAreExcluded(): void
    {
        $kitaYear = $this->createTestYear();

        $excludedDates = $this->service->getExcludedDatesForKitaYear($kitaYear);

        // Samstag 2087-09-05 sollte ausgeschlossen sein
        // Berechne einen sicheren Samstag im September 2087
        $date = new \DateTimeImmutable('2087-09-01');
        while ((int) $date->format('N') !== 6) { // Samstag finden
            $date = $date->modify('+1 day');
        }
        $saturdayStr = $date->format('Y-m-d');
        $sundayStr = $date->modify('+1 day')->format('Y-m-d');

        $this->assertArrayHasKey($saturdayStr, $excludedDates);
        $this->assertSame('Wochenende', $excludedDates[$saturdayStr]);
        $this->assertArrayHasKey($sundayStr, $excludedDates);
        $this->assertSame('Wochenende', $excludedDates[$sundayStr]);

        $this->cleanupYear($kitaYear);
    }

    public function testWeekdaysAreNotExcluded(): void
    {
        $kitaYear = $this->createTestYear();

        $excludedDates = $this->service->getExcludedDatesForKitaYear($kitaYear);

        // Finde einen Montag
        $date = new \DateTimeImmutable('2087-09-01');
        while ((int) $date->format('N') !== 1) {
            $date = $date->modify('+1 day');
        }
        $mondayStr = $date->format('Y-m-d');

        $this->assertArrayNotHasKey($mondayStr, $excludedDates);

        $this->cleanupYear($kitaYear);
    }

    // ========== Feiertage ==========

    public function testHolidaysAreExcluded(): void
    {
        $kitaYear = $this->createTestYear();

        $holiday = new Holiday();
        $holiday->setDate(new \DateTimeImmutable('2087-12-25'));
        $holiday->setName('1. Weihnachtstag');
        $holiday->setKitaYear($kitaYear);
        $this->em->persist($holiday);
        $this->em->flush();

        $excludedDates = $this->service->getExcludedDatesForKitaYear($kitaYear);

        $this->assertArrayHasKey('2087-12-25', $excludedDates);
        $this->assertSame('1. Weihnachtstag', $excludedDates['2087-12-25']);

        $this->cleanupYear($kitaYear);
    }

    // ========== Ferien ==========

    public function testVacationsAreExcluded(): void
    {
        $kitaYear = $this->createTestYear();

        $vacation = new Vacation();
        $vacation->setStartDate(new \DateTimeImmutable('2087-10-20'));
        $vacation->setEndDate(new \DateTimeImmutable('2087-10-24'));
        $vacation->setName('Herbstferien');
        $vacation->setKitaYear($kitaYear);
        $this->em->persist($vacation);
        $this->em->flush();

        $excludedDates = $this->service->getExcludedDatesForKitaYear($kitaYear);

        // Alle Tage der Ferien sollten ausgeschlossen sein
        $this->assertArrayHasKey('2087-10-20', $excludedDates);
        $this->assertSame('Herbstferien', $excludedDates['2087-10-20']);
        $this->assertArrayHasKey('2087-10-21', $excludedDates);
        $this->assertArrayHasKey('2087-10-22', $excludedDates);
        $this->assertArrayHasKey('2087-10-23', $excludedDates);
        $this->assertArrayHasKey('2087-10-24', $excludedDates);

        $this->cleanupYear($kitaYear);
    }

    // ========== Priorität ==========

    public function testHolidayOverridesWeekend(): void
    {
        $kitaYear = $this->createTestYear();

        // Finde einen Samstag
        $date = new \DateTimeImmutable('2087-09-01');
        while ((int) $date->format('N') !== 6) {
            $date = $date->modify('+1 day');
        }

        $holiday = new Holiday();
        $holiday->setDate($date);
        $holiday->setName('Test-Feiertag');
        $holiday->setKitaYear($kitaYear);
        $this->em->persist($holiday);
        $this->em->flush();

        $excludedDates = $this->service->getExcludedDatesForKitaYear($kitaYear);

        // Feiertag sollte Wochenende überschreiben
        $this->assertSame('Test-Feiertag', $excludedDates[$date->format('Y-m-d')]);

        $this->cleanupYear($kitaYear);
    }

    public function testVacationOverridesWeekend(): void
    {
        $kitaYear = $this->createTestYear();

        // Ferien die ein Wochenende einschließen
        $vacation = new Vacation();
        $vacation->setStartDate(new \DateTimeImmutable('2087-10-19'));
        $vacation->setEndDate(new \DateTimeImmutable('2087-10-25'));
        $vacation->setName('Herbstferien');
        $vacation->setKitaYear($kitaYear);
        $this->em->persist($vacation);
        $this->em->flush();

        $excludedDates = $this->service->getExcludedDatesForKitaYear($kitaYear);

        // Finde Samstag/Sonntag in dem Zeitraum
        $date = new \DateTimeImmutable('2087-10-19');
        while ((int) $date->format('N') !== 6) {
            $date = $date->modify('+1 day');
        }
        if ($date <= new \DateTimeImmutable('2087-10-25')) {
            // Ferien sollten Wochenende überschreiben
            $this->assertSame('Herbstferien', $excludedDates[$date->format('Y-m-d')]);
        }

        $this->cleanupYear($kitaYear);
    }

    // ========== Leeres Jahr ==========

    public function testEmptyYearOnlyHasWeekends(): void
    {
        $kitaYear = $this->createTestYear();

        $excludedDates = $this->service->getExcludedDatesForKitaYear($kitaYear);

        // Nur Wochenenden sollten enthalten sein
        foreach ($excludedDates as $date => $reason) {
            $this->assertSame('Wochenende', $reason, "Ohne Feiertage/Ferien sollte nur 'Wochenende' vorkommen, gefunden: '$reason' am $date");
        }

        $this->cleanupYear($kitaYear);
    }
}
