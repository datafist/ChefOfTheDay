<?php

namespace App\DataFixtures;

use App\Entity\Holiday;
use App\Entity\KitaYear;
use App\Entity\Party;
use App\Entity\User;
use App\Entity\Vacation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Admin User erstellen
        $admin = new User();
        $admin->setEmail('admin@kita.local');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        // Kita-Jahr 2024/2025 erstellen
        $kitaYear = new KitaYear();
        $kitaYear->setStartDate(new \DateTimeImmutable('2024-09-01'));
        $kitaYear->setEndDate(new \DateTimeImmutable('2025-08-31'));
        $kitaYear->setIsActive(true);
        $manager->persist($kitaYear);

        // Beispiel-Familien (6 Familien für einfache Demo)
        // Für umfangreiche Tests: php bin/console doctrine:fixtures:load --group=large-scale
        $families = [
            [['name' => 'Max', 'birthYear' => 2019], ['Maria Müller', 'Thomas Müller'], 'mueller@example.com'],
            [['name' => 'Sophie', 'birthYear' => 2020], ['Anna Schmidt'], 'schmidt@example.com'],
            [['name' => 'Leon', 'birthYear' => 2018], ['Julia Weber', 'Michael Weber'], 'weber@example.com'],
            [['name' => 'Emma', 'birthYear' => 2021], ['Sandra Meier', 'Frank Meier'], 'meier@example.com'],
            [['name' => 'Noah', 'birthYear' => 2019], ['Lisa Schulz'], 'schulz@example.com'],
            [['name' => 'Mia', 'birthYear' => 2020], ['Kathrin Fischer', 'Martin Fischer'], 'fischer@example.com'],
        ];

        foreach ($families as $familyData) {
            $party = new Party();
            $party->setChildren([$familyData[0]]);
            $party->setParentNames($familyData[1]);
            $party->setEmail($familyData[2]);
            $manager->persist($party);
        }

        // Feiertage für 2024/2025
        $holidays = [
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

        foreach ($holidays as $holidayData) {
            $holiday = new Holiday();
            $holiday->setDate(new \DateTimeImmutable($holidayData[0]));
            $holiday->setName($holidayData[1]);
            $holiday->setKitaYear($kitaYear);
            $manager->persist($holiday);
        }

        // Ferienzeiten
        $vacations = [
            ['2024-10-21', '2024-11-03', 'Herbstferien'],
            ['2024-12-23', '2025-01-05', 'Weihnachtsferien'],
            ['2025-04-14', '2025-04-25', 'Osterferien'],
            ['2025-07-07', '2025-08-15', 'Sommerferien'],
        ];

        foreach ($vacations as $vacationData) {
            $vacation = new Vacation();
            $vacation->setStartDate(new \DateTimeImmutable($vacationData[0]));
            $vacation->setEndDate(new \DateTimeImmutable($vacationData[1]));
            $vacation->setName($vacationData[2]);
            $vacation->setKitaYear($kitaYear);
            $manager->persist($vacation);
        }

        $manager->flush();
    }
}
