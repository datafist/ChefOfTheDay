#!/usr/bin/env php
<?php

/**
 * Erstellt LastYearCooking Einträge aus den CookingAssignments des aktuellen Jahres
 * 
 * Verwendung: Am Ende eines Kita-Jahres (z.B. Ende August) ausführen,
 * BEVOR das neue Kita-Jahr erstellt wird.
 * 
 * Das Script findet für jede Familie die letzte Zuweisung des aktuellen Jahres
 * und speichert diese als LastYearCooking für die Verwendung im nächsten Jahr.
 */

require __DIR__.'/../vendor/autoload.php';

use App\Entity\LastYearCooking;
use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/../.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  LastYearCooking Generator                                   ║\n";
echo "║  Bereitet Daten für Jahr-Übergang vor                        ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Finde aktives Kita-Jahr
$kitaYear = $em->getRepository(\App\Entity\KitaYear::class)
    ->findOneBy(['isActive' => true]);

if (!$kitaYear) {
    echo "❌ FEHLER: Kein aktives Kita-Jahr gefunden!\n";
    echo "   Bitte stellen Sie sicher, dass ein Kita-Jahr als 'aktiv' markiert ist.\n\n";
    exit(1);
}

echo "📅 Aktives Kita-Jahr: {$kitaYear->getStartDate()->format('d.m.Y')} - {$kitaYear->getEndDate()->format('d.m.Y')}\n";
echo "\n";

// Finde alle Familien
$parties = $em->getRepository(\App\Entity\Party::class)->findAll();

if (empty($parties)) {
    echo "❌ FEHLER: Keine Familien gefunden!\n\n";
    exit(1);
}

echo "👨‍👩‍👧‍👦 Gefundene Familien: " . count($parties) . "\n";
echo str_repeat("─", 66) . "\n\n";

$created = 0;
$updated = 0;
$skipped = 0;
$noAssignment = 0;

foreach ($parties as $party) {
    $familyName = $party->getChildName();
    
    // Finde letzte Zuweisung dieser Familie im aktuellen Jahr
    $lastAssignment = $em->getRepository(\App\Entity\CookingAssignment::class)
        ->createQueryBuilder('ca')
        ->where('ca.party = :party')
        ->andWhere('ca.kitaYear = :kitaYear')
        ->setParameter('party', $party)
        ->setParameter('kitaYear', $kitaYear)
        ->orderBy('ca.assignedDate', 'DESC')
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
    
    if (!$lastAssignment) {
        echo "⚠️  {$familyName}: Keine Zuweisung in diesem Jahr\n";
        $noAssignment++;
        continue;
    }
    
    $lastDate = $lastAssignment->getAssignedDate();
    
    // Prüfe ob bereits LastYearCooking existiert
    $existing = $em->getRepository(LastYearCooking::class)
        ->findOneBy([
            'party' => $party,
            'kitaYear' => $kitaYear
        ]);
    
    if ($existing) {
        $oldDate = $existing->getLastCookingDate();
        
        // Aktualisiere nur wenn neues Datum später ist
        if ($lastDate > $oldDate) {
            $existing->setLastCookingDate($lastDate);
            echo "🔄 {$familyName}: Aktualisiert ({$oldDate->format('d.m.Y')} → {$lastDate->format('d.m.Y')})\n";
            $updated++;
        } else {
            echo "✓  {$familyName}: Bereits vorhanden ({$oldDate->format('d.m.Y')})\n";
            $skipped++;
        }
        continue;
    }
    
    // Erstelle neuen LastYearCooking Eintrag
    $lastYearCooking = new LastYearCooking();
    $lastYearCooking->setParty($party);
    $lastYearCooking->setKitaYear($kitaYear);
    $lastYearCooking->setLastCookingDate($lastDate);
    
    $em->persist($lastYearCooking);
    
    echo "✅ {$familyName}: Erstellt ({$lastDate->format('d.m.Y')})\n";
    $created++;
}

// Speichere alle Änderungen
$em->flush();

echo "\n";
echo str_repeat("─", 66) . "\n";
echo "📊 Zusammenfassung:\n";
echo "   • Neu erstellt:      {$created}\n";
echo "   • Aktualisiert:      {$updated}\n";
echo "   • Bereits vorhanden: {$skipped}\n";
echo "   • Keine Zuweisung:   {$noAssignment}\n";
echo str_repeat("─", 66) . "\n";

if ($created > 0 || $updated > 0) {
    echo "\n";
    echo "✅ Erfolgreich! Die LastYearCooking Einträge wurden gespeichert.\n";
    echo "\n";
    echo "📌 Nächste Schritte:\n";
    echo "   1. Neues Kita-Jahr erstellen (Admin-Interface)\n";
    echo "   2. Neuen Kochplan generieren\n";
    echo "   3. Die letzten Zuweisungen aus diesem Jahr werden automatisch\n";
    echo "      berücksichtigt, um zu kurze Abstände zu vermeiden.\n";
} else {
    echo "\n";
    echo "ℹ️  Keine neuen Einträge erstellt.\n";
}

echo "\n";
