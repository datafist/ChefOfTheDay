#!/usr/bin/env php
<?php

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

    $kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
    $kernel->boot();
    $container = $kernel->getContainer();
    $entityManager = $container->get('doctrine')->getManager();

    echo "\n=== ANALYSE: Nicht vergebene Tage im August 2026 ===\n\n";

    $dates = ['2026-08-25', '2026-08-26', '2026-08-27', '2026-08-28', '2026-08-31'];
    
    // Hole alle Familien
    $parties = $entityManager->getRepository('App\Entity\Party')->findAll();
    echo "Gesamtanzahl Familien: " . count($parties) . "\n\n";

    // Hole aktives Kita-Jahr
    $kitaYear = $entityManager->getRepository('App\Entity\KitaYear')->findOneBy(['isActive' => true]);
    echo "Aktives Jahr: " . $kitaYear->getStartDate()->format('Y-m-d') . " bis " . $kitaYear->getEndDate()->format('Y-m-d') . "\n\n";

    // Hole alle Verfügbarkeiten
    $availabilities = $entityManager->getRepository('App\Entity\Availability')->findBy(['kitaYear' => $kitaYear]);
    $availabilityMap = [];
    foreach ($availabilities as $availability) {
        $availabilityMap[$availability->getParty()->getId()] = $availability;
    }

    echo "Anzahl Verfügbarkeiten: " . count($availabilities) . "\n\n";

    // Hole alle Zuweisungen
    $assignments = $entityManager->getRepository('App\Entity\CookingAssignment')->findBy(
        ['kitaYear' => $kitaYear],
        ['assignedDate' => 'ASC']
    );

    // Erstelle Map: PartyId => [Zuweisungen]
    $assignmentsByParty = [];
    foreach ($assignments as $assignment) {
        $partyId = $assignment->getParty()->getId();
        if (!isset($assignmentsByParty[$partyId])) {
            $assignmentsByParty[$partyId] = [];
        }
        $assignmentsByParty[$partyId][] = $assignment;
    }

    echo "Gesamtanzahl Zuweisungen: " . count($assignments) . "\n\n";

    // Analysiere jeden fehlenden Tag
    foreach ($dates as $dateStr) {
        echo "=====================================\n";
        echo "DATUM: " . $dateStr . " (" . (new \DateTime($dateStr))->format('l') . ")\n";
        echo "=====================================\n\n";

        $date = new \DateTimeImmutable($dateStr);
        $availableParties = [];
        $unavailableReasons = [
            'no_availability_entry' => 0,
            'date_not_in_list' => 0,
            'too_soon_after_last' => 0,
        ];

        foreach ($parties as $party) {
            $partyId = $party->getId();
            $reason = null;

            // 1. Hat die Familie eine Verfügbarkeit eingetragen?
            if (!isset($availabilityMap[$partyId])) {
                $unavailableReasons['no_availability_entry']++;
                $reason = "Keine Verfügbarkeit eingetragen";
            } else {
                $availability = $availabilityMap[$partyId];
                
                // 2. Ist der Tag in der Verfügbarkeitsliste?
                if (!$availability->isDateAvailable($dateStr)) {
                    $unavailableReasons['date_not_in_list']++;
                    $reason = "Tag nicht als verfügbar markiert";
                } else {
                    // 3. Prüfe letztes Assignment
                    $lastAssignment = null;
                    if (isset($assignmentsByParty[$partyId]) && !empty($assignmentsByParty[$partyId])) {
                        // Finde letztes Assignment vor diesem Datum
                        foreach (array_reverse($assignmentsByParty[$partyId]) as $assignment) {
                            if ($assignment->getAssignedDate() < $date) {
                                $lastAssignment = $assignment;
                                break;
                            }
                        }
                    }

                    if ($lastAssignment) {
                        $daysSince = $lastAssignment->getAssignedDate()->diff($date)->days;
                        if ($daysSince < 28) { // 4 Wochen Minimum
                            $unavailableReasons['too_soon_after_last']++;
                            $reason = sprintf(
                                "Zu kurzer Abstand (nur %d Tage seit %s)",
                                $daysSince,
                                $lastAssignment->getAssignedDate()->format('Y-m-d')
                            );
                        } else {
                            $availableParties[] = [
                                'party' => $party,
                                'days_since' => $daysSince,
                                'last_date' => $lastAssignment->getAssignedDate()->format('Y-m-d'),
                                'assignment_count' => count($assignmentsByParty[$partyId])
                            ];
                        }
                    } else {
                        // Nie zugewiesen oder nur nach diesem Datum
                        $availableParties[] = [
                            'party' => $party,
                            'days_since' => 999,
                            'last_date' => 'nie',
                            'assignment_count' => isset($assignmentsByParty[$partyId]) ? count($assignmentsByParty[$partyId]) : 0
                        ];
                    }
                }
            }

            // Ausgabe nicht verfügbare Familien (nur erste 5)
            if ($reason && $unavailableReasons['no_availability_entry'] + $unavailableReasons['date_not_in_list'] + $unavailableReasons['too_soon_after_last'] <= 5) {
                echo sprintf(
                    "  ❌ %s: %s\n",
                    $party->getChildName(),
                    $reason
                );
            }
        }

        echo "\n--- ZUSAMMENFASSUNG ---\n";
        echo sprintf("Keine Verfügbarkeit eingetragen: %d Familien\n", $unavailableReasons['no_availability_entry']);
        echo sprintf("Tag nicht verfügbar: %d Familien\n", $unavailableReasons['date_not_in_list']);
        echo sprintf("Zu kurzer Abstand: %d Familien\n", $unavailableReasons['too_soon_after_last']);
        echo sprintf("\n✅ VERFÜGBAR UND GEEIGNET: %d Familien\n\n", count($availableParties));

        if (count($availableParties) > 0) {
            echo "Top 10 verfügbare Familien (sortiert nach Abstand):\n";
            usort($availableParties, fn($a, $b) => $b['days_since'] <=> $a['days_since']);
            
            foreach (array_slice($availableParties, 0, 10) as $item) {
                echo sprintf(
                    "  ✓ %s: %d Tage seit letztem Dienst (%s), %d Zuweisungen gesamt\n",
                    $item['party']->getChildName(),
                    $item['days_since'],
                    $item['last_date'],
                    $item['assignment_count']
                );
            }
        } else {
            echo "❌ KEINE VERFÜGBARE FAMILIE GEFUNDEN!\n";
            echo "\nMÖGLICHE URSACHEN:\n";
            echo "1. Alle verfügbaren Familien haben kürzlich gekocht (< 4 Wochen)\n";
            echo "2. Keine Familie hat diesen Tag als verfügbar markiert\n";
            echo "3. Kombination aus beidem\n";
        }

        echo "\n\n";
    }

    echo "=== ANALYSE ABGESCHLOSSEN ===\n\n";

    return 0;
};
