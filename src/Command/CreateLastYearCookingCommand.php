<?php

namespace App\Command;

use App\Repository\KitaYearRepository;
use App\Service\LastYearCookingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-last-year-cooking',
    description: 'Erstellt LastYearCooking Einträge aus den CookingAssignments des aktiven Jahres für den Jahresübergang',
)]
class CreateLastYearCookingCommand extends Command
{
    public function __construct(
        private readonly KitaYearRepository $kitaYearRepository,
        private readonly LastYearCookingService $lastYearCookingService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('LastYearCooking Generator — Jahresübergang vorbereiten');

        $kitaYear = $this->kitaYearRepository->findOneBy(['isActive' => true]);
        if (!$kitaYear) {
            $io->error('Kein aktives Kita-Jahr gefunden!');
            return Command::FAILURE;
        }

        $io->info(sprintf('Aktives Kita-Jahr: %s', $kitaYear->getYearString()));

        $result = $this->lastYearCookingService->createFromKitaYear($kitaYear);

        // Details ausgeben
        foreach ($result['details'] as $detail) {
            match ($detail['action']) {
                'created' => $io->text(sprintf('  ✅ %s: Erstellt (%s, %d Dienste)',
                    $detail['family'], $detail['date'], $detail['count'])),
                'updated' => $io->text(sprintf('  🔄 %s: Aktualisiert (→ %s, %d Dienste)',
                    $detail['family'], $detail['date'], $detail['count'])),
                'skipped' => $io->text(sprintf('  ✓  %s: Bereits vorhanden', $detail['family'])),
                'noAssignment' => $io->text(sprintf('  ⚠️  %s: Keine Zuweisung in diesem Jahr', $detail['family'])),
            };
        }

        $io->section('Zusammenfassung');
        $io->table(
            ['Aktion', 'Anzahl'],
            [
                ['Neu erstellt', $result['created']],
                ['Aktualisiert', $result['updated']],
                ['Bereits vorhanden', $result['skipped']],
                ['Keine Zuweisung', $result['noAssignment']],
            ]
        );

        // Verwaiste Einträge aufräumen
        $cleaned = $this->lastYearCookingService->cleanupOrphaned();
        if ($cleaned > 0) {
            $io->info(sprintf('%d verwaiste Vorjahres-Einträge bereinigt.', $cleaned));
        }

        if ($result['created'] > 0 || $result['updated'] > 0) {
            $io->success('LastYearCooking Einträge erfolgreich gespeichert!');
            $io->note([
                'Nächste Schritte:',
                '1. Neues Kita-Jahr erstellen (Admin-Interface)',
                '2. Neues Kita-Jahr aktivieren (LastYearCooking wird automatisch gespeichert)',
                '3. Neuen Kochplan generieren — die Vorjahresdaten werden automatisch berücksichtigt.',
            ]);
        } else {
            $io->info('Keine neuen Einträge erstellt.');
        }

        return Command::SUCCESS;
    }
}
