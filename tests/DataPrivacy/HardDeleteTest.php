<?php

namespace App\Tests\DataPrivacy;

use App\Entity\Availability;
use App\Entity\CookingAssignment;
use App\Entity\KitaYear;
use App\Entity\LastYearCooking;
use App\Entity\Party;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test zur Überprüfung der Hard Delete Funktionalität (DSGVO-Konformität)
 * 
 * Dieser Test verifiziert, dass beim Löschen von Entities alle zugehörigen
 * Daten ebenfalls physisch aus der Datenbank entfernt werden.
 */
class HardDeleteTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    /**
     * Test: Beim Löschen einer Party werden alle zugehörigen Daten gelöscht
     */
    public function testPartyHardDelete(): void
    {
        // 1. Testdaten erstellen
        $party = new Party();
        $party->setChildren([['name' => 'Test Kind', 'birthYear' => 2020]]);
        $party->setParentNames(['Test Elternteil']);
        $party->setEmail('test@example.com');
        $this->entityManager->persist($party);

        $kitaYear = new KitaYear();
        $kitaYear->setStartDate(new \DateTimeImmutable('2024-09-01'));
        $kitaYear->setEndDate(new \DateTimeImmutable('2025-08-31'));
        $kitaYear->setActive(true);
        $this->entityManager->persist($kitaYear);

        $availability = new Availability();
        $availability->setParty($party);
        $availability->setKitaYear($kitaYear);
        $availability->setAvailableDates(['2024-10-01', '2024-10-08']);
        $this->entityManager->persist($availability);

        $assignment = new CookingAssignment();
        $assignment->setParty($party);
        $assignment->setKitaYear($kitaYear);
        $assignment->setAssignedDate(new \DateTimeImmutable('2024-10-01'));
        $this->entityManager->persist($assignment);

        $lastYear = new LastYearCooking();
        $lastYear->setParty($party);
        $lastYear->setKitaYear($kitaYear);
        $lastYear->setLastCookingDate(new \DateTimeImmutable('2023-12-15'));
        $lastYear->setCookingCount(5);
        $this->entityManager->persist($lastYear);

        $this->entityManager->flush();

        $partyId = $party->getId();
        $availabilityId = $availability->getId();
        $assignmentId = $assignment->getId();
        $lastYearId = $lastYear->getId();

        // 2. Party löschen
        $this->entityManager->remove($party);
        $this->entityManager->flush();
        $this->entityManager->clear(); // Cache leeren

        // 3. Verifizieren: Party ist gelöscht
        $deletedParty = $this->entityManager->getRepository(Party::class)->find($partyId);
        $this->assertNull($deletedParty, 'Party wurde nicht gelöscht (Hard Delete fehlgeschlagen)');

        // 4. Verifizieren: Alle zugehörigen Daten sind gelöscht
        $deletedAvailability = $this->entityManager->getRepository(Availability::class)->find($availabilityId);
        $this->assertNull($deletedAvailability, 'Availability wurde nicht gelöscht (Cascade Delete fehlgeschlagen)');

        $deletedAssignment = $this->entityManager->getRepository(CookingAssignment::class)->find($assignmentId);
        $this->assertNull($deletedAssignment, 'CookingAssignment wurde nicht gelöscht (Cascade Delete fehlgeschlagen)');

        $deletedLastYear = $this->entityManager->getRepository(LastYearCooking::class)->find($lastYearId);
        $this->assertNull($deletedLastYear, 'LastYearCooking wurde nicht gelöscht (Cascade Delete fehlgeschlagen)');

        // 5. Cleanup: KitaYear löschen
        $this->entityManager->remove($kitaYear);
        $this->entityManager->flush();
    }

    /**
     * Test: Beim Löschen eines KitaYear werden alle zugehörigen Daten gelöscht
     */
    public function testKitaYearHardDelete(): void
    {
        // 1. Testdaten erstellen
        $party = new Party();
        $party->setChildren([['name' => 'Test Kind', 'birthYear' => 2020]]);
        $party->setParentNames(['Test Elternteil']);
        $this->entityManager->persist($party);

        $kitaYear = new KitaYear();
        $kitaYear->setStartDate(new \DateTimeImmutable('2023-09-01'));
        $kitaYear->setEndDate(new \DateTimeImmutable('2024-08-31'));
        $kitaYear->setActive(false); // Nicht aktiv, damit löschbar
        $this->entityManager->persist($kitaYear);

        $availability = new Availability();
        $availability->setParty($party);
        $availability->setKitaYear($kitaYear);
        $availability->setAvailableDates(['2023-10-01']);
        $this->entityManager->persist($availability);

        $assignment = new CookingAssignment();
        $assignment->setParty($party);
        $assignment->setKitaYear($kitaYear);
        $assignment->setAssignedDate(new \DateTimeImmutable('2023-10-01'));
        $this->entityManager->persist($assignment);

        $this->entityManager->flush();

        $kitaYearId = $kitaYear->getId();
        $availabilityId = $availability->getId();
        $assignmentId = $assignment->getId();

        // 2. KitaYear löschen
        $this->entityManager->remove($kitaYear);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // 3. Verifizieren: KitaYear ist gelöscht
        $deletedYear = $this->entityManager->getRepository(KitaYear::class)->find($kitaYearId);
        $this->assertNull($deletedYear, 'KitaYear wurde nicht gelöscht (Hard Delete fehlgeschlagen)');

        // 4. Verifizieren: Alle zugehörigen Daten sind gelöscht
        $deletedAvailability = $this->entityManager->getRepository(Availability::class)->find($availabilityId);
        $this->assertNull($deletedAvailability, 'Availability wurde nicht gelöscht (Cascade Delete fehlgeschlagen)');

        $deletedAssignment = $this->entityManager->getRepository(CookingAssignment::class)->find($assignmentId);
        $this->assertNull($deletedAssignment, 'CookingAssignment wurde nicht gelöscht (Cascade Delete fehlgeschlagen)');

        // 5. Cleanup: Party löschen
        $this->entityManager->remove($party);
        $this->entityManager->flush();
    }

    /**
     * Test: Keine Waisenkinder-Datensätze nach Löschung
     */
    public function testNoOrphanedRecords(): void
    {
        // Alle Availabilities sollten gültige Party und KitaYear Referenzen haben
        $availabilities = $this->entityManager->getRepository(Availability::class)->findAll();
        foreach ($availabilities as $availability) {
            $this->assertNotNull($availability->getParty(), 'Orphaned Availability found: keine Party');
            $this->assertNotNull($availability->getKitaYear(), 'Orphaned Availability found: kein KitaYear');
        }

        // Alle CookingAssignments sollten gültige Party und KitaYear Referenzen haben
        $assignments = $this->entityManager->getRepository(CookingAssignment::class)->findAll();
        foreach ($assignments as $assignment) {
            $this->assertNotNull($assignment->getParty(), 'Orphaned CookingAssignment found: keine Party');
            $this->assertNotNull($assignment->getKitaYear(), 'Orphaned CookingAssignment found: kein KitaYear');
        }

        // Alle LastYearCookings sollten gültige Party und KitaYear Referenzen haben
        $lastYears = $this->entityManager->getRepository(LastYearCooking::class)->findAll();
        foreach ($lastYears as $lastYear) {
            $this->assertNotNull($lastYear->getParty(), 'Orphaned LastYearCooking found: keine Party');
            $this->assertNotNull($lastYear->getKitaYear(), 'Orphaned LastYearCooking found: kein KitaYear');
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}
