<?php

namespace App\Command;

use App\Service\CookingPlanGenerator;
use App\Repository\KitaYearRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-plan-generation',
    description: 'Testet die Plan-Generierung für das aktive Kita-Jahr',
)]
class TestPlanGenerationCommand extends Command
{
    public function __construct(
        private readonly CookingPlanGenerator $planGenerator,
        private readonly KitaYearRepository $kitaYearRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('TESTE PLAN-GENERIERUNG FÜR 25/26');

        // Hole aktives Kita-Jahr
        $kitaYear = $this->kitaYearRepository->findOneBy(['isActive' => true]);
        
        if (!$kitaYear) {
            $io->error('Kein aktives Kita-Jahr gefunden!');
            return Command::FAILURE;
        }

        $io->info(sprintf(
            'Aktives Jahr: %s bis %s',
            $kitaYear->getStartDate()->format('Y-m-d'),
            $kitaYear->getEndDate()->format('Y-m-d')
        ));

        // Generiere Plan
        $io->section('Generiere Kochplan...');
        $result = $this->planGenerator->generatePlan($kitaYear);

        $io->success(sprintf('Zuweisungen erstellt: %d', count($result['assignments'])));
        
        if (!empty($result['conflicts'])) {
            $io->warning(sprintf('Konflikte: %d', count($result['conflicts'])));
            $io->listing($result['conflicts']);
        } else {
            $io->success('Keine Konflikte!');
        }

        // Speichere Zuweisungen
        $io->section('Speichere Zuweisungen...');
        $this->planGenerator->saveAssignments($result['assignments']);
        $io->success('Gespeichert!');

        // Prüfe speziell die August-Tage
        $testDates = ['2026-08-25', '2026-08-26', '2026-08-27', '2026-08-28', '2026-08-31'];
        
        $io->section('Prüfe August-Tage');
        
        $assignmentsByDate = [];
        foreach ($result['assignments'] as $assignment) {
            $dateStr = $assignment->getAssignedDate()->format('Y-m-d');
            $assignmentsByDate[$dateStr] = $assignment;
        }
        
        $rows = [];
        foreach ($testDates as $dateStr) {
            $date = new \DateTime($dateStr);
            $dayName = $date->format('l');
            
            if (isset($assignmentsByDate[$dateStr])) {
                $assignment = $assignmentsByDate[$dateStr];
                $rows[] = [
                    '✅',
                    $dateStr,
                    $dayName,
                    $assignment->getParty()->getChildrenNames()
                ];
            } else {
                $rows[] = [
                    '❌',
                    $dateStr,
                    $dayName,
                    'NICHT VERGEBEN!'
                ];
            }
        }
        
        $io->table(['Status', 'Datum', 'Wochentag', 'Familie'], $rows);

        return Command::SUCCESS;
    }
}
