<?php

namespace App\Controller\Parent;

use App\Entity\Availability;
use App\Entity\KitaYear;
use App\Repository\AvailabilityRepository;
use App\Repository\KitaYearRepository;
use App\Repository\PartyRepository;
use App\Service\DateExclusionService;
use App\Util\DateHelper;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/parent')]
class ParentController extends AbstractController
{
    private const SESSION_TIMEOUT = 3600; // 1 Stunde

    #[Route('/login', name: 'parent_login', methods: ['GET', 'POST'])]
    public function login(
        Request $request,
        PartyRepository $partyRepository,
        RateLimiterFactory $parentLoginLimiter,
        LoggerInterface $logger
    ): Response {
        if ($request->isMethod('POST')) {
            // CSRF-Validierung
            $submittedToken = $request->request->get('_csrf_token');
            if (!$this->isCsrfTokenValid('parent_login', $submittedToken)) {
                $this->addFlash('error', 'Ungültiger Sicherheitstoken. Bitte versuchen Sie es erneut.');
                return $this->redirectToRoute('parent_login');
            }

            // Rate-Limiting: max. 5 Versuche pro Minute pro IP
            $limiter = $parentLoginLimiter->create($request->getClientIp());
            if (false === $limiter->consume(1)->isAccepted()) {
                $logger->warning('Parent login rate limit exceeded', [
                    'ip' => $request->getClientIp(),
                ]);
                $this->addFlash('error', 'Zu viele Login-Versuche. Bitte warten Sie einen Moment.');
                return $this->redirectToRoute('parent_login');
            }

            $partyId = $request->request->get('party_id');
            $password = $request->request->get('password');

            $party = $partyRepository->find($partyId);

            if ($party && hash_equals($party->getGeneratedPassword(), (string) $password)) {
                // Session-Regeneration gegen Session-Fixation
                $request->getSession()->migrate(true);
                $request->getSession()->set('parent_party_id', $party->getId());
                $request->getSession()->set('parent_login_time', time());

                $logger->info('Parent login successful', [
                    'party_id' => $party->getId(),
                    'family' => $party->getChildrenNames(),
                ]);

                return $this->redirectToRoute('parent_availability');
            }

            $logger->notice('Parent login failed', [
                'party_id' => $partyId,
                'ip' => $request->getClientIp(),
            ]);
            $this->addFlash('error', 'Ungültiges Passwort');
        }

        $parties = $partyRepository->findAll();

        return $this->render('parent/login.html.twig', [
            'parties' => $parties,
        ]);
    }

    #[Route('/availability', name: 'parent_availability', methods: ['GET', 'POST'])]
    public function availability(
        Request $request,
        PartyRepository $partyRepository,
        KitaYearRepository $kitaYearRepository,
        AvailabilityRepository $availabilityRepository,
        DateExclusionService $dateExclusionService,
        EntityManagerInterface $em
    ): Response {
        $partyId = $request->getSession()->get('parent_party_id');
        $loginTime = $request->getSession()->get('parent_login_time', 0);

        // Session-Timeout prüfen
        if (!$partyId || (time() - $loginTime) > self::SESSION_TIMEOUT) {
            $request->getSession()->remove('parent_party_id');
            $request->getSession()->remove('parent_login_time');
            if ($partyId && (time() - $loginTime) > self::SESSION_TIMEOUT) {
                $this->addFlash('warning', 'Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.');
            }
            return $this->redirectToRoute('parent_login');
        }

        $party = $partyRepository->find($partyId);
        if (!$party) {
            return $this->redirectToRoute('parent_login');
        }

        $activeYear = $kitaYearRepository->findOneBy(['isActive' => true]);
        if (!$activeYear) {
            $this->addFlash('error', 'Kein aktives Kita-Jahr gefunden.');
            return $this->render('parent/availability.html.twig', [
                'party' => $party,
                'activeYear' => null,
                'assignmentCount' => 0,
                'assignedDateStrings' => [],
            ]);
        }

        // Zähle Zuweisungen für das aktive Jahr
        $assignmentCount = $em->getRepository(\App\Entity\CookingAssignment::class)
            ->count([
                'party' => $party,
                'kitaYear' => $activeYear
            ]);

        // Prüfe ob Plan bereits generiert wurde (irgendeine Zuweisung existiert für das aktive Jahr)
        $planGenerated = $em->getRepository(\App\Entity\CookingAssignment::class)
            ->count(['kitaYear' => $activeYear]) > 0;

        // Lade zugewiesene Termine für diese Partei
        $assignedDates = [];
        if ($planGenerated) {
            $assignments = $em->getRepository(\App\Entity\CookingAssignment::class)
                ->findBy([
                    'party' => $party,
                    'kitaYear' => $activeYear
                ], ['assignedDate' => 'ASC']);
            
            foreach ($assignments as $assignment) {
                $assignedDates[] = [
                    'date' => $assignment->getAssignedDate(),
                    'dateFormatted' => $assignment->getAssignedDate()->format('d.m.Y'),
                    'dayName' => DateHelper::getDayNameGerman((int)$assignment->getAssignedDate()->format('N')),
                ];
            }
        }

        // Handle form submission (nur wenn Plan noch nicht generiert)
        if ($request->isMethod('POST')) {
            // Prüfe ob Plan bereits generiert wurde
            if ($planGenerated) {
                $this->addFlash('error', 'Der Kochplan wurde bereits generiert. Verfügbarkeiten können nicht mehr geändert werden.');
                return $this->redirectToRoute('parent_availability');
            }
            
            // CSRF Token validieren
            $submittedToken = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('availability', $submittedToken)) {
                $this->addFlash('error', 'Ungültiger Sicherheitstoken. Bitte versuchen Sie es erneut.');
                return $this->redirectToRoute('parent_availability');
            }

            $availableDatesJson = $request->request->get('available_dates');
            $availableDates = json_decode($availableDatesJson, true) ?? [];

            // Debugging
            if (empty($availableDates)) {
                $this->addFlash('warning', 'Keine Termine ausgewählt. JSON: ' . $availableDatesJson);
            }

            // Finde oder erstelle Availability-Eintrag
            $availability = $availabilityRepository->findOneBy([
                'party' => $party,
                'kitaYear' => $activeYear,
            ]);

            if (!$availability) {
                $availability = new Availability();
                $availability->setParty($party);
                $availability->setKitaYear($activeYear);
            }

            $availability->setAvailableDates($availableDates);
            $em->persist($availability);
            $em->flush();

            $this->addFlash('success', sprintf('Ihre Verfügbarkeit wurde gespeichert! (%d Tage ausgewählt)', count($availableDates)));
            return $this->redirectToRoute('parent_availability');
        }

