#!/usr/bin/env php
<?php

/**
 * Berechnet und zeigt die dynamischen Abstände für die aktuelle Konfiguration
 */

require __DIR__.'/../vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/../.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  Dynamische Abstands-Berechnung                              ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Lade Kita-Jahr
$kitaYear = $em->getRepository(\App\Entity\KitaYear::class)->findOneBy(['isActive' => true]);

if (!$kitaYear) {
    echo "❌ Kein aktives Kita-Jahr gefunden!\n\n";
    exit(1);
}

echo "📅 Kita-Jahr: {$kitaYear->getStartDate()->format('d.m.Y')} - {$kitaYear->getEndDate()->format('d.m.Y')}\n\n";

// Lade Familien
$parties = $em->getRepository(\App\Entity\Party::class)->findAll();
echo "👨‍👩‍👧‍👦 Familien: " . count($parties) . "\n";

$totalWeight = 0;
$partyInfo = [];
foreach ($parties as $party) {
    $weight = $party->isSingleParent() ? 1 : 2;
    $totalWeight += $weight;
    $type = $party->isSingleParent() ? 'Alleinerziehend' : 'Paar';
    $partyInfo[] = "   - {$party->getChildName()}: $type (Gewicht: $weight)";
}

foreach ($partyInfo as $info) {
    echo "$info\n";
}

echo "\n📊 Gesamt-Gewicht: $totalWeight\n\n";

// Zähle verfügbare Tage (ohne Ferien/Feiertage/Wochenenden)
$holidayRepo = $em->getRepository(\App\Entity\Holiday::class);
$vacationRepo = $em->getRepository(\App\Entity\Vacation::class);

$excludedDates = [];

// Feiertage
$holidays = $holidayRepo->findBy(['kitaYear' => $kitaYear]);
foreach ($holidays as $holiday) {
    $excludedDates[$holiday->getDate()->format('Y-m-d')] = true;
}

// Ferien
$vacations = $vacationRepo->findBy(['kitaYear' => $kitaYear]);
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

$availableDays = 0;
foreach ($period as $date) {
    $dayOfWeek = (int)$date->format('N');
    if ($dayOfWeek === 6 || $dayOfWeek === 7) {
        $excludedDates[$date->format('Y-m-d')] = true;
    }
    
    if (!isset($excludedDates[$date->format('Y-m-d')])) {
        $availableDays++;
    }
}

echo "📆 Verfügbare Kochdienst-Tage: $availableDays\n";
echo "   (Mo-Fr ohne Feiertage/Ferien)\n\n";

// Berechne Dienste pro Gewichtseinheit
$servicesPerWeightUnit = $availableDays / $totalWeight;

echo "🔢 Durchschnittliche Dienste:\n";
echo "   Pro Gewichtseinheit: " . round($servicesPerWeightUnit, 1) . "\n";
echo "   Pro Paar (Gewicht 2): " . round($servicesPerWeightUnit * 2, 1) . " Dienste/Jahr\n";
echo "   Pro Alleinerziehend (Gewicht 1): " . round($servicesPerWeightUnit * 1, 1) . " Dienste/Jahr\n\n";

// Berechne durchschnittliche Abstände
$avgDaysForPairs = (int)floor($availableDays / ($servicesPerWeightUnit * 2));
$avgDaysForSingle = (int)floor($availableDays / ($servicesPerWeightUnit * 1));

echo "📏 Durchschnittliche Abstände:\n";
echo "   Paare: ~$avgDaysForPairs Tage (" . round($avgDaysForPairs / 7, 1) . " Wochen)\n";
echo "   Alleinerziehende: ~$avgDaysForSingle Tage (" . round($avgDaysForSingle / 7, 1) . " Wochen)\n\n";

// Berechne Target und Min (wie im Generator)
$targetDays = max(7, (int)($avgDaysForPairs * 0.8));
$minDays = max(4, (int)($avgDaysForPairs * 0.5));

if ($minDays > $targetDays) {
    $minDays = $targetDays;
}

echo str_repeat("═", 66) . "\n";
echo "🎯 BERECHNETE ABSTÄNDE (dynamisch):\n";
echo str_repeat("═", 66) . "\n";
echo "   TARGET (bevorzugt): $targetDays Tage (" . round($targetDays / 7, 1) . " Wochen)\n";
echo "   MINIMUM (Notfall):  $minDays Tage (" . round($minDays / 7, 1) . " Wochen)\n";
echo str_repeat("═", 66) . "\n\n";

echo "💡 Bedeutung:\n";
echo "   • Familien mit ≥ $targetDays Tagen Abstand werden BEVORZUGT\n";
echo "   • Familien mit $minDays-$targetDays Tagen werden im NOTFALL zugewiesen\n";
echo "   • Familien mit < $minDays Tagen werden BLOCKIERT\n\n";

echo "✅ Diese Werte werden automatisch bei jeder Plan-Generierung berechnet!\n\n";
