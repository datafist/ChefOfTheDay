#!/usr/bin/env php
<?php

use App\Kernel;
use App\Service\CookingPlanGenerator;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

    $kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
    $kernel->boot();
    $container = $kernel->getContainer();
    $entityManager = $container->get('doctrine')->getManager();
    $planGenerator = $container->get(CookingPlanGenerator::class);

    echo "\n=== TESTE PLAN-GENERIERUNG FÜR 25/26 ===\n\n";

    // Hole aktives Kita-Jahr
    $kitaYear = $entityManager->getRepository('App\Entity\KitaYear')->findOneBy(['isActive' => true]);
    
    if (!$kitaYear) {
        echo "❌ Kein aktives Kita-Jahr gefunden!\n";
        return 1;
    }

    echo "Aktives Jahr: " . $kitaYear->getStartDate()->format('Y-m-d') . " bis " . $kitaYear->getEndDate()->format('Y-m-d') . "\n\n";

    // Generiere Plan
    echo "⏳ Generiere Kochplan...\n";
    $result = $planGenerator->generatePlan($kitaYear);

    echo "\n--- ERGEBNIS ---\n";
    echo "Zuweisungen: " . count($result['assignments']) . "\n";
    echo "Konflikte: " . count($result['conflicts']) . "\n\n";

    if (!empty($result['conflicts'])) {
        echo "⚠️  KONFLIKTE:\n";
        foreach ($result['conflicts'] as $conflict) {
            echo "   - " . $conflict . "\n";
        }
        echo "\n";
    }

    // Speichere Zuweisungen
    echo "💾 Speichere Zuweisungen...\n";
    $planGenerator->saveAssignments($result['assignments']);
    echo "✅ Gespeichert!\n\n";

    // Prüfe speziell die August-Tage
    $testDates = ['2026-08-25', '2026-08-26', '2026-08-27', '2026-08-28', '2026-08-31'];
    
    echo "=== PRÜFE AUGUST-TAGE ===\n\n";
    
    $assignmentsByDate = [];
    foreach ($result['assignments'] as $assignment) {
        $dateStr = $assignment->getAssignedDate()->format('Y-m-d');
        $assignmentsByDate[$dateStr] = $assignment;
    }
    
    foreach ($testDates as $dateStr) {
        $date = new \DateTime($dateStr);
        $dayName = $date->format('l');
        
        if (isset($assignmentsByDate[$dateStr])) {
            $assignment = $assignmentsByDate[$dateStr];
            echo sprintf(
                "✅ %s (%s): %s\n",
                $dateStr,
                $dayName,
                $assignment->getParty()->getChildName()
            );
        } else {
            echo sprintf(
                "❌ %s (%s): NICHT VERGEBEN!\n",
                $dateStr,
                $dayName
            );
        }
    }

    echo "\n=== FERTIG ===\n\n";

    return 0;
};
