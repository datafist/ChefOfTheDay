<?php

namespace App\Service;

use App\Entity\KitaYear;
use App\Entity\LastYearCooking;
use App\Repository\CookingAssignmentRepository;
use App\Repository\LastYearCookingRepository;
use App\Repository\PartyRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service für die Erstellung und Verwaltung von LastYearCooking-Einträgen.
 * Wird beim Jahresübergang automatisch und manuell per Command verwendet.
 */
class LastYearCookingService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PartyRepository $partyRepository,
        private readonly CookingAssignmentRepository $assignmentRepository,
        private readonly LastYearCookingRepository $lastYearCookingRepository,
    ) {
    }

    /**
     * Erstellt LastYearCooking-Einträge aus den CookingAssignments eines KitaJahrs.
     *
     * @return array{created: int, updated: int, skipped: int, noAssignment: int, details: array}
     */
    public function createFromKitaYear(KitaYear $kitaYear): array
    {
        $parties = $this->partyRepository->findAll();
        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'noAssignment' => 0,
            'details' => [],
        ];

        foreach ($parties as $party) {
            $familyName = $party->getChildrenNames();

            // Finde letzte Zuweisung dieser Familie im gegebenen Jahr
            $lastAssignment = $this->assignmentRepository->createQueryBuilder('ca')
                ->where('ca.party = :party')
                ->andWhere('ca.kitaYear = :kitaYear')
                ->setParameter('party', $party)
                ->setParameter('kitaYear', $kitaYear)
                ->orderBy('ca.assignedDate', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$lastAssignment) {
                $result['noAssignment']++;
                $result['details'][] = ['family' => $familyName, 'action' => 'noAssignment'];
                continue;
            }

            // Zähle Gesamtanzahl der Dienste im gegebenen Jahr
            $totalCount = $this->assignmentRepository->count([
                'party' => $party,
                'kitaYear' => $kitaYear,
            ]);

            $lastDate = $lastAssignment->getAssignedDate();

            // Prüfe ob bereits LastYearCooking existiert
            $existing = $this->lastYearCookingRepository->findOneBy([
                'party' => $party,
                'kitaYear' => $kitaYear,
            ]);

            if ($existing) {
                if ($lastDate > $existing->getLastCookingDate()) {
                    $existing->setLastCookingDate($lastDate);
                    $existing->setCookingCount($totalCount);
                    $result['updated']++;
                    $result['details'][] = [
                        'family' => $familyName,
                        'action' => 'updated',
                        'date' => $lastDate->format('d.m.Y'),
                        'count' => $totalCount,
                    ];
                } else {
                    $result['skipped']++;
                    $result['details'][] = ['family' => $familyName, 'action' => 'skipped'];
                }
                continue;
            }

            // Erstelle neuen LastYearCooking Eintrag
            $lastYearCooking = new LastYearCooking();
            $lastYearCooking->setParty($party);
            $lastYearCooking->setKitaYear($kitaYear);
            $lastYearCooking->setLastCookingDate($lastDate);
            $lastYearCooking->setCookingCount($totalCount);

            $this->entityManager->persist($lastYearCooking);
            $result['created']++;
            $result['details'][] = [
                'family' => $familyName,
                'action' => 'created',
                'date' => $lastDate->format('d.m.Y'),
                'count' => $totalCount,
            ];
        }

        $this->entityManager->flush();

        return $result;
    }

    /**
     * Löscht verwaiste LastYearCooking-Einträge (kitaYear = NULL),
     * die älter als die neuesten Einträge pro Familie sind.
     * Wird beim Jahreswechsel aufgerufen, um alte Daten zu bereinigen.
     *
     * @return int Anzahl gelöschter Einträge
     */
    public function cleanupOrphaned(): int
    {
        // Finde alle Einträge ohne KitaYear-Zuordnung (verwaist durch SET NULL)
        $orphaned = $this->lastYearCookingRepository->findBy(['kitaYear' => null]);
        $deleted = 0;

        foreach ($orphaned as $entry) {
            $partyId = $entry->getParty()->getId();

            // Prüfe ob es einen neueren Eintrag MIT KitaYear für diese Familie gibt
            $newerExists = $this->lastYearCookingRepository->createQueryBuilder('lyc')
                ->where('lyc.party = :party')
                ->andWhere('lyc.kitaYear IS NOT NULL')
                ->andWhere('lyc.lastCookingDate >= :date')
                ->setParameter('party', $entry->getParty())
                ->setParameter('date', $entry->getLastCookingDate())
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($newerExists) {
                $this->entityManager->remove($entry);
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $this->entityManager->flush();
        }

        return $deleted;
    }
}
