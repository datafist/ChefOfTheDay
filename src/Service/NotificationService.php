<?php

namespace App\Service;

use App\Entity\CookingAssignment;
use App\Entity\KitaYear;
use App\Repository\CookingAssignmentRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class NotificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly CookingAssignmentRepository $assignmentRepository,
    ) {
    }

    /**
     * Sendet Benachrichtigungen über neue Kochplan-Zuweisung
     */
    public function sendPlanGeneratedNotifications(KitaYear $kitaYear): int
    {
        $assignments = $this->assignmentRepository->findBy([
            'kitaYear' => $kitaYear,
        ], ['assignedDate' => 'ASC']);

        $sentCount = 0;
        $partiesNotified = [];

        foreach ($assignments as $assignment) {
            $party = $assignment->getParty();
            $partyId = $party->getId();

            // Sende nur eine Email pro Familie (nicht für jeden Tag)
            if (in_array($partyId, $partiesNotified)) {
                continue;
            }

            if (!$party->getEmail()) {
                continue; // Keine Email-Adresse vorhanden
            }

            // Sammle alle Termine dieser Familie
            $partyAssignments = array_filter($assignments, fn($a) => $a->getParty()->getId() === $partyId);

            $email = (new TemplatedEmail())
                ->from(new Address('kochdienst@kita.local', 'Kita Kochdienst'))
                ->to(new Address($party->getEmail()))
                ->subject('Ihr Kochplan für ' . $kitaYear->getYearString())
                ->htmlTemplate('emails/plan_generated.html.twig')
                ->context([
                    'party' => $party,
                    'kitaYear' => $kitaYear,
                    'assignments' => $partyAssignments,
                ]);

            try {
                $this->mailer->send($email);
                $sentCount++;
                $partiesNotified[] = $partyId;
            } catch (\Exception $e) {
                // Logging würde hier erfolgen
            }
        }

        return $sentCount;
    }

    /**
     * Sendet Erinnerung X Tage vor dem Kochdienst
     */
    public function sendUpcomingReminders(int $daysInAdvance = 3): int
    {
        $targetDate = new \DateTimeImmutable("+{$daysInAdvance} days");
        $targetDateStr = $targetDate->format('Y-m-d');

        $assignments = $this->assignmentRepository->createQueryBuilder('a')
            ->where('a.assignedDate = :targetDate')
            ->setParameter('targetDate', $targetDateStr)
            ->getQuery()
            ->getResult();

        $sentCount = 0;

        foreach ($assignments as $assignment) {
            $party = $assignment->getParty();

            if (!$party->getEmail()) {
                continue;
            }

            $email = (new TemplatedEmail())
                ->from(new Address('kochdienst@kita.local', 'Kita Kochdienst'))
                ->to(new Address($party->getEmail()))
                ->subject('Erinnerung: Kochdienst am ' . $assignment->getAssignedDate()->format('d.m.Y'))
                ->htmlTemplate('emails/reminder.html.twig')
                ->context([
                    'party' => $party,
                    'assignment' => $assignment,
                    'daysInAdvance' => $daysInAdvance,
                ]);

            try {
                $this->mailer->send($email);
                $sentCount++;
            } catch (\Exception $e) {
                // Logging würde hier erfolgen
            }
        }

        return $sentCount;
    }
}
