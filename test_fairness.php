#!/usr/bin/env php
<?php

// Test-Skript: Plan generieren und Verteilung analysieren

require __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

$entityManager = $container->get('doctrine')->getManager();
$planGenerator = $container->get('App\Service\CookingPlanGenerator');
$kitaYearRepo = $entityManager->getRepository('App\Entity\KitaYear');
$partyRepo = $entityManager->getRepository('App\Entity\Party');

// Hole aktives Kita-Jahr
$kitaYear = $kitaYearRepo->findOneBy(['isActive' => true]);

if (!$kitaYear) {
    echo "Kein aktives Kita-Jahr gefunden!\n";
    exit(1);
}

echo "Generiere Plan für Kita-Jahr: " . $kitaYear->getStartDate()->format('Y') . "/" . $kitaYear->getEndDate()->format('Y') . "\n";
echo "===========================================\n\n";

// Generiere Plan
$result = $planGenerator->generatePlan($kitaYear);

echo "Ergebnis:\n";
echo "  - " . count($result['assignments']) . " Zuweisungen erstellt\n";
echo "  - " . count($result['conflicts']) . " Konflikte\n\n";

if (!empty($result['conflicts'])) {
    echo "Konflikte:\n";
    foreach (array_slice($result['conflicts'], 0, 5) as $conflict) {
        echo "  - $conflict\n";
    }
    if (count($result['conflicts']) > 5) {
        echo "  - ... und " . (count($result['conflicts']) - 5) . " weitere\n";
    }
    echo "\n";
}

// Speichere Zuweisungen
$planGenerator->saveAssignments($result['assignments']);
echo "Zuweisungen gespeichert!\n\n";

// Analyse der Verteilung
echo "Analyse nach Elternanzahl:\n";
echo "==========================\n\n";

$parties = $partyRepo->findAll();
$singleParents = [];
$couples = [];

foreach ($parties as $party) {
    $count = 0;
    foreach ($result['assignments'] as $assignment) {
        if ($assignment->getParty()->getId() === $party->getId()) {
            $count++;
        }
    }
    
    $data = [
        'party' => $party,
        'count' => $count
    ];
    
    if ($party->isSingleParent()) {
        $singleParents[] = $data;
    } else {
        $couples[] = $data;
    }
}

// Sortiere nach Anzahl Dienste
usort($singleParents, fn($a, $b) => $b['count'] <=> $a['count']);
usort($couples, fn($a, $b) => $b['count'] <=> $a['count']);

echo "Alleinerziehende (" . count($singleParents) . " Familien):\n";
$singleCounts = array_map(fn($d) => $d['count'], $singleParents);
$singleMin = !empty($singleCounts) ? min($singleCounts) : 0;
$singleMax = !empty($singleCounts) ? max($singleCounts) : 0;
$singleAvg = !empty($singleCounts) ? round(array_sum($singleCounts) / count($singleCounts), 1) : 0;

echo "  Min: $singleMin, Max: $singleMax, Durchschnitt: $singleAvg\n";
foreach (array_slice($singleParents, 0, 5) as $data) {
    echo "  - " . $data['party']->getChildrenNames() . ": " . $data['count'] . " Dienste\n";
}
if (count($singleParents) > 5) {
    echo "  - ... und " . (count($singleParents) - 5) . " weitere\n";
}

echo "\nPaare (" . count($couples) . " Familien):\n";
$coupleCounts = array_map(fn($d) => $d['count'], $couples);
$coupleMin = !empty($coupleCounts) ? min($coupleCounts) : 0;
$coupleMax = !empty($coupleCounts) ? max($coupleCounts) : 0;
$coupleAvg = !empty($coupleCounts) ? round(array_sum($coupleCounts) / count($coupleCounts), 1) : 0;

echo "  Min: $coupleMin, Max: $coupleMax, Durchschnitt: $coupleAvg\n";
foreach (array_slice($couples, 0, 5) as $data) {
    echo "  - " . $data['party']->getChildrenNames() . ": " . $data['count'] . " Dienste\n";
}
if (count($couples) > 5) {
    echo "  - ... und " . (count($couples) - 5) . " weitere\n";
}

echo "\n✅ ERFOLG: Alleinerziehende sollten jetzt MAXIMAL $singleMax Dienste haben,\n";
echo "          während Paare durchschnittlich $coupleAvg haben.\n";

if ($singleMax > $coupleMin) {
    echo "\n⚠️  WARNUNG: Es gibt Alleinerziehende mit mehr Diensten als manche Paare!\n";
    echo "          Dies sollte NICHT passieren!\n";
    exit(1);
} else {
    echo "\n✅ PERFEKT: Alle Alleinerziehenden haben weniger oder gleich viele Dienste wie Paare!\n";
}
