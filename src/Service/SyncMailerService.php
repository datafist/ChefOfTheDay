<?php

namespace App\Service;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Synchroner E-Mail-Versand ohne Messenger Queue
 * Wird für Test-E-Mails verwendet, um sofortiges Feedback zu erhalten
 */
class SyncMailerService
{
    public function __construct(
        private readonly MailerInterface $mailer
    ) {
    }

    /**
     * Sendet eine E-Mail synchron (ohne Queue)
     * 
     * @throws TransportExceptionInterface Wenn der Versand fehlschlägt
     */
    public function send(Email $email): void
    {
        // Direkt senden, umgeht Messenger
        $this->mailer->send($email);
    }
}
