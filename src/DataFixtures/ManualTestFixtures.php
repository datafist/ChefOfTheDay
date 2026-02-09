<?php

namespace App\DataFixtures;

use App\Entity\Availability;
use App\Entity\Holiday;
use App\Entity\KitaYear;
use App\Entity\Party;
use App\Entity\User;
use App\Entity\Vacation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * 50 realistische Familien für manuelles Testen.
 * 
 * Erstellt:
 * - 1 Admin-User (admin / admin123)
 * - 1 aktives KitaYear 2025/26
 * - 50 Familien (46 Paare, 4 Alleinerziehende; 40×1 Kind, 10×2 Kinder = 60 Kinder)
 * - Feiertage + Ferien für 2025/26
 * - Realistische Verfügbarkeiten
 * - KEIN automatisch generierter Plan (zum manuellen Testen über UI)
 * 
 * Laden: php bin/console doctrine:fixtures:load --group=manual-test
 */
class ManualTestFixtures extends Fixture implements FixtureGroupInterface
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public static function getGroups(): array
    {
        return ['manual-test'];
    }

    public function load(ObjectManager $manager): void
    {
        echo "\n=== Erstelle 50 Familien für manuelles Testen ===\n\n";

        // Admin User
        $existingAdmin = $manager->getRepository(User::class)->findOneBy(['username' => 'admin']);
        if (!$existingAdmin) {
            $admin = new User();
            $admin->setUsername('admin');
            $admin->setEmail('admin@kita.local');
            $admin->setRoles(['ROLE_ADMIN']);
            $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
            $manager->persist($admin);
            echo "✓ Admin-User erstellt (admin / admin123)\n";
        } else {
            echo "✓ Admin-User bereits vorhanden\n";
        }

        // Aktives Kita-Jahr 2025/26
        $kitaYear = new KitaYear();
        $kitaYear->setStartDate(new \DateTimeImmutable('2025-09-01'));
        $kitaYear->setEndDate(new \DateTimeImmutable('2026-08-31'));
        $kitaYear->setIsActive(true);
        $manager->persist($kitaYear);
        echo "✓ KitaYear 2025/26 erstellt (aktiv)\n";

        // Feiertage 2025/26
        $this->createHolidays($manager, $kitaYear);
        echo "✓ Feiertage erstellt\n";

        // Ferien 2025/26
        $this->createVacations($manager, $kitaYear);
        echo "✓ Ferien erstellt\n";

        // 50 Familien erstellen
        $families = $this->createFamilies($manager);
        $manager->flush();
        echo sprintf("✓ %d Familien erstellt (46 Paare, 4 Alleinerziehende)\n", count($families));

        // Verfügbarkeiten
        $this->createAvailabilities($manager, $kitaYear, $families);
        $manager->flush();
        echo "✓ Verfügbarkeiten erstellt\n";

        // Zusammenfassung
        $singleParents = 0;
        $twoKidFamilies = 0;
        $totalChildren = 0;
        foreach ($families as $f) {
            if (count($f->getParentNames()) === 1) {
                $singleParents++;
            }
            $totalChildren += count($f->getChildren());
            if (count($f->getChildren()) === 2) {
                $twoKidFamilies++;
            }
        }

        echo "\n=== ✅ Fertig! ===\n";
        echo sprintf("📊 %d Familien, %d Kinder\n", count($families), $totalChildren);
        echo sprintf("   - %d Paare, %d Alleinerziehende\n", count($families) - $singleParents, $singleParents);
        echo sprintf("   - %d Familien mit 1 Kind, %d mit 2 Kindern\n", count($families) - $twoKidFamilies, $twoKidFamilies);
        echo "\n🎯 Nächste Schritte:\n";
        echo "   1. http://localhost:8000 öffnen\n";
        echo "   2. Admin-Login: admin / admin123\n";
        echo "   3. Kochplan über UI generieren\n";
        echo "   4. Familien hinzufügen/entfernen testen\n";
        echo "   5. Neues Kita-Jahr anlegen testen\n\n";
    }

    /**
     * @return Party[]
     */
    private function createFamilies(ObjectManager $manager): array
    {
        $childFirstNames = [
            'Max', 'Sophie', 'Leon', 'Emma', 'Noah', 'Mia', 'Felix', 'Hannah',
            'Paul', 'Lena', 'Luis', 'Clara', 'Jonas', 'Ella', 'Tim', 'Amelie',
            'Finn', 'Lara', 'Ben', 'Sophia', 'Tom', 'Emily', 'Jan', 'Leonie',
            'Luca', 'Anna', 'Nico', 'Julia', 'David', 'Laura', 'Simon', 'Marie',
            'Moritz', 'Sarah', 'Erik', 'Lisa', 'Jakob', 'Nina', 'Oliver', 'Maja',
            'Daniel', 'Eva', 'Fabian', 'Pia', 'Alexander', 'Nora', 'Oskar', 'Helena',
            'Elias', 'Emilia', 'Henry', 'Mila', 'Theo', 'Ida', 'Anton', 'Greta',
            'Leo', 'Charlotte', 'Valentin', 'Mathilda',
        ];

        $lastNames = [
            'Müller', 'Schmidt', 'Schneider', 'Fischer', 'Weber', 'Meyer', 'Wagner',
            'Becker', 'Schulz', 'Hoffmann', 'Koch', 'Bauer', 'Richter', 'Klein',
            'Wolf', 'Schröder', 'Neumann', 'Braun', 'Werner', 'Schwarz', 'Krüger',
            'Hofmann', 'Zimmermann', 'Schmitt', 'Hartmann', 'Lange', 'Schmid',
            'Krause', 'Meier', 'Lehmann', 'Huber', 'Mayer', 'Herrmann', 'Köhler',
            'Walter', 'König', 'Weiß', 'Peters', 'Kaiser', 'Böhm', 'Fuchs',
            'Lang', 'Schuster', 'Vogel', 'Friedrich', 'Sommer', 'Winter', 'Stein',
            'Berger', 'Frank',
        ];

        $parentFirstNamesMale = [
            'Thomas', 'Michael', 'Stefan', 'Martin', 'Frank', 'Peter', 'Andreas',
            'Christian', 'Jörg', 'Markus', 'Dirk', 'Sven', 'Tobias', 'Matthias',
            'Florian', 'Alexander', 'Daniel', 'Robert', 'Patrick', 'Philipp',
            'Sebastian', 'Torsten', 'Wolfgang', 'Ralf', 'Oliver',
            'Carsten', 'Holger', 'Bernd', 'Uwe', 'Klaus',
            'Jan', 'Lars', 'Marco', 'Ingo', 'Volker',
            'Kai', 'Jens', 'René', 'Heiko', 'Mario',
            'Gerald', 'Norbert', 'Christoph', 'Bruno', 'Armin',
            'Lutz', 'Gunther', 'Ernst', 'Hugo', 'Viktor',
        ];

        $parentFirstNamesFemale = [
            'Maria', 'Julia', 'Sandra', 'Lisa', 'Anna', 'Sarah', 'Kathrin',
            'Nicole', 'Claudia', 'Petra', 'Susanne', 'Sabine', 'Andrea', 'Daniela',
            'Stefanie', 'Simone', 'Martina', 'Anja', 'Kerstin', 'Marion',
            'Birgit', 'Monika', 'Heike', 'Silke', 'Manuela',
            'Renate', 'Bettina', 'Cornelia', 'Doris', 'Elke',
            'Gabriele', 'Beate', 'Ute', 'Sonja', 'Tanja',
            'Michaela', 'Nadine', 'Franziska', 'Verena', 'Barbara',
            'Christine', 'Christiane', 'Dagmar', 'Gisela', 'Helga',
            'Ilona', 'Karen', 'Lydia', 'Marina', 'Rita',
        ];

        // Indices der Alleinerziehenden (4 von 50)
        $singleParentIndices = [7, 19, 31, 43];

        // Indices der 2-Kind-Familien (10 von 50)
        $twoKidIndices = [2, 8, 14, 20, 25, 30, 35, 40, 45, 48];

        $families = [];
        $childNameIndex = 0;

        for ($i = 0; $i < 50; $i++) {
            $party = new Party();
            $isSingle = in_array($i, $singleParentIndices, true);
            $hasTwoKids = in_array($i, $twoKidIndices, true);

            // Kinder
            $children = [];
            $child1Name = $childFirstNames[$childNameIndex % count($childFirstNames)];
            $children[] = ['name' => $child1Name, 'birthYear' => 2020 + ($i % 4)];
            $childNameIndex++;

            if ($hasTwoKids) {
                $child2Name = $childFirstNames[$childNameIndex % count($childFirstNames)];
                $children[] = ['name' => $child2Name, 'birthYear' => 2021 + ($i % 3)];
                $childNameIndex++;
            }

            $party->setChildren($children);

            // Eltern
            $lastName = $lastNames[$i % count($lastNames)];
            if ($isSingle) {
                // Alleinerziehend: zufällig Mutter oder Vater
                if ($i % 2 === 0) {
                    $party->setParentNames([$parentFirstNamesFemale[$i % count($parentFirstNamesFemale)] . ' ' . $lastName]);
                } else {
                    $party->setParentNames([$parentFirstNamesMale[$i % count($parentFirstNamesMale)] . ' ' . $lastName]);
                }
            } else {
                $party->setParentNames([
                    $parentFirstNamesMale[$i % count($parentFirstNamesMale)] . ' ' . $lastName,
                    $parentFirstNamesFemale[$i % count($parentFirstNamesFemale)] . ' ' . $lastName,
                ]);
            }

            // E-Mail
            $party->setEmail(strtolower(str_replace(['ü', 'ö', 'ä', 'ß', 'é'], ['ue', 'oe', 'ae', 'ss', 'e'], $lastName)) . ($i + 1) . '@example.com');

            $manager->persist($party);
            $families[] = $party;
        }

        return $families;
    }

    private function createHolidays(ObjectManager $manager, KitaYear $kitaYear): void
    {
        $holidays = [
            ['2025-10-03', 'Tag der Deutschen Einheit'],
            ['2025-10-31', 'Reformationstag'],
            ['2025-12-25', '1. Weihnachtstag'],
            ['2025-12-26', '2. Weihnachtstag'],
            ['2026-01-01', 'Neujahr'],
            ['2026-01-06', 'Heilige Drei Könige'],
            ['2026-04-03', 'Karfreitag'],
            ['2026-04-06', 'Ostermontag'],
            ['2026-05-01', 'Tag der Arbeit'],
            ['2026-05-14', 'Christi Himmelfahrt'],
            ['2026-05-25', 'Pfingstmontag'],
            ['2026-06-04', 'Fronleichnam'],
        ];

        foreach ($holidays as [$dateStr, $name]) {
            $holiday = new Holiday();
            $holiday->setDate(new \DateTimeImmutable($dateStr));
            $holiday->setName($name);
            $holiday->setKitaYear($kitaYear);
            $manager->persist($holiday);
        }
    }

    private function createVacations(ObjectManager $manager, KitaYear $kitaYear): void
    {
        $vacations = [
            ['2025-10-20', '2025-11-02', 'Herbstferien'],
            ['2025-12-22', '2026-01-04', 'Weihnachtsferien'],
            ['2026-02-16', '2026-02-20', 'Winterferien'],
            ['2026-03-30', '2026-04-10', 'Osterferien'],
            ['2026-05-26', '2026-06-05', 'Pfingstferien'],
            ['2026-07-06', '2026-08-14', 'Sommerferien'],
        ];

        foreach ($vacations as [$start, $end, $name]) {
            $vacation = new Vacation();
            $vacation->setStartDate(new \DateTimeImmutable($start));
            $vacation->setEndDate(new \DateTimeImmutable($end));
            $vacation->setName($name);
            $vacation->setKitaYear($kitaYear);
            $manager->persist($vacation);
        }
    }

    /**
     * @param Party[] $families
     */
    private function createAvailabilities(ObjectManager $manager, KitaYear $kitaYear, array $families): void
    {
        $start = $kitaYear->getStartDate();
        $end = $kitaYear->getEndDate();
        $allWorkdays = $this->getWorkdays($start, $end);

        foreach ($families as $index => $party) {
            $availability = new Availability();
            $availability->setParty($party);
            $availability->setKitaYear($kitaYear);

            // Realistische Verfügbarkeitsverteilung:
            // ~15% sehr eingeschränkt (nur 1-2 Tage/Woche)
            // ~20% eingeschränkt (2-3 Tage/Woche)
            // ~35% mittel flexibel (3-4 Tage/Woche)
            // ~25% flexibel (80-90%)
            // ~5%  sehr flexibel (alle Tage)
            $scenario = $index % 20;

            if ($scenario < 3) {
                // Sehr eingeschränkt: nur Mo+Fr oder Di+Do
                $weekdays = $scenario % 2 === 0 ? [1, 5] : [2, 4];
                $availableDates = $this->getSpecificWeekdays($allWorkdays, $weekdays);
            } elseif ($scenario < 7) {
                // Eingeschränkt: 2-3 bestimmte Tage
                $weekdays = $scenario % 2 === 0 ? [1, 3, 5] : [2, 4];
                $availableDates = $this->getSpecificWeekdays($allWorkdays, $weekdays);
            } elseif ($scenario < 14) {
                // Mittel flexibel: alle Tage außer einem festen Tag
                $excludedDay = ($scenario % 5) + 1;
                $availableDates = $this->getWeekdaysExcept($allWorkdays, [$excludedDay]);
            } elseif ($scenario < 19) {
                // Flexibel: 80-90%
                $percentage = 0.80 + (($scenario % 3) * 0.05);
                $availableDates = $this->getRandomDates($allWorkdays, $percentage);
            } else {
                // Sehr flexibel: alle Tage
                $availableDates = $allWorkdays;
            }

            $availability->setAvailableDates($availableDates);
            $manager->persist($availability);
        }
    }

    // === Hilfs-Methoden ===

    private function getWorkdays(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $workdays = [];
        $period = new \DatePeriod($start, new \DateInterval('P1D'), $end->modify('+1 day'));
        foreach ($period as $date) {
            if ((int) $date->format('N') <= 5) {
                $workdays[] = $date->format('Y-m-d');
            }
        }
        return $workdays;
    }

    private function getSpecificWeekdays(array $allDates, array $weekdays): array
    {
        return array_values(array_filter($allDates, function ($dateStr) use ($weekdays) {
            return in_array((int) (new \DateTimeImmutable($dateStr))->format('N'), $weekdays, true);
        }));
    }

    private function getWeekdaysExcept(array $allDates, array $excluded): array
    {
        return array_values(array_filter($allDates, function ($dateStr) use ($excluded) {
            return !in_array((int) (new \DateTimeImmutable($dateStr))->format('N'), $excluded, true);
        }));
    }

    private function getRandomDates(array $allDates, float $percentage): array
    {
        $count = max(1, (int) (count($allDates) * $percentage));
        $keys = (array) array_rand($allDates, min($count, count($allDates)));
        $selected = array_map(fn($k) => $allDates[$k], $keys);
        sort($selected);
        return $selected;
    }
}
