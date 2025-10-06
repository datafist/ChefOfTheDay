<?php

namespace App\Controller\Admin;

use App\Repository\HolidayRepository;
use App\Repository\KitaYearRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/holiday')]
#[IsGranted('ROLE_ADMIN')]
class HolidayController extends AbstractController
{
    #[Route('/', name: 'admin_holiday_index', methods: ['GET'])]
    public function index(HolidayRepository $holidayRepository, KitaYearRepository $kitaYearRepository): Response
    {
        $activeYear = $kitaYearRepository->findOneBy(['isActive' => true]);
        
        $holidays = $activeYear 
            ? $holidayRepository->findBy(['kitaYear' => $activeYear], ['date' => 'ASC'])
            : [];

        return $this->render('admin/holiday/index.html.twig', [
            'holidays' => $holidays,
            'active_year' => $activeYear,
        ]);
    }
}