        // Lade bestehende Verfügbarkeit
        $availability = $availabilityRepository->findOneBy([
            'party' => $party,
            'kitaYear' => $activeYear,
        ]);

        $savedDates = $availability ? $availability->getAvailableDates() : [];

        // Erstelle Kalender
        $calendar = $this->buildCalendar($activeYear, $dateExclusionService);

        // Einfaches Array mit Y-m-d Strings für Kalender-Markierung
        $assignedDateStrings = array_map(
            fn($a) => $a['date']->format('Y-m-d'),
            $assignedDates
        );

        return $this->render('parent/availability.html.twig', [
            'party' => $party,
            'activeYear' => $activeYear,
            'calendar' => $calendar,
            'savedDates' => $savedDates,
            'assignmentCount' => $assignmentCount,
            'planGenerated' => $planGenerated,
            'assignedDates' => $assignedDates,
            'assignedDateStrings' => $assignedDateStrings,
        ]);
    }

    private function buildCalendar(KitaYear $kitaYear, DateExclusionService $dateExclusionService): array
    {
        $excludedDates = $dateExclusionService->getExcludedDatesForKitaYear($kitaYear);

        $calendar = [];
        $currentDate = clone $kitaYear->getStartDate();
        $endDate = clone $kitaYear->getEndDate();
        $currentMonth = null;
        $monthData = null;

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $month = $currentDate->format('Y-m');
            $dayOfWeek = (int)$currentDate->format('N');

            if ($month !== $currentMonth) {
                if ($monthData !== null) {
                    $calendar[] = $monthData;
                }
                $currentMonth = $month;
                $monthData = [
                    'name' => $currentDate->format('F Y'),
                    'name_de' => DateHelper::getMonthNameGerman((int)$currentDate->format('n')) . ' ' . $currentDate->format('Y'),
                    'weeks' => [[]],
                ];
                $firstDayOfWeek = (int)$currentDate->format('N');
                for ($i = 1; $i < $firstDayOfWeek; $i++) {
                    $monthData['weeks'][0][] = null;
                }
            }

            $dayData = [
                'date' => $dateStr,
                'day' => (int)$currentDate->format('j'),
                'dayOfWeek' => $dayOfWeek,
                'isExcluded' => isset($excludedDates[$dateStr]),
                'excludeReason' => $excludedDates[$dateStr] ?? null,
            ];

            $currentWeekIndex = count($monthData['weeks']) - 1;
            $monthData['weeks'][$currentWeekIndex][] = $dayData;

            if ($dayOfWeek === 7) {
                $monthData['weeks'][] = [];
            }

            $currentDate = $currentDate->modify('+1 day');
        }

        if ($monthData !== null) {
            $lastWeek = &$monthData['weeks'][count($monthData['weeks']) - 1];
            while (count($lastWeek) < 7) {
                $lastWeek[] = null;
            }
            $calendar[] = $monthData;
        }

        return $calendar;
    }

    #[Route('/logout', name: 'parent_logout')]
    public function logout(Request $request): Response
    {
        $request->getSession()->remove('parent_party_id');
        $this->addFlash('success', 'Erfolgreich abgemeldet');
        return $this->redirectToRoute('parent_login');
    }
}
