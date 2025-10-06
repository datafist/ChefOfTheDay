<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:test-email',
    description: 'Testet den E-Mail-Versand mit den konfigurierten SMTP-Einstellungen (SYNCHRON)',
)]
class TestEmailCommand extends Command
{
    public function __construct(
        #[Autowire('%env(MAILER_DSN)%')]
        private readonly string $mailerDsn,
        #[Autowire('%mailer.from_email%')]
        private readonly string $fromEmail,
        #[Autowire('%mailer.from_name%')]
        private readonly string $fromName,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('recipient', InputArgument::REQUIRED, 'E-Mail-Adresse des Empfängers')
            ->setHelp(
                'Dieser Befehl sendet eine Test-E-Mail an die angegebene Adresse.' . PHP_EOL .
                'Verwendung: php bin/console app:test-email deine-email@example.com'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $recipient = $input->getArgument('recipient');

        $io->title('E-Mail-Versand Test (SYNCHRON)');
        $io->text('Sende Test-E-Mail an: ' . $recipient);
        $io->text('MAILER_DSN: ' . preg_replace('/:[^:]+@/', ':***@', $this->mailerDsn)); // Passwort verstecken

        try {
            // Erstelle synchronen Mailer (umgeht Messenger Queue)
            $transport = Transport::fromDsn($this->mailerDsn);
            $mailer = new Mailer($transport);
            
            // Verwende die zentral konfigurierte Absender-Adresse
            $email = (new Email())
                ->from($this->fromEmail)
                ->to($recipient)
                ->subject('🧪 Test-E-Mail von Kita Kochdienst-App')
                ->html($this->getTestEmailHtml());

            // Synchroner Versand - wirft Exception bei Fehler
            $mailer->send($email);

            $io->success('✅ E-Mail erfolgreich versendet!');
            $io->text([
                'Prüfen Sie Ihr E-Mail-Postfach (auch Spam-Ordner).',
                'Bei GMX: Kann bis zu 10 Minuten dauern!',
                '',
                'Falls keine E-Mail ankommt, prüfen Sie:',
                '  • MAILER_DSN in .env.local',
                '  • SMTP-Credentials beim Provider',
                '  • GMX: POP3/SMTP-Zugriff aktiviert?',
                '  • Firewall-Einstellungen (Ports 25, 465, 587)',
            ]);

            return Command::SUCCESS;
        } catch (TransportExceptionInterface $e) {
            $io->error('❌ E-Mail-Versand fehlgeschlagen!');
            $io->text('Transport-Fehler: ' . $e->getMessage());
            $io->text([
                '',
                'Mögliche Ursachen:',
                '  • Falsche SMTP-Credentials (Passwort falsch?)',
                '  • SMTP-Server nicht erreichbar',
                '  • GMX: POP3/SMTP-Zugriff nicht aktiviert',
                '  • Port blockiert durch Firewall',
                '  • IP temporär gesperrt (15 Min warten)',
                '',
                'Siehe GMX_SMTP_CONFIG.md für Hilfe',
            ]);

            return Command::FAILURE;
        } catch (\Exception $e) {
            $io->error('❌ Allgemeiner Fehler!');
            $io->text('Fehler: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function getTestEmailHtml(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #27ae60; color: white; padding: 20px; text-align: center; border-radius: 5px; }
        .content { background-color: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .footer { text-align: center; color: #666; font-size: 0.9em; margin-top: 20px; }
        .success { background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 Test-E-Mail</h1>
            <p>Kita Kochdienst-Verwaltung</p>
        </div>
        
        <div class="content">
            <div class="success">
                <h2>✅ E-Mail-Versand funktioniert!</h2>
                <p>Wenn Sie diese E-Mail erhalten haben, ist Ihr SMTP-Server korrekt konfiguriert.</p>
            </div>
            
            <h3>System-Informationen:</h3>
            <ul>
                <li><strong>Anwendung:</strong> Kita Kochdienst-Verwaltung</li>
                <li><strong>Test-Zeitpunkt:</strong> {date}</li>
                <li><strong>Symfony-Version:</strong> 6.4 LTS</li>
            </ul>
            
            <p><strong>Nächste Schritte:</strong></p>
            <ol>
                <li>Im Admin-Dashboard einen Kochplan generieren</li>
                <li>Mit dem Button "📧 E-Mails versenden" Benachrichtigungen an Familien senden</li>
                <li>Eltern erhalten dann ihre individuellen Kochdienst-Termine</li>
            </ol>
        </div>
        
        <div class="footer">
            <p>Diese E-Mail wurde automatisch von der Kita Kochdienst-App generiert.</p>
            <p>Test-Command: <code>php bin/console app:test-email</code></p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
