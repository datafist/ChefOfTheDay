<?php

namespace App\Tests\Integration\Service;

use App\Entity\CookingAssignment;
use App\Entity\KitaYear;
use App\Entity\LastYearCooking;
use App\Entity\Party;
use App\Service\LastYearCookingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration Tests für den LastYearCookingService
 * 
 * Testet Jahresübergang-Funktionalität:
 * - Erstellen von LastYearCooking-Einträgen aus CookingAssignments
 * - Update bestehender Einträge
 * - Cleanup verwaister Einträge
 */
class LastYearCookingServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private LastYearCookingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->service = self::getContainer()->get(LastYearCookingService::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }

    private function createTestData(): array
    {
        $kitaYear = new KitaYear();
        $kitaYear->setStartDate(new \DateTimeImmutable('2088-09-01'));
        $kitaYear->setEndDate(new \DateTimeImmutable('2089-08-31'));
        $kitaYear->setIsActive(false);
        $this->em->persist($kitaYear);

        $party1 = new Party();
        $party1->setChildren([['name' => 'LYC_Kind1', 'birthYear' => 2083]]);
        $party1->setParentNames(['LYC_Mutter1', 'LYC_Vater1']);
        $this->em->persist($party1);

        $party2 = new Party();
        $party2->setChildren([['name' => 'LYC_Kind2', 'birthYear' => 2083]]);
        $party2->setParentNames(['LYC_Mutter2']);
        $this->em->persist($party2);

        // Erstelle CookingAssignments für party1
        $dates1 = ['2088-10-01', '2088-11-05', '2088-12-03', '2089-01-15', '2089-02-20'];
        foreach ($dates1 as $date) {
            $assignment = new CookingAssignment();
            $assignment->setParty($party1);
            $assignment->setKitaYear($kitaYear);
            $assignment->setAssignedDate(new \DateTimeImmutable($date));
            $this->em->persist($assignment);
        }

        // Erstelle CookingAssignments für party2
        $dates2 = ['2088-10-08', '2088-11-12', '2089-03-10'];
        foreach ($dates2 as $date) {
            $assignment = new CookingAssignment();
            $assignment->setParty($party2);
            $assignment->setKitaYear($kitaYear);
            $assignment->setAssignedDate(new \DateTimeImmutable($date));
            $this->em->persist($assignment);
        }

        $this->em->flush();

        return ['kitaYear' => $kitaYear, 'parties' => [$party1, $party2]];
    }

    private function cleanup(KitaYear $kitaYear, array $parties): void
    {
        $conn = $this->em->getConnection();
        $kitaYearId = $kitaYear->getId();
        $ids = array_map(fn($p) => $p->getId(), $parties);

        $conn->executeStatement('DELETE FROM cooking_assignments WHERE kita_year_id = ?', [$kitaYearId]);
        $conn->executeStatement('DELETE FROM last_year_cookings WHERE kita_year_id = ? OR party_id IN (' . implode(',', $ids) . ')', [$kitaYearId]);
        $conn->executeStatement('DELETE FROM availabilities WHERE kita_year_id = ?', [$kitaYearId]);
        $conn->executeStatement('DELETE FROM parties WHERE id IN (' . implode(',', $ids) . ')');
        $conn->executeStatement('DELETE FROM kita_years WHERE id = ?', [$kitaYearId]);
        $this->em->clear();
    }

    // ========== createFromKitaYear() ==========

    public function testCreateFromKitaYearCreatesEntries(): void
    {
        $data = $this->createTestData();

        $result = $this->service->createFromKitaYear($data['kitaYear']);

        // 2 Familien mit Zuweisungen
        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['skipped']);

        // Prüfe in DB
        $entries = $this->em->getRepository(LastYearCooking::class)->findBy(['kitaYear' => $data['kitaYear']]);
        $this->assertCount(2, $entries);

        // Prüfe Counts
        foreach ($entries as $entry) {
            if ($entry->getParty()->getId() === $data['parties'][0]->getId()) {
                $this->assertSame(5, $entry->getCookingCount());
                $this->assertSame('2089-02-20', $entry->getLastCookingDate()->format('Y-m-d'));
            } elseif ($entry->getParty()->getId() === $data['parties'][1]->getId()) {
                $this->assertSame(3, $entry->getCookingCount());
                $this->assertSame('2089-03-10', $entry->getLastCookingDate()->format('Y-m-d'));
            }
        }

        $this->cleanup($data['kitaYear'], $data['parties']);
    }

    public function testCreateFromKitaYearUpdatesExistingEntries(): void
    {
        $data = $this->createTestData();

        // Erstelle existierenden LastYearCooking-Eintrag mit ÄLTEREM Datum
        $existing = new LastYearCooking();
        $existing->setParty($data['parties'][0]);
        $existing->setKitaYear($data['kitaYear']);
        $existing->setLastCookingDate(new \DateTimeImmutable('2088-10-01'));
        $existing->setCookingCount(1);
        $this->em->persist($existing);
        $this->em->flush();

        $result = $this->service->createFromKitaYear($data['kitaYear']);

        // 1 updated (party1, da neuer als bestehend), 1 created (party2)
        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['updated']);

        // Prüfe dass der bestehende Eintrag aktualisiert wurde
        $this->em->clear();
        $updatedEntry = $this->em->getRepository(LastYearCooking::class)->findOneBy([
            'party' => $data['parties'][0],
            'kitaYear' => $data['kitaYear'],
        ]);
        $this->assertSame('2089-02-20', $updatedEntry->getLastCookingDate()->format('Y-m-d'));
        $this->assertSame(5, $updatedEntry->getCookingCount());

        $this->cleanup($data['kitaYear'], $data['parties']);
    }

    public function testCreateFromKitaYearSkipsNewerExistingEntries(): void
    {
        $data = $this->createTestData();

        // Erstelle existierenden Eintrag mit NEUEREM Datum als letzte Zuweisung
        $existing = new LastYearCooking();
        $existing->setParty($data['parties'][0]);
        $existing->setKitaYear($data['kitaYear']);
        $existing->setLastCookingDate(new \DateTimeImmutable('2099-12-31'));
        $existing->setCookingCount(99);
        $this->em->persist($existing);
        $this->em->flush();

        $result = $this->service->createFromKitaYear($data['kitaYear']);

        // party1 sollte übersprungen werden (bestehend ist neuer)
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, $result['created']); // nur party2

        $this->cleanup($data['kitaYear'], $data['parties']);
    }

    public function testCreateFromKitaYearHandlesFamiliesWithoutAssignments(): void
    {
        $data = $this->createTestData();

        // Dritte Familie OHNE Zuweisungen
        $party3 = new Party();
        $party3->setChildren([['name' => 'LYC_Kind3', 'birthYear' => 2084]]);
        $party3->setParentNames(['LYC_Mutter3']);
        $this->em->persist($party3);
        $this->em->flush();

        $result = $this->service->createFromKitaYear($data['kitaYear']);

        $this->assertGreaterThanOrEqual(1, $result['noAssignment']);

        $data['parties'][] = $party3;
        $this->cleanup($data['kitaYear'], $data['parties']);
    }

    // ========== cleanupOrphaned() ==========

    public function testCleanupOrphanedDeletesWhenNewerExists(): void
    {
        $data = $this->createTestData();

        // Erstelle verwaisten Eintrag (kitaYear = null) mit altem Datum
        $orphan = new LastYearCooking();
        $orphan->setParty($data['parties'][0]);
        $orphan->setKitaYear(null);
        $orphan->setLastCookingDate(new \DateTimeImmutable('2087-06-15'));
        $orphan->setCookingCount(3);
        $this->em->persist($orphan);

        // Erstelle neueren Eintrag MIT KitaYear für gleiche Familie
        $newer = new LastYearCooking();
        $newer->setParty($data['parties'][0]);
        $newer->setKitaYear($data['kitaYear']);
        $newer->setLastCookingDate(new \DateTimeImmutable('2089-02-20'));
        $newer->setCookingCount(5);
        $this->em->persist($newer);
        $this->em->flush();

        $deleted = $this->service->cleanupOrphaned();

        $this->assertSame(1, $deleted);

        $this->cleanup($data['kitaYear'], $data['parties']);
    }

    public function testCleanupOrphanedKeepsWhenNoNewerExists(): void
    {
        $data = $this->createTestData();

        // Verwaister Eintrag mit NEUEREM Datum als alles (kein neuerer existiert)
        $orphan = new LastYearCooking();
        $orphan->setParty($data['parties'][0]);
        $orphan->setKitaYear(null);
        $orphan->setLastCookingDate(new \DateTimeImmutable('2099-12-31'));
        $orphan->setCookingCount(99);
        $this->em->persist($orphan);
        $this->em->flush();

        $deleted = $this->service->cleanupOrphaned();

        // Sollte NICHT gelöscht werden (kein neuerer Eintrag)
        $this->assertSame(0, $deleted);

        // Cleanup manuell
        $this->em->remove($orphan);
        $this->em->flush();

        $this->cleanup($data['kitaYear'], $data['parties']);
    }
}
