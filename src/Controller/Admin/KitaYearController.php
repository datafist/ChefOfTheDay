<?php

namespace App\Controller\Admin;

use App\Entity\KitaYear;
use App\Repository\KitaYearRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/kita-year')]
#[IsGranted('ROLE_ADMIN')]
class KitaYearController extends AbstractController
{
    #[Route('/', name: 'admin_kita_year_index', methods: ['GET'])]
    public function index(
        KitaYearRepository $kitaYearRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $kitaYears = $kitaYearRepository->findAll();
        $activeYear = $kitaYearRepository->findOneBy(['isActive' => true]);
        
        // Prüfe für jedes Jahr, ob es gelöscht werden kann
        $deletabilityInfo = [];
        foreach ($kitaYears as $year) {
            $canDelete = true;
            $reason = '';
            
            // Aktives Jahr kann nicht gelöscht werden
            if ($year->isActive()) {
                $canDelete = false;
                $reason = 'Aktives Jahr kann nicht gelöscht werden';
            }
            // Vorjahr: Kann gelöscht werden wenn das aktive Jahr einen Plan hat
            elseif ($activeYear && $year->getStartDate() < $activeYear->getStartDate()) {
                $activePlanExists = $entityManager->getRepository(\App\Entity\CookingAssignment::class)
                    ->count(['kitaYear' => $activeYear]) > 0;
                
                if (!$activePlanExists) {
                    $canDelete = false;
                    $reason = 'Plan für ' . $activeYear->getYearString() . ' muss erst generiert werden';
                }
            }
            // Zukünftiges Jahr: Kann nicht gelöscht werden, wenn Eltern bereits Verfügbarkeiten eingetragen haben
            elseif ($activeYear && $year->getStartDate() > $activeYear->getStartDate()) {
                $availabilityCount = $entityManager->getRepository(\App\Entity\Availability::class)
                    ->count(['kitaYear' => $year]);
                
                if ($availabilityCount > 0) {
                    $canDelete = false;
                    $reason = 'Eltern haben bereits Verfügbarkeiten eingetragen (' . $availabilityCount . ' Einträge)';
                }
            }
            // Aktuelles Jahr (nicht aktiv): Kann nicht gelöscht werden, wenn Verfügbarkeiten existieren
            else {
                $availabilityCount = $entityManager->getRepository(\App\Entity\Availability::class)
                    ->count(['kitaYear' => $year]);
                
                if ($availabilityCount > 0) {
                    $canDelete = false;
                    $reason = 'Eltern haben bereits Verfügbarkeiten eingetragen (' . $availabilityCount . ' Einträge)';
                }
            }
            
            $deletabilityInfo[$year->getId()] = [
                'canDelete' => $canDelete,
                'reason' => $reason
            ];
        }
        
        return $this->render('admin/kita_year/index.html.twig', [
            'kita_years' => $kitaYears,
            'deletability_info' => $deletabilityInfo,
        ]);
    }

