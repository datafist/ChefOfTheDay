<?php

namespace App\Command;

use App\Repository\CookingAssignmentRepository;
use App\Repository\KitaYearRepository;
use App\Repository\PartyRepository;
use App\Service\CookingPlanGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-fairness',
    description: 'Generiert einen neuen Plan und analysiert die Fairness-Verteilung',
)]
class TestFairnessCommand extends Command
{
    public function __construct(
        private readonly CookingPlanGenerator $planGenerator,
        private readonly KitaYearRepository $kitaYearRepository,
        private readonly PartyRepository $partyRepository,
        private readonly CookingAssignmentRepository $assignmentRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Hole aktives Kita-Jahr
        $kitaYear = $this->kitaYearRepository->findOneBy(['isActive' => true]);

        if (!$kitaYear) {
            $io->error('Kein aktives Kita-Jahr gefunden!');
            return Command::FAILURE;
        }

        $io->title('Plan-Generierung für ' . $kitaYear->getStartDate()->format('Y') . '/' . $kitaYear->getEndDate()->format('Y'));

        // Lösche alte Zuweisungen
        $oldAssignments = $this->assignmentRepository->findBy(['kitaYear' => $kitaYear]);
        $io->info('Lösche ' . count($oldAssignments) . ' alte Zuweisungen...');
        
        foreach ($oldAssignments as $assignment) {
            $this->entityManager->remove($assignment);
        }
        $this->entityManager->flush();

        // Generiere neuen Plan
        $io->section('Generiere neuen Plan...');
        $result = $this->planGenerator->generatePlan($kitaYear);

        $io->success('Plan erstellt: ' . count($result['assignments']) . ' Zuweisungen, ' . count($result['conflicts']) . ' Konflikte');

        if (!empty($result['conflicts'])) {
            $io->warning('Konflikte gefunden:');
            foreach (array_slice($result['conflicts'], 0, 5) as $conflict) {
                $io->text('  - ' . $conflict);
            }
            if (count($result['conflicts']) > 5) {
                $io->text('  - ... und ' . (count($result['conflicts']) - 5) . ' weitere');
            }
        }

        // Speichere Zuweisungen
        $this->planGenerator->saveAssignments($result['assignments']);
        $io->success('Zuweisungen gespeichert!');

        // Analyse der Verteilung
        $io->section('Fairness-Analyse');

        $parties = $this->partyRepository->findAll();
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

        // Alleinerziehende
        $singleCounts = array_map(fn($d) => $d['count'], $singleParents);
        $singleMin = !empty($singleCounts) ? min($singleCounts) : 0;
        $singleMax = !empty($singleCounts) ? max($singleCounts) : 0;
        $singleAvg = !empty($singleCounts) ? round(array_sum($singleCounts) / count($singleCounts), 1) : 0;

        $io->writeln('');
        $io->writeln('<fg=cyan>Alleinerziehende (' . count($singleParents) . ' Familien)</>');
        $io->table(
            ['Statistik', 'Wert'],
            [
                ['Minimum', $singleMin],
                ['Maximum', $singleMax],
                ['Durchschnitt', $singleAvg],
            ]
        );

        $io->writeln('Top 5:');
        foreach (array_slice($singleParents, 0, 5) as $data) {
            $io->writeln('  • ' . $data['party']->getChildrenNames() . ': ' . $data['count'] . ' Dienste');
        }

        // Paare
        $coupleCounts = array_map(fn($d) => $d['count'], $couples);
        $coupleMin = !empty($coupleCounts) ? min($coupleCounts) : 0;
        $coupleMax = !empty($coupleCounts) ? max($coupleCounts) : 0;
        $coupleAvg = !empty($coupleCounts) ? round(array_sum($coupleCounts) / count($coupleCounts), 1) : 0;

        $io->writeln('');
        $io->writeln('<fg=cyan>Paare (' . count($couples) . ' Familien)</>');
        $io->table(
            ['Statistik', 'Wert'],
            [
                ['Minimum', $coupleMin],
                ['Maximum', $coupleMax],
                ['Durchschnitt', $coupleAvg],
            ]
        );

        $io->writeln('Top 5:');
        foreach (array_slice($couples, 0, 5) as $data) {
            $io->writeln('  • ' . $data['party']->getChildrenNames() . ': ' . $data['count'] . ' Dienste');
        }

        // Validierung
        $io->section('Validierung');

        if ($singleMax > $coupleMin) {
            $io->error('FEHLER: Es gibt Alleinerziehende mit mehr Diensten als manche Paare!');
            $io->text('  Alleinerziehende Maximum: ' . $singleMax);
            $io->text('  Paare Minimum: ' . $coupleMin);
            return Command::FAILURE;
        } else {
            $io->success('✅ PERFEKT: Alle Alleinerziehenden haben weniger oder gleich viele Dienste wie Paare!');
            $io->text('  Alleinerziehende: ' . $singleMin . ' bis ' . $singleMax . ' Dienste');
            $io->text('  Paare: ' . $coupleMin . ' bis ' . $coupleMax . ' Dienste');
        }

        return Command::SUCCESS;
    }
}
