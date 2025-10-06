#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

$entityManager = $container->get('doctrine')->getManager();

// Hole alle Zuweisungen sortiert nach Partei und Datum
$sql = "
    SELECT party_id, assigned_date 
    FROM cooking_assignments 
    ORDER BY party_id, assigned_date
";

$stmt = $entityManager->getConnection()->prepare($sql);
$result = $stmt->executeQuery();
$assignments = $result->fetchAllAssociative();

// Gruppiere nach Partei
$byParty = [];
foreach ($assignments as $assignment) {
    $partyId = $assignment['party_id'];
    if (!isset($byParty[$partyId])) {
        $byParty[$partyId] = [];
    }
    $byParty[$partyId][] = new DateTime($assignment['assigned_date']);
}

// Berechne Intervalle
$intervals = [];
foreach ($byParty as $partyId => $dates) {
    for ($i = 0; $i < count($dates) - 1; $i++) {
        $diff = $dates[$i]->diff($dates[$i + 1])->days;
        $intervals[] = [
            'party_id' => $partyId,
            'date1' => $dates[$i]->format('Y-m-d'),
            'date2' => $dates[$i + 1]->format('Y-m-d'),
            'days' => $diff
        ];
    }
}

// Sortiere nach Tagen
usort($intervals, function($a, $b) {
    return $a['days'] <=> $b['days'];
});

// Hole Parteinamen
$partyNames = [];
$partyStmt = $entityManager->getConnection()->prepare("SELECT id, parent_names FROM parties");
$partyResult = $partyStmt->executeQuery();
foreach ($partyResult->fetchAllAssociative() as $party) {
    $partyNames[$party['id']] = json_decode($party['parent_names'])[0] ?? 'Unbekannt';
}

// Ausgabe
echo "\n=== ANALYSE DER ABSTÄNDE ZWISCHEN KOCHDIENSTEN ===\n\n";

echo "TOP 30 KÜRZESTE ABSTÄNDE:\n";
echo str_repeat("-", 100) . "\n";
printf("%-5s %-25s %-12s %-12s %-10s\n", "ID", "Name", "Datum 1", "Datum 2", "Tage");
echo str_repeat("-", 100) . "\n";

foreach (array_slice($intervals, 0, 30) as $interval) {
    printf(
        "%-5d %-25s %-12s %-12s %-10d\n",
        $interval['party_id'],
        substr($partyNames[$interval['party_id']], 0, 25),
        $interval['date1'],
        $interval['date2'],
        $interval['days']
    );
}

echo "\n";

// Statistik
$totalIntervals = count($intervals);
$minDays = $intervals[0]['days'];
$maxDays = $intervals[count($intervals) - 1]['days'];
$avgDays = array_sum(array_column($intervals, 'days')) / $totalIntervals;

$under60 = count(array_filter($intervals, fn($i) => $i['days'] < 60));
$under56 = count(array_filter($intervals, fn($i) => $i['days'] < 56));
$under45 = count(array_filter($intervals, fn($i) => $i['days'] < 45));

echo "\n=== STATISTIK ===\n";
echo "Gesamt Übergänge: $totalIntervals\n";
echo "Minimum: $minDays Tage\n";
echo "Maximum: $maxDays Tage\n";
echo "Durchschnitt: " . round($avgDays, 1) . " Tage\n";
echo "\n";
echo "< 45 Tage: $under45 (" . round($under45/$totalIntervals*100, 1) . "%)\n";
echo "< 56 Tage: $under56 (" . round($under56/$totalIntervals*100, 1) . "%)\n";
echo "< 60 Tage: $under60 (" . round($under60/$totalIntervals*100, 1) . "%)\n";

echo "\n=== JAHRESÜBERGANG (August 2025 → September 2025) ===\n";
echo str_repeat("-", 100) . "\n";

$yearTransitions = array_filter($intervals, function($interval) {
    return $interval['date1'] >= '2025-08-01' && $interval['date1'] <= '2025-08-31' 
        && $interval['date2'] >= '2025-09-01' && $interval['date2'] <= '2025-09-30';
});

if (empty($yearTransitions)) {
    echo "✅ Keine direkten Übergänge von August auf September!\n";
} else {
    printf("%-5s %-25s %-12s %-12s %-10s\n", "ID", "Name", "August", "September", "Tage");
    echo str_repeat("-", 100) . "\n";
    foreach ($yearTransitions as $interval) {
        printf(
            "%-5d %-25s %-12s %-12s %-10d %s\n",
            $interval['party_id'],
            substr($partyNames[$interval['party_id']], 0, 25),
            $interval['date1'],
            $interval['date2'],
            $interval['days'],
            $interval['days'] < 60 ? '⚠️' : '✅'
        );
    }
}

echo "\n";