    #[Route('/new', name: 'admin_kita_year_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager, 
        KitaYearRepository $kitaYearRepository,
        \App\Service\GermanHolidayService $holidayService
    ): Response {
        if ($request->isMethod('POST')) {
            $startYear = (int)$request->request->get('start_year');
            
            // Validierung: Mindestens 2024
            if ($startYear < 2024) {
                $this->addFlash('error', 'Das Kita-Jahr muss mindestens 2024/25 sein.');
                return $this->redirectToRoute('admin_kita_year_new');
            }
            
            // Validierung: Nicht zu weit in der Zukunft (max. 20 Jahre)
            $currentYear = (int)date('Y');
            if ($startYear > $currentYear + 20) {
                $this->addFlash('error', 'Das Kita-Jahr darf nicht mehr als 20 Jahre in der Zukunft liegen.');
                return $this->redirectToRoute('admin_kita_year_new');
            }
            
            // Prüfe ob das Jahr bereits existiert
            $existingYear = $kitaYearRepository->findOneBy([
                'startDate' => new \DateTimeImmutable($startYear . '-09-01')
            ]);
            
            if ($existingYear) {
                $this->addFlash('error', 'Das Kita-Jahr ' . $startYear . '/' . ($startYear + 1) . ' existiert bereits.');
                return $this->redirectToRoute('admin_kita_year_new');
            }
            
            $kitaYear = new KitaYear();
            $kitaYear->setStartDate(new \DateTimeImmutable($startYear . '-09-01'));
            $kitaYear->setEndDate(new \DateTimeImmutable(($startYear + 1) . '-08-31'));
            $kitaYear->setIsActive(false);

            $entityManager->persist($kitaYear);
            $entityManager->flush();
            
            // Automatisch Feiertage für Baden-Württemberg anlegen
            $holidays = $holidayService->getHolidaysForKitaYear($startYear);
            $holidayCount = 0;
            
            foreach ($holidays as $dateString => $name) {
                $holiday = new \App\Entity\Holiday();
                $holiday->setDate(new \DateTimeImmutable($dateString));
                $holiday->setName($name);
                $holiday->setKitaYear($kitaYear);
                
                $entityManager->persist($holiday);
                $holidayCount++;
            }
            
            $entityManager->flush();

            $this->addFlash('success', 'Kita-Jahr ' . $startYear . '/' . ($startYear + 1) . ' erfolgreich angelegt mit ' . $holidayCount . ' Feiertagen (Baden-Württemberg).');
            return $this->redirectToRoute('admin_kita_year_index');
        }

        return $this->render('admin/kita_year/new.html.twig');
    }

    #[Route('/{id}/activate', name: 'admin_kita_year_activate', methods: ['POST'])]
    public function activate(
        KitaYear $kitaYear,
        EntityManagerInterface $entityManager,
        KitaYearRepository $kitaYearRepository
    ): Response {
        // Deaktiviere alle anderen
        foreach ($kitaYearRepository->findAll() as $year) {
            $year->setIsActive(false);
        }

        // Aktiviere ausgewähltes
        $kitaYear->setIsActive(true);
        $entityManager->flush();

        $this->addFlash('success', 'Kita-Jahr ' . $kitaYear->getYearString() . ' aktiviert.');
        return $this->redirectToRoute('admin_kita_year_index');
    }

    #[Route('/{id}', name: 'admin_kita_year_delete', methods: ['POST'])]
    public function delete(
        Request $request, 
        KitaYear $kitaYear, 
        EntityManagerInterface $entityManager,
        KitaYearRepository $kitaYearRepository
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$kitaYear->getId(), $request->request->get('_token'))) {
            // Sicherheitsprüfung: Ist dies das aktive Jahr?
            if ($kitaYear->isActive()) {
                $this->addFlash('error', 'Das aktive Kita-Jahr kann nicht gelöscht werden.');
                return $this->redirectToRoute('admin_kita_year_index');
            }

            $activeYear = $kitaYearRepository->findOneBy(['isActive' => true]);
            
            // Sicherheitsprüfung: Ist dies ein Vorjahr?
            if ($activeYear && $kitaYear->getStartDate() < $activeYear->getStartDate()) {
                // Dies ist ein Vorjahr - prüfe ob das aktive Jahr bereits einen Plan hat
                $activePlanExists = $entityManager->getRepository(\App\Entity\CookingAssignment::class)
                    ->count(['kitaYear' => $activeYear]) > 0;
                
                if (!$activePlanExists) {
                    $this->addFlash('error', 
                        'Das Vorjahr kann erst gelöscht werden, nachdem der Kochplan für das aktuelle Jahr (' 
                        . $activeYear->getYearString() . ') generiert wurde.'
                    );
                    return $this->redirectToRoute('admin_kita_year_index');
                }
                // Vorjahr mit Plan kann gelöscht werden (auch wenn Verfügbarkeiten existieren)
            }
            // Sicherheitsprüfung für zukünftige oder nicht-aktive Jahre (aber nicht Vorjahr)
            else {
                // Haben Eltern bereits Verfügbarkeiten eingetragen?
                $availabilityCount = $entityManager->getRepository(\App\Entity\Availability::class)
                    ->count(['kitaYear' => $kitaYear]);
                
                if ($availabilityCount > 0) {
                    $this->addFlash('error', 
                        'Das Kita-Jahr kann nicht gelöscht werden, da bereits ' . $availabilityCount 
                        . ' Verfügbarkeits-Einträge von Eltern vorhanden sind.'
                    );
                    return $this->redirectToRoute('admin_kita_year_index');
                }
            }

            // Löschung erlaubt
            $entityManager->remove($kitaYear);
            $entityManager->flush();
            $this->addFlash('success', 'Kita-Jahr ' . $kitaYear->getYearString() . ' erfolgreich gelöscht.');
        }

        return $this->redirectToRoute('admin_kita_year_index');
    }
}
