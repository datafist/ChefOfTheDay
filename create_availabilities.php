#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

$entityManager = $container->get('doctrine')->getManager();

// Lade Kita-Jahr und Familien
$kitaYear = $entityManager->getRepository(\App\Entity\KitaYear::class)->findOneBy(['isActive' => true]);
$parties = $entityManager->getRepository(\App\Entity\Party::class)->findAll();

if (!$kitaYear) {
    echo "Kein aktives Kita-Jahr gefunden!\n";
    exit(1);
}

echo "Erstelle Verfügbarkeiten für " . count($parties) . " Familien...\n";

// Erstelle alle Werktage zwischen Start und Ende
$start = $kitaYear->getStartDate();
$end = $kitaYear->getEndDate();
$period = new \DatePeriod($start, new \DateInterval('P1D'), $end->modify('+1 day'));

$availableDates = [];
foreach ($period as $date) {
    $dayOfWeek = (int)$date->format('N');
    if ($dayOfWeek >= 1 && $dayOfWeek <= 5) { // Mo-Fr
        $availableDates[] = $date->format('Y-m-d');
    }
}

echo "Verfügbare Werktage: " . count($availableDates) . "\n\n";

foreach ($parties as $party) {
    // Prüfe ob bereits Verfügbarkeit existiert
    $existing = $entityManager->getRepository(\App\Entity\Availability::class)->findOneBy([
        'party' => $party,
        'kitaYear' => $kitaYear
    ]);
    
    if ($existing) {
        echo "✓ {$party->getChildName()}: Verfügbarkeit existiert bereits\n";
        continue;
    }
    
    $availability = new \App\Entity\Availability();
    $availability->setParty($party);
    $availability->setKitaYear($kitaYear);
    $availability->setAvailableDates($availableDates); // Alle Werktage verfügbar
    
    $entityManager->persist($availability);
    echo "✓ {$party->getChildName()}: Verfügbarkeit erstellt (" . count($availableDates) . " Tage)\n";
}

$entityManager->flush();

echo "\n✅ Alle Verfügbarkeiten erstellt!\n";
