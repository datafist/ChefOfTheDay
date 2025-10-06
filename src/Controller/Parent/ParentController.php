<?php

namespace App\Controller\Parent;

use App\Entity\Availability;
use App\Repository\AvailabilityRepository;
use App\Repository\HolidayRepository;
use App\Repository\KitaYearRepository;
use App\Repository\PartyRepository;
use App\Repository\VacationRepository;
use App\Util\DateHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/parent')]
class ParentController extends AbstractController
{
    #[Route('/login', name: 'parent_login', methods: ['GET', 'POST'])]
    public function login(Request $request, PartyRepository $partyRepository): Response
    {
        if ($request->isMethod('POST')) {
            $partyId = $request->request->get('party_id');
            $password = $request->request->get('password');

            $party = $partyRepository->find($partyId);

            if ($party && $party->getGeneratedPassword() === $password) {
                // Erfolgreicher Login - speichere in Session
                $request->getSession()->set('parent_party_id', $party->getId());
                return $this->redirectToRoute('parent_availability');
            }

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
        HolidayRepository $holidayRepository,
        VacationRepository $vacationRepository,
        AvailabilityRepository $availabilityRepository,
        EntityManagerInterface $em
    ): Response {
        $partyId = $request->getSession()->get('parent_party_id');

        if (!$partyId) {
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
        $calendar = $this->buildCalendar($activeYear, $holidayRepository, $vacationRepository);

        return $this->render('parent/availability.html.twig', [
            'party' => $party,
            'activeYear' => $activeYear,
            'calendar' => $calendar,
            'savedDates' => $savedDates,
            'assignmentCount' => $assignmentCount,
            'planGenerated' => $planGenerated,
            'assignedDates' => $assignedDates,
        ]);
    }

    private function buildCalendar(
        $kitaYear,
        HolidayRepository $holidayRepository,
        VacationRepository $vacationRepository,
        bool $excludeWeekendsAndHolidays = true
    ): array {
        $holidays = $holidayRepository->findBy(['kitaYear' => $kitaYear]);
        $vacations = $vacationRepository->findBy(['kitaYear' => $kitaYear]);

        $holidayDates = [];
        foreach ($holidays as $holiday) {
            $holidayDates[$holiday->getDate()->format('Y-m-d')] = $holiday->getName();
        }

        $vacationRanges = [];
        foreach ($vacations as $vacation) {
            $vacationRanges[] = [
                'start' => $vacation->getStartDate(),
                'end' => $vacation->getEndDate(),
                'name' => $vacation->getName(),
            ];
        }

        $calendar = [];
        $currentDate = clone $kitaYear->getStartDate();
        $endDate = clone $kitaYear->getEndDate();

        $currentMonth = null;
        $monthData = null;

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $month = $currentDate->format('Y-m');
            $dayOfWeek = (int)$currentDate->format('N'); // 1=Monday, 7=Sunday

            // Neuer Monat?
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

                // Fülle Tage bis zum ersten Tag des Monats
                $firstDayOfWeek = (int)$currentDate->format('N');
                for ($i = 1; $i < $firstDayOfWeek; $i++) {
                    $monthData['weeks'][0][] = null;
                }
            }

            // Prüfe ob Tag ausgeschlossen ist
            $isExcluded = false;
            $excludeReason = null;

            if ($excludeWeekendsAndHolidays) {
                // Wochenende?
                if ($dayOfWeek >= 6) {
                    $isExcluded = true;
                    $excludeReason = 'Wochenende';
                }

                // Feiertag?
                if (isset($holidayDates[$dateStr])) {
                    $isExcluded = true;
                    $excludeReason = $holidayDates[$dateStr];
                }

                // Ferien?
                foreach ($vacationRanges as $vacation) {
                    if ($currentDate >= $vacation['start'] && $currentDate <= $vacation['end']) {
                        $isExcluded = true;
                        $excludeReason = $vacation['name'];
                        break;
                    }
                }
            }

            $dayData = [
                'date' => $dateStr,
                'day' => (int)$currentDate->format('j'),
                'dayOfWeek' => $dayOfWeek,
                'isExcluded' => $isExcluded,
                'excludeReason' => $excludeReason,
            ];

            // Füge Tag zur aktuellen Woche hinzu
            $currentWeekIndex = count($monthData['weeks']) - 1;
            $monthData['weeks'][$currentWeekIndex][] = $dayData;

            // Neue Woche beginnen wenn Sonntag
            if ($dayOfWeek === 7) {
                $monthData['weeks'][] = [];
            }

            $currentDate = $currentDate->modify('+1 day');
        }

        // Füge letzten Monat hinzu
        if ($monthData !== null) {
            // Fülle letzte Woche auf
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
