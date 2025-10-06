<?php

namespace App\Controller\Admin;

use App\Entity\Vacation;
use App\Repository\KitaYearRepository;
use App\Repository\VacationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/vacation')]
#[IsGranted('ROLE_ADMIN')]
class VacationController extends AbstractController
{
    #[Route('/', name: 'admin_vacation_index', methods: ['GET'])]
    public function index(VacationRepository $vacationRepository, KitaYearRepository $kitaYearRepository): Response
    {
        $activeYear = $kitaYearRepository->findOneBy(['isActive' => true]);
        
        $vacations = $activeYear 
            ? $vacationRepository->findBy(['kitaYear' => $activeYear], ['startDate' => 'ASC'])
            : [];

        return $this->render('admin/vacation/index.html.twig', [
            'vacations' => $vacations,
            'active_year' => $activeYear,
        ]);
    }

    #[Route('/new', name: 'admin_vacation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, KitaYearRepository $kitaYearRepository): Response
    {
        $activeYear = $kitaYearRepository->findOneBy(['isActive' => true]);
        
        if (!$activeYear) {
            $this->addFlash('error', 'Bitte aktivieren Sie zuerst ein Kita-Jahr.');
            return $this->redirectToRoute('admin_kita_year_index');
        }

        if ($request->isMethod('POST')) {
            $vacation = new Vacation();
            $vacation->setName($request->request->get('name'));
            $vacation->setStartDate(new \DateTimeImmutable($request->request->get('start_date')));
            $vacation->setEndDate(new \DateTimeImmutable($request->request->get('end_date')));
            $vacation->setKitaYear($activeYear);

            $entityManager->persist($vacation);
            $entityManager->flush();

            $this->addFlash('success', 'Ferienzeit erfolgreich angelegt.');
            return $this->redirectToRoute('admin_vacation_index');
        }

        return $this->render('admin/vacation/new.html.twig', [
            'active_year' => $activeYear,
        ]);
    }

    #[Route('/{id}', name: 'admin_vacation_delete', methods: ['POST'])]
    public function delete(Request $request, Vacation $vacation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$vacation->getId(), $request->request->get('_token'))) {
            $entityManager->remove($vacation);
            $entityManager->flush();
            $this->addFlash('success', 'Ferienzeit erfolgreich gelöscht.');
        }

        return $this->redirectToRoute('admin_vacation_index');
    }
}
