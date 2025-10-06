<?php

namespace App\Service;

use App\Entity\Availability;
use App\Entity\CookingAssignment;
use App\Entity\Holiday;
use App\Entity\KitaYear;
use App\Entity\LastYearCooking;
use App\Entity\Party;
use App\Entity\Vacation;
use App\Repository\AvailabilityRepository;
use App\Repository\HolidayRepository;
use App\Repository\LastYearCookingRepository;
use App\Repository\PartyRepository;
use App\Repository\VacationRepository;
use Doctrine\ORM\EntityManagerInterface;

class CookingPlanGenerator
{
    // Wird dynamisch basierend auf Anzahl Familien und verfügbaren Tagen berechnet
    private int $targetDaysBetweenAssignments;
    private int $minDaysBetweenAssignments;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PartyRepository $partyRepository,
        private readonly AvailabilityRepository $availabilityRepository,
        private readonly HolidayRepository $holidayRepository,
        private readonly VacationRepository $vacationRepository,
        private readonly LastYearCookingRepository $lastYearCookingRepository,
    ) {
    }

    /**
     * Generiert den Kochplan für ein Kita-Jahr
     * 
     * @return array{assignments: CookingAssignment[], conflicts: array}
     */
    public function generatePlan(KitaYear $kitaYear): array
    {
        $parties = $this->partyRepository->findAll();
        
        if (empty($parties)) {
            return ['assignments' => [], 'conflicts' => ['Keine Familien vorhanden.']];
        }

        // Lade alle Verfügbarkeiten
        $availabilities = $this->loadAvailabilities($kitaYear, $parties);
        
        // Lade Feiertage und Ferien
        $excludedDates = $this->getExcludedDates($kitaYear);
        
        // Lade letztjährige Kochdienste für jahresübergreifende Fairness
        $lastYearCookings = $this->loadLastYearCookings($parties);
        
        // Berechne erwartete Verteilung als Referenz für Priorisierung
        // Dies ist KEIN Zielwert oder Pflicht, sondern nur für die Sortierung:
        // Familien, die noch wenig Dienste haben, werden bevorzugt.
        $cookingRequirements = $this->calculateCookingRequirements($parties, $kitaYear, $excludedDates);
        
        // Berechne realistische Abstände basierend auf verfügbaren Tagen und Familien
        $this->calculateTargetIntervals($parties, $kitaYear, $excludedDates);
        
        // Generiere Zuweisungen
        $result = $this->assignCookingDays(
            $kitaYear,
            $parties,
            $availabilities,
            $excludedDates,
            $lastYearCookings,
            $cookingRequirements
        );

        return $result;
    }

    /**
     * @param Party[] $parties
     * @return array<int, Availability>
     */
    private function loadAvailabilities(KitaYear $kitaYear, array $parties): array
    {
        $availabilities = [];
        foreach ($parties as $party) {
            $availability = $this->availabilityRepository->findOneBy([
                'party' => $party,
                'kitaYear' => $kitaYear
            ]);
            if ($availability) {
                $availabilities[$party->getId()] = $availability;
            }
        }
        return $availabilities;
    }

    /**
     * @return array<string, bool> date => true
     */
    private function getExcludedDates(KitaYear $kitaYear): array
    {
        $excludedDates = [];
        
        // Feiertage
        $holidays = $this->holidayRepository->findBy(['kitaYear' => $kitaYear]);
        foreach ($holidays as $holiday) {
            $excludedDates[$holiday->getDate()->format('Y-m-d')] = true;
        }
        
        // Ferien
        $vacations = $this->vacationRepository->findBy(['kitaYear' => $kitaYear]);
        foreach ($vacations as $vacation) {
            $period = new \DatePeriod(
                $vacation->getStartDate(),
                new \DateInterval('P1D'),
                $vacation->getEndDate()->modify('+1 day')
            );
            foreach ($period as $date) {
                $excludedDates[$date->format('Y-m-d')] = true;
            }
        }
        
        // Wochenenden
        $period = new \DatePeriod(
            $kitaYear->getStartDate(),
            new \DateInterval('P1D'),
            $kitaYear->getEndDate()->modify('+1 day')
        );
        foreach ($period as $date) {
            $dayOfWeek = (int)$date->format('N');
            if ($dayOfWeek === 6 || $dayOfWeek === 7) {
                $excludedDates[$date->format('Y-m-d')] = true;
            }
        }
        
        return $excludedDates;
    }

    /**
     * @param Party[] $parties
     * @return array<int, LastYearCooking|null>
     */
    private function loadLastYearCookings(array $parties): array
    {
        $lastYearCookings = [];
        foreach ($parties as $party) {
            $lastCooking = $this->lastYearCookingRepository->findOneBy(
                ['party' => $party],
                ['lastCookingDate' => 'DESC']
            );
            $lastYearCookings[$party->getId()] = $lastCooking;
        }
        return $lastYearCookings;
    }

    /**
     * @param Party[] $parties
     * @return array<int, int>
     */
    private function calculateCookingRequirements(
        array $parties,
        KitaYear $kitaYear,
        array $excludedDates
    ): array {
        // Zähle verfügbare Kochdienst-Tage (Werktage ohne Ferien/Feiertage)
        $availableDays = 0;
        $period = new \DatePeriod(
            $kitaYear->getStartDate(),
            new \DateInterval('P1D'),
            $kitaYear->getEndDate()->modify('+1 day')
        );
        
        foreach ($period as $date) {
            if (!isset($excludedDates[$date->format('Y-m-d')])) {
                $availableDays++;
            }
        }

        // NEUE FAIRNESS-REGEL:
        // Alleinerziehende sollen MINDESTENS 1 Dienst weniger haben als das MINIMUM der Paare
        // 
        // Strategie:
        // 1. Berechne zunächst ein niedriges Ziel für Alleinerziehende
        // 2. Berechne höheres Ziel für Paare
        // 3. Der Algorithmus wird dann durch die strengen Limits gesteuert
        
        $numSingleParents = 0;
        $numCouples = 0;
        
        foreach ($parties as $party) {
            if ($party->isSingleParent()) {
                $numSingleParents++;
            } else {
                $numCouples++;
            }
        }
        
        $requirements = [];
        
        if ($numCouples > 0 && $numSingleParents > 0) {
            // Mit Alleinerziehenden: strenge Trennung
            // 
            // Formel: Alleinerziehende sollen exakt (Minimum-Paare - 1) bekommen
            // X = Dienste pro Paar (Minimum), Y = Dienste pro Alleinerziehende
            // Bedingung: Y = X - 1
            // 
            // Gleichung: numCouples × X + numSingleParents × (X - 1) = availableDays
            // Umgestellt: X × (numCouples + numSingleParents) - numSingleParents = availableDays
            // Lösung: X = (availableDays + numSingleParents) / (numCouples + numSingleParents)
            
            $minServicesPerCouple = ($availableDays + $numSingleParents) / ($numCouples + $numSingleParents);
            $maxServicesPerSingle = $minServicesPerCouple - 1;
            
            // Runde KONSERVATIV:
            // - Für Alleinerziehende: Abrunden (weniger Dienste)
            // - Für Paare: Aufrunden (mehr Dienste möglich)
            $targetSingle = max(1, (int)floor($maxServicesPerSingle)); // Abrunden
            $targetCouple = max($targetSingle + 1, (int)ceil($minServicesPerCouple)); // Mindestens 1 mehr
            
            foreach ($parties as $party) {
                if ($party->isSingleParent()) {
                    $requirements[$party->getId()] = $targetSingle;
                } else {
                    $requirements[$party->getId()] = $targetCouple;
                }
            }
        } elseif ($numCouples > 0) {
            // Nur Paare: gleichmäßig verteilen
            $servicesPerCouple = (int)round($availableDays / $numCouples);
            foreach ($parties as $party) {
                $requirements[$party->getId()] = $servicesPerCouple;
            }
        } elseif ($numSingleParents > 0) {
            // Nur Alleinerziehende: gleichmäßig verteilen
            $servicesPerSingle = (int)round($availableDays / $numSingleParents);
            foreach ($parties as $party) {
                $requirements[$party->getId()] = $servicesPerSingle;
            }
        }

        return $requirements;
    }

    /**
     * Berechnet realistische Ziel- und Mindest-Abstände basierend auf:
     * - Anzahl verfügbarer Tage
     * - Anzahl Familien und deren Gewichtung
     * 
     * @param Party[] $parties
     * @param array<string, bool> $excludedDates
     */
    private function calculateTargetIntervals(array $parties, KitaYear $kitaYear, array $excludedDates): void
    {
        // Zähle verfügbare Kochdienst-Tage
        $availableDays = 0;
        $period = new \DatePeriod(
            $kitaYear->getStartDate(),
            new \DateInterval('P1D'),
            $kitaYear->getEndDate()->modify('+1 day')
        );
        
        foreach ($period as $date) {
            if (!isset($excludedDates[$date->format('Y-m-d')])) {
                $availableDays++;
            }
        }

        // Berechne Gesamt-Gewicht und durchschnittliche Dienste pro Familie
        $totalWeight = 0;
        foreach ($parties as $party) {
            $weight = $party->isSingleParent() ? 1 : 2;
            $totalWeight += $weight;
        }

        // Durchschnittliche Anzahl Dienste pro Gewichtseinheit
        $servicesPerWeightUnit = $availableDays / $totalWeight;
        
        // Durchschnittlicher Abstand = Verfügbare Tage / Durchschnittliche Dienste
        // Für Paare (Gewicht 2): availableDays / (servicesPerWeightUnit * 2)
        // Für Alleinerziehende (Gewicht 1): availableDays / (servicesPerWeightUnit * 1)
        
        // Berechne Durchschnitts-Abstand für Paare (häufigster Fall)
        $avgDaysForPairs = (int)floor($availableDays / ($servicesPerWeightUnit * 2));
        
        // Target: 80% des Durchschnitts-Abstands (gibt etwas Puffer)
        // Min: 50% des Durchschnitts-Abstands (für Notfälle)
        $this->targetDaysBetweenAssignments = max(7, (int)($avgDaysForPairs * 0.8));
        $this->minDaysBetweenAssignments = max(4, (int)($avgDaysForPairs * 0.5));
        
        // Sicherheitscheck: Min darf nicht größer als Target sein
        if ($this->minDaysBetweenAssignments > $this->targetDaysBetweenAssignments) {
            $this->minDaysBetweenAssignments = $this->targetDaysBetweenAssignments;
        }
    }

    /**
     * @param Party[] $parties
     * @param array<int, Availability> $availabilities
     * @param array<string, bool> $excludedDates
     * @param array<int, LastYearCooking|null> $lastYearCookings
     * @param array<int, int> $cookingRequirements
     * @return array{assignments: CookingAssignment[], conflicts: array}
     */
    private function assignCookingDays(
        KitaYear $kitaYear,
        array $parties,
        array $availabilities,
        array $excludedDates,
        array $lastYearCookings,
        array $cookingRequirements
    ): array {
        $assignments = [];
        $conflicts = [];
        
        // Erstelle eine Liste aller verfügbaren Tage
        $period = new \DatePeriod(
            $kitaYear->getStartDate(),
            new \DateInterval('P1D'),
            $kitaYear->getEndDate()->modify('+1 day')
        );
        
        $availableDays = [];
        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            if (!isset($excludedDates[$dateStr])) {
                // DatePeriod gibt bereits DateTimeImmutable zurück wenn Start-Datum DateTimeImmutable ist
                if ($date instanceof \DateTimeImmutable) {
                    $availableDays[] = $date;
                } else {
                    $availableDays[] = \DateTimeImmutable::createFromMutable($date);
                }
            }
        }

        // Tracking: wie oft wurde jede Familie bereits zugewiesen
        $assignedCount = array_fill_keys(array_map(fn($p) => $p->getId(), $parties), 0);
        
        // Tracking: letztes Zuweisungsdatum pro Familie
        $lastAssignmentDate = [];
        foreach ($parties as $party) {
            $lastCooking = $lastYearCookings[$party->getId()] ?? null;
            if ($lastCooking) {
                $lastAssignmentDate[$party->getId()] = $lastCooking->getLastCookingDate();
            }
        }

        // Sortiere Tage chronologisch
        usort($availableDays, fn($a, $b) => $a <=> $b);

        foreach ($availableDays as $date) {
            $dateStr = $date->format('Y-m-d');
            
            // Finde geeignete Familien für diesen Tag
            // Strategie: Erst Familien mit TARGET_WEEKS (6 Wochen) suchen, dann fallback auf MIN_WEEKS (4 Wochen)
            $eligiblePartiesTarget = [];  // Familien mit 6+ Wochen Abstand
            $eligiblePartiesMinimum = []; // Familien mit 4+ Wochen Abstand (Notfall)
            
            foreach ($parties as $party) {
                $partyId = $party->getId();
                
                // HARTE REGEL FÜR ALLEINERZIEHENDE:
                // Alleinerziehende dürfen MAXIMAL ihren berechneten fairen Anteil bekommen
                // Dies ist eine ABSOLUTE GRENZE, keine Empfehlung!
                if ($party->isSingleParent() && $assignedCount[$partyId] >= $cookingRequirements[$partyId]) {
                    // Alleinerziehende hat ihr Maximum erreicht - AUSSCHLIESSEN
                    continue;
                }
                
                // Prüfe Verfügbarkeit
                $availability = $availabilities[$partyId] ?? null;
                if (!$availability || !$availability->isDateAvailable($dateStr)) {
                    continue;
                }
                
                // Prüfe Abstand zur letzten Zuweisung (inkl. Vorjahr!)
                if (isset($lastAssignmentDate[$partyId])) {
                    $daysSinceLastAssignment = $lastAssignmentDate[$partyId]->diff($date)->days;
                    
                    // SOFT-Regel: Bevorzuge Familien mit größerem Abstand
                    // Bevorzuge Familien mit Target-Abstand (dynamisch berechnet)
                    if ($daysSinceLastAssignment >= $this->targetDaysBetweenAssignments) {
                        $eligiblePartiesTarget[] = $party;
                    } 
                    // Fallback: Familien mit Mindest-Abstand (dynamisch berechnet)
                    // Auch wenn < 60 Tage: erlaubt, aber niedriger priorisiert
                    elseif ($daysSinceLastAssignment >= $this->minDaysBetweenAssignments) {
                        $eligiblePartiesMinimum[] = $party;
                    }
                    // Unter Minimum: nicht geeignet
                } else {
                    // Noch nie zugewiesen: immer geeignet (höchste Priorität)
                    $eligiblePartiesTarget[] = $party;
                }
            }
            
            // Wähle die beste Liste: Bevorzuge Target, fallback auf Minimum
            $eligibleParties = !empty($eligiblePartiesTarget) ? $eligiblePartiesTarget : $eligiblePartiesMinimum;

            if (empty($eligibleParties)) {
                $conflicts[] = "Kein geeignete Familie für " . $date->format('d.m.Y') . " gefunden.";
                continue;
            }

            // Sortiere Familien nach Fairness-Score:
            // 1. Priorität: Alleinerziehende bevorzugen, WENN sie UNTER ihrem Minimum sind
            //               Paare zurückstellen, WENN sie bereits ihren fairen Anteil haben
            // 2. Priorität: Jahresübergreifende Fairness - weniger Vorjahr-Dienste = höhere Priorität
            // 3. Priorität: Längster zeitlicher Abstand zur letzten Zuweisung
            // 4. Priorität: Familien unter ihrem erwarteten Wert
            // 5. Priorität: Wenigste Zuweisungen bisher
            usort($eligibleParties, function($a, $b) use ($assignedCount, $lastAssignmentDate, $date, $cookingRequirements, $lastYearCookings) {
                $partyIdA = $a->getId();
                $partyIdB = $b->getId();
                
                // NEUE LOGIK: Schutz für Alleinerziehende
                // Alleinerziehende dürfen MAXIMAL ihren fairen Anteil bekommen
                // Paare müssen bevorzugt werden, wenn Alleinerziehende bereits genug haben
                $isSingleA = $a->isSingleParent();
                $isSingleB = $b->isSingleParent();
                $hasReachedLimitA = $assignedCount[$partyIdA] >= $cookingRequirements[$partyIdA];
                $hasReachedLimitB = $assignedCount[$partyIdB] >= $cookingRequirements[$partyIdB];
                
                // Fall 1: A ist alleinerziehend und hat Limit erreicht, B nicht → B bevorzugen
                if ($isSingleA && $hasReachedLimitA && (!$isSingleB || !$hasReachedLimitB)) {
                    return 1; // B bevorzugen
                }
                // Fall 2: B ist alleinerziehend und hat Limit erreicht, A nicht → A bevorzugen
                if ($isSingleB && $hasReachedLimitB && (!$isSingleA || !$hasReachedLimitA)) {
                    return -1; // A bevorzugen
                }
                
                // JAHRESÜBERGREIFENDE FAIRNESS:
                // Familien mit WENIGER Diensten im Vorjahr werden bevorzugt
                // So rotiert die "Mehr-Last" zwischen den Jahren
                // WICHTIG: Neue Familien (ohne Vorjahr-Eintrag) werden neutral behandelt
                // Sie bekommen ihren ERWARTETEN Wert als virtuellen Vorjahres-Count
                // Dies verhindert:
                // - Überlastung neuer Familien (wenn Default zu niedrig ist)
                // - Bevorzugung neuer Familien (wenn Default niedriger als etablierte Familien ist)
                
                // Neue Familien starten mit ihrem fairen Erwartungswert
                // Dies ist der gleiche Wert wie etablierte Familien bekommen sollten
                $defaultLastYearCountA = $cookingRequirements[$partyIdA];
                $defaultLastYearCountB = $cookingRequirements[$partyIdB];
                
                $lastYearCountA = $lastYearCookings[$partyIdA]?->getCookingCount() ?? $defaultLastYearCountA;
                $lastYearCountB = $lastYearCookings[$partyIdB]?->getCookingCount() ?? $defaultLastYearCountB;
                
                // Berechne "Gesamtbelastung" über beide Jahre
                // Dies sorgt dafür, dass Familien mit 5 Diensten letztes Jahr dieses Jahr weniger bekommen
                $totalLoadA = $lastYearCountA + $assignedCount[$partyIdA];
                $totalLoadB = $lastYearCountB + $assignedCount[$partyIdB];
                
                // Berechne Tage seit letzter Zuweisung (oder sehr große Zahl wenn nie zugewiesen)
                $daysSinceA = isset($lastAssignmentDate[$partyIdA]) 
                    ? $lastAssignmentDate[$partyIdA]->diff($date)->days 
                    : 9999;
                $daysSinceB = isset($lastAssignmentDate[$partyIdB]) 
                    ? $lastAssignmentDate[$partyIdB]->diff($date)->days 
                    : 9999;
                
                // PRIMÄR: Längerer Abstand gewinnt (WICHTIGSTE REGEL!)
                // Dies verhindert, dass Familien zwei Dienste kurz hintereinander bekommen
                // z.B. 03.09. und dann schon wieder 08.10. (35 Tage)
                $daysDiff = $daysSinceB <=> $daysSinceA;
                if ($daysDiff !== 0) {
                    return $daysDiff;
                }
                
                // Sekundär: Niedrigere Gesamtbelastung über beide Jahre wird bevorzugt
                $loadDiff = $totalLoadA <=> $totalLoadB;
                if ($loadDiff !== 0) {
                    return $loadDiff;
                }
                
                // Tertiär: Bevorzuge Familien, die noch unter ihrem erwarteten Wert sind
                $underExpectedA = $assignedCount[$partyIdA] < $cookingRequirements[$partyIdA];
                $underExpectedB = $assignedCount[$partyIdB] < $cookingRequirements[$partyIdB];
                
                if ($underExpectedA && !$underExpectedB) {
                    return -1; // A bevorzugen
                }
                if (!$underExpectedA && $underExpectedB) {
                    return 1; // B bevorzugen
                }
                
                // Quaternär: Weniger Zuweisungen im aktuellen Jahr gewinnt
                return $assignedCount[$partyIdA] <=> $assignedCount[$partyIdB];
            });

            $selectedParty = $eligibleParties[0];
            $partyId = $selectedParty->getId();

            // Erstelle Zuweisung
            $assignment = new CookingAssignment();
            $assignment->setParty($selectedParty);
            $assignment->setKitaYear($kitaYear);
            $assignment->setAssignedDate($date);
            $assignment->setIsManuallyAssigned(false);

            $assignments[] = $assignment;
            $assignedCount[$partyId]++;
            $lastAssignmentDate[$partyId] = $date;
        }

        // Information: Zeige Verteilung (keine Warnung, nur Info)
        // Es gibt keinen "Zielwert" - jede Familie bekommt so viele Dienste wie möglich
        // basierend auf ihren Verfügbarkeiten und der gerechten Verteilung

        return [
            'assignments' => $assignments,
            'conflicts' => $conflicts
        ];
    }

    /**
     * Speichert die Zuweisungen in der Datenbank
     */
    public function saveAssignments(array $assignments): void
    {
        foreach ($assignments as $assignment) {
            $this->entityManager->persist($assignment);
        }
        $this->entityManager->flush();
    }
}
