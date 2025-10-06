<?php

namespace App\Command;

use App\Repository\KitaYearRepository;
use App\Service\CookingPlanGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-cooking-plan',
    description: 'Generiert den Kochplan für das aktive Kita-Jahr',
)]
class GenerateCookingPlanCommand extends Command
{
    public function __construct(
        private readonly CookingPlanGenerator $cookingPlanGenerator,
        private readonly KitaYearRepository $kitaYearRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Kochplan-Generierung');

        // Finde aktives Kita-Jahr
        $kitaYear = $this->kitaYearRepository->findOneBy(['isActive' => true]);

        if (!$kitaYear) {
            $io->error('Kein aktives Kita-Jahr gefunden.');
            return Command::FAILURE;
        }

        $io->info('Aktives Kita-Jahr: ' . $kitaYear->getYearString());

        // Generiere Plan
        $io->text('Generiere Kochplan...');
        $result = $this->cookingPlanGenerator->generatePlan($kitaYear);

        $assignments = $result['assignments'];
        $conflicts = $result['conflicts'];

        // Zeige Ergebnisse
        $io->success(sprintf('✓ %d Kochdienste erfolgreich zugewiesen.', count($assignments)));

        if (!empty($conflicts)) {
            $io->warning('Es gab ' . count($conflicts) . ' Konflikte:');
            foreach ($conflicts as $conflict) {
                $io->text('  • ' . $conflict);
            }
        }

        // Frage ob speichern
        if ($io->confirm('Möchten Sie die Zuweisungen speichern?', true)) {
            $this->cookingPlanGenerator->saveAssignments($assignments);
            $io->success('Kochplan erfolgreich gespeichert!');
            return Command::SUCCESS;
        }

        $io->info('Kochplan wurde nicht gespeichert.');
        return Command::SUCCESS;
    }
}
