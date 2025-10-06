#!/usr/bin/env php
<?php

/**
 * Test-Skript für jahresübergreifende Fairness
 * 
 * Zeigt die Verteilung der Kochdienste über mehrere Jahre und demonstriert
 * die Rotation der "Mehr-Last" zwischen Familien.
 */

require __DIR__ . '/vendor/autoload.php';

use App\Entity\CookingAssignment;
use App\Entity\LastYearCooking;
use App\Entity\Party;
use Symfony\Component\Dotenv\Dotenv;

// Lade Umgebungsvariablen
(new Dotenv())->bootEnv(__DIR__ . '/.env');

// Erstelle Kernel und Container
$kernel = new App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

$entityManager = $container->get('doctrine')->getManager();

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║   Jahresübergreifende Fairness - Analyse                     ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Finde aktives Kita-Jahr
$kitaYearRepo = $entityManager->getRepository(\App\Entity\KitaYear::class);
$activeYear = $kitaYearRepo->findOneBy(['isActive' => true]);

if (!$activeYear) {
    echo "❌ Kein aktives Kita-Jahr gefunden.\n";
    exit(1);
}

echo "📅 Aktives Jahr: " . $activeYear->getYearString() . "\n";
echo "\n";

// Lade alle Familien
$partyRepo = $entityManager->getRepository(Party::class);
$parties = $partyRepo->findAll();

if (empty($parties)) {
    echo "❌ Keine Familien gefunden.\n";
    exit(1);
}

echo "👨‍👩‍👧‍👦 Anzahl Familien: " . count($parties) . "\n";
echo "\n";

// Prüfe ob Vorjahr-Daten existieren
$lastYearRepo = $entityManager->getRepository(LastYearCooking::class);
$hasLastYearData = $lastYearRepo->count([]) > 0;

if (!$hasLastYearData) {
    echo "⚠️  Keine Vorjahr-Daten vorhanden.\n";
    echo "    Die jahresübergreifende Fairness greift erst ab dem zweiten Jahr.\n";
    echo "\n";
}

// Prüfe ob aktueller Plan existiert
$assignmentRepo = $entityManager->getRepository(CookingAssignment::class);
$hasCurrentPlan = $assignmentRepo->count(['kitaYear' => $activeYear]) > 0;

if (!$hasCurrentPlan) {
    echo "⚠️  Noch kein Plan für " . $activeYear->getYearString() . " generiert.\n";
    echo "    Bitte Plan im Admin-Dashboard generieren.\n";
    echo "\n";
    exit(0);
}

echo "═══════════════════════════════════════════════════════════════\n";
echo " VERTEILUNGS-ANALYSE\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "\n";

// Sammle Daten
$data = [];
foreach ($parties as $party) {
    $partyId = $party->getId();
    
    // Vorjahr
    $lastYearCooking = $lastYearRepo->findOneBy(['party' => $party]);
    $lastYearCount = $lastYearCooking ? $lastYearCooking->getCookingCount() : 0;
    
    // Aktuelles Jahr
    $currentYearCount = $assignmentRepo->count([
        'party' => $party,
        'kitaYear' => $activeYear
    ]);
    
    $data[] = [
        'party' => $party,
        'lastYear' => $lastYearCount,
        'currentYear' => $currentYearCount,
        'total' => $lastYearCount + $currentYearCount,
        'diff' => $currentYearCount - $lastYearCount,
    ];
}

// Sortiere nach Differenz (größte Veränderung zuerst)
usort($data, fn($a, $b) => abs($b['diff']) <=> abs($a['diff']));

echo "Legende:\n";
echo "  Vorjahr:  Dienste im vorherigen Kita-Jahr\n";
echo "  Aktuell:  Dienste im aktuellen Jahr\n";
echo "  Diff:     Veränderung (positiv = mehr, negativ = weniger)\n";
echo "  Total:    Gesamtbelastung über beide Jahre\n";
echo "\n";

// Zeige Top 20 größte Veränderungen
echo "TOP 20 - Größte Veränderungen:\n";
echo "─────────────────────────────────────────────────────────────\n";
printf("%-30s %8s %8s %6s %7s\n", "Familie", "Vorjahr", "Aktuell", "Diff", "Total");
echo "─────────────────────────────────────────────────────────────\n";

$countUp = 0;
$countDown = 0;
$countSame = 0;

foreach (array_slice($data, 0, 20) as $row) {
    $party = $row['party'];
    $name = $party->getName();
    if (strlen($name) > 28) {
        $name = substr($name, 0, 25) . '...';
    }
    
    $diffStr = $row['diff'] > 0 ? '+' . $row['diff'] : (string)$row['diff'];
    $indicator = '';
    if ($row['diff'] > 0) {
        $indicator = '↑';
        $countUp++;
    } elseif ($row['diff'] < 0) {
        $indicator = '↓';
        $countDown++;
    } else {
        $indicator = '→';
        $countSame++;
    }
    
    printf(
        "%-30s %8d %8d %5s %1s %7d\n",
        $name,
        $row['lastYear'],
        $row['currentYear'],
        $diffStr,
        $indicator,
        $row['total']
    );
}

