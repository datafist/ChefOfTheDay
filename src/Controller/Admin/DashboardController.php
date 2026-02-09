<?php

namespace App\Controller\Admin;

use App\Entity\CookingAssignment;
use App\Entity\KitaYear;
use App\Entity\Party;
use App\Repository\CookingAssignmentRepository;
use App\Repository\HolidayRepository;
use App\Repository\KitaYearRepository;
use App\Repository\LastYearCookingRepository;
use App\Repository\PartyRepository;
use App\Repository\VacationRepository;
use App\Service\AuditLogger;
use App\Service\CookingPlanGenerator;
use App\Service\DateExclusionService;
use App\Service\NotificationService;
use App\Service\PdfExportService;
use App\Util\DateHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    #[Route('/', name: 'admin_dashboard')]
    public function index(
        KitaYearRepository $kitaYearRepository,
        CookingAssignmentRepository $cookingAssignmentRepository,
        LastYearCookingRepository $lastYearCookingRepository,
        PartyRepository $partyRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $activeKitaYear = $kitaYearRepository->findOneBy(['isActive' => true]);
        
        $assignments = [];
        $totalAssignments = 0;
        $familyStats = [];
        $previousYearsDeletable = [];
        $newFamilies = [];
        $familiesNotInPlan = [];
        $familiesInPlan = [];
        
        if ($activeKitaYear) {
            $assignments = $cookingAssignmentRepository->findBy(
                ['kitaYear' => $activeKitaYear],
                ['assignedDate' => 'ASC']
            );
            $totalAssignments = count($assignments);
            
            // Identifiziere neue Familien (ohne LastYearCooking-Eintrag)
            $allLastYearCookings = $lastYearCookingRepository->findAll();
            $familiesWithHistory = array_map(fn($lyc) => $lyc->getParty()->getId(), $allLastYearCookings);
            
            // Berechne Statistik nach Familien
            $statsMap = [];
            $assignedPartyIds = [];
            foreach ($assignments as $assignment) {
                $partyId = $assignment->getParty()->getId();
                $assignedPartyIds[$partyId] = true;
                if (!isset($statsMap[$partyId])) {
                    $isNewFamily = !in_array($partyId, $familiesWithHistory);
                    $statsMap[$partyId] = [
                        'party' => $assignment->getParty(),
                        'count' => 0,
                        'isNew' => $isNewFamily
                    ];
                    if ($isNewFamily) {
                        $newFamilies[] = $assignment->getParty();
                    }
                }
                $statsMap[$partyId]['count']++;
            }
            
            // Konvertiere zu Array und sortiere nach count (absteigend)
            $familyStats = array_values($statsMap);
            usort($familyStats, function($a, $b) {
                return $b['count'] <=> $a['count'];
            });

            // Familien identifizieren, die NICHT im Plan sind (für "In Plan aufnehmen")
            $allParties = $partyRepository->findAll();
            foreach ($allParties as $party) {
                if (!isset($assignedPartyIds[$party->getId()])) {
                    $familiesNotInPlan[] = $party;
                } else {
                    $familiesInPlan[] = $party;
                }
            }

            // Prüfe ob Plan für aktives Jahr generiert wurde
            $activePlanGenerated = $totalAssignments > 0;

            // Wenn Plan generiert ist, prüfe ob es Vorjahre gibt, die gelöscht werden können
            if ($activePlanGenerated) {
                $allYears = $kitaYearRepository->findAll();
                foreach ($allYears as $year) {
                    if (!$year->isActive() && $year->getStartDate() < $activeKitaYear->getStartDate()) {
                        $previousYearsDeletable[] = $year;
                    }
                }
            }
        }

        return $this->render('admin/dashboard/index.html.twig', [
            'activeKitaYear' => $activeKitaYear,
            'assignments' => $assignments,
            'totalAssignments' => $totalAssignments,
            'familyStats' => $familyStats,
            'previousYearsDeletable' => $previousYearsDeletable,
            'newFamilies' => $newFamilies,
            'familiesNotInPlan' => $familiesNotInPlan,
            'familiesInPlan' => $familiesInPlan,
            'today' => new \DateTime('today'),
        ]);
    }

    #[Route('/generate-plan', name: 'admin_generate_plan', methods: ['POST'])]
    public function generatePlan(
        Request $request,
        KitaYearRepository $kitaYearRepository,
        CookingPlanGenerator $generator,
        EntityManagerInterface $entityManager,
        CookingAssignmentRepository $assignmentRepository,
        AuditLogger $auditLogger
    ): Response {
        if (!$this->isCsrfTokenValid('generate-plan', $request->request->get('_token'))) {
            $this->addFlash('error', 'Ungültiger Sicherheits-Token.');
            return $this->redirectToRoute('admin_dashboard');
        }

        $activeKitaYear = $kitaYearRepository->findOneBy(['isActive' => true]);
        
        if (!$activeKitaYear) {
            $this->addFlash('error', 'Kein aktives Kita-Jahr gefunden.');
            return $this->redirectToRoute('admin_dashboard');
        }

        // Manuelle Zuweisungen schützen
        $existingAssignments = $assignmentRepository->findBy(['kitaYear' => $activeKitaYear]);
        $manualAssignments = array_filter($existingAssignments, fn($a) => $a->isManuallyAssigned());
        $manualCount = count($manualAssignments);

        // Nur automatische Zuweisungen löschen
        foreach ($existingAssignments as $assignment) {
            if (!$assignment->isManuallyAssigned()) {
                $entityManager->remove($assignment);
            }
        }
        $entityManager->flush();

        // Generiere neuen Plan (berücksichtigt bestehende manuelle Zuweisungen)
        $result = $generator->generatePlan($activeKitaYear);
        $assignments = $result['assignments'];
        $conflicts = $result['conflicts'];

        if (!empty($conflicts)) {
            foreach ($conflicts as $conflict) {
                $this->addFlash('warning', $conflict);
            }
        }

        // Speichere neue Zuweisungen
        $generator->saveAssignments($assignments);

        $totalNew = count($assignments);
        $this->addFlash('success', sprintf(
            '✓ Kochplan mit %d Zuweisungen erfolgreich generiert!%s',
            $totalNew + $manualCount,
            $manualCount > 0 ? sprintf(' (%d manuelle Zuweisungen beibehalten)', $manualCount) : ''
        ));
        $this->addFlash('info', 'E-Mail-Benachrichtigungen können jetzt manuell versendet werden.');

        $auditLogger->logPlanGenerated(
            $this->getUser()->getUserIdentifier(),
            $activeKitaYear->getYearString(),
            $totalNew + $manualCount,
            $manualCount
        );

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/delete-plan', name: 'admin_delete_plan', methods: ['POST'])]
    public function deletePlan(
        Request $request,
        KitaYearRepository $kitaYearRepository,
        CookingAssignmentRepository $assignmentRepository,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger
    ): Response {
        // CSRF Token validieren
        if (!$this->isCsrfTokenValid('delete-plan', $request->request->get('_token'))) {
            $this->addFlash('error', 'Ungültiger Sicherheits-Token.');
            return $this->redirectToRoute('admin_dashboard');
        }

        $activeKitaYear = $kitaYearRepository->findOneBy(['isActive' => true]);
        
        if (!$activeKitaYear) {
            $this->addFlash('error', 'Kein aktives Kita-Jahr gefunden.');
            return $this->redirectToRoute('admin_dashboard');
        }

        // Lösche alle Zuweisungen für das aktive Jahr
        $assignments = $assignmentRepository->findBy(['kitaYear' => $activeKitaYear]);
        $count = count($assignments);
        
        foreach ($assignments as $assignment) {
            $entityManager->remove($assignment);
        }
        $entityManager->flush();

        $this->addFlash('success', sprintf('✅ Kochplan für %s erfolgreich gelöscht! (%d Zuweisungen entfernt)', 
            $activeKitaYear->getYearString(), 
            $count
        ));

        $auditLogger->logPlanDeleted(
            $this->getUser()->getUserIdentifier(),
            $activeKitaYear->getYearString(),
            $count
        );

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/send-notifications', name: 'admin_send_notifications', methods: ['POST'])]
    public function sendNotifications(
        Request $request,
        KitaYearRepository $kitaYearRepository,
        CookingAssignmentRepository $assignmentRepository,
        NotificationService $notificationService
    ): Response {
        // CSRF Token validieren
        if (!$this->isCsrfTokenValid('send-notifications', $request->request->get('_token'))) {
            $this->addFlash('error', 'Ungültiger Sicherheits-Token.');
            return $this->redirectToRoute('admin_dashboard');
        }

        $activeKitaYear = $kitaYearRepository->findOneBy(['isActive' => true]);
        
        if (!$activeKitaYear) {
            $this->addFlash('error', 'Kein aktives Kita-Jahr gefunden.');
            return $this->redirectToRoute('admin_dashboard');
        }

        // Prüfe ob ein Plan existiert
        $hasAssignments = $assignmentRepository->count(['kitaYear' => $activeKitaYear]) > 0;

        if (!$hasAssignments) {
            $this->addFlash('error', 'Es existiert noch kein Kochplan für das aktive Jahr. Bitte generieren Sie zuerst einen Plan.');
            return $this->redirectToRoute('admin_dashboard');
        }

        // Sende Email-Benachrichtigungen
        $emailsSent = $notificationService->sendPlanGeneratedNotifications($activeKitaYear);

        if ($emailsSent > 0) {
            $this->addFlash('success', sprintf('✅ %d E-Mail-Benachrichtigungen wurden erfolgreich versendet!', $emailsSent));
        } else {
            $this->addFlash('warning', '⚠️ Es wurden keine E-Mails versendet. Möglicherweise haben keine Familien eine E-Mail-Adresse hinterlegt.');
        }

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/export-pdf', name: 'admin_export_pdf')]
    public function exportPdf(
        KitaYearRepository $kitaYearRepository,
        PdfExportService $pdfExportService
    ): Response {
        $activeKitaYear = $kitaYearRepository->findOneBy(['isActive' => true]);
        
        if (!$activeKitaYear) {
            $this->addFlash('error', 'Kein aktives Kita-Jahr gefunden.');
            return $this->redirectToRoute('admin_dashboard');
        }

        $pdfContent = $pdfExportService->generateCookingPlanPdf($activeKitaYear);
        $filename = sprintf('Kochplan_%s.pdf', $activeKitaYear->getYearString());

        return new Response(
            $pdfContent,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            ]
        );
    }

    #[Route('/calendar', name: 'admin_calendar')]
    public function calendar(
        KitaYearRepository $kitaYearRepository,
        CookingAssignmentRepository $cookingAssignmentRepository,
        DateExclusionService $dateExclusionService,
        EntityManagerInterface $entityManager
    ): Response {
        $activeKitaYear = $kitaYearRepository->findOneBy(['isActive' => true]);
        
        if (!$activeKitaYear) {
            $this->addFlash('error', 'Kein aktives Kita-Jahr gefunden.');
            return $this->redirectToRoute('admin_dashboard');
        }

        $assignments = $cookingAssignmentRepository->findBy(
            ['kitaYear' => $activeKitaYear],
            ['assignedDate' => 'ASC']
        );

        // Lade alle Familien für die Auswahl
        $allParties = $entityManager->getRepository(Party::class)->findAll();

        // Ermittle ausgeschlossene Tage (Wochenenden, Feiertage, Ferien)
        $excludedDates = $dateExclusionService->getExcludedDatesForKitaYear($activeKitaYear);

        // Baue Kalender-Struktur auf
        $calendar = $this->buildCalendarView($activeKitaYear, $assignments, $excludedDates);

        return $this->render('admin/dashboard/calendar.html.twig', [
            'activeKitaYear' => $activeKitaYear,
            'calendar' => $calendar,
            'totalAssignments' => count($assignments),
            'allParties' => $allParties,
        ]);
    }

    #[Route('/assignment/{id}/edit', name: 'admin_assignment_edit', methods: ['POST'])]
    public function editAssignment(
        Request $request,
        int $id,
        CookingAssignmentRepository $assignmentRepository,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger
    ): Response {
        // CSRF Token validieren
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('edit-assignment-' . $id, $token)) {
            $this->addFlash('error', 'Ungültiger Sicherheits-Token.');
            return $this->redirectToRoute('admin_calendar');
        }

        $assignment = $assignmentRepository->find($id);
        
        if (!$assignment) {
            $this->addFlash('error', 'Zuweisung nicht gefunden.');
            return $this->redirectToRoute('admin_calendar');
        }

        $partyId = $request->request->get('party_id');
        if ($partyId) {
            $party = $entityManager->getRepository(Party::class)->find($partyId);
            if ($party) {
                // Prüfe ob neue Familie an diesem Tag verfügbar ist
                $date = $assignment->getAssignedDate()->format('Y-m-d');
                $kitaYear = $assignment->getKitaYear();
                
                $availability = $entityManager->getRepository(\App\Entity\Availability::class)
                    ->findOneBy(['party' => $party, 'kitaYear' => $kitaYear]);
                
                if (!$availability) {
                    $this->addFlash('error', sprintf(
                        'Familie "%s" hat noch keine Verfügbarkeiten angegeben.',
                        $party->getChildrenNames()
                    ));
                    return $this->redirectToRoute('admin_calendar');
                }
                
                $availableDates = $availability->getAvailableDates();
                if (!in_array($date, $availableDates)) {
                    $this->addFlash('error', sprintf(
                        'Familie "%s" ist an diesem Tag nicht verfügbar.',
                        $party->getChildrenNames()
                    ));
                    return $this->redirectToRoute('admin_calendar');
                }
                
                $oldParty = $assignment->getParty()->getChildrenNames();
                $assignment->setParty($party);
                $assignment->setIsManuallyAssigned(true);
                $entityManager->flush();

                $auditLogger->logAssignmentChanged(
                    $this->getUser()->getUserIdentifier(),
                    $date,
                    $oldParty,
                    $party->getChildrenNames()
                );
                
                $this->addFlash('success', sprintf(
                    'Zuweisung erfolgreich geändert: %s → %s', 
                    $oldParty, 
                    $party->getChildrenNames()
                ));
            } else {
                $this->addFlash('error', 'Familie nicht gefunden.');
            }
        }

        return $this->redirectToRoute('admin_calendar');
    }

    #[Route('/assignment/create', name: 'admin_assignment_create', methods: ['POST'])]
    public function createAssignment(
        Request $request,
        KitaYearRepository $kitaYearRepository,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger
    ): Response {
        // CSRF Token validieren
        $token = $request->request->get('_token');
        $date = $request->request->get('date');
        
        if (!$this->isCsrfTokenValid('create-assignment-' . $date, $token)) {
            $this->addFlash('error', 'Ungültiger Sicherheits-Token.');
            return $this->redirectToRoute('admin_calendar');
        }

        $activeKitaYear = $kitaYearRepository->findOneBy(['isActive' => true]);
        
        if (!$activeKitaYear) {
            $this->addFlash('error', 'Kein aktives Kita-Jahr gefunden.');
            return $this->redirectToRoute('admin_calendar');
        }

        $partyId = $request->request->get('party_id');
        if (!$partyId) {
            $this->addFlash('error', 'Bitte wählen Sie eine Familie aus.');
            return $this->redirectToRoute('admin_calendar');
        }

        $party = $entityManager->getRepository(Party::class)->find($partyId);
        if (!$party) {
            $this->addFlash('error', 'Familie nicht gefunden.');
            return $this->redirectToRoute('admin_calendar');
        }

        // Prüfe ob Familie an diesem Tag verfügbar ist
        $availability = $entityManager->getRepository(\App\Entity\Availability::class)
            ->findOneBy(['party' => $party, 'kitaYear' => $activeKitaYear]);
        
        if (!$availability) {
            $this->addFlash('error', sprintf(
                'Familie "%s" hat noch keine Verfügbarkeiten angegeben.',
                $party->getChildrenNames()
            ));
            return $this->redirectToRoute('admin_calendar');
        }
        
        $availableDates = $availability->getAvailableDates();
        if (!in_array($date, $availableDates)) {
            $this->addFlash('error', sprintf(
                'Familie "%s" ist an diesem Tag nicht verfügbar.',
                $party->getChildrenNames()
            ));
            return $this->redirectToRoute('admin_calendar');
        }

        // Erstelle neue Zuweisung
        $assignment = new CookingAssignment();
        $assignment->setKitaYear($activeKitaYear);
        $assignment->setParty($party);
        $assignment->setAssignedDate(new \DateTimeImmutable($date));
        $assignment->setIsManuallyAssigned(true);

        $entityManager->persist($assignment);
        $entityManager->flush();

        $auditLogger->logAssignmentCreated(
            $this->getUser()->getUserIdentifier(),
            $date,
            $party->getChildrenNames()
        );

        $this->addFlash('success', sprintf(
            'Kochdienst für %s erfolgreich zugewiesen!',
            $party->getChildrenNames()
        ));

        return $this->redirectToRoute('admin_calendar');
    }

    #[Route('/assignment/{id}/delete', name: 'admin_assignment_delete', methods: ['POST'])]
    public function deleteAssignment(
        Request $request,
        int $id,
        CookingAssignmentRepository $assignmentRepository,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger
    ): Response {
        // CSRF Token validieren
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete-assignment-' . $id, $token)) {
            $this->addFlash('error', 'Ungültiger Sicherheits-Token.');
            return $this->redirectToRoute('admin_calendar');
        }

        $assignment = $assignmentRepository->find($id);
        
        if (!$assignment) {
            $this->addFlash('error', 'Zuweisung nicht gefunden.');
            return $this->redirectToRoute('admin_calendar');
        }

        $partyName = $assignment->getParty()->getChildrenNames();
        $date = $assignment->getAssignedDate()->format('d.m.Y');
        
        $entityManager->remove($assignment);
        $entityManager->flush();

        $auditLogger->logAssignmentDeleted(
            $this->getUser()->getUserIdentifier(),
            $date,
            $partyName
        );

        $this->addFlash('success', sprintf(
            'Zuweisung für %s am %s wurde gelöscht.',
            $partyName,
            $date
        ));

        return $this->redirectToRoute('admin_calendar');
    }

    /**
     * Fügt eine neue Familie inkrementell in den bestehenden Plan ein.
     * Übernimmt zukünftige Zuweisungen von überbelasteten Familien.
     */
    #[Route('/add-family-to-plan/{id}', name: 'admin_add_family_to_plan', methods: ['POST'])]
    public function addFamilyToPlan(
        Request $request,
        Party $party,
        KitaYearRepository $kitaYearRepository,
        CookingPlanGenerator $generator,
        AuditLogger $auditLogger
    ): Response {
        if (!$this->isCsrfTokenValid('add-family-' . $party->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Ungültiger Sicherheits-Token.');
            return $this->redirectToRoute('admin_dashboard');
        }

        $activeKitaYear = $kitaYearRepository->findOneBy(['isActive' => true]);
        if (!$activeKitaYear) {
            $this->addFlash('error', 'Kein aktives Kita-Jahr gefunden.');
            return $this->redirectToRoute('admin_dashboard');
        }

        $result = $generator->addFamilyToPlan($activeKitaYear, $party);

        if ($result['transferred'] > 0) {
            $this->addFlash('success', sprintf(
                '✅ %s wurde in den Plan aufgenommen: %d Dienste übertragen.',
                $party->getChildrenNames(),
                $result['transferred']
            ));
        }

        $auditLogger->logFamilyAddedToPlan(
            $this->getUser()->getUserIdentifier(),
            $party->getChildrenNames(),
            $result['transferred']
        );

        foreach ($result['conflicts'] as $conflict) {
            $this->addFlash('warning', $conflict);
        }

        return $this->redirectToRoute('admin_dashboard');
    }

    /**
     * Entfernt eine Familie aus dem Plan und verteilt deren zukünftige Dienste um.
     */
    #[Route('/remove-family-from-plan/{id}', name: 'admin_remove_family_from_plan', methods: ['POST'])]
    public function removeFamilyFromPlan(
        Request $request,
        Party $party,
        KitaYearRepository $kitaYearRepository,
        CookingPlanGenerator $generator,
        AuditLogger $auditLogger
    ): Response {
        if (!$this->isCsrfTokenValid('remove-family-' . $party->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Ungültiger Sicherheits-Token.');
            return $this->redirectToRoute('admin_dashboard');
        }

        $activeKitaYear = $kitaYearRepository->findOneBy(['isActive' => true]);
        if (!$activeKitaYear) {
            $this->addFlash('error', 'Kein aktives Kita-Jahr gefunden.');
            return $this->redirectToRoute('admin_dashboard');
        }

        $result = $generator->removeFamilyFromPlan($activeKitaYear, $party);

        $auditLogger->logFamilyRemovedFromPlan(
            $this->getUser()->getUserIdentifier(),
            $party->getChildrenNames(),
            $result['redistributed'],
            $result['removed']
        );

        $this->addFlash('success', sprintf(
            '✅ %s aus dem Plan entfernt: %d Dienste umverteilt, %d gelöscht.',
            $party->getChildrenNames(),
            $result['redistributed'],
            $result['removed']
        ));

        foreach ($result['conflicts'] as $conflict) {
            $this->addFlash('warning', $conflict);
        }

        return $this->redirectToRoute('admin_dashboard');
    }

    private function buildCalendarView(KitaYear $kitaYear, array $assignments, array $excludedDates): array
    {
        $calendar = [];
        $assignmentsByDate = [];
        
        // Indexiere Zuweisungen nach Datum
        foreach ($assignments as $assignment) {
            $dateKey = $assignment->getAssignedDate()->format('Y-m-d');
            $assignmentsByDate[$dateKey] = $assignment;
        }

        // Erstelle Kalender für jeden Monat
        $period = new \DatePeriod(
            $kitaYear->getStartDate(),
            new \DateInterval('P1M'),
            $kitaYear->getEndDate()->modify('+1 day')
        );

        foreach ($period as $monthStart) {
            $monthData = [
                'month' => (int)$monthStart->format('n'),
                'year' => (int)$monthStart->format('Y'),
                'name_de' => DateHelper::getMonthNameGerman((int)$monthStart->format('n')),
                'weeks' => []
            ];

            // Erstelle Wochen-Struktur nur für Tage des aktuellen Monats
            $firstDayOfMonth = new \DateTimeImmutable($monthStart->format('Y-m-01'));
            $lastDayOfMonth = new \DateTimeImmutable($monthStart->format('Y-m-t'));
            
            $currentDate = $firstDayOfMonth;
            $week = [];
            
            // Fülle erste Woche mit leeren Tagen bis zum 1. des Monats
            $firstDayWeekday = (int)$firstDayOfMonth->format('N'); // 1=Mo, 7=So
            for ($i = 1; $i < $firstDayWeekday; $i++) {
                $week[] = null;
            }
            
            // Iteriere nur über Tage des aktuellen Monats
            while ($currentDate <= $lastDayOfMonth) {
                $dateKey = $currentDate->format('Y-m-d');
                $dayOfWeek = (int)$currentDate->format('N');
                
                // Ermittle Ausschluss-Grund falls vorhanden
                $excludeReason = null;
                $isExcluded = isset($excludedDates[$dateKey]);
                if ($isExcluded) {
                    $excludeReason = $excludedDates[$dateKey];
                }
                
                $dayData = [
                    'date' => $currentDate->format('Y-m-d'),
                    'day' => (int)$currentDate->format('j'),
                    'isCurrentMonth' => true, // Immer true, da wir nur aktuelle Monatstage anzeigen
                    'assignment' => $assignmentsByDate[$dateKey] ?? null,
                    'isExcluded' => $isExcluded,
                    'excludeReason' => $excludeReason,
                ];
                
                $week[] = $dayData;
                
                // Wenn Sonntag erreicht, speichere Woche und starte neue
                if ($dayOfWeek === 7) {
                    $monthData['weeks'][] = $week;
                    $week = [];
                }
                
                $currentDate = $currentDate->modify('+1 day');
            }
            
            // Letzte unvollständige Woche mit null auffüllen
            if (!empty($week)) {
                while (count($week) < 7) {
                    $week[] = null;
                }
                $monthData['weeks'][] = $week;
            }

            $calendar[] = $monthData;
        }

        return $calendar;
    }
}
