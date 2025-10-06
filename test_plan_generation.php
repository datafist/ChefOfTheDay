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
$generator = $container->get(\App\Service\CookingPlanGenerator::class);

// Lade Kita-Jahr
$kitaYear = $entityManager->getRepository(\App\Entity\KitaYear::class)->findOneBy(['isActive' => true]);

if (!$kitaYear) {
    echo "Kein aktives Kita-Jahr gefunden!\n";
    exit(1);
}

echo "Generiere Plan für Kita-Jahr {$kitaYear->getStartDate()->format('Y-m-d')} bis {$kitaYear->getEndDate()->format('Y-m-d')}...\n\n";

// Lösche alte Zuweisungen
$oldAssignments = $entityManager->getRepository(\App\Entity\CookingAssignment::class)->findBy([
    'kitaYear' => $kitaYear,
    'isManuallyAssigned' => false
]);

foreach ($oldAssignments as $assignment) {
    $entityManager->remove($assignment);
}
$entityManager->flush();

// Generiere Plan
$result = $generator->generatePlan($kitaYear);

echo "Zuweisungen: " . count($result['assignments']) . "\n";
echo "Konflikte: " . count($result['conflicts']) . "\n\n";

if (!empty($result['conflicts'])) {
    echo "⚠️ KONFLIKTE:\n";
    foreach ($result['conflicts'] as $conflict) {
        echo "  - $conflict\n";
    }
    echo "\n";
}

// Speichere Zuweisungen
$generator->saveAssignments($result['assignments']);

echo "✅ Plan gespeichert!\n\n";

// Zeige die ersten 10 Zuweisungen
echo "Erste 10 Zuweisungen:\n";
echo str_repeat("-", 60) . "\n";

$count = 0;
foreach ($result['assignments'] as $assignment) {
    if ($count >= 10) break;
    
    $date = $assignment->getAssignedDate()->format('d.m.Y (l)');
    $family = $assignment->getParty()->getChildName();
    
    echo sprintf("%-20s -> %s\n", $date, $family);
    $count++;
}

echo str_repeat("-", 60) . "\n";

// Prüfe speziell: Wann wird Max zugewiesen?
$maxParty = $entityManager->getRepository(\App\Entity\Party::class)->findOneBy(['childName' => 'Max']);
if ($maxParty) {
    $maxAssignments = [];
    foreach ($result['assignments'] as $assignment) {
        if ($assignment->getParty()->getId() === $maxParty->getId()) {
            $maxAssignments[] = $assignment->getAssignedDate()->format('Y-m-d');
        }
    }
    
    echo "\n🔍 Max (hatte 31.08.2024) wird zugewiesen an:\n";
    $first = true;
    foreach (array_slice($maxAssignments, 0, 3) as $date) {
        $daysSince = (new DateTimeImmutable('2024-08-31'))->diff(new DateTimeImmutable($date))->days;
        $weeks = round($daysSince / 7, 1);
        $status = $first ? ($daysSince < 28 ? "❌ ZU KURZ!" : ($daysSince < 42 ? "⚠️ Notfall (4-6 Wochen)" : "✅ Optimal (6+ Wochen)")) : "";
        echo "  - $date ($daysSince Tage = $weeks Wochen) $status\n";
        $first = false;
    }
}
