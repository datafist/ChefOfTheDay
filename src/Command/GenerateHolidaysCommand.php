<?php

namespace App\Command;

use App\Entity\Holiday;
use App\Repository\KitaYearRepository;
use App\Service\GermanHolidayService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-holidays',
    description: 'Generiert Feiertage (Baden-Württemberg) für alle Kita-Jahre'
)]
class GenerateHolidaysCommand extends Command
{
    public function __construct(
        private KitaYearRepository $kitaYearRepository,
        private GermanHolidayService $holidayService,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Überschreibt existierende Feiertage'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');

        $io->title('Feiertage-Generator für Baden-Württemberg');

        $kitaYears = $this->kitaYearRepository->findAll();

        if (empty($kitaYears)) {
            $io->warning('Keine Kita-Jahre gefunden.');
            return Command::SUCCESS;
        }

        $io->info('Gefundene Kita-Jahre: ' . count($kitaYears));

        $totalGenerated = 0;
        $totalSkipped = 0;

        foreach ($kitaYears as $kitaYear) {
            $startYear = (int)$kitaYear->getStartDate()->format('Y');
            $yearString = $kitaYear->getYearString();

            // Prüfe ob bereits Feiertage existieren
            $existingCount = count($kitaYear->getHolidays());

            if ($existingCount > 0 && !$force) {
                $io->text("⏭️  $yearString: Übersprungen ($existingCount Feiertage bereits vorhanden)");
                $totalSkipped++;
                continue;
            }

            if ($existingCount > 0 && $force) {
                // Lösche existierende Feiertage
                foreach ($kitaYear->getHolidays() as $holiday) {
                    $this->entityManager->remove($holiday);
                }
                $this->entityManager->flush();
                $io->text("🗑️  $yearString: $existingCount alte Feiertage gelöscht");
            }

            // Generiere neue Feiertage
            $holidays = $this->holidayService->getHolidaysForKitaYear($startYear);
            $count = 0;

            foreach ($holidays as $dateString => $name) {
                $holiday = new Holiday();
                $holiday->setDate(new \DateTimeImmutable($dateString));
                $holiday->setName($name);
                $holiday->setKitaYear($kitaYear);

                $this->entityManager->persist($holiday);
                $count++;
            }

            $this->entityManager->flush();

            $io->text("✅ $yearString: $count Feiertage generiert");
            $totalGenerated += $count;
        }

        $io->newLine();
        $io->success([
            "Feiertage erfolgreich generiert!",
            "Kita-Jahre verarbeitet: " . count($kitaYears),
            "Feiertage generiert: $totalGenerated",
            "Übersprungen: $totalSkipped"
        ]);

        return Command::SUCCESS;
    }
}
