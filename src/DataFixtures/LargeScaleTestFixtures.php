<?php

namespace App\DataFixtures;

use App\Entity\Availability;
use App\Entity\CookingAssignment;
use App\Entity\Holiday;
use App\Entity\KitaYear;
use App\Entity\LastYearCooking;
use App\Entity\Party;
use App\Entity\User;
use App\Entity\Vacation;
use App\Service\CookingPlanGenerator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class LargeScaleTestFixtures extends Fixture implements FixtureGroupInterface
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly CookingPlanGenerator $planGenerator
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        echo "\n=== Erstelle umfangreiche Test-Daten (45 Familien) ===\n\n";

        // Admin User (nur erstellen, wenn noch nicht vorhanden)
        $existingAdmin = $manager->getRepository(User::class)->findOneBy(['username' => 'admin']);
        if (!$existingAdmin) {
            $admin = new User();
            $admin->setUsername('admin');
            $admin->setEmail('admin@kita.local'); // Optional für E-Mails
            $admin->setRoles(['ROLE_ADMIN']);
            $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
            $manager->persist($admin);
            echo "✓ Admin-User erstellt\n";
        } else {
            echo "✓ Admin-User bereits vorhanden\n";
        }

        // Kita-Jahre
        $kitaYear2024 = new KitaYear();
        $kitaYear2024->setStartDate(new \DateTimeImmutable('2024-09-01'));
        $kitaYear2024->setEndDate(new \DateTimeImmutable('2025-08-31'));
        $kitaYear2024->setIsActive(false);
        $manager->persist($kitaYear2024);

        $kitaYear2025 = new KitaYear();
        $kitaYear2025->setStartDate(new \DateTimeImmutable('2025-09-01'));
        $kitaYear2025->setEndDate(new \DateTimeImmutable('2026-08-31'));
        $kitaYear2025->setIsActive(true);
        $manager->persist($kitaYear2025);

        echo "✓ Kita-Jahre erstellt\n\n";

        // === REALISTISCHE FAMILIEN-STRUKTUR ===
        // Kita hat 45 Kinder-Plätze
        // Manche Familien haben 2 Kinder in der Kita → weniger Familien als Kinder
        
        echo "=== JAHR 24/25: Familien erstellen ===\n";
        
        // Erstelle Familien für 24/25
        // - 40 Familien mit je 1 Kind = 40 Kinder
        // - 2 Familien mit je 2 Kindern = 4 Kinder
        // - 1 Familie mit 1 Kind (wird ausscheiden) = 1 Kind
        // GESAMT: 43 Familien, 45 Kinder
        
        $familiesData2024 = $this->prepareFamiliesData2024();
        $families2024 = [];
        $leavingFamilies = []; // Familien, die komplett ausscheiden
        $familiesLosingChildren = []; // Familien, die nur einzelne Kinder verlieren
        $totalChildren = 0;
        
        foreach ($familiesData2024 as $familyData) {
            $party = new Party();
            $party->setChildren($familyData['children']);
            $party->setParentNames($familyData['parents']);
            $party->setEmail($familyData['email']);
            
            $manager->persist($party);
            $families2024[] = $party;
            $totalChildren += count($familyData['children']);
            
            // Markiere Familien nach Ausscheide-Status
            if ($familyData['willCompletelyLeave']) {
                $leavingFamilies[] = $party;
            } elseif (!empty($familyData['childrenWhoLeave'])) {
                $familiesLosingChildren[] = [
                    'party' => $party,
                    'leavingChildren' => $familyData['childrenWhoLeave']
                ];
            }
        }
        
        $manager->flush();
        
        echo sprintf("✓ %d Familien mit %d Kindern für 24/25 erstellt\n", count($families2024), $totalChildren);
        echo sprintf("  - %d Familien mit 1 Kind\n", count($families2024) - 2);
        echo sprintf("  - %d Familien mit 2 Kindern\n", 2);
        echo sprintf("  - %d Familien werden 25/26 komplett ausscheiden\n", count($leavingFamilies));
        echo sprintf("  - %d Familien verlieren 1 Kind (Geschwister bleibt)\n\n", count($familiesLosingChildren));

        // Feiertage für beide Jahre
        $this->createHolidays($manager, $kitaYear2024, $kitaYear2025);
        echo "✓ Feiertage erstellt\n";

        // Ferien für beide Jahre
        $this->createVacations($manager, $kitaYear2024, $kitaYear2025);
        echo "✓ Ferien erstellt\n";

        // Verfügbarkeiten für Jahr 24/25 (unterschiedlich für jede Familie)
        $this->createAvailabilities2024($manager, $kitaYear2024, $families2024);
        echo "✓ Verfügbarkeiten für 24/25 erstellt (realistische Szenarien)\n";

        $manager->flush();

        // Generiere und speichere Plan für 24/25 (als Altdaten für Test)
        echo "⏳ Generiere Kochplan für 24/25...\n";
        $assignments2024 = $this->generateCookingPlan2024($manager, $kitaYear2024, $families2024);
        echo "✓ Kochplan für 24/25 generiert (" . count($assignments2024) . " Zuweisungen)\n";
        
        // Erstelle LastYearCooking Einträge basierend auf dem letzten Assignment jeder Familie
        $this->createLastYearCookingsFromAssignments($manager, $kitaYear2024, $families2024, $assignments2024);
        echo "✓ LastYearCooking Einträge aus tatsächlichen Zuweisungen erstellt\n";

        $manager->flush();

        // === JAHR 25/26: Familien-Wechsel simulieren ===
        echo "\n=== JAHR 25/26: Familien-Wechsel ===\n";
        
        // 1. Familien komplett löschen, die ausscheiden
        echo sprintf("⏳ Lösche %d komplett ausscheidende Familien (DSGVO)...\n", count($leavingFamilies));
        foreach ($leavingFamilies as $leavingFamily) {
            // Lösche alle Zuweisungen dieser Familie (Cascade sollte das machen, aber sicher ist sicher)
            $leavingAssignments = $manager->getRepository(CookingAssignment::class)
                ->findBy(['party' => $leavingFamily]);
            foreach ($leavingAssignments as $assignment) {
                $manager->remove($assignment);
            }
            
            // Lösche LastYearCooking Einträge
            $leavingLastYear = $manager->getRepository(LastYearCooking::class)
                ->findBy(['party' => $leavingFamily]);
            foreach ($leavingLastYear as $lyc) {
                $manager->remove($lyc);
            }
            
            // Lösche Verfügbarkeiten
            $leavingAvailabilities = $manager->getRepository(Availability::class)
                ->findBy(['party' => $leavingFamily]);
            foreach ($leavingAvailabilities as $avail) {
                $manager->remove($avail);
            }
            
            // Lösche Familie
            $manager->remove($leavingFamily);
            echo sprintf("  ✓ Familie '%s' komplett gelöscht\n", $leavingFamily->getChildrenNames());
        }
        $manager->flush();
        
        // 2. Entferne ausscheidende Kinder aus Familien, die teilweise bleiben
        echo sprintf("⏳ Passe %d Familien an (Kind scheidet aus, Geschwister bleiben)...\n", count($familiesLosingChildren));
        foreach ($familiesLosingChildren as $flc) {
            $party = $flc['party'];
            $leavingChildrenNames = $flc['leavingChildren'];
            
            $currentChildren = $party->getChildren();
            $remainingChildren = array_filter($currentChildren, function($child) use ($leavingChildrenNames) {
                return !in_array($child['name'], $leavingChildrenNames);
            });
            
            $party->setChildren(array_values($remainingChildren));
            $manager->persist($party);
            
            echo sprintf("  ✓ Familie '%s': Kind(er) %s entfernt, %d Kind(er) verbleiben\n", 
                $party->getEmail(), 
                implode(', ', $leavingChildrenNames),
                count($remainingChildren)
            );
        }
        $manager->flush();
        
        // 3. Verbleibende Familien
        $remainingFamilies2025 = array_diff($families2024, $leavingFamilies);
        echo sprintf("✓ %d Familien verbleiben (davon %d mit reduzierter Kinderzahl)\n\n", 
            count($remainingFamilies2025), 
            count($familiesLosingChildren)
        );
        
        // 4. Neue Familien für 25/26 erstellen (um wieder 45 Kinder zu erreichen)
        echo "⏳ Erstelle neue Familien für 25/26...\n";
        $newFamiliesData2025 = $this->prepareNewFamiliesData2025();
        $newFamilies2025 = [];
        
        foreach ($newFamiliesData2025 as $familyData) {
            $party = new Party();
            $party->setChildren($familyData['children']);
            $party->setParentNames($familyData['parents']);
            $party->setEmail($familyData['email']);
            
            $manager->persist($party);
            $newFamilies2025[] = $party;
        }
        $manager->flush();
        
        // Zähle Kinder in neuen Familien
        $newChildren = 0;
        foreach ($newFamiliesData2025 as $fd) {
            $newChildren += count($fd['children']);
        }
        echo sprintf("✓ %d neue Familien mit %d Kindern erstellt\n\n", count($newFamilies2025), $newChildren);

        // Gesamt für 25/26
        $allFamilies2025 = array_merge(array_values($remainingFamilies2025), $newFamilies2025);
        
        $this->createAvailabilities2025($manager, $kitaYear2025, $allFamilies2025);
        echo "✓ Verfügbarkeiten für 25/26 erstellt\n";

        $manager->flush();

        // Finale Statistik
        $totalFamilies2025 = count($allFamilies2025);
        $totalChildren2025 = 45; // Fixe Kita-Kapazität
        
        echo "\n=== ✅ Test-Daten erfolgreich erstellt! ===\n\n";
        echo "📅 Jahr 24/25 (ABGESCHLOSSEN):\n";
        echo sprintf("   - %d Familien mit 45 Kindern\n", count($families2024));
        echo "   - Kochplan generiert und gespeichert (" . count($assignments2024) . " Zuweisungen)\n";
        echo "   - LastYearCooking Einträge erstellt\n";
        echo "   - Status: isActive = false\n\n";
        
        echo "� Jahreswechsel 24/25 → 25/26:\n";
        echo sprintf("   - %d Familien ausgeschieden (Kinder verlassen Kita)\n", count($leavingFamilies));
        echo sprintf("   - %d neue Familien mit %d Kindern aufgenommen\n", count($newFamilies2025), $newChildren);
        echo sprintf("   - %d Familien verbleiben aus 24/25\n\n", count($remainingFamilies2025));
        
        echo "�📅 Jahr 25/26 (AKTIV):\n";
        echo sprintf("   - %d Familien mit 45 Kindern\n", $totalFamilies2025);
        echo sprintf("     └─ %d bestehende Familien (aus 24/25)\n", count($remainingFamilies2025));
        echo sprintf("     └─ %d neue Familien\n", count($newFamilies2025));
        echo "   - Verfügbarkeiten angelegt (realistische Szenarien)\n";
        echo "   - Status: isActive = true\n";
        echo "   - ⚠️  KEIN Plan generiert - bereit zum Testen!\n\n";
        
        echo "🎯 TEST-SZENARIO:\n";
        echo "   1. In Browser öffnen: http://localhost:8000\n";
        echo "   2. Als Admin einloggen (admin / admin123)\n";
        echo "      ⚠️  WICHTIG: Passwort nach erstem Login ändern!\n";
        echo "   3. Kochplan für 25/26 generieren\n";
        echo "   4. Prüfen:\n";
        echo "      ✓ Werden Altdaten (LastYearCooking) von bestehenden Familien berücksichtigt?\n";
        echo "      ✓ Bekommen neue Familien (ohne LastYearCooking) Priorität?\n";
        echo "      ✓ Werden nur verfügbare Termine zugewiesen?\n";
        echo "      ✓ Funktioniert die Fairness mit eingeschränkten Verfügbarkeiten?\n\n";
        
        echo "📊 VERFÜGBARKEITS-SZENARIEN in Testdaten:\n";
        echo "   - 15% sehr eingeschränkt (nur Mo+Fr oder Di+Do)\n";
        echo "   - 20% eingeschränkt (2-3 Tage/Woche)\n";
        echo "   - 35% mittel flexibel (3-4 Tage/Woche)\n";
        echo "   - 25% flexibel (80-90% verfügbar)\n";
        echo "   - 5% sehr flexibel (alle Tage)\n\n";
        
        echo "💡 BESONDERHEITEN:\n";
        echo "   - Manche Familien haben 2 Kinder in der Kita (teilen sich Kochdienste)\n";
        echo "   - Ausscheidende Familien wurden gemäß DSGVO vollständig gelöscht\n";
        echo "   - Neue Familien haben keine Historie aus 24/25\n\n";
    }

    public static function getGroups(): array
    {
        return ['large-scale'];
    }

    /**
     * Erstellt Familien-Daten für Jahr 24/25
     * - 43 Familien mit 45 Kindern
     * - 2 Familien mit je 2 Kindern
     * - 5 Familien werden komplett ausscheiden (Kinder zu alt)
     * - 1 Familie verliert 1 Kind (Geschwister bleibt)
     * 
     * NEUE STRUKTUR: Eine Party = Eine Familie mit 1-3 Kindern
     */
    private function prepareFamiliesData2024(): array
    {
        $firstNames = [
            'Max', 'Sophie', 'Leon', 'Emma', 'Noah', 'Mia', 'Felix', 'Hannah',
            'Paul', 'Lena', 'Luis', 'Clara', 'Jonas', 'Ella', 'Tim', 'Amelie',
            'Finn', 'Lara', 'Ben', 'Sophia', 'Tom', 'Emily', 'Jan', 'Leonie',
            'Luca', 'Anna', 'Nico', 'Julia', 'David', 'Laura', 'Simon', 'Marie',
            'Moritz', 'Sarah', 'Erik', 'Lisa', 'Jakob', 'Nina', 'Oliver', 'Maja',
            'Daniel', 'Eva', 'Fabian', 'Pia', 'Alexander', 'Nora', 'Oskar'
        ];

        $lastNames = [
            'Müller', 'Schmidt', 'Schneider', 'Fischer', 'Weber', 'Meyer', 'Wagner',
            'Becker', 'Schulz', 'Hoffmann', 'Koch', 'Bauer', 'Richter', 'Klein',
            'Wolf', 'Schröder', 'Neumann', 'Braun', 'Werner', 'Schwarz', 'Krüger',
            'Hofmann', 'Zimmermann', 'Schmitt', 'Hartmann', 'Lange', 'Schmid',
            'Krause', 'Meier', 'Lehmann', 'Huber', 'Mayer', 'Herrmann', 'Köhler',
            'Walter', 'König', 'Weiß', 'Peters', 'Kaiser', 'Böhm', 'Fuchs',
            'Lang', 'Schuster', 'Vogel', 'Friedrich', 'Sommer', 'Winter', 'Stein'
        ];

        $families = [];

        // 1. Familie Müller mit 2 Kindern (Max und Sophie) - beide bleiben
        $families[] = [
            'children' => [
                ['name' => 'Max', 'birthYear' => 2020],      // 4-5 Jahre alt
                ['name' => 'Sophie', 'birthYear' => 2021],   // 3-4 Jahre alt
            ],
            'parents' => ['Thomas Müller', 'Maria Müller'],
            'email' => 'mueller@example.com',
            'willCompletelyLeave' => false,
            'childrenWhoLeave' => [], // Keine Kinder scheiden aus
        ];

        // 2. Familie Weber mit 2 Kindern - Leon scheidet aus, Emma bleibt
        $families[] = [
            'children' => [
                ['name' => 'Leon', 'birthYear' => 2018],     // 6-7 Jahre alt → SCHEIDET AUS
                ['name' => 'Emma', 'birthYear' => 2020],     // 4-5 Jahre alt → BLEIBT
            ],
            'parents' => ['Michael Weber', 'Julia Weber'],
            'email' => 'weber@example.com',
            'willCompletelyLeave' => false,
            'childrenWhoLeave' => ['Leon'], // Nur Leon scheidet aus
        ];

        // 3-7: 5 Familien die komplett ausscheiden (Kinder zu alt, keine Geschwister)
        $leavingFamilies = [
            [['Noah', 2018], ['Lisa Schulz'], 'schulz@example.com'],
            [['Felix', 2018], ['Peter Koch', 'Anna Koch'], 'koch@example.com'],
            [['Tim', 2018], ['Martin Richter', 'Sandra Richter'], 'richter@example.com'],
            [['Ben', 2018], ['Frank Klein', 'Laura Klein'], 'klein@example.com'],
            [['Jan', 2018], ['Stefan Wolf', 'Sabine Wolf'], 'wolf@example.com'],
        ];

        foreach ($leavingFamilies as $lfData) {
            $families[] = [
                'children' => [
                    ['name' => $lfData[0][0], 'birthYear' => $lfData[0][1]],
                ],
                'parents' => $lfData[1],
                'email' => $lfData[2],
                'willCompletelyLeave' => true, // Gesamte Familie scheidet aus
                'childrenWhoLeave' => [],
            ];
        }

        // 4. Rest: 36 normale Familien mit je 1 Kind (bleiben alle)
        $remainingNames = array_slice($firstNames, 9, 36);
        
        for ($i = 0; $i < 36; $i++) {
            // 2 Alleinerziehende einbauen (Index 5 und 18)
            $isSingleParent = ($i === 5 || $i === 18);
            
            if ($isSingleParent) {
                $parents = [$remainingNames[$i] . ' ' . $lastNames[$i + 7]];
            } else {
                $parents = [
                    ucfirst($remainingNames[$i]) . ' ' . $lastNames[$i + 7],
                    ucfirst($remainingNames[($i + 15) % 36]) . ' ' . $lastNames[$i + 7]
                ];
            }
            
            $families[] = [
                'children' => [
                    ['name' => $remainingNames[$i], 'birthYear' => 2019 + ($i % 4)],
                ],
                'parents' => $parents,
                'email' => strtolower($lastNames[$i + 7]) . $i . '@example.com',
                'willCompletelyLeave' => false,
                'childrenWhoLeave' => [],
            ];
        }

        return $families;
    }

    /**
     * Erstellt neue Familien für Jahr 25/26
     * - 6 neue Familien mit 6 Kindern (Ersatz für ausgeschiedene: 5 komplette + 1 Kind von Weber)
     * 
     * NEUE STRUKTUR: Eine Party = Eine Familie mit 1-3 Kindern
     */
    private function prepareNewFamiliesData2025(): array
    {
        $newFamilies = [
            [
                'children' => [
                    ['name' => 'Tobias', 'birthYear' => 2022], // 3 Jahre alt in 25/26
                ],
                'parents' => ['Michael Keller', 'Sarah Keller'],
                'email' => 'keller.neu@example.com',
            ],
            [
                'children' => [
                    ['name' => 'Johanna', 'birthYear' => 2022],
                ],
                'parents' => ['Stefan Graf', 'Anna Graf'],
                'email' => 'graf.neu@example.com',
            ],
            [
                'children' => [
                    ['name' => 'Lukas', 'birthYear' => 2021],
                ],
                'parents' => ['Peter Roth'],  // Alleinerziehend
                'email' => 'roth.neu@example.com',
            ],
            [
                'children' => [
                    ['name' => 'Charlotte', 'birthYear' => 2022],
                ],
                'parents' => ['Martin Baumann', 'Lisa Baumann'],
                'email' => 'baumann.neu@example.com',
            ],
            [
                'children' => [
                    ['name' => 'Matthias', 'birthYear' => 2021],
                ],
                'parents' => ['Thomas Sommer', 'Nina Sommer'],
                'email' => 'sommer.neu@example.com',
            ],
            [
                'children' => [
                    ['name' => 'Isabella', 'birthYear' => 2022],
                ],
                'parents' => ['Robert Krüger', 'Anna Krüger'],
                'email' => 'krueger.neu@example.com',
            ],
        ];

        return $newFamilies;
    }

    private function createHolidays(ObjectManager $manager, KitaYear $year2024, KitaYear $year2025): void
    {
        $holidays2024 = [
            ['2024-10-03', 'Tag der Deutschen Einheit'],
            ['2024-12-25', '1. Weihnachtstag'],
            ['2024-12-26', '2. Weihnachtstag'],
            ['2025-01-01', 'Neujahr'],
            ['2025-04-18', 'Karfreitag'],
            ['2025-04-21', 'Ostermontag'],
            ['2025-05-01', 'Tag der Arbeit'],
            ['2025-05-29', 'Christi Himmelfahrt'],
            ['2025-06-09', 'Pfingstmontag'],
        ];

        foreach ($holidays2024 as $data) {
            $holiday = new Holiday();
            $holiday->setDate(new \DateTimeImmutable($data[0]));
            $holiday->setName($data[1]);
            $holiday->setKitaYear($year2024);
            $manager->persist($holiday);
        }

        $holidays2025 = [
            ['2025-10-03', 'Tag der Deutschen Einheit'],
            ['2025-12-25', '1. Weihnachtstag'],
            ['2025-12-26', '2. Weihnachtstag'],
            ['2026-01-01', 'Neujahr'],
            ['2026-04-03', 'Karfreitag'],
            ['2026-04-06', 'Ostermontag'],
            ['2026-05-01', 'Tag der Arbeit'],
            ['2026-05-14', 'Christi Himmelfahrt'],
            ['2026-05-25', 'Pfingstmontag'],
        ];

        foreach ($holidays2025 as $data) {
            $holiday = new Holiday();
            $holiday->setDate(new \DateTimeImmutable($data[0]));
            $holiday->setName($data[1]);
            $holiday->setKitaYear($year2025);
            $manager->persist($holiday);
        }
    }

    private function createVacations(ObjectManager $manager, KitaYear $year2024, KitaYear $year2025): void
    {
        $vacations2024 = [
            ['2024-10-21', '2024-11-03', 'Herbstferien'],
            ['2024-12-23', '2025-01-05', 'Weihnachtsferien'],
            ['2025-04-14', '2025-04-25', 'Osterferien'],
            ['2025-07-07', '2025-08-15', 'Sommerferien'],
        ];

        foreach ($vacations2024 as $data) {
            $vacation = new Vacation();
            $vacation->setStartDate(new \DateTimeImmutable($data[0]));
            $vacation->setEndDate(new \DateTimeImmutable($data[1]));
            $vacation->setName($data[2]);
            $vacation->setKitaYear($year2024);
            $manager->persist($vacation);
        }

        $vacations2025 = [
            ['2025-10-20', '2025-11-02', 'Herbstferien'],
            ['2025-12-22', '2026-01-04', 'Weihnachtsferien'],
            ['2026-03-30', '2026-04-10', 'Osterferien'],
            ['2026-07-06', '2026-08-14', 'Sommerferien'],
        ];

        foreach ($vacations2025 as $data) {
            $vacation = new Vacation();
            $vacation->setStartDate(new \DateTimeImmutable($data[0]));
            $vacation->setEndDate(new \DateTimeImmutable($data[1]));
            $vacation->setName($data[2]);
            $vacation->setKitaYear($year2025);
            $manager->persist($vacation);
        }
    }

    private function createAvailabilities2024(ObjectManager $manager, KitaYear $kitaYear, array $families): void
    {
        $start = $kitaYear->getStartDate();
        $end = $kitaYear->getEndDate();
        
        // Alle Werktage des Jahres
        $allWorkdays = $this->getWorkdays($start, $end);

        foreach ($families as $index => $party) {
            $availability = new Availability();
            $availability->setParty($party);
            $availability->setKitaYear($kitaYear);

            // REALISTISCHE Verfügbarkeiten basierend auf praktischen Erfahrungen:
            // - 15% sehr eingeschränkt: nur 1-2 Tage pro Woche (z.B. nur Mo+Fr)
            // - 20% eingeschränkt: nur 2-3 Tage pro Woche
            // - 35% mittel flexibel: 3-4 Tage pro Woche
            // - 25% flexibel: fast alle Tage (80-90%)
            // - 5% sehr flexibel: alle Tage
            
            $scenario = $index % 20;
            
            if ($scenario < 3) {
                // Sehr eingeschränkt: nur Montag + Freitag (oder Di+Do)
                $availableDates = $this->getSpecificWeekdays($allWorkdays, $scenario % 2 === 0 ? [1, 5] : [2, 4]);
            } elseif ($scenario < 7) {
                // Eingeschränkt: nur 2-3 Tage pro Woche
                $weekdays = $scenario % 2 === 0 ? [1, 3, 5] : [2, 4];
                $availableDates = $this->getSpecificWeekdays($allWorkdays, $weekdays);
            } elseif ($scenario < 14) {
                // Mittel flexibel: 3-4 Tage (ohne einen festen Tag)
                $excludedDay = ($scenario % 5) + 1; // Schließt Mo, Di, Mi, Do oder Fr aus
                $availableDates = $this->getWeekdaysExcept($allWorkdays, [$excludedDay]);
            } elseif ($scenario < 19) {
                // Flexibel: 80-90% verfügbar (zufällige Lücken)
                $percentage = 0.80 + (($scenario % 3) * 0.05);
                $availableDates = $this->getRandomDates($allWorkdays, $percentage);
            } else {
                // Sehr flexibel: alle Tage verfügbar
                $availableDates = $allWorkdays;
            }

            $availability->setAvailableDates($availableDates);
            $manager->persist($availability);
        }
    }

    private function createAvailabilities2025(ObjectManager $manager, KitaYear $kitaYear, array $families): void
    {
        $start = $kitaYear->getStartDate();
        $end = $kitaYear->getEndDate();
        
        $allWorkdays = $this->getWorkdays($start, $end);

        foreach ($families as $index => $party) {
            $availability = new Availability();
            $availability->setParty($party);
            $availability->setKitaYear($kitaYear);

            // Ähnliche realistische Verteilung wie 2024, aber mit leichten Variationen
            // (Lebensumstände ändern sich: neue Jobs, Arbeitszeiten etc.)
            $scenario = ($index + 3) % 20; // +3 für Variation gegenüber 2024
            
            if ($scenario < 3) {
                $availableDates = $this->getSpecificWeekdays($allWorkdays, $scenario % 2 === 0 ? [1, 5] : [2, 4]);
            } elseif ($scenario < 7) {
                $weekdays = $scenario % 2 === 0 ? [1, 3, 5] : [2, 4];
                $availableDates = $this->getSpecificWeekdays($allWorkdays, $weekdays);
            } elseif ($scenario < 14) {
                $excludedDay = ($scenario % 5) + 1;
                $availableDates = $this->getWeekdaysExcept($allWorkdays, [$excludedDay]);
            } elseif ($scenario < 19) {
                $percentage = 0.80 + (($scenario % 3) * 0.05);
                $availableDates = $this->getRandomDates($allWorkdays, $percentage);
            } else {
                $availableDates = $allWorkdays;
            }

            $availability->setAvailableDates($availableDates);
            $manager->persist($availability);
        }
    }

    /**
     * Generiert den kompletten Kochplan für 24/25 (als Altdaten)
     * 
     * @return CookingAssignment[]
     */
    private function generateCookingPlan2024(ObjectManager $manager, KitaYear $kitaYear, array $families): array
    {
        // Generiere Plan mit dem echten Generator
        $result = $this->planGenerator->generatePlan($kitaYear);
        
        if (!empty($result['conflicts'])) {
            echo "⚠️  Konflikte beim Plan-Generieren:\n";
            foreach ($result['conflicts'] as $conflict) {
                echo "   - " . $conflict . "\n";
            }
        }
        
        $assignments = $result['assignments'];
        
        // Speichere Zuweisungen
        foreach ($assignments as $assignment) {
            $manager->persist($assignment);
        }
        
        return $assignments;
    }

    /**
     * Erstellt LastYearCooking Einträge basierend auf dem letzten Assignment jeder Familie
     */
    private function createLastYearCookingsFromAssignments(
        ObjectManager $manager, 
        KitaYear $kitaYear, 
        array $families, 
        array $assignments
    ): void {
        // Gruppiere Assignments nach Familie
        $assignmentsByParty = [];
        foreach ($assignments as $assignment) {
            $partyId = $assignment->getParty()->getId();
            if (!isset($assignmentsByParty[$partyId])) {
                $assignmentsByParty[$partyId] = [];
            }
            $assignmentsByParty[$partyId][] = $assignment;
        }
        
        // Für jede Familie: finde letztes Assignment und zähle Anzahl
        foreach ($families as $party) {
            $partyId = $party->getId();
            
            if (!isset($assignmentsByParty[$partyId]) || empty($assignmentsByParty[$partyId])) {
                // Familie hat keine Zuweisungen erhalten - erstelle einen simulierten Eintrag
                // aus dem Vorjahr (Sommer 2024)
                $lastDate = new \DateTimeImmutable('2024-08-' . (15 + ($partyId % 10)));
                $cookingCount = 0;
            } else {
                // Sortiere Assignments nach Datum
                $partyAssignments = $assignmentsByParty[$partyId];
                usort($partyAssignments, fn($a, $b) => $a->getAssignedDate() <=> $b->getAssignedDate());
                
                // Nimm das letzte Assignment und zähle Anzahl
                $lastAssignment = end($partyAssignments);
                $lastDate = $lastAssignment->getAssignedDate();
                $cookingCount = count($partyAssignments);
            }
            
            $lastYearCooking = new LastYearCooking();
            $lastYearCooking->setParty($party);
            $lastYearCooking->setKitaYear($kitaYear);
            $lastYearCooking->setLastCookingDate($lastDate);
            $lastYearCooking->setCookingCount($cookingCount);
            
            $manager->persist($lastYearCooking);
        }
    }

    private function getWorkdays(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $workdays = [];
        $period = new \DatePeriod($start, new \DateInterval('P1D'), $end->modify('+1 day'));

        foreach ($period as $date) {
            $dayOfWeek = (int)$date->format('N');
            if ($dayOfWeek >= 1 && $dayOfWeek <= 5) { // Mo-Fr
                $workdays[] = $date->format('Y-m-d');
            }
        }

        return $workdays;
    }

    private function getRandomDates(array $allDates, float $percentage): array
    {
        $count = (int)(count($allDates) * $percentage);
        $keys = array_rand($allDates, $count);
        
        if (!is_array($keys)) {
            $keys = [$keys];
        }

        $selected = [];
        foreach ($keys as $key) {
            $selected[] = $allDates[$key];
        }

        sort($selected);
        return $selected;
    }

    /**
     * Gibt nur bestimmte Wochentage zurück (1=Mo, 2=Di, 3=Mi, 4=Do, 5=Fr)
     */
    private function getSpecificWeekdays(array $allDates, array $weekdays): array
    {
        $result = [];
        foreach ($allDates as $dateStr) {
            $date = new \DateTimeImmutable($dateStr);
            $dayOfWeek = (int)$date->format('N');
            if (in_array($dayOfWeek, $weekdays, true)) {
                $result[] = $dateStr;
            }
        }
        return $result;
    }

    /**
     * Gibt alle Wochentage AUSSER den angegebenen zurück
     */
    private function getWeekdaysExcept(array $allDates, array $excludedWeekdays): array
    {
        $result = [];
        foreach ($allDates as $dateStr) {
            $date = new \DateTimeImmutable($dateStr);
            $dayOfWeek = (int)$date->format('N');
            if (!in_array($dayOfWeek, $excludedWeekdays, true)) {
                $result[] = $dateStr;
            }
        }
        return $result;
    }
}