echo "─────────────────────────────────────────────────────────────\n";
echo "\n";

// Statistiken
echo "═══════════════════════════════════════════════════════════════\n";
echo " STATISTIKEN\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "\n";

$totalLastYear = array_sum(array_column($data, 'lastYear'));
$totalCurrentYear = array_sum(array_column($data, 'currentYear'));
$avgLastYear = $totalLastYear / count($data);
$avgCurrentYear = $totalCurrentYear / count($data);

echo "Gesamt:\n";
echo "  Vorjahr:        $totalLastYear Dienste\n";
echo "  Aktuelles Jahr: $totalCurrentYear Dienste\n";
echo "\n";

echo "Durchschnitt pro Familie:\n";
printf("  Vorjahr:        %.2f Dienste\n", $avgLastYear);
printf("  Aktuelles Jahr: %.2f Dienste\n", $avgCurrentYear);
echo "\n";

echo "Veränderungen:\n";
echo "  ↑ Mehr Dienste:    $countUp Familien\n";
echo "  ↓ Weniger Dienste: $countDown Familien\n";
echo "  → Gleich:          $countSame Familien\n";
echo "\n";

// Fairness-Index
$lastYearCounts = array_column($data, 'lastYear');
$currentYearCounts = array_column($data, 'currentYear');

$maxLastYear = max($lastYearCounts);
$minLastYear = min($lastYearCounts);
$maxCurrentYear = max($currentYearCounts);
$minCurrentYear = min($currentYearCounts);

echo "Verteilungs-Spannweite:\n";
echo "  Vorjahr:        Min: $minLastYear, Max: $maxLastYear (Differenz: " . ($maxLastYear - $minLastYear) . ")\n";
echo "  Aktuelles Jahr: Min: $minCurrentYear, Max: $maxCurrentYear (Differenz: " . ($maxCurrentYear - $minCurrentYear) . ")\n";
echo "\n";

// Rotation-Analyse
if ($hasLastYearData) {
    echo "═══════════════════════════════════════════════════════════════\n";
    echo " ROTATION-ANALYSE\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "\n";
    
    // Wer hatte letztes Jahr viel und hat jetzt weniger?
    $rotatedDown = array_filter($data, fn($d) => $d['lastYear'] >= $avgLastYear && $d['currentYear'] < $d['lastYear']);
    
    // Wer hatte letztes Jahr wenig und hat jetzt mehr?
    $rotatedUp = array_filter($data, fn($d) => $d['lastYear'] <= $avgLastYear && $d['currentYear'] > $d['lastYear']);
    
    echo "Erfolgreiche Rotation:\n";
    echo "  Entlastete Familien: " . count($rotatedDown) . " (hatten ≥Ø, jetzt weniger)\n";
    echo "  Aufgestockte Familien: " . count($rotatedUp) . " (hatten ≤Ø, jetzt mehr)\n";
    echo "\n";
    
    if (count($rotatedDown) > 0 || count($rotatedUp) > 0) {
        echo "✅ Die jahresübergreifende Fairness funktioniert!\n";
        echo "   Familien mit hoher Last im Vorjahr wurden entlastet.\n";
    } else {
        echo "⚠️  Keine Rotation erkennbar.\n";
        echo "   Mögliche Gründe:\n";
        echo "   - Erstes Jahr mit diesem Feature\n";
        echo "   - Verfügbarkeiten haben Rotation verhindert\n";
        echo "   - Sehr gleichmäßige Verteilung\n";
    }
    echo "\n";
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "\n";

// Beispiel: Wer hatte die größte Verbesserung?
$bestImprovement = array_reduce($data, fn($carry, $item) => 
    $carry === null || $item['diff'] < $carry['diff'] ? $item : $carry
);

// Wer hatte die größte Verschlechterung?
$worstChange = array_reduce($data, fn($carry, $item) => 
    $carry === null || $item['diff'] > $carry['diff'] ? $item : $carry
);

echo "🏆 Größte Entlastung:\n";
echo "   " . $bestImprovement['party']->getName() . "\n";
echo "   Vorjahr: " . $bestImprovement['lastYear'] . ", Aktuell: " . $bestImprovement['currentYear'];
echo " (Differenz: " . $bestImprovement['diff'] . ")\n";
echo "\n";

echo "⚖️  Größte Belastung:\n";
echo "   " . $worstChange['party']->getName() . "\n";
echo "   Vorjahr: " . $worstChange['lastYear'] . ", Aktuell: " . $worstChange['currentYear'];
echo " (Differenz: +" . $worstChange['diff'] . ")\n";
echo "\n";

echo "═══════════════════════════════════════════════════════════════\n";
echo "Fertig!\n";
echo "\n";
