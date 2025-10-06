<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:setup-admin',
    description: 'Erstellt oder aktualisiert den Admin-Benutzer'
)]
class SetupAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Admin-Benutzer Setup');

        // Prüfe ob Admin bereits existiert
        $admin = $this->userRepository->findOneBy(['username' => 'admin']);
        
        if ($admin) {
            $io->warning('Admin-Benutzer existiert bereits!');
            $io->text('Aktueller Username: admin');
            
            $helper = $this->getHelper('question');
            $question = new Question('Möchten Sie das Passwort ändern? (j/n): ', 'n');
            $answer = $helper->ask($input, $output, $question);
            
            if (strtolower($answer) !== 'j' && strtolower($answer) !== 'ja') {
                $io->info('Abgebrochen. Keine Änderungen vorgenommen.');
                return Command::SUCCESS;
            }
        } else {
            $io->info('Erstelle neuen Admin-Benutzer...');
            $admin = new User();
            $admin->setUsername('admin');
            $admin->setRoles(['ROLE_ADMIN']);
            $this->entityManager->persist($admin);
        }

        // Passwort abfragen
        $helper = $this->getHelper('question');
        $passwordQuestion = new Question('Neues Admin-Passwort: ');
        $passwordQuestion->setHidden(true);
        $passwordQuestion->setHiddenFallback(false);
        $passwordQuestion->setValidator(function ($value) {
            if (empty($value)) {
                throw new \RuntimeException('Passwort darf nicht leer sein.');
            }
            if (strlen($value) < 6) {
                throw new \RuntimeException('Passwort muss mindestens 6 Zeichen lang sein.');
            }
            return $value;
        });
        
        $password = $helper->ask($input, $output, $passwordQuestion);
        
        // Passwort bestätigen
        $confirmQuestion = new Question('Passwort wiederholen: ');
        $confirmQuestion->setHidden(true);
        $confirmQuestion->setHiddenFallback(false);
        
        $confirmPassword = $helper->ask($input, $output, $confirmQuestion);
        
        if ($password !== $confirmPassword) {
            $io->error('Passwörter stimmen nicht überein!');
            return Command::FAILURE;
        }

        // Passwort hashen und speichern
        $hashedPassword = $this->passwordHasher->hashPassword($admin, $password);
        $admin->setPassword($hashedPassword);
        
        $this->entityManager->flush();

        $io->success('Admin-Benutzer erfolgreich ' . ($admin->getId() ? 'aktualisiert' : 'erstellt') . '!');
        $io->text([
            '',
            'Login-Daten:',
            '  Username: admin',
            '  Passwort: ' . str_repeat('*', strlen($password)),
            '',
            'Login-URL: /login',
        ]);

        return Command::SUCCESS;
    }
}
