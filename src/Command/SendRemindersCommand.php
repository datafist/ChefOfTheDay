<?php

namespace App\Command;

use App\Service\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-reminders',
    description: 'Sendet Erinnerungen für bevorstehende Kochdienste',
)]
class SendRemindersCommand extends Command
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('days', InputArgument::OPTIONAL, 'Tage im Voraus (Standard: 3)', 3);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getArgument('days');

        $io->info(sprintf('Sende Erinnerungen für Kochdienste in %d Tagen...', $days));

        $sentCount = $this->notificationService->sendUpcomingReminders($days);

        if ($sentCount > 0) {
            $io->success(sprintf('%d Erinnerungs-Emails wurden versendet.', $sentCount));
        } else {
            $io->info('Keine Erinnerungen zu versenden.');
        }

        return Command::SUCCESS;
    }
}
